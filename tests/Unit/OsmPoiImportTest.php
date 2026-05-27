<?php

declare(strict_types=1);

use App\Dto\OsmEcPoiPropertiesData;
use App\Dto\OsmNodePoiData;
use App\Services\Osm\ImportReport;
use App\Services\Osm\OsmPoiImporter;
use App\Services\Osm\OsmTaxonomyPoiTypeResolver;
use Illuminate\Support\Facades\Http;
use Wm\WmPackage\Exceptions\OsmClientExceptionNoTags;
use Wm\WmPackage\Http\Clients\OsmClient;
use Wm\WmPackage\Models\TaxonomyPoiType;

uses(Tests\TestCase::class);

afterEach(function () {
    \Mockery::close();
});

describe('OsmNodePoiData', function () {
    it('prefers information=guidepost over tourism=information for taxonomy key', function () {
        $dto = OsmNodePoiData::fromOsmNode(
            73_057_667_56,
            [
                'name' => 'Passo',
                'tourism' => 'information',
                'information' => 'guidepost',
            ],
            ['type' => 'Point', 'coordinates' => [10.1, 44.2]],
        );

        expect($dto->poiTypeOsmKey)->toBe('information')
            ->and($dto->poiTypeOsmValue)->toBe('guidepost')
            ->and($dto->poiTypeCompositeIdentifier())->toBe('information-guidepost');
    });

    it('normalizes composite identifiers for taxonomy matching', function () {
        expect(OsmNodePoiData::normalizeIdentifier('Tourism-Viewpoint'))->toBe('tourism-viewpoint')
            ->and(OsmNodePoiData::normalizeIdentifier('post_office'))->toBe('post-office');
    });

    it('rejects non-point geometry', function () {
        expect(fn () => OsmNodePoiData::fromOsmNode(
            1,
            ['name' => 'X'],
            ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]],
        ))->toThrow(\InvalidArgumentException::class);
    });
});

describe('OsmEcPoiPropertiesData', function () {
    it('maps OSM tags to properties payload', function () {
        $data = OsmEcPoiPropertiesData::fromOsmTags(
            [
                'name' => 'Poste',
                'amenity' => 'post_office',
                'phone' => '+390000',
                'addr:city' => 'Test City',
                'addr:street' => 'Via Roma',
                'addr:housenumber' => '1',
                'addr:postcode' => '41000',
                'opening_hours' => '24/7',
                'description:it' => 'Desc IT',
                'ref' => 'REF-1',
            ],
            ['osmid' => 4_769_303_114, 'type' => 'node', 'source_updated_at' => '2020-01-01 00:00:00', 'tags' => []],
        );

        $arr = $data->toArray();

        expect($arr['contact_phone'])->toBe('+390000')
            ->and($arr['opening_hours'])->toBe('24/7')
            ->and($arr['addr_locality'])->toBe('Test City')
            ->and($arr['addr_housenumber'])->toBe('1')
            ->and($arr['addr_complete'])->toContain('Via Roma')
            ->and($arr['addr_complete'])->toContain('41000')
            ->and($arr['description']['it'])->toBe('Desc IT')
            ->and($arr['out_source_feature_id'])->toBe('REF-1');
    });
});

describe('ImportReport', function () {
    it('aggregates outcomes and failures', function () {
        $report = new ImportReport(false);
        $report->addOutcome([
            'action' => 'created',
            'osmid' => 1,
            'ec_poi_id' => 10,
            'taxonomy_identifier' => 'amenity-bench',
            'taxonomy_created' => false,
        ]);
        $report->addFailure(2, 'skipped', 'no_tags');
        $report->setTruncatedBeyondLimit(3);

        expect($report->createdCount())->toBe(1)
            ->and($report->failuresCount())->toBe(1)
            ->and($report->truncatedBeyondLimit())->toBe(3)
            ->and($report->failuresByCategory())->toHaveKey('no_tags');
    });
});

describe('OsmClient with Http::fake (no real OSM calls)', function () {
    it('parses a mocked node JSON response like production OSM API', function () {
        $payload = [
            'elements' => [
                [
                    'type' => 'node',
                    'id' => 99_900_001,
                    'lat' => 44.5,
                    'lon' => 10.25,
                    'timestamp' => '2024-06-01T10:00:00Z',
                    'tags' => [
                        'name' => 'Fake Bench',
                        'amenity' => 'bench',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openstreetmap.org/api/0.6/node/99900001.json' => Http::response($payload, 200),
        ]);

        $client = new OsmClient;
        [$properties, $geometry] = $client->getPropertiesAndGeometry('node/99900001');

        expect($geometry['type'])->toBe('Point')
            ->and($geometry['coordinates'])->toBe([10.25, 44.5])
            ->and($properties['name'])->toBe('Fake Bench')
            ->and($properties['amenity'])->toBe('bench')
            ->and($properties)->toHaveKey('_updated_at');
    });
});

describe('OsmPoiImporter dry-run (mocked OSM + taxonomy, no DB writes)', function () {
    it('returns expected outcome without persisting', function () {
        $properties = [
            'name' => 'Test POI',
            'amenity' => 'bench',
            '_updated_at' => '2024-01-01 12:00:00',
        ];
        $geometry = ['type' => 'Point', 'coordinates' => [9.0, 45.0]];

        $osmClient = \Mockery::mock(OsmClient::class);
        $osmClient->shouldReceive('getPropertiesAndGeometry')
            ->once()
            ->with('node/1001')
            ->andReturn([$properties, $geometry]);

        $taxonomy = new TaxonomyPoiType;
        $taxonomy->forceFill([
            'id' => 42,
            'identifier' => 'amenity-bench',
        ]);

        $resolver = \Mockery::mock(OsmTaxonomyPoiTypeResolver::class);
        $resolver->shouldReceive('resolve')
            ->once()
            ->andReturn(['taxonomy' => $taxonomy, 'created' => false]);

        $importer = new class ($osmClient, $resolver) extends OsmPoiImporter {
            protected function findExistingEcPoiByOsmid(int $osmid): ?\Wm\WmPackage\Models\EcPoi
            {
                return null;
            }
        };

        $report = $importer->importNodes([1001], appId: 1, userId: null, dryRun: true, global: true);

        expect($report->dryRun)->toBeTrue()
            ->and($report->outcomes())->toHaveCount(1)
            ->and($report->outcomes()[0]['action'])->toBe('created')
            ->and($report->outcomes()[0]['osmid'])->toBe(1001)
            ->and($report->outcomes()[0]['ec_poi_id'])->toBeNull()
            ->and($report->outcomes()[0]['taxonomy_identifier'])->toBe('amenity-bench')
            ->and($report->failuresCount())->toBe(0);
    });

    it('records failures when OSM client throws', function () {
        $osmClient = \Mockery::mock(OsmClient::class);
        $osmClient->shouldReceive('getPropertiesAndGeometry')
            ->andThrow(new OsmClientExceptionNoTags('no tags', 1));

        $resolver = \Mockery::mock(OsmTaxonomyPoiTypeResolver::class);
        $resolver->shouldReceive('resolve')->never();

        $importer = new class ($osmClient, $resolver) extends OsmPoiImporter {
            protected function findExistingEcPoiByOsmid(int $osmid): ?\Wm\WmPackage\Models\EcPoi
            {
                return null;
            }
        };

        $report = $importer->importNodes([2002], 1, null, true, true);

        expect($report->outcomes())->toBeEmpty()
            ->and($report->failuresCount())->toBe(1)
            ->and($report->failures()[0]['category'])->toBe('no_tags')
            ->and($report->failures()[0]['osmid'])->toBe(2002);
    });
});

describe('Simulated import pipeline (DTO only, no persistence)', function () {
    it('builds EcPoi attributes from mocked OSM node payload', function () {
        Http::fake([
            'api.openstreetmap.org/api/0.6/node/88800001.json' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 88_800_001,
                        'lat' => 46.0,
                        'lon' => 11.0,
                        'timestamp' => '2023-05-05T08:00:00Z',
                        'tags' => [
                            'name' => 'Summit',
                            'natural' => 'peak',
                            'ele' => '2000',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new OsmClient;
        [$props, $geom] = $client->getPropertiesAndGeometry('node/88800001');
        $dto = OsmNodePoiData::fromOsmNode(88_800_001, $props, $geom);
        $attrs = $dto->toEcPoiAttributes(appId: 5, userId: null);

        expect($attrs['app_id'])->toBe(5)
            ->and($attrs['osmid'])->toBe(88_800_001)
            ->and($attrs['properties']['osmid'])->toBe(88_800_001)
            ->and($attrs['properties']['osm_data']['tags']['natural'])->toBe('peak')
            ->and($dto->poiTypeOsmKey)->toBe('natural')
            ->and($dto->poiTypeOsmValue)->toBe('peak');
    });
});

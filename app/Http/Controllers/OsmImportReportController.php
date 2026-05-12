<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Osm\OsmImportReportStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OsmImportReportController extends Controller
{
    /**
     * Pagina di riepilogo import OSM (stessa scheda del browser, nessun popup esterno).
     */
    public function show(Request $request, string $token): View
    {
        $user = $request->user();
        if ($user === null) {
            throw new NotFoundHttpException;
        }

        $payload = OsmImportReportStore::get($token, (int) $user->id);
        if ($payload === null) {
            throw new NotFoundHttpException;
        }

        return view('nova.osm-import-report', [
            'report' => $payload,
            'backUrl' => Nova::url('/resources/ec-pois'),
            'ttlMinutes' => OsmImportReportStore::TTL_MINUTES,
        ]);
    }
}

# Changelog

## [1.4.0](https://github.com/webmappsrl/maphub/compare/v1.3.0...v1.4.0) (2026-07-24)


### Features

* add improved POI import and reporting features OC: 7815 ([8da03b5](https://github.com/webmappsrl/maphub/commit/8da03b56350aada300ba14d8907653c163ed2e20))
* add Tile class and related migrations OC:7969 ([cd4e7c1](https://github.com/webmappsrl/maphub/commit/cd4e7c1900cbfea7a41fccb00d5338b1a6975b28))
* add unit tests for OSM POI importer OC:7815 ([64fcc9d](https://github.com/webmappsrl/maphub/commit/64fcc9dcf396ca1ab95f29bea023300a98474028))
* enhance Import Layer with EcPoi taxonomy OC:8043 ([#10](https://github.com/webmappsrl/maphub/issues/10)) ([33575af](https://github.com/webmappsrl/maphub/commit/33575af958b2f7f4a56d73d5767945c161859187))
* enhance POI import with global flag option OC:7815 ([1e3af04](https://github.com/webmappsrl/maphub/commit/1e3af04be0fe5030110a6aa1bad339ad03cb2db8))
* enhance role assignment process ([e41d451](https://github.com/webmappsrl/maphub/commit/e41d451cdf11c7a608799f18ccef2da1e6b26931))
* **migrations:** ✨ add initial database schema for applications and related entities ([d65bed1](https://github.com/webmappsrl/maphub/commit/d65bed1ee91282773edbe76de2a3a32615320ecd))
* **migrations:** ✨ add new taxonomy and feature collection related tables ([7333c3b](https://github.com/webmappsrl/maphub/commit/7333c3bd146df75e41fe3b2ed25958726ecc1975))
* **models:** ✨ add EcTrack model extending WmEcTrack ([8eea5e5](https://github.com/webmappsrl/maphub/commit/8eea5e58681625dd82337387a44e0d205449c9a6))
* **nova:** ✨ add TaxonomyActivity resource to Nova ([ff98e0e](https://github.com/webmappsrl/maphub/commit/ff98e0e2461bf629de23ee52fc2c518efec1a048))
* **nova:** ✨ add TaxonomyTheme resource to Nova OC:8014 ([#3](https://github.com/webmappsrl/maphub/issues/3)) ([b69e761](https://github.com/webmappsrl/maphub/commit/b69e761ebbc70635e102f461590290292e14e771))
* **nova:** ✨ add TaxonomyWhere resource to Nova ([d0663c7](https://github.com/webmappsrl/maphub/commit/d0663c707f54a0c2c3409423d160d5cb23c2149f))
* **nova:** ✨ extend AbstractUserResource in User resource OC:8072 ([#4](https://github.com/webmappsrl/maphub/issues/4)) ([a0a87f2](https://github.com/webmappsrl/maphub/commit/a0a87f2c72ef3dfef4abce8f31c6980c6a60d5ba))
* **oc:8082:** CI/CD GitHub Actions — deploy, smoke test, Slack notify ([#9](https://github.com/webmappsrl/maphub/issues/9)) ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** restore Slack notifications and remove temp feature branch trigger ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8161:** require access-nova permission for Nova web access ([b5dc85f](https://github.com/webmappsrl/maphub/commit/b5dc85fcc9b5cda1db4c6badec1aeaa03be01fdf))
* **oc:8161:** require access-nova permission for Nova web access ([f787960](https://github.com/webmappsrl/maphub/commit/f787960b3933d84379151f0d4cbebc5ca25a41ac))
* **oc:8161:** require access-nova permission for Nova web access ([ca9e865](https://github.com/webmappsrl/maphub/commit/ca9e8652c923cf1426bed3dbb1c95e8e0a78806e))
* **oc:8218:** gate migration wm-package in CI e permission:cache-reset in deploy ([f35ac02](https://github.com/webmappsrl/maphub/commit/f35ac0266db10fb9b3ffc2e9d044d4bc2f4e80a8))
* **oc:8218:** gate migration wm-package in CI e permission:cache-reset in deploy ([f35ac02](https://github.com/webmappsrl/maphub/commit/f35ac0266db10fb9b3ffc2e9d044d4bc2f4e80a8))
* **oc:8218:** gate migration wm-package in CI e permission:cache-reset in deploy ([5389524](https://github.com/webmappsrl/maphub/commit/5389524c2fd00a38bcc2ec78b63dfa1a8e5abd87))
* **osm:** add ability to import POIs from OSM OC:7815 ([2b0cda0](https://github.com/webmappsrl/maphub/commit/2b0cda03de9022516c60de2d6e7c997770e23256))
* **taxonomy:** ✨ add TaxonomyTheme and TaxonomyThemeable migrations, update Nova menu oc:8081 ([1f8f62e](https://github.com/webmappsrl/maphub/commit/1f8f62ec25a485a58f7a1668c125a02f82327847))


### Bug Fixes

* aggiorna baseline PHPStan dopo rimozione ExampleTest.php ([629256c](https://github.com/webmappsrl/maphub/commit/629256cf678dc1b2eee7a77d5efaa5aa919d86a6))
* aggiorna baseline PHPStan dopo rimozione ExampleTest.php ([fc63663](https://github.com/webmappsrl/maphub/commit/fc63663722e92a42c90e56dfb2a6fe0927746296))
* **oc:8082:** add safe.directory config before git pull to fix dubious ownership error ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** add safe.directory inside Docker container to fix dubious ownership on submodule update ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** broaden horizon:status grep to match Horizon v5 output format ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** chown .git/modules to www-data after SSH-level submodule update ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** inject safe.directory via GIT_CONFIG env vars instead of git config --global ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** pass GIT_CONFIG env vars via docker exec -e to fix dubious ownership ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** remove boilerplate ExampleTest that asserted 200 on redirect route ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** remove Feature testsuite from phpunit.xml — directory no longer exists ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** remove git submodule update from deploy scripts ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** remove horizon wait loop — supervisor auto-restarts Horizon ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** run docker exec as root to bypass file ownership mismatches ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))
* **oc:8082:** use explicit paths for safe.directory instead of wildcard ([a0d18e8](https://github.com/webmappsrl/maphub/commit/a0d18e8991bffa4efec52f0a240d37ba891d005a))


### Miscellaneous Chores

* update .gitignore for new storage paths OC:7911 ([a81c52d](https://github.com/webmappsrl/maphub/commit/a81c52d93e84272f92bae734ae6d65b0acb44e44))
* update .gitignore for storage dirs OC:8007 ([13e99a1](https://github.com/webmappsrl/maphub/commit/13e99a1ba8eb92b2390533cfb142755abac2f84e))
* update .gitignore for storage path ([691fbe5](https://github.com/webmappsrl/maphub/commit/691fbe55a1199bf8a15bb03f47aab7b334c00336))
* update composer.lock to support Laravel 13 and adjust package versions ([e245172](https://github.com/webmappsrl/maphub/commit/e245172866339404a2eb901e0705bd26c7b955de))
* update plugin-api-version in composer.lock to 2.9.0 ([caecb75](https://github.com/webmappsrl/maphub/commit/caecb75990ff5235517a92cd26347df8f9ac24d4))
* update submodule wm-package ([70753ae](https://github.com/webmappsrl/maphub/commit/70753aebd65f0b066b2122d69ea94c5d111f8c2c))
* update submodule wm-package OC: 8298 ([3c05181](https://github.com/webmappsrl/maphub/commit/3c05181b79dce5c0fdfbb5a30a830927910a1e54))
* update wm-package version and plugin-api OC: 8298 ([5a2e2d7](https://github.com/webmappsrl/maphub/commit/5a2e2d7f19b71a326e5156a219afaa5c39652512))

## [1.3.0](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.2.1...v1.3.0) (2026-03-17)


### Features

* add local.compose.yml for standalone local development ([40e2117](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/40e21175ba80d779e5a70cb14d359b18ec51a9b1))
* align boilerplate with upstream reference improvements ([b3400c4](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/b3400c423212637755e15b8b6127b282810e446a))
* **nova:** ✨ add Media resource and update NovaServiceProvider ([7516557](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/75165577e8fbccbe20d19e1ee81659242309f147))


### Bug Fixes

* add scout-init service to local.compose.yml ([7d7c62f](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/7d7c62fcbfc66bd9a285083154bd79ebe98c10ca))
* align all container names to dash convention ([fc6a6fb](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/fc6a6fb6f66f0fb8b8215a05022eb4aab47831be))
* update README to Laravel 12 and align deploy_prod.sh ([e594d61](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/e594d61f231827b227b2c7ec14b0c02329cad731))


### Miscellaneous Chores

* **gitignore:** ➕ add .gitignore to storage/debugbar directory ([c232f51](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/c232f51b2be3fe724de132e593f5762927ff0dfe))
* **gitignore:** ➕ add nova directory to ignore list ([f79141f](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/f79141fffdd28ba3a0c83a4c7e3a05e86052dba4))
* **scripts:** 🚀 add comprehensive installation script ([7516557](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/75165577e8fbccbe20d19e1ee81659242309f147))

## [1.2.1](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.2.0...v1.2.1) (2025-04-23)


### Bug Fixes

* dependencies ([5722f55](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/5722f55ccca693860da3bbc33d0229fa47553a07))
* jwt ([0a32edc](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/0a32edca820bbfae95b7036f7979c7eb92ae4799))
* providers ([f42feaa](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/f42feaa9a5493b7aa3a9b414c93702ddedd523dc))
* providers ([800cae6](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/800cae6e1d534e0f71ede4bd4688b042e08806a4))
* queue connection ([c418307](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/c418307119d72a74cb7a821c17b38e798a8450c7))

## [1.2.0](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.1.3...v1.2.0) (2025-04-23)


### Features

* redirect to nova dashboard ([381f238](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/381f238faa6bedbd1c47d5ae436f82cbef88c4bd))


### Bug Fixes

* user model ([08776ab](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/08776ab7755809b1fdff8b9cdbf016058da96866))
* user model ([77afed0](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/77afed0f71ad771cd2f6cb919ca480547028846c))

## [1.1.3](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.1.2...v1.1.3) (2025-04-09)


### Bug Fixes

* Update composer.json ([0bf706c](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/0bf706c04764af041ad9a408a84edc769f418420))
* Update composer.json ([c371dc2](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/c371dc2a8de7b513c96603fa11f9af5242cfe4af))

## [1.1.2](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.1.1...v1.1.2) (2025-03-17)


### Miscellaneous Chores

* Update .gitignore ([003b369](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/003b369248b0533821bd33a3d0c115e56c439042))
* Update .gitignore ([38db055](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/38db0555894764d4259308cea2a943aa5c7bb04d))

## [1.1.1](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.1.0...v1.1.1) (2025-03-11)


### Bug Fixes

* docker compose elasticsearch oc:4975 ([6b2b549](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/6b2b549e4786fefb6f4b838f87be6c2f46fcef4d))
* Update develop.compose.yml ([8fc9f31](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/8fc9f311340b00d374cecac912d1d2005ff5ffb4))
* Update develop.compose.yml ([002d2a6](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/002d2a699cf6ec1e719d7ddb4ca972797f52fcdb))


### Miscellaneous Chores

* Update .env-example ([1c21546](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/1c215463a1e59aa67a50156baaa7f6502b8da6a3))
* Update .env-example ([fec7c1c](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/fec7c1c2d193d797aa07a5daaf4339c381ff2fd8))
* Update init-docker.sh ([0714a88](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/0714a88e2e2b2ebcee5880dbf99287f9d0aff414))
* Update init-docker.sh ([d1edddb](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/d1edddb6c0218c35aaf5c3757f2a398970851934))

## [1.1.0](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.0.1...v1.1.0) (2025-02-27)


### Features

* increase post_max_size in php.ini ([59332d6](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/59332d6912a835890c2681552f4a19b6b6388b63))

## [1.0.1](https://github.com/webmappsrl/laravel-postgis-boilerplate/compare/v1.0.0...v1.0.1) (2025-02-20)


### Bug Fixes

* pg_dump version ([e440add](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/e440add260da5c7e404e14894a62d9a78cb4cea9))
* update wm-package version ([4c6d152](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/4c6d1524310f9ff05c7c5fa15820687a4db8e9ec))

## 1.0.0 (2025-02-12)


### ⚠ BREAKING CHANGES

* laravel 11 and new wm-package
* updated to laravel 11

### Features

* add GitHub Actions workflows for CI/CD ([0f81bff](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/0f81bff0aa3cd6c4535f56f3b101b7e0497e0703))
* configured log viewer ([1f0c069](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/1f0c06991956553a174bdb377e0b23bb92c7c86f))
* enable code coverage xdebug feature on xdebug.ini oc: 4354 ([1a24374](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/1a2437416f22adab474f6e74de634ba40774bfe8))
* laravel 11 and new wm-package ([7bd7913](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/7bd79139340c25bbbb53ddf1bf51ba1466428d8a))
* update wm-package ([f40e772](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/f40e772befbc22931033f647ab916e1ce7a9fd21))
* updated compose and readme ([acb0111](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/acb01115edd598d8111b9cb4c54d7b46997ebe44))
* updated to laravel 11 ([87715ca](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/87715caa106cf25f041e6c06befb10f8531ee3b1))


### Bug Fixes

* github actions ([6f61f43](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/6f61f43a64d6acb6dff489b13420cc951845c466))
* init-docker script ([6366ccc](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/6366ccc327b37aadd839048cd92b0c1a4583a71d))
* phpstan error ([d181a54](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/d181a54283eafcffeb411ca832084c5bd5bbec1f))
* remove --build flag from docker compose commands ([251e0aa](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/251e0aa88fa74267061f35f7332946fb702ee7ac))
* update Nova service provider imports and menu authorization ([1bf75d6](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/1bf75d68fdc74175539cd0805906f973ca502479))
* update README and init-docker script with minor improvements ([91ea0a4](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/91ea0a4c843eb5a0e4d234bb828504b89799aa8d))


### Miscellaneous Chores

* add git submodule initialization to deployment scripts ([ebe6955](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/ebe6955221137eb694ccd4581d4f0e3281b015a9))
* installed horizon and log viewer packages ([262ea7a](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/262ea7a8c48221b749e05fba1430a3ee46842388))
* supervisor docker configuration ([4fe19fa](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/4fe19fa3333074e717673ce067ae7201eef7e0a1))
* updated dependencies ([25587f0](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/25587f032339379bd7e24b8c4ea38835ee54c677))
* updated docker compose and dockerFile ([d2f8ab1](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/d2f8ab1ebfd62a920d3ae8f69efc48659429ae59))
* updated laravel horizon and supervisor docker conf ([15f87e9](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/15f87e93374ee9c765ff849aaf91d5cb7e8491ad))
* updated to php 8.3 ([617f65b](https://github.com/webmappsrl/laravel-postgis-boilerplate/commit/617f65b96a52207b0d38aa1157ee99be6462aad6))

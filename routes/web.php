<?php

use App\Http\Controllers\OsmImportReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return redirect('/nova');
});

Route::middleware(['web', 'auth'])
    ->get('/nova-vendor/maphub/osm-import-reports/{token}', [OsmImportReportController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-]{16,64}')
    ->name('osm.import.report');

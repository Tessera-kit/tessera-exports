<?php

use App\Modules\Exports\Http\Controllers\Web\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:export reports')->prefix('exports')->name('exports.')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('index');
    Route::post('/', [ExportController::class, 'store'])->name('store');
    Route::get('/{export}/download', [ExportController::class, 'download'])->whereNumber('export')->name('download');
    Route::delete('/{export}', [ExportController::class, 'destroy'])->whereNumber('export')->name('destroy');
});

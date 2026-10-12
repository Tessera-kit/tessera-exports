<?php

namespace App\Modules\Exports;

use App\Modules\Exports\Services\ExportService;
use Illuminate\Console\Scheduling\Schedule;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Exports module.
 *
 * The engine that writes Excel and PDF files. The reports themselves come from the modules that own the data.
 */
class ExportsServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'exports',
            name: 'Exports',
            requires: [],
            packages: ['mpdf/mpdf', 'openspout/openspout'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('exports', 'Report exports (Excel & PDF)', ['export reports'], order: 130),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'reports' => ['export reports'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['export reports'],
            'Manager' => ['export reports'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('reports', 'exports.nav', 'exports.index', 'download', 'export reports', ['exports.*'], 40),
        ];
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->call(fn () => app(ExportService::class)->pruneExpired())->daily()->name('prune-exports')->onOneServer();
    }
}

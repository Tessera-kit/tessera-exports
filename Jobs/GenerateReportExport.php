<?php

namespace App\Modules\Exports\Jobs;

use App\Modules\Exports\Models\ExportFile;
use App\Modules\Exports\Services\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $exportId) {}

    public function handle(ExportService $service): void
    {
        $export = ExportFile::query()->with(['user.company'])->find($this->exportId);

        if ($export === null) {
            return;
        }

        $limit = config('exports.memory_limit');

        if ($limit) {
            ini_set('memory_limit', (string) $limit);
        }

        try {
            $service->generate($export);
        } catch (Throwable $e) {
            $service->fail($export, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $export = ExportFile::find($this->exportId);

        if ($export) {
            app(ExportService::class)->fail($export, $e);
        }
    }
}

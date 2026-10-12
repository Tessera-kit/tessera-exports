<?php

namespace App\Modules\Exports\Services;

use App\Modules\Exports\Engine\ExportGenerator;
use App\Modules\Exports\Engine\ReportRegistry;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportStatus;
use App\Modules\Exports\Jobs\GenerateReportExport;
use App\Modules\Exports\Models\ExportFile;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;
use Throwable;

class ExportService
{
    public function __construct(private readonly ReportRegistry $reports, private readonly ExportGenerator $generator) {}

    /**
     * Starts an export. Small ones are produced right away; big ones are queued and picked up by a worker.
     *
     * @param  array<string, mixed>  $filters
     */
    public function request(User $user, string $report, ExportFormat $format, string $locale, array $filters): ExportFile
    {
        $definition = $this->reports->get($report);

        if (! $user->can($definition->permission())) {
            throw ValidationException::withMessages(['report' => __('exports::exports.errors.not_allowed', [], $locale)]);
        }

        $filters = $this->validatedFilters($definition, $user, $filters);
        $context = $this->context($user, $locale, $filters);
        $definition->authorize($context);
        $rows = $definition->count($context);

        if ($format === ExportFormat::Pdf && $rows > (int) config('exports.pdf_max_rows')) {
            throw ValidationException::withMessages(['format' => __('exports::exports.errors.pdf_too_large', ['max' => number_format((int) config('exports.pdf_max_rows'))], $locale)]);
        }

        $export = ExportFile::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'report' => $report,
            'format' => $format,
            'locale' => $locale,
            'filters' => $filters,
            'status' => ExportStatus::Queued,
            'estimated_rows' => $rows,
        ]);

        $syncLimit = (int) config($format === ExportFormat::Pdf ? 'exports.pdf_sync_limit' : 'exports.sync_limit');

        if ($rows <= $syncLimit) {
            GenerateReportExport::dispatchSync($export->id);
        } else {
            GenerateReportExport::dispatch($export->id)->onQueue(config('exports.queue'));
        }

        return $export->refresh();
    }

    /**
     * Produces the file of an export record. Called by the queue job (or inline for small exports).
     */
    public function generate(ExportFile $export): void
    {
        if ($export->status === ExportStatus::Ready) {
            return;
        }

        $export->update(['status' => ExportStatus::Processing, 'started_at' => now(), 'error' => null]);

        $definition = $this->reports->get($export->report);
        $context = $this->context($export->user, $export->locale, $export->filters ?? []);

        $directory = "exports/{$export->tenant_id}";
        $disk = Storage::disk(config('exports.disk'));
        $disk->makeDirectory($directory);

        $relative = "{$directory}/{$export->id}-".bin2hex(random_bytes(6)).'.'.$export->format->value;
        $absolute = $disk->path($relative);

        try {
            $rows = $this->generator->generate($definition, $context, $export->format, $absolute);
        } catch (Throwable $e) {
            if (is_file($absolute)) {
                @unlink($absolute);
            }

            throw $e;
        }

        $export->update([
            'status' => ExportStatus::Ready,
            'rows' => $rows,
            'path' => $relative,
            'filename' => $definition->filename($context).'.'.$export->format->value,
            'size' => $disk->size($relative),
            'finished_at' => now(),
            'expires_at' => now()->addDays((int) config('exports.keep_days')),
        ]);
    }

    public function fail(ExportFile $export, Throwable $e): void
    {
        report($e);

        $export->update([
            'status' => ExportStatus::Failed,
            'error' => __('exports::exports.errors.failed', [], $export->locale),
            'finished_at' => now(),
        ]);
    }

    public function delete(ExportFile $export): void
    {
        if ($export->path) {
            Storage::disk(config('exports.disk'))->delete($export->path);
        }

        $export->delete();
    }

    /**
     * Removes expired files and the records of exports that never finished.
     */
    public function pruneExpired(): int
    {
        $expired = ExportFile::query()
            ->where(fn ($query) => $query->where('expires_at', '<', now())->orWhere(fn ($stale) => $stale->whereIn('status', [ExportStatus::Queued->value, ExportStatus::Processing->value])->where('created_at', '<', now()->subDay())))
            ->get();

        $expired->each(fn (ExportFile $export) => $this->delete($export));

        return $expired->count();
    }

    public function context(User $user, string $locale, array $filters): ExportContext
    {
        return new ExportContext($user, $user->tenant, $locale, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function validatedFilters(ReportDefinition $definition, User $user, array $filters): array
    {
        $rules = [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            ...$definition->filterRules($user->tenant_id),
        ];

        $validator = Validator::make($filters, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn (array $messages, string $key): array => ["filters.{$key}" => $messages])->all());
        }

        return array_filter($validator->validated(), fn ($value): bool => $value !== null && $value !== '');
    }
}

<?php

namespace App\Modules\Exports\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Http\Requests\StoreExportRequest;
use App\Modules\Exports\Models\ExportFile;
use App\Modules\Exports\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    public function index(Request $request): Response
    {
        $files = ExportFile::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ExportFile $export): array => $this->payload($export));

        return Inertia::render('Exports/Index', [
            'exports' => $files,
            'keepDays' => (int) config('exports.keep_days'),
        ]);
    }

    public function store(StoreExportRequest $request): JsonResponse
    {
        $export = $this->exports->request(
            $request->user(),
            $request->validated('report'),
            ExportFormat::from($request->validated('format')),
            $request->validated('locale') ?? app()->getLocale(),
            $request->validated('filters') ?? [],
        );

        return response()->json($this->payload($export), 201);
    }

    public function download(Request $request, ExportFile $export): StreamedResponse
    {
        $this->ensureOwned($request, $export);
        abort_unless($export->isDownloadable() && Storage::disk(config('exports.disk'))->exists($export->path), 404);

        return Storage::disk(config('exports.disk'))->download($export->path, $export->filename, [
            'Content-Type' => $export->format->mimeType(),
        ]);
    }

    public function destroy(Request $request, ExportFile $export): RedirectResponse
    {
        $this->ensureOwned($request, $export);
        $this->exports->delete($export);

        return back()->with('success', 'Export deleted.');
    }

    private function ensureOwned(Request $request, ExportFile $export): void
    {
        abort_unless($export->tenant_id === $request->user()->tenant_id && $export->user_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ExportFile $export): array
    {
        return [
            'id' => $export->id,
            'report' => $export->report,
            'format' => $export->format->value,
            'status' => $export->status->value,
            'rows' => $export->rows,
            'estimated_rows' => $export->estimated_rows,
            'size' => $export->size,
            'filename' => $export->filename,
            'error' => $export->error,
            'created_at' => $export->created_at->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'download_url' => $export->isDownloadable() ? route('exports.download', $export) : null,
        ];
    }
}

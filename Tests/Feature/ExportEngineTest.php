<?php

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Exports\Engine\ReportRegistry;
use App\Modules\Exports\Models\ExportFile;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;
use Tessera\Engine\ModuleRegistry;

class EngineDemoReport extends ReportDefinition
{
    public function key(): string
    {
        return 'demo';
    }

    public function permission(): string
    {
        return 'export reports';
    }

    public function columns(ExportContext $context): array
    {
        return [new Column('name', Column::TEXT, 20), new Column('amount', Column::MONEY, 12)];
    }

    public function rows(ExportContext $context): iterable
    {
        yield ['name' => 'Coffee', 'amount' => 4.5];
        yield ['name' => 'Tea', 'amount' => 3];
    }

    public function count(ExportContext $context): int
    {
        return 2;
    }

    public function filename(ExportContext $context): string
    {
        return 'demo';
    }
}

/**
 * @return list<list<mixed>>
 */
function engineRows(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();

    return $rows;
}

beforeEach(function () {
    Storage::fake('local');
    Permission::findOrCreate('export reports');

    $demo = new EngineDemoReport;
    $this->app->instance(ReportRegistry::class, new class(app(ModuleRegistry::class), $demo) extends ReportRegistry
    {
        public function __construct(ModuleRegistry $modules, private readonly ReportDefinition $demo)
        {
            parent::__construct($modules);
        }

        public function all(): array
        {
            return ['demo' => $this->demo];
        }
    });

    $this->company = Company::factory()->create(['name' => 'Beirut Mart', 'timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id])->givePermissionTo('export reports');
});

it('writes a report to Excel with the company name, headings and rows', function () {
    $response = $this->actingAs($this->user)->postJson(route('exports.store'), ['report' => 'demo', 'format' => 'xlsx', 'locale' => 'en', 'filters' => []])
        ->assertCreated()->assertJsonPath('status', 'ready');

    $export = ExportFile::findOrFail($response->json('id'));
    $rows = engineRows(Storage::disk(config('exports.disk'))->path($export->path));

    expect($rows[1][0])->toBe('Beirut Mart')
        ->and(collect($rows)->contains(fn (array $row): bool => ($row[0] ?? null) === 'Coffee' && (float) ($row[1] ?? 0) === 4.5))->toBeTrue()
        ->and(collect($rows)->contains(fn (array $row): bool => ($row[0] ?? null) === 'Tea'))->toBeTrue();

    $this->actingAs($this->user)->get($response->json('download_url'))->assertOk()->assertHeader('content-type', $export->format->mimeType());
});

it('writes the same report to PDF in Arabic', function () {
    $response = $this->actingAs($this->user)->postJson(route('exports.store'), ['report' => 'demo', 'format' => 'pdf', 'locale' => 'ar', 'filters' => []])->assertCreated();

    $path = Storage::disk(config('exports.disk'))->path(ExportFile::findOrFail($response->json('id'))->path);

    expect(file_get_contents($path, false, null, 0, 5))->toBe('%PDF-');
});

it('refuses reports no module offers and people without the permission', function () {
    $this->actingAs($this->user)->postJson(route('exports.store'), ['report' => 'ghost', 'format' => 'xlsx', 'locale' => 'en'])->assertUnprocessable()->assertJsonValidationErrors('report');

    $stranger = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
    $this->actingAs($stranger)->postJson(route('exports.store'), ['report' => 'demo', 'format' => 'xlsx', 'locale' => 'en'])->assertForbidden();
});

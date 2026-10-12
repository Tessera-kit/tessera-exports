<?php

namespace App\Modules\Exports\Engine;

use App\Modules\Exports\Enums\ExportFormat;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;
use Tessera\Engine\Exports\ReportLayout;

/**
 * Builds the layout of a report for a context and writes it in the requested format.
 */
class ExportGenerator
{
    public function __construct(private readonly XlsxWriter $xlsx, private readonly PdfWriter $pdf) {}

    public function layout(ReportDefinition $report, ExportContext $context): ReportLayout
    {
        $columns = $report->columns($context);

        $headings = array_map(function (Column $column) use ($context): string {
            $label = $context->t("columns.{$column->key}");

            return $column->type === Column::MONEY ? "{$label} ({$context->currency()})" : $label;
        }, $columns);

        $generated = $context->t('generated', [
            'date' => $context->local(now())?->format('Y-m-d H:i'),
            'user' => $context->user->name,
        ]);

        return new ReportLayout(
            context: $context,
            title: $context->t("reports.{$report->key()}"),
            companyName: $context->company->name,
            generatedLine: $generated,
            filterLines: $report->filterLines($context),
            summary: $report->summary($context),
            columns: $columns,
            headings: $headings,
            rows: $report->rows($context),
        );
    }

    /**
     * Writes the report to a file and returns how many data rows it holds.
     */
    public function generate(ReportDefinition $report, ExportContext $context, ExportFormat $format, string $path): int
    {
        $layout = $this->layout($report, $context);

        return $format === ExportFormat::Pdf ? $this->pdf->write($layout, $path) : $this->xlsx->write($layout, $path);
    }
}

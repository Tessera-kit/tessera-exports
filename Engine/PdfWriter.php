<?php

namespace App\Modules\Exports\Engine;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ReportLayout;

/**
 * Writes a report as PDF with mPDF, which shapes Arabic and lays out right-to-left correctly. Rows are written in
 * small tables of CHUNK rows so the HTML never has to exist as one huge string.
 */
class PdfWriter
{
    private const CHUNK = 250;

    public function write(ReportLayout $layout, string $path): int
    {
        $context = $layout->context;
        $rtl = $context->isRtl();
        $formatter = new ValueFormatter($context);

        $temp = storage_path('app/private/mpdf');

        if (! is_dir($temp)) {
            mkdir($temp, 0775, true);
        }

        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => count($layout->columns) > 6 ? 'A4-L' : 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 6,
            'tempDir' => $temp,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'fontDir' => $defaults['fontDir'],
            'fontdata' => $fonts['fontdata'],
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'simpleTables' => true,
            'packTableData' => true,
            'shrink_tables_to_fit' => 1,
        ]);

        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->SetTitle($layout->title);
        $mpdf->SetAuthor($layout->tenantName);
        $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#777777;">'.e($layout->title).' — {PAGENO} / {nbpg}</div>');

        $mpdf->WriteHTML($this->styles($rtl), HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($this->heading($layout, $formatter), HTMLParserMode::HTML_BODY);

        $widths = $this->widths($layout->columns);
        $count = 0;
        $batch = [];

        foreach ($layout->rows as $row) {
            $batch[] = $row;

            if (count($batch) >= self::CHUNK) {
                $mpdf->WriteHTML($this->table($layout, $formatter, $widths, $batch), HTMLParserMode::HTML_BODY);
                $count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $mpdf->WriteHTML($this->table($layout, $formatter, $widths, $batch), HTMLParserMode::HTML_BODY);
            $count += count($batch);
        }

        $mpdf->Output($path, Destination::FILE);

        return $count;
    }

    private function styles(bool $rtl): string
    {
        $start = $rtl ? 'right' : 'left';
        $end = $rtl ? 'left' : 'right';

        return <<<CSS
        body { font-family: dejavusans; font-size: 9pt; color: #222222; }
        h1 { font-size: 15pt; margin: 0 0 2pt 0; }
        .tenant { font-size: 10pt; font-weight: bold; margin: 0; }
        .muted { color: #666666; font-size: 8pt; margin: 1pt 0; }
        table.summary { border-collapse: collapse; margin: 6pt 0 8pt 0; }
        table.summary td { padding: 2pt 8pt; border: 0.3pt solid #cccccc; font-size: 9pt; }
        table.summary td.label { background-color: #f2f2f2; font-weight: bold; text-align: {$start}; }
        table.summary td.value { text-align: {$end}; }
        table.data { border-collapse: collapse; width: 100%; margin-top: 2pt; }
        table.data th { background-color: #373737; color: #ffffff; padding: 3pt 4pt; font-size: 8pt; border: 0.3pt solid #373737; text-align: center; }
        table.data td { padding: 2.5pt 4pt; border: 0.3pt solid #d4d4d4; font-size: 8pt; }
        table.data tr.alt td { background-color: #f7f7f7; }
        td.text { text-align: {$start}; }
        td.num { text-align: {$end}; }
        CSS;
    }

    private function heading(ReportLayout $layout, ValueFormatter $formatter): string
    {
        $html = '<h1>'.e($layout->title).'</h1>';
        $html .= '<p class="tenant">'.e($layout->tenantName).'</p>';
        $html .= '<p class="muted">'.e($layout->generatedLine).'</p>';

        foreach ($layout->filterLines as $line) {
            $html .= '<p class="muted">'.e($line).'</p>';
        }

        if ($layout->summary !== []) {
            $html .= '<table class="summary">';

            foreach ($layout->summary as $figure) {
                $html .= '<tr><td class="label">'.e($figure['label']).'</td><td class="value">'.e($formatter->text($figure['value'], $figure['type'])).'</td></tr>';
            }

            $html .= '</table>';
        }

        return $html;
    }

    /**
     * @param  list<Column>  $columns
     * @return list<float> percentages
     */
    private function widths(array $columns): array
    {
        $total = array_sum(array_map(fn (Column $column): float => $column->width, $columns)) ?: 1;

        return array_map(fn (Column $column): float => round($column->width / $total * 100, 2), $columns);
    }

    /**
     * @param  list<float>  $widths
     * @param  list<array<string, mixed>>  $rows
     */
    private function table(ReportLayout $layout, ValueFormatter $formatter, array $widths, array $rows): string
    {
        $html = '<table class="data"><thead><tr>';

        foreach ($layout->headings as $index => $heading) {
            $html .= '<th style="width:'.$widths[$index].'%">'.e($heading).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $position => $row) {
            $html .= '<tr'.($position % 2 === 1 ? ' class="alt"' : '').'>';

            foreach ($layout->columns as $column) {
                $html .= '<td class="'.($column->isNumeric() ? 'num' : 'text').'">'.e($formatter->text($row[$column->key] ?? null, $column->type)).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }
}

<?php

namespace App\Modules\Exports\Engine;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ReportLayout;

/**
 * Writes a report as .xlsx one row at a time, so memory stays flat however many rows there are.
 * Text is always stored as text, never as a formula.
 */
class XlsxWriter
{
    public function write(ReportLayout $layout, string $path): int
    {
        $context = $layout->context;
        $options = new Options;

        foreach ($layout->columns as $index => $column) {
            $options->setColumnWidth($column->width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        $bold = (new Style)->withFontBold(true);
        $titleStyle = (new Style)->withFontBold(true)->withFontSize(14);
        $muted = (new Style)->withFontColor(Color::rgb(100, 100, 100));
        $headerStyle = (new Style)
            ->withFontBold(true)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor(Color::rgb(55, 55, 55))
            ->withShouldWrapText(true)
            ->withCellAlignment(CellAlignment::CENTER);

        $lines = [[$layout->title, $titleStyle], [$layout->companyName, $bold], [$layout->generatedLine, $muted]];

        foreach ($layout->filterLines as $line) {
            $lines[] = [$line, $muted];
        }

        $headerRow = count($lines) + 1 + ($layout->summary === [] ? 0 : count($layout->summary) + 1) + 1;

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($layout->title));
        $sheet->setSheetView(new SheetView(rightToLeft: $context->isRtl(), freezeRow: $headerRow + 1));

        foreach ($lines as [$text, $style]) {
            $writer->addRow(new Row([new StringCell($text, $style)]));
        }

        $writer->addRow(new Row([]));

        foreach ($layout->summary as $figure) {
            $writer->addRow(new Row([
                new StringCell($figure['label'], $bold),
                $this->cell($figure['value'], $figure['type'], $this->style($figure['type'], numeric: true)),
            ]));
        }

        if ($layout->summary !== []) {
            $writer->addRow(new Row([]));
        }

        $writer->addRow(new Row(array_map(fn (string $heading): StringCell => new StringCell($heading, $headerStyle), $layout->headings)));

        $styles = array_map(fn (Column $column): Style => $this->style($column->type, $column->isNumeric()), $layout->columns);
        $limit = (int) config('exports.xlsx_max_rows') - $headerRow - 1;
        $count = 0;

        foreach ($layout->rows as $row) {
            if ($count >= $limit) {
                break;
            }

            $cells = [];

            foreach ($layout->columns as $index => $column) {
                $cells[] = $this->cell($row[$column->key] ?? null, $column->type, $styles[$index]);
            }

            $writer->addRow(new Row($cells));
            $count++;
        }

        $writer->close();

        return $count;
    }

    private function cell(mixed $value, string $type, Style $style): Cell
    {
        if ($value === null || $value === '') {
            return new StringCell('', $style);
        }

        if ($value instanceof CarbonInterface) {
            return Cell::fromValue(DateTimeImmutable::createFromInterface($value), $style);
        }

        if (in_array($type, [Column::INTEGER, Column::NUMBER, Column::MONEY, Column::PERCENT], true) && is_numeric($value)) {
            return Cell::fromValue(0 + $value, $style);
        }

        return new StringCell((string) $value, $style);
    }

    private function style(string $type, bool $numeric): Style
    {
        $style = new Style;
        $format = ValueFormatter::excelFormat($type);

        if ($format !== null) {
            $style = $style->withFormat($format);
        }

        return $numeric ? $style->withCellAlignment(CellAlignment::RIGHT) : $style;
    }

    /**
     * Excel limits sheet names to 31 characters and forbids : \ / ? * [ ]
     */
    private function sheetName(string $title): string
    {
        $clean = trim(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $title) ?? '');

        return mb_substr($clean !== '' ? $clean : 'Report', 0, 31);
    }
}

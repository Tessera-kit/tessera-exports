<?php

namespace App\Modules\Exports\Engine;

use Carbon\CarbonInterface;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;

/**
 * Turns raw report values into text for the PDF and into number formats for Excel.
 */
class ValueFormatter
{
    public function __construct(private readonly ExportContext $context) {}

    public function text(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            Column::INTEGER => number_format((float) $value, 0),
            Column::NUMBER, Column::MONEY => number_format((float) $value, 2),
            Column::PERCENT => number_format((float) $value, 2).'%',
            Column::DATE => $value instanceof CarbonInterface ? $this->context->local($value)?->format('Y-m-d') ?? '' : (string) $value,
            Column::DATETIME => $value instanceof CarbonInterface ? $this->context->local($value)?->format('Y-m-d H:i') ?? '' : (string) $value,
            default => (string) $value,
        };
    }

    public static function excelFormat(string $type): ?string
    {
        return match ($type) {
            Column::INTEGER => '#,##0',
            Column::NUMBER, Column::MONEY => '#,##0.00',
            Column::PERCENT => '0.00"%"',
            Column::DATE => 'yyyy-mm-dd',
            Column::DATETIME => 'yyyy-mm-dd hh:mm',
            default => null,
        };
    }
}

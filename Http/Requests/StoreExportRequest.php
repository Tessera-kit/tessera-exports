<?php

namespace App\Modules\Exports\Http\Requests;

use App\Modules\Exports\Engine\ReportRegistry;
use App\Modules\Exports\Enums\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export reports') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'report' => ['required', 'string', Rule::in(app(ReportRegistry::class)->keys())],
            'format' => ['required', Rule::in(ExportFormat::values())],
            'locale' => ['nullable', Rule::in(['en', 'ar'])],
            'filters' => ['nullable', 'array'],
        ];
    }
}

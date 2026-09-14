<?php

namespace App\Http\Requests\Legislation;

use App\Models\Ordinance;
use App\Models\Resolution;
use Illuminate\Foundation\Http\FormRequest;

class StoreLegislationImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('ordinances.import.store')) {
            return $this->user()?->can('create', Ordinance::class) ?? false;
        }

        return $this->user()?->can('create', Resolution::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $csvMax = (int) config('sentria.legislation.import_csv_max_kb', 2048);
        $zipMax = (int) config('sentria.legislation.import_zip_max_kb', 204800);

        return [
            'csv' => [
                'required',
                'file',
                'max:'.$csvMax,
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
            ],
            'zip' => [
                'required',
                'file',
                'max:'.$zipMax,
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
            ],
        ];
    }
}

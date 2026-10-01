<?php

namespace App\Http\Requests\Legislation;

use App\Models\Ordinance;
use App\Models\Resolution;
use App\States\Publication\Published;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSignedCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('ordinance') ?? $this->route('resolution');

        if (! $record instanceof Ordinance && ! $record instanceof Resolution) {
            return false;
        }

        return $this->user()?->can('update', $record) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('sentria.documents.max_upload_size_kb', 51200);

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:application/pdf',
            ],
            'confirm_unpublish' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $record = $this->route('ordinance') ?? $this->route('resolution');

            if (! $record instanceof Ordinance && ! $record instanceof Resolution) {
                return;
            }

            $published = $record->document?->publications()
                ->where('status', Published::$name)
                ->exists() ?? false;

            if ($published && ! $this->boolean('confirm_unpublish')) {
                $validator->errors()->add(
                    'confirm_unpublish',
                    __('legislation.signed_copy_unpublish_required'),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => __('legislation.signed_copy_pdf_only'),
        ];
    }
}

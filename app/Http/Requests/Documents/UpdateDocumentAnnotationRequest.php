<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        $user = $this->user();

        return $user !== null
            && $document instanceof Document
            && $user->can('download', $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'array', 'max:5000'],
            'payload.*' => ['array'],
        ];
    }
}

<?php

namespace App\Http\Requests\Sessions;

use App\Models\AgendaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkCalendarRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AgendaItem::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:second-reading,postpone,third-reading,undo'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.agenda_item_id' => ['nullable', 'ulid', 'exists:agenda_items,id'],
            'items.*.document_id' => ['nullable', 'ulid', 'exists:documents,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = $this->input('action');

            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $agendaItemId = $item['agenda_item_id'] ?? null;
                $documentId = $item['document_id'] ?? null;
                $hasAgenda = is_string($agendaItemId) && $agendaItemId !== '';
                $hasDocument = is_string($documentId) && $documentId !== '';

                if (! $hasAgenda && ! $hasDocument) {
                    $validator->errors()->add("items.{$index}", 'An agenda item or document is required.');
                }

                if ($action === 'undo' && ! $hasAgenda) {
                    $validator->errors()->add("items.{$index}.agenda_item_id", 'An agenda item is required.');
                }
            }
        });
    }
}

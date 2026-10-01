<?php

namespace App\Http\Requests\Concerns;

trait ResolvesCommitteeReferralInput
{
    /**
     * Validated committee ids after the request has passed validation.
     *
     * @return list<string>
     */
    public function committeeIds(): array
    {
        $ids = $this->validated('committee_ids');

        if (is_array($ids) && $ids !== []) {
            $normalized = [];

            foreach ($ids as $id) {
                if (is_string($id) && $id !== '' && ! in_array($id, $normalized, true)) {
                    $normalized[] = $id;
                }
            }

            return $normalized;
        }

        $id = $this->validated('committee_id');

        return is_string($id) && $id !== '' ? [$id] : [];
    }

    public function meetingOn(): ?string
    {
        $value = $this->validated('meeting_on');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function remarks(): ?string
    {
        $value = $this->validated('remarks');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function committeeReferralDetailRules(bool $committeesRequired): array
    {
        $hasList = $this->incomingCommitteeIds() !== [];

        return [
            'committee_id' => [
                $committeesRequired && ! $hasList ? 'required' : 'nullable',
                'ulid',
                'exists:committees,id',
            ],
            'committee_ids' => [
                $committeesRequired && ! $this->filled('committee_id') ? 'required' : 'nullable',
                'array',
                'min:1',
            ],
            'committee_ids.*' => ['ulid', 'distinct', 'exists:committees,id'],
            'meeting_on' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareCommitteeReferralInput(): void
    {
        foreach (['meeting_on', 'remarks'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $ids = $this->incomingCommitteeIds();

        if ($ids !== []) {
            $this->merge(['committee_ids' => $ids]);
        }
    }

    /**
     * @return list<string>
     */
    private function incomingCommitteeIds(): array
    {
        $ids = $this->input('committee_ids');

        if (is_array($ids) && $ids !== []) {
            $normalized = [];

            foreach ($ids as $id) {
                if (is_string($id) && $id !== '' && ! in_array($id, $normalized, true)) {
                    $normalized[] = $id;
                }
            }

            return $normalized;
        }

        $id = $this->input('committee_id');

        return is_string($id) && $id !== '' ? [$id] : [];
    }
}

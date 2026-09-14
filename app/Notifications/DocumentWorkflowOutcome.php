<?php

namespace App\Notifications;

use App\Models\Document;

class DocumentWorkflowOutcome extends Notification
{
    public function __construct(
        public Document $document,
        public string $outcome,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'workflow';
    }

    public function priority(): string
    {
        return 'high';
    }

    public function actionUrl(): ?string
    {
        return route('documents.show', $this->document, absolute: false);
    }

    public function titleKey(): string
    {
        return match ($this->outcome) {
            'approved' => 'notifications.approved_title',
            'archive' => 'notifications.shelved_title',
            default => 'notifications.rejected_title',
        };
    }

    public function bodyKey(): string
    {
        return match ($this->outcome) {
            'approved' => 'notifications.approved_body',
            'archive' => 'notifications.shelved_body',
            default => 'notifications.rejected_body',
        };
    }

    /**
     * @return array<string, string|int|null>
     */
    public function titleParams(): array
    {
        return [
            'title' => $this->document->title,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function bodyParams(): array
    {
        return [
            'title' => $this->document->title,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Document;

class DocumentResubmitted extends Notification
{
    public function __construct(
        public Document $document,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'documents';
    }

    public function priority(): string
    {
        return 'normal';
    }

    public function actionUrl(): ?string
    {
        return route('documents.show', $this->document, absolute: false);
    }

    public function titleKey(): string
    {
        return 'notifications.document_resubmitted_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.document_resubmitted_body';
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
            'submitter' => $this->document->author?->display_name,
        ];
    }
}

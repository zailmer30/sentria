<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentVersion;

class DocumentProcessingCompleted extends Notification
{
    public function __construct(
        public Document $document,
        public DocumentVersion $version,
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
        return 'notifications.processing_completed_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.processing_completed_body';
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
            'version' => $this->version->version_number,
        ];
    }
}

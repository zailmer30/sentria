<?php

namespace App\Notifications;

use App\Models\Document;

class DocumentAccessGranted extends Notification
{
    public function __construct(
        public Document $document,
        public string $ability = 'view',
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
        return 'notifications.access_granted_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.access_granted_body';
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
            'ability' => $this->ability,
        ];
    }
}

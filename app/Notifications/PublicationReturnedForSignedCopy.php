<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\Publication;

class PublicationReturnedForSignedCopy extends Notification
{
    public function __construct(
        public Document $document,
        public Publication $publication,
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
        return route('publications.show', $this->publication, absolute: false);
    }

    public function titleKey(): string
    {
        return 'notifications.signed_copy_rewind_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.signed_copy_rewind_body';
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

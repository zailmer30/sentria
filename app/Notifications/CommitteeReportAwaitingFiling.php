<?php

namespace App\Notifications;

use App\Models\Committee;
use App\Models\CommitteeReport;
use App\Models\Document;

class CommitteeReportAwaitingFiling extends Notification
{
    public function __construct(
        public CommitteeReport $report,
        public Committee $committee,
        public ?Document $document = null,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'committees';
    }

    public function priority(): string
    {
        return 'normal';
    }

    public function actionUrl(): ?string
    {
        if ($this->document instanceof Document) {
            return route('documents.show', $this->document, absolute: false);
        }

        return route('committees.show', $this->committee, absolute: false);
    }

    public function titleKey(): string
    {
        return 'notifications.report_for_secretariat_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.report_for_secretariat_body';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function titleParams(): array
    {
        return [
            'committee' => $this->committee->name,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function bodyParams(): array
    {
        return [
            'committee' => $this->committee->name,
            'title' => $this->document?->title,
            'recommendation' => $this->report->recommendation,
        ];
    }
}

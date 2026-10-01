<?php

namespace App\Services\Minutes;

use App\Models\Minutes;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\Branding\BrandingService;
use App\States\Minutes\AiDraft;
use App\States\Minutes\Archive;
use App\States\Minutes\FinalMinutes;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MinutesPdf
{
    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    public function download(Minutes $minutes): Response
    {
        $minutes->loadMissing('session');
        $session = $minutes->session;
        abort_unless($session !== null, 404);

        $fontDir = storage_path('fonts');

        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }

        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('minutes.pdf', $this->viewData($minutes));
        $pdf->setPaper('a4');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('times');

        if (is_string($font)) {
            $canvas->page_text(48, 810, $this->footerSessionNumber($session->session_number), $font, 9, [0, 0, 0]);
            $canvas->page_text(455, 810, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 9, [0, 0, 0]);
        }

        return $pdf->download($this->filename($session->session_number));
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Minutes $minutes): array
    {
        $session = $minutes->session;
        abort_unless($session !== null, 404);

        $brand = $this->branding->snapshot();
        $seal = $this->sealPath($brand->logoPath);
        $when = $session->adjourned_at ?? $session->actual_start_at ?? $session->scheduled_start_at;

        return [
            'locality' => $brand->locality,
            'organization' => $brand->name,
            'sealPath' => $seal,
            'title' => mb_strtoupper($session->title, 'UTF-8'),
            'dateLine' => $when?->copy()->timezone(LegislativeMinutesGenerator::DISPLAY_TIMEZONE)->format('F j, Y'),
            'venue' => $session->venue,
            'showDraft' => ! ($minutes->status instanceof FinalMinutes) && ! ($minutes->status instanceof Archive),
            'aiBanner' => $minutes->status instanceof AiDraft ? LegislativeMinutesGenerator::DRAFT_BANNER : null,
            'bodyHtml' => Str::markdown($this->bodyMarkdown((string) $minutes->content), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ];
    }

    /**
     * The page chrome already prints the sitting title and the draft marks.
     * Drop only the generator's opening heading and banner so a finalized PDF
     * does not repeat them, and keep every later section, including discussion
     * lines under each agenda item.
     */
    private function bodyMarkdown(string $content): string
    {
        $content = preg_replace('/\A#\s+Minutes\s+—\s+.*\R+/u', '', $content) ?? $content;
        $banner = preg_quote(LegislativeMinutesGenerator::DRAFT_BANNER, '/');
        $content = preg_replace('/\A>\s*'.$banner.'\s*\R+/u', '', ltrim($content)) ?? $content;

        return ltrim($content);
    }

    private function sealPath(?string $logoPath): ?string
    {
        if ($logoPath === null || $logoPath === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $absolute = Storage::disk('public')->path($logoPath);

        return is_file($absolute) ? $absolute : null;
    }

    private function filename(string $sessionNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $sessionNumber) ?: 'minutes';

        return 'minutes-'.$safe.'.pdf';
    }

    private function footerSessionNumber(string $sessionNumber): string
    {
        if (mb_strlen($sessionNumber) <= 48) {
            return $sessionNumber;
        }

        return mb_substr($sessionNumber, 0, 45).'...';
    }
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 48pt 48pt 56pt 48pt; }
        body { font-family: Times, "Times New Roman", serif; font-size: 11pt; color: #111; }
        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .letterhead td { vertical-align: middle; }
        .seal { width: 64pt; }
        .seal img { width: 58pt; height: auto; }
        .identity { text-align: center; }
        .republic { font-style: italic; font-size: 11pt; margin: 0; }
        .locality { margin: 1pt 0 0; }
        .organization { margin: 2pt 0 0; font-weight: bold; letter-spacing: 0.04em; }
        .rule { border: 0; border-top: 1px solid #111; margin: 8pt 0 12pt; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 4pt; line-height: 1.35; }
        .meta { text-align: center; margin: 0 0 8pt; }
        .draft { text-align: center; font-weight: bold; letter-spacing: 0.14em; margin: 8pt 0 2pt; }
        .banner { text-align: center; font-size: 9pt; margin: 0 0 10pt; }
        .body h1 { font-size: 13pt; text-align: left; }
        .body h2 { font-size: 12pt; margin: 12pt 0 4pt; }
        .body h3 { font-size: 11pt; margin: 8pt 0 2pt; }
        .body p { margin: 0 0 6pt; }
        .body ul, .body ol { margin: 0 0 8pt 16pt; padding: 0; }
        .body li { margin: 0 0 2pt; }
    </style>
</head>
<body>
    <table class="letterhead">
        <tr>
            <td class="seal">
                @if ($sealPath)
                    <img src="{{ $sealPath }}" alt="">
                @endif
            </td>
            <td class="identity">
                <p class="republic">Republic of the Philippines</p>
                <p class="locality">{{ $locality }}</p>
                <p class="organization">{{ $organization }}</p>
            </td>
            <td class="seal"></td>
        </tr>
    </table>
    <hr class="rule">
    <h1>{{ $title }}</h1>
    @if ($dateLine || $venue)
        <p class="meta">
            {{ $dateLine }}@if ($dateLine && $venue) · @endif{{ $venue }}
        </p>
    @endif
    @if ($showDraft)
        <p class="draft">DRAFT</p>
    @endif
    @if ($aiBanner)
        <p class="banner">{{ $aiBanner }}</p>
    @endif
    <div class="body">{!! $bodyHtml !!}</div>
</body>
</html>

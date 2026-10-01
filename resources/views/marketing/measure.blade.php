<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 54pt 60pt 64pt 60pt; }
        body { font-family: Times, "Times New Roman", serif; font-size: 11.5pt; color: #111; line-height: 1.45; }
        .identity { text-align: center; margin: 0 0 6pt; }
        .identity p { margin: 0; text-align: center; }
        .republic { font-style: italic; }
        .organization { font-weight: bold; letter-spacing: 0.06em; margin-top: 2pt; }
        .office { font-size: 10pt; letter-spacing: 0.04em; margin-top: 2pt; }
        .rule { border: 0; border-top: 1.5px solid #111; margin: 8pt 0 14pt; }
        .number { text-align: center; font-weight: bold; font-size: 12.5pt; margin: 0; }
        .series { text-align: center; margin: 0 0 10pt; }
        .authors { margin: 0 0 12pt; font-size: 10.5pt; }
        .authors span { font-weight: bold; }
        h1 { font-size: 12pt; text-align: center; text-transform: uppercase; margin: 0 0 14pt; line-height: 1.4; }
        h2 { font-size: 11.5pt; text-align: center; letter-spacing: 0.08em; margin: 16pt 0 8pt; }
        p { margin: 0 0 8pt; text-align: justify; }
        .clause { font-style: italic; margin: 10pt 0 10pt; }
        .section-heading { font-weight: bold; }
        .whereas span { font-weight: bold; }
        .items { margin: 0 0 8pt 22pt; padding: 0; }
        .items li { margin: 0 0 4pt; text-align: justify; }
        .certification { margin-top: 22pt; }
        .signatures { width: 100%; margin-top: 18pt; border-collapse: collapse; }
        .signatures td { width: 50%; vertical-align: top; padding-top: 22pt; font-size: 10.5pt; }
        .signatures .name { font-weight: bold; text-transform: uppercase; }
        .footer { position: fixed; bottom: -40pt; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #777; }
    </style>
</head>
<body>
    <div class="footer">{{ $footer }}</div>

    <div class="identity">
        <p class="republic">Republic of the Philippines</p>
        <p>{{ $locality }}</p>
        <p class="organization">{{ mb_strtoupper($organization, 'UTF-8') }}</p>
        <p class="office">OFFICE OF THE SECRETARY TO THE SANGGUNIAN</p>
    </div>
    <hr class="rule">

    <p class="number">{{ $measure['number_label'] }}</p>
    <p class="series">Series of {{ $year }}</p>

    <p class="authors">
        <span>{{ $measure['authorship_label'] }}:</span> {{ $author }}
        @if ($coAuthors !== [])
            <br><span>Co-authors:</span> {{ implode('; ', $coAuthors) }}
        @endif
    </p>

    <h1>{{ $measure['title'] }}</h1>

    @if (! empty($measure['explanatory_note']))
        <h2>EXPLANATORY NOTE</h2>
        @foreach ($measure['explanatory_note'] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    @endif

    @if (! empty($measure['whereas']))
        @foreach ($measure['whereas'] as $paragraph)
            <p class="whereas"><span>WHEREAS,</span> {{ $paragraph }}</p>
        @endforeach
    @endif

    <p class="clause">{{ $measure['enacting_clause'] }}</p>

    @foreach ($measure['sections'] as $section)
        <p>
            @if (! empty($section['heading']))
                <span class="section-heading">{{ $section['heading'] }}</span>
            @endif
            {{ $section['body'] }}
        </p>
        @if (! empty($section['items']))
            <ol class="items" type="a">
                @foreach ($section['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ol>
        @endif
    @endforeach

    @if ($enactedLine)
        <p class="certification">{{ $enactedLine }}</p>
        <table class="signatures">
            <tr>
                <td>
                    I hereby certify to the correctness of the foregoing.<br><br>
                    <span class="name">{{ $secretary }}</span><br>
                    Secretary to the Sanggunian
                </td>
                <td>
                    Attested:<br><br>
                    <span class="name">{{ $presidingOfficer }}</span><br>
                    Vice Governor and Presiding Officer
                </td>
            </tr>
        </table>
    @endif
</body>
</html>

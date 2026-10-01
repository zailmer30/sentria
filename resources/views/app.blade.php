@php
    $platePattern = app(\App\Services\Branding\BrandingService::class)->platePatternAttribute();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="sentria" @if ($platePattern) data-plate-pattern="{{ $platePattern }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#e6eaee" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#06080a" media="(prefers-color-scheme: dark)">

        <title inertia>{{ config('app.name', 'Sentria') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Resolve the theme before first paint: a chamber tablet must never flash white. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('sentria.theme');
                    var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                } catch (e) {
                    /* Private mode or blocked storage: fall through to the light default. */
                }
            })();
        </script>

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @php
            $brandCss = app(\App\Services\Branding\BrandingService::class)->css();
        @endphp
        @if ($brandCss !== '')
            <style id="sentria-brand">{!! $brandCss !!}</style>
        @endif
        @inertiaHead
    </head>
    <body class="min-h-screen bg-shell font-sans text-ink antialiased">
        <!--
        THESIS: The legislative record as a precision instrument. Authority here is earned by
        typographic discipline, alignment and unambiguous state — not by ornament, and not by the
        archival paper costume a records system is always tempted to wear.
        OWN-WORLD: Graphite and white on cool neutrals, hairline separation, one grotesque (Space Grotesk)
        for every word — headings, body, identity, and measurement. The only two
        saturated colours in the entire product are the two colours of the flag, each with exactly
        one job: national blue #0038A8 marks where you act, national red #CE1126 marks what is
        happening now. Nothing decorative may claim either.
        STORY: Find the record, read its state at a glance, take the one action available, leave a
        traceable mark.
        FIRST VIEWPORT: Nav rail left; the live session panel across the top with agenda item, tally,
        quorum and running clock; a dense register beneath. The primary action sits on the live panel.
        FORM: Order of Business — precision-technical register, replacing The Transparency Board.
        FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
        -->
        @inertia
    </body>
</html>

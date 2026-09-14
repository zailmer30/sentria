<?php

use Illuminate\Support\Facades\Http;

it('emits same-origin Vite URLs during development so the SPA can load through a forwarded port', function (): void {
    config(['app.debug' => true]);

    $hot = public_path('hot');
    $original = is_file($hot) ? file_get_contents($hot) : null;
    file_put_contents($hot, "http://127.0.0.1:5173\n");

    try {
        $html = (string) $this->get('/portal')->getContent();

        expect($html)
            ->toContain('src="/@vite/client"')
            ->toContain("from '/@react-refresh'")
            ->not->toContain('http://127.0.0.1:5173/@vite/client')
            ->not->toContain('http://localhost:5173/@vite/client');
    } finally {
        if ($original === null) {
            @unlink($hot);
        } else {
            file_put_contents($hot, $original);
        }
    }
});

it('proxies Vite HMR assets through Laravel during development', function (): void {
    config(['app.debug' => true]);

    $hot = public_path('hot');
    $original = is_file($hot) ? file_get_contents($hot) : null;
    file_put_contents($hot, "http://127.0.0.1:5173\n");

    Http::fake([
        'http://127.0.0.1:5173/@vite/client' => Http::response(
            'export default {}',
            200,
            ['Content-Type' => 'text/javascript'],
        ),
    ]);

    try {
        $this->get('/@vite/client')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript')
            ->assertSee('export default', false);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:5173/@vite/client');
    } finally {
        if ($original === null) {
            @unlink($hot);
        } else {
            file_put_contents($hot, $original);
        }
    }
});

it('does not proxy Vite assets when debug is off', function (): void {
    config(['app.debug' => false]);

    Http::fake();

    $this->get('/@vite/client')->assertNotFound();

    Http::assertNothingSent();
});

<?php

it('sets security headers on web responses', function (): void {
    $response = $this->get('/portal');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader(
            'Permissions-Policy',
            'camera=(), microphone=(self), geolocation=(), payment=(), usb=()',
        );

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain('frame-ancestors \'none\'')
        ->toContain("frame-src 'self' blob:")
        ->toContain("connect-src 'self' blob:")
        ->toContain("'wasm-unsafe-eval'");
});

it('allows the local Vite origin in CSP during development so the SPA can mount', function (): void {
    config(['app.debug' => true]);

    $csp = (string) $this->get('/portal')->headers->get('Content-Security-Policy');

    expect(app()->isProduction())->toBeFalse()
        ->and($csp)
        ->toContain('http://127.0.0.1:5173')
        ->toContain('http://localhost:5173');
});

it('allows the Impeccable live origin in CSP during development so live.js can load', function (): void {
    config(['app.debug' => true]);

    $csp = (string) $this->get('/portal')->headers->get('Content-Security-Policy');

    expect(app()->isProduction())->toBeFalse()
        ->and($csp)
        ->toContain('http://localhost:8400')
        ->toContain('http://127.0.0.1:8400');
});

it('does not allow the Impeccable live origin in CSP when debug is off', function (): void {
    config(['app.debug' => false]);

    $csp = (string) $this->get('/portal')->headers->get('Content-Security-Policy');

    expect($csp)
        ->not->toContain('http://localhost:8400')
        ->not->toContain('http://127.0.0.1:8400')
        ->not->toContain('http://localhost:5173');
});

it('does not set strict transport security over plain http', function (): void {
    $response = $this->get('/portal');

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('sets strict transport security when the request is secure', function (): void {
    $response = $this->get('https://localhost/portal');

    expect($response->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=31536000');
});

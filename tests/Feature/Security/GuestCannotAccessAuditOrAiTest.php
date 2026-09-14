<?php

it('redirects guests from audit and ai routes to login without leaking content', function (): void {
    $this->get('/audit')->assertRedirect('/login');
    $this->get('/ai')->assertRedirect('/login');
    $this->get('/ai/conversations/01JTESTCONVERSATION0000000000')->assertRedirect('/login');
});

it('does not expose audit log entries to guests', function (): void {
    $response = $this->get('/audit');

    $response->assertRedirect('/login')
        ->assertDontSee('audit', false)
        ->assertDontSee('sequence', false);
});

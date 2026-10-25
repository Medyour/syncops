<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_production_https_responses_include_security_headers(): void
    {
        app()->instance('env', 'production');

        $response = $this->withHeader('X-Forwarded-Proto', 'https')->get('/health');

        $response
            ->assertNoContent()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        app()->instance('env', 'production');

        $this->get('/health')
            ->assertNoContent()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_layout_exposes_a_keyboard_skip_link_and_main_landmark(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('Aller au contenu principal');
    }
}

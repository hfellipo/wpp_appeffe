<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Secretário');
    }

    public function test_api_ping_returns_ok_json(): void
    {
        $response = $this->getJson('/api/ping');
        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
        ]);
        $response->assertJsonStructure([
            'status',
            'message',
            'timestamp',
            'server_time',
        ]);
    }

    public function test_debug_test_endpoint_accepts_get_and_post(): void
    {
        $get = $this->getJson('/debug/test');
        $get->assertOk();
        $get->assertJson([
            'status' => 'ok',
        ]);

        $post = $this->postJson('/debug/test', ['hello' => 'world']);
        $post->assertOk();
        $post->assertJson([
            'status' => 'ok',
        ]);
    }

    public function test_public_debug_test_endpoint_accepts_get_and_post(): void
    {
        $get = $this->getJson('/public/debug/test');
        $get->assertOk();
        $get->assertJson([
            'status' => 'ok',
        ]);

        $post = $this->postJson('/public/debug/test', ['hello' => 'world']);
        $post->assertOk();
        $post->assertJson([
            'status' => 'ok',
        ]);
    }

    public function test_debug_db_test_endpoint_returns_json(): void
    {
        $response = $this->getJson('/debug/db-test');
        $response->assertOk();
        $response->assertJsonStructure([
            'php_version',
            'extensions',
            'database',
            'tables_count',
            'laravel_version',
        ]);
    }
}


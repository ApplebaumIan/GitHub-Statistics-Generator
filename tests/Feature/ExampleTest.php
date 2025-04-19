<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_mermaid_url_redirected(): void
    {
        //        $response = $this->get('/api/pull-requests/Capstone-Projects-2025-Spring/project-aac-game-team-b');
        $response = $this->get('/api/pull-requests/Capstone-Projects-2025-Spring/sample-project');
        $response->assertStatus(301)->assertRedirect(
            env('MERMAID', 'https://mermaid.ink').'/img/eyJjb2RlIjoieHljaGFydC1iZXRhXG50aXRsZSBcIlB1bGwgUmVxdWVzdHMg4oCUIHNhbXBsZS1wcm9qZWN0IFwiXG54LWF4aXMgW1wiQXBwbGViYXVtSWFuXCJdXG55LWF4aXMgXCJQdWxsIFJlcXVlc3RzXCIgMCAtLT4gMVxuYmFyIFsxXSIsIm1lcm1haWQiOnsidGhlbWUiOiJkZWZhdWx0IiwidGhlbWVWYXJpYWJsZXMiOnsieHlDaGFydCI6eyJwbG90Q29sb3JQYWxldHRlIjoiIzMzYTNmZiJ9fX19'
        );
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_default_credentials(): void
    {
        $response = $this->post('/login', [
            'username' => 'user',
            'password' => 'user',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('user', session('monitoring_user'));
    }
}
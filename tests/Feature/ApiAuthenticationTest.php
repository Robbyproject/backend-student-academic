<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    public function test_unauthenticated_api_requests_return_json_instead_of_redirecting_to_login(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}

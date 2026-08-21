<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Jobs\SendWelcomeEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegisterDispatchesWelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_dispatches_send_welcome_email_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        Queue::assertPushed(SendWelcomeEmailJob::class, function (SendWelcomeEmailJob $job) {
            return $job->user->email === 'jane@example.com';
        });
    }
}

<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendWelcomeEmailJob;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWelcomeEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sends_the_welcome_email_to_the_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        (new SendWelcomeEmailJob($user))->handle();

        Mail::assertSent(WelcomeEmail::class, function (WelcomeEmail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->user->is($user);
        });
    }
}

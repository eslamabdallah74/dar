<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        return true;
    });
});

test('reset password mail message is styled with nook mate brand template', function () {
    $user = User::factory()->create([
        'name' => 'أحمد الشامي',
        'email' => 'ahmed@example.com',
    ]);

    $notification = new ResetPassword('sample-test-token-12345');
    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    if (! $mailMessage instanceof MailMessage) {
        return;
    }

    expect($mailMessage->subject)->toBe('إعادة تعيين كلمة المرور — Nook Mate');
    expect($mailMessage->view)->toBe([
        'html' => 'emails.auth.reset-password',
        'text' => 'emails.auth.reset-password-text',
    ]);

    $html = (string) $mailMessage->render();

    expect($html)
        ->toContain('Nook')
        ->toContain('Mate')
        ->toContain('أحمد الشامي')
        ->toContain('sample-test-token-12345')
        ->toContain('إعادة تعيين كلمة المرور')
        ->toContain('إدارة ممتلكاتك، بكل سهولة')
        ->toContain('60');

    $text = (string) view('emails.auth.reset-password-text', $mailMessage->viewData)->render();
    expect($text)
        ->toContain('Nook Mate')
        ->toContain('أحمد الشامي')
        ->toContain('sample-test-token-12345');
});

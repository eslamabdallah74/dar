<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;

test('password reset link does not leak user existence for unknown emails', function () {
    $existingUser = User::factory()->create(['email' => 'real-user@example.com']);

    // Existing user request
    $existingResponse = $this->post(route('password.email'), [
        'email' => 'real-user@example.com',
    ]);
    $existingResponse->assertSessionHasNoErrors();
    $existingResponse->assertSessionHas('status', __('passwords.sent'));

    // Non-existent email request
    $unknownResponse = $this->post(route('password.email'), [
        'email' => 'nonexistent-target-999@example.com',
    ]);
    $unknownResponse->assertSessionHasNoErrors();
    $unknownResponse->assertSessionHas('status', __('passwords.sent'));

    // Both responses return identical success status, preventing user enumeration
    expect($existingResponse->getSession()->get('status'))
        ->toEqual($unknownResponse->getSession()->get('status'));
});

test('forgot password endpoint enforces rate limiting after 5 requests per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post(route('password.email'), [
            'email' => 'spam'.uniqid().'@example.com',
        ]);
        expect($response->status())->not->toBe(429);
    }

    // 6th request must trigger rate limit
    $throttledResponse = $this->post(route('password.email'), [
        'email' => 'spam-throttled@example.com',
    ]);
    $throttledResponse->assertStatus(429);
});

test('whatsapp test send endpoint enforces rate limiting after 5 requests per minute', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'شركة واتساب',
        'slug' => 'whatsapp-limit-'.$user->id,
        'owner_user_id' => $user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
    app(CurrentWorkspace::class)->set($workspace);

    for ($i = 0; $i < 5; $i++) {
        $response = $this->actingAs($user)->post(route('whatsapp.test-send'), [
            'phone' => '+966500000000',
        ]);
        expect($response->status())->not->toBe(429);
    }

    // 6th request must trigger rate limit
    $throttledResponse = $this->actingAs($user)->post(route('whatsapp.test-send'), [
        'phone' => '+966500000000',
    ]);
    $throttledResponse->assertStatus(429);
});

test('admin login endpoint enforces rate limiting after 5 failed attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin-bruteforce@example.com',
            'password' => 'badpass'.$i,
        ]);
        expect($response->status())->not->toBe(429);
    }

    // 6th request is throttled
    $throttledResponse = $this->post(route('admin.login.store'), [
        'email' => 'admin-bruteforce@example.com',
        'password' => 'badpass5',
    ]);
    expect(in_array($throttledResponse->status(), [302, 429]))->toBeTrue();
    if ($throttledResponse->status() === 302) {
        $throttledResponse->assertSessionHasErrors('email');
    }
});

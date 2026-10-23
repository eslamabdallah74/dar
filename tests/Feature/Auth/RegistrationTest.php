<?php

use App\Models\User;
use App\Models\Workspace;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and their workspace is automatically provisioned', function () {
    $response = $this->post('/register', [
        'name' => 'Ahmed Mansour',
        'email' => 'ahmed@example.com',
        'company_name' => 'Al-Andalus Real Estate',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'ahmed@example.com')->first();
    expect($user)->not->toBeNull();

    $workspace = Workspace::where('owner_user_id', $user->id)->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->name)->toBe('Al-Andalus Real Estate');
    expect($workspace->currency)->toBe('EGP');

    // Verify membership record with owner role
    expect($workspace->members()->where('user_id', $user->id)->first()->role)->toBe('owner');
});

test('new users can register without an email', function () {
    $response = $this->post('/register', [
        'name' => 'Kareem Tarek',
        'email' => '',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('name', 'Kareem Tarek')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBeNull();

    $workspace = Workspace::where('owner_user_id', $user->id)->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->name)->toBe("Kareem Tarek's Workspace");
});

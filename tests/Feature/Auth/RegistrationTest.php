<?php

use App\Models\User;
use App\Models\Workspace;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users receive a workspace and are sent to the property setup wizard', function () {
    $response = $this->post('/register', [
        'name' => 'Ahmed Mansour',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('properties.create'));

    $user = User::where('name', 'Ahmed Mansour')->first();
    expect($user)->not->toBeNull();

    $workspace = Workspace::where('owner_user_id', $user->id)->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->name)->toBe("Ahmed Mansour's Workspace");
    expect($workspace->currency)->toBe('EGP');

    // Verify membership record with owner role
    expect($workspace->members()->where('user_id', $user->id)->first()->role)->toBe('owner');

    $this->actingAs($user)
        ->get(route('properties.create'))
        ->assertInertia(fn ($page) => $page->component('Properties/Create'));
});

test('new users can register without an email', function () {
    $response = $this->post('/register', [
        'name' => 'Kareem Tarek',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('properties.create'));

    $user = User::where('name', 'Kareem Tarek')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBeNull();

    $workspace = Workspace::where('owner_user_id', $user->id)->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->name)->toBe("Kareem Tarek's Workspace");
});

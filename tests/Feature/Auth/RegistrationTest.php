<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users receive a workspace and are sent to the property setup wizard', function () {
    $response = $this->post('/register', [
        'name' => 'Ahmed Mansour',
        'email' => 'ahmed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('properties.create'));

    $user = User::where('email', 'ahmed@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Ahmed Mansour');

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

test('registration requires an email address', function () {
    $response = $this->from('/register')->post('/register', [
        'name' => 'Kareem Tarek',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertRedirect('/register')
        ->assertSessionHasErrors([
            'email' => __('validation.required', ['attribute' => 'email']),
        ]);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['name' => 'Kareem Tarek']);
});

test('registration rejects an invalid email address', function () {
    $response = $this->from('/register')->post('/register', [
        'name' => 'Kareem Tarek',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertRedirect('/register')
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['name' => 'Kareem Tarek']);
});

test('registration rejects an email address that is already registered', function () {
    User::factory()->create(['email' => 'kareem@example.com']);

    $response = $this->from('/register')->post('/register', [
        'name' => 'Kareem Tarek',
        'email' => 'kareem@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertRedirect('/register')
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['name' => 'Kareem Tarek']);
});

test('the database rejects users without an email address', function () {
    $this->expectException(QueryException::class);

    User::factory()->create(['email' => null]);
});

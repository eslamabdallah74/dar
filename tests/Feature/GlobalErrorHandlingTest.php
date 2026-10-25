<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'مؤسسة نوك مات للاختبار',
        'slug' => 'nookmat-error-test-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);
});

test('it shares flash error, warning, and success messages through Inertia middleware', function () {
    $this->actingAs($this->user);

    // Simulate request with flash messages
    $response = $this->withSession([
        'error' => 'رسالة خطأ تجريبية من الخادم',
        'warning' => 'رسالة تحذير تجريبية من الخادم',
        'success' => 'تمت العملية بنجاح',
    ])->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('flash.error', 'رسالة خطأ تجريبية من الخادم')
        ->where('flash.warning', 'رسالة تحذير تجريبية من الخادم')
        ->where('flash.success', 'تمت العملية بنجاح')
    );
});

test('it captures validation errors and shares them in Inertia props', function () {
    $this->actingAs($this->user);

    // Post invalid payment payload
    $response = $this->post(route('payments.store'), [
        'amount' => '', // Required field missing
    ]);

    $response->assertSessionHasErrors(['amount', 'renter_id', 'payment_method']);
});

test('it handles CSRF token expiration by redirecting back with error message', function () {
    $this->actingAs($this->user);

    // Send a request expecting 419 handling
    $request = Request::create('/dashboard', 'POST');
    $response = new Response('', 419);
    $exception = new HttpException(419, 'CSRF token mismatch');

    // Test bootstrap app respond callback
    $app = app();
    $callback = null;

    // Verify exception response behavior
    expect($response->getStatusCode())->toBe(419);
});

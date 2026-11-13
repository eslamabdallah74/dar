<?php

use App\Contracts\WhatsAppGatewayInterface;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;

afterEach(function () {
    App::setLocale('ar');
});

test('set user locale middleware sets app locale from session for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withSession(['locale' => 'en'])->get('/');
    $response->assertOk();

    expect(App::getLocale())->toBe('en');
});

test('set user locale middleware falls back to session or ar default for guests', function () {
    $response = $this->get('/');
    $response->assertOk();
    expect(App::getLocale())->toBe('ar');

    $this->withSession(['locale' => 'en'])->get('/');
    expect(App::getLocale())->toBe('en');
});

test('locale switch endpoint updates session locale', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('user.locale'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    expect(session('locale'))->toBe('en');
});

test('locale switch endpoint works for guest users too', function () {
    $response = $this->post(route('user.locale'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    expect(session('locale'))->toBe('en');
});

test('locale switch endpoint rejects unsupported locales', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('user.locale'), [
        'locale' => 'fr',
    ]);

    $response->assertSessionHasErrors(['locale']);
    expect(session('locale'))->toBeNull();
});

test('whatsapp service resolves template suffix dynamically based on workspace setting', function () {
    Queue::fake();

    $user = User::factory()->create();
    $workspaceAr = Workspace::factory()->create([
        'owner_user_id' => $user->id,
        'whatsapp_message_language' => 'ar',
    ]);
    $workspaceEn = Workspace::factory()->create([
        'owner_user_id' => $user->id,
        'whatsapp_message_language' => 'en',
    ]);

    $whatsAppService = app(WhatsAppService::class);

    expect($whatsAppService->resolveTemplateSuffix($workspaceAr->id))->toBe('_ar')
        ->and($whatsAppService->resolveTemplateSuffix($workspaceEn->id))->toBe('_en');

    $renter = Renter::factory()->create(['workspace_id' => $workspaceEn->id, 'phone' => '01012345678']);
    $payment = Payment::factory()->create([
        'workspace_id' => $workspaceEn->id,
        'renter_id' => $renter->id,
        'amount' => 1500,
    ]);

    $whatsAppService->queuePaymentReceipt($payment);

    Queue::assertPushed(SendWhatsAppMessageJob::class, function (SendWhatsAppMessageJob $job) {
        return $job->templateName === 'payment_receipt_instant_en';
    });
});

test('settings controller updates workspace whatsapp message language preference', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $user->id,
        'whatsapp_message_language' => 'ar',
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->withSession(['current_workspace_id' => $workspace->id])
        ->put(route('settings.update'), [
            'whatsapp_message_language' => 'en',
        ]);

    $response->assertRedirect();
    $fresh = $workspace->fresh();
    expect($fresh)->toBeInstanceOf(Workspace::class)
        ->and($fresh?->whatsapp_message_language?->value)->toBe('en');
});

test('handle inertia requests shares locale and dir', function () {
    $userAr = User::factory()->create();
    $workspaceAr = Workspace::factory()->create(['owner_user_id' => $userAr->id]);
    $workspaceAr->users()->attach($userAr->id, ['role' => 'owner']);

    $responseAr = $this->actingAs($userAr)
        ->withSession(['current_workspace_id' => $workspaceAr->id, 'locale' => 'ar'])
        ->get(route('dashboard'));
    $responseAr->assertOk();
    $responseAr->assertInertia(fn ($page) => $page
        ->where('locale', 'ar')
        ->where('dir', 'rtl')
    );

    $userEn = User::factory()->create();
    $workspaceEn = Workspace::factory()->create(['owner_user_id' => $userEn->id]);
    $workspaceEn->users()->attach($userEn->id, ['role' => 'owner']);

    $responseEn = $this->actingAs($userEn)
        ->withSession(['current_workspace_id' => $workspaceEn->id, 'locale' => 'en'])
        ->get(route('dashboard'));
    $responseEn->assertOk();
    $responseEn->assertInertia(fn ($page) => $page
        ->where('locale', 'en')
        ->where('dir', 'ltr')
    );
});

test('whatsapp service send derives language code from template suffix', function () {
    $gateway = Mockery::mock(WhatsAppGatewayInterface::class);
    $gateway->shouldReceive('sendTemplate')
        ->with('01012345678', 'payment_receipt_instant_en', [], 'en_US')
        ->once()
        ->andReturn([
            'success' => true,
            'status' => 'sent',
            'provider_message_id' => 'msg_test_123',
            'error_message' => null,
        ]);

    $this->instance(WhatsAppGatewayInterface::class, $gateway);
    $service = app(WhatsAppService::class);

    $user = User::factory()->create();
    $workspaceAr = Workspace::factory()->create([
        'owner_user_id' => $user->id,
        'whatsapp_message_language' => 'ar',
    ]);
    $renter = Renter::factory()->create(['workspace_id' => $workspaceAr->id, 'phone' => '01012345678']);

    $service->send(
        workspaceId: $workspaceAr->id,
        renterId: $renter->id,
        phone: '01012345678',
        templateName: 'payment_receipt_instant_en',
        parameters: [],
        deduplicationKey: 'test_key_1'
    );
});

<?php

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;

test('set user locale middleware sets app locale for authenticated user', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/');
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

test('locale switch endpoint updates user locale and session', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $response = $this->actingAs($user)->post(route('user.locale'), [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $fresh = $user->fresh();
    expect($fresh)->toBeInstanceOf(User::class)
        ->and($fresh?->locale)->toBe('en')
        ->and(session('locale'))->toBe('en');
});

test('locale switch endpoint rejects unsupported locales', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $response = $this->actingAs($user)->post(route('user.locale'), [
        'locale' => 'fr',
    ]);

    $response->assertSessionHasErrors(['locale']);
    $fresh = $user->fresh();
    expect($fresh)->toBeInstanceOf(User::class)
        ->and($fresh?->locale)->toBe('ar');
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

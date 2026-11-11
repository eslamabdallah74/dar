<?php

use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WhatsApp\MetaApiException;
use App\Services\WhatsApp\MetaCloudApiGateway;
use App\Services\WhatsAppService;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'عقارات الواتساب',
        'slug' => 'whatsapp-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'خالد السعيد',
        'phone' => '+966500000000',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    config([
        'services.whatsapp.webhook_verify_token' => 'test_meta_verify_token_123',
        'services.whatsapp.app_secret' => 'test_meta_app_secret_abc',
    ]);
});

test('meta webhook verification succeeds with matching token', function () {
    $response = $this->get('/webhooks/whatsapp?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'test_meta_verify_token_123',
        'hub_challenge' => 'challenge_code_98765',
    ]));

    $response->assertStatus(200);
    expect($response->getContent())->toBe('challenge_code_98765');
});

test('meta webhook verification fails with wrong token', function () {
    $response = $this->get('/webhooks/whatsapp?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'invalid_token',
        'hub_challenge' => 'challenge_code_98765',
    ]));

    $response->assertStatus(403);
});

test('meta webhook updates message log status on delivery and read events', function () {
    $msgId = 'wamid.HBgLM_'.uniqid();
    $log = MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000000',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_1_'.uniqid(),
        'status' => 'pending',
        'provider_message_id' => $msgId,
        'scheduled_at' => now(),
    ]);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'statuses' => [
                                [
                                    'id' => $msgId,
                                    'status' => 'delivered',
                                    'timestamp' => '1729771200',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc');

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertStatus(200);

    $log->refresh();
    expect($log->status)->toBe('delivered')
        ->and($log->sent_at)->not->toBeNull();
});

test('meta webhook marks a message log as failed with the reported error', function () {
    $failedId = 'wamid.FAILED_'.uniqid();
    $log = MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000000',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_failed_'.uniqid(),
        'status' => 'sent',
        'provider_message_id' => $failedId,
        'scheduled_at' => now(),
    ]);
    $content = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [[
        'id' => $failedId,
        'status' => 'failed',
        'errors' => [['code' => 131026, 'title' => 'Message undeliverable']],
    ]]]]]]]], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertOk();
    $this->assertDatabaseHas('message_logs', [
        'id' => $log->id,
        'status' => 'failed',
        'error_code' => '131026',
        'error_message' => 'Message undeliverable',
    ]);
});

test('meta webhook rejects request with invalid hmac signature', function () {
    $content = json_encode(['entry' => []], JSON_THROW_ON_ERROR);
    $invalidSignature = 'sha256=invalid_hash';

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => $invalidSignature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertStatus(401);
});

test('whatsapp index displays real stats and connection status without faking delivery', function () {
    MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000000',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_2_'.uniqid(),
        'status' => 'delivered',
        'scheduled_at' => now(),
        'sent_at' => now(),
    ]);

    MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000001',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_3_'.uniqid(),
        'status' => 'failed',
        'error_message' => 'Invalid phone number',
        'scheduled_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('whatsapp.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('WhatsApp/Index')
        ->has('stats')
        ->where('stats.total_sent', 1)
        ->where('stats.delivered_rate', 50)
        ->where('stats.active_templates', 4)
        ->has('connection')
    );
});

test('meta webhook processes inbound STOP and marks renter opted out', function () {
    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'messages' => [
                                [
                                    'from' => '966500000000',
                                    'id' => 'wamid.INBOUND_1',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'توقف',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc');

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertStatus(200);
    $this->renter->refresh();
    expect($this->renter->whatsapp_opted_out_at)->not->toBeNull();
});

test('meta webhook processes inbound STOP for Egyptian renter stored in local format', function () {
    $egyptianRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'محمد أحمد',
        'phone' => '01012345678',
        'whatsapp_opt_in_at' => now(),
    ]);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'messages' => [
                                [
                                    'from' => '201012345678',
                                    'id' => 'wamid.INBOUND_EGY_1',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'STOP',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc');

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertStatus(200);
    $egyptianRenter->refresh();
    expect($egyptianRenter->whatsapp_opted_out_at)->not->toBeNull();
});

test('meta webhook processes inbound START and marks renter opted in', function () {
    $this->renter->update(['whatsapp_opted_out_at' => now()]);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'messages' => [
                                [
                                    'from' => '966500000000',
                                    'id' => 'wamid.INBOUND_2',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'START',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc');

    $response = $this->call(
        'POST',
        route('webhooks.whatsapp.handle'),
        [],
        [],
        [],
        [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $content
    );

    $response->assertStatus(200);
    $this->renter->refresh();
    expect($this->renter->whatsapp_opted_out_at)->toBeNull()
        ->and($this->renter->whatsapp_opt_in_at)->not->toBeNull();
});

test('guest can view receipt via cryptographically signed url but fails with invalid signature', function () {
    $payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 1500.00,
        'paid_at' => now(),
        'payment_method' => 'bank_transfer',
        'reference' => 'REC-TEST-999',
    ]);

    $validSignedUrl = URL::signedRoute('public.receipts.show', ['payment' => $payment->id]);
    $invalidSignedUrl = route('public.receipts.show', ['payment' => $payment->id]).'?signature=tampered_signature';

    // Guest accessing with valid signed URL succeeds
    $this->get($validSignedUrl)->assertOk()->assertSee('REC-TEST-999');

    // Guest accessing without valid signature fails with 403
    $this->get($invalidSignedUrl)->assertForbidden();
});

test('meta gateway normalizes egyptian and saudi phone numbers', function () {
    $gateway = new MetaCloudApiGateway;

    // Egyptian normalization
    expect($gateway->formatPhoneNumber('01012345678'))->toBe('201012345678')
        ->and($gateway->formatPhoneNumber('+201012345678'))->toBe('201012345678');

    // Saudi normalization
    expect($gateway->formatPhoneNumber('0501234567'))->toBe('966501234567')
        ->and($gateway->formatPhoneNumber('+966501234567'))->toBe('966501234567')
        ->and($gateway->formatPhoneNumber('00966501234567'))->toBe('966501234567');
});

test('whatsapp service saves payload parameters into message log', function () {
    $service = app(WhatsAppService::class);

    $log = $service->send(
        workspaceId: $this->workspace->id,
        renterId: $this->renter->id,
        phone: $this->renter->phone,
        templateName: 'rent_due_reminder_ar',
        parameters: [
            'renter_name' => 'خالد السعيد',
            'unit' => 'شقة 104',
            'amount' => '3,500 ر.س',
            'due_date' => '2026-12-01',
        ],
        deduplicationKey: 'test_payload_save_1_'.uniqid()
    );

    expect($log)->toBeInstanceOf(MessageLog::class);
    assert($log instanceof MessageLog);

    $payload = $log->payload_parameters;
    expect($payload)->toBeArray();
    assert(is_array($payload));

    expect($payload['renter_name'] ?? null)->toBe('خالد السعيد')
        ->and($payload['unit'] ?? null)->toBe('شقة 104');
});

test('renter update endpoint toggles whatsapp consent and updates opt in or out timestamps', function () {
    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'طارق الزهراني',
        'phone' => '01011223344',
        'national_id' => '1099887766',
        'whatsapp_opt_in_at' => now(),
        'whatsapp_opted_out_at' => null,
    ]);

    // 1. Opt out via update form
    $response = $this->actingAs($this->user)->put(route('renters.update', $renter->id), [
        'name' => 'طارق الزهراني',
        'phone' => '01011223344',
        'national_id' => '1099887766',
        'whatsapp_consent' => false,
    ]);

    $response->assertSessionHas('success');
    $renter->refresh();
    expect($renter->whatsapp_opted_out_at)->not->toBeNull()
        ->and($renter->hasConsentedToWhatsApp())->toBeFalse();

    // 2. Re-enable consent via update form
    $response = $this->actingAs($this->user)->put(route('renters.update', $renter->id), [
        'name' => 'طارق الزهراني',
        'phone' => '01011223344',
        'national_id' => '1099887766',
        'whatsapp_consent' => true,
    ]);

    $response->assertSessionHas('success');
    $renter->refresh();
    expect($renter->whatsapp_opted_out_at)->toBeNull()
        ->and($renter->whatsapp_opt_in_at)->not->toBeNull()
        ->and($renter->hasConsentedToWhatsApp())->toBeTrue();

    // 3. Updating renter name without changing consent preserves original opt_in timestamp
    $originalOptInAt = $renter->whatsapp_opt_in_at;
    $response = $this->actingAs($this->user)->put(route('renters.update', $renter->id), [
        'name' => 'طارق الزهراني المعدل',
        'phone' => '01011223344',
        'national_id' => '1099887766',
        'whatsapp_consent' => true,
    ]);

    $response->assertSessionHas('success');
    $renter->refresh();
    expect($renter->name)->toBe('طارق الزهراني المعدل')
        ->and($renter->whatsapp_opt_in_at?->timestamp)->toBe($originalOptInAt?->timestamp);
});

test('renter profile page passes correct whatsapp consent status and timestamps', function () {
    $this->renter->update([
        'whatsapp_opt_in_at' => now()->subDays(5),
        'whatsapp_opted_out_at' => null,
    ]);

    $response = $this->actingAs($this->user)->get(route('renters.show', $this->renter->id));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renters/Show')
            ->where('renter.whatsapp_opted_in', true)
            ->has('renter.whatsapp_opt_in_date')
            ->where('renter.whatsapp_opted_out_date', null)
        );

    // If opted out
    $this->renter->update(['whatsapp_opted_out_at' => now()]);

    $response = $this->actingAs($this->user)->get(route('renters.show', $this->renter->id));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renters/Show')
            ->where('renter.whatsapp_opted_in', false)
            ->has('renter.whatsapp_opted_out_date')
        );
});

test('meta gateway rethrows MetaApiException on server 5xx temporary outage', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Internal service error']], 503),
    ]);

    config([
        'services.whatsapp.token' => 'mock_token',
        'services.whatsapp.phone_number_id' => 'mock_phone_id',
    ]);

    $gateway = new MetaCloudApiGateway;

    expect(fn () => $gateway->sendTemplate('0501234567', 'rent_due_reminder_ar', []))
        ->toThrow(MetaApiException::class);
});

test('migration down handles message logs with null renter_id without integrity failure', function () {
    DB::table('message_logs')->insert([
        'workspace_id' => $this->workspace->id,
        'renter_id' => null,
        'recipient_phone' => '+966500000000',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'null_renter_test_'.uniqid(),
        'status' => 'pending',
        'scheduled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_11_10_120000_update_message_logs_for_payload_and_nullable_renter.php');
    $migration->down();

    // Re-run up to restore schema for subsequent tests
    $migration->up();

    expect(DB::table('message_logs')->whereNull('renter_id')->count())->toBe(0);
});

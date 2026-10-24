<?php

use App\Models\MessageLog;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
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
    $log = MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000000',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_1',
        'status' => 'pending',
        'provider_message_id' => 'wamid.HBgLM...',
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
                                    'id' => 'wamid.HBgLM...',
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

    $content = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $content, 'test_meta_app_secret_abc');

    $response = $this->call(
        'POST',
        '/webhooks/whatsapp',
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

test('meta webhook rejects request with invalid hmac signature', function () {
    $content = json_encode(['entry' => []]);
    $invalidSignature = 'sha256=invalid_hash';

    $response = $this->call(
        'POST',
        '/webhooks/whatsapp',
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
        'deduplication_key' => 'dedup_test_msg_2',
        'status' => 'delivered',
        'scheduled_at' => now(),
        'sent_at' => now(),
    ]);

    MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => '+966500000001',
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => 'dedup_test_msg_3',
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

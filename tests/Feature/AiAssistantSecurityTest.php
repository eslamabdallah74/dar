<?php

declare(strict_types=1);

use App\Models\AiConfiguration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AI\AiConfigurationService;
use Illuminate\Support\Facades\Http;

test('unauthenticated request to ask ai is rejected with 401', function () {
    $this->postJson(route('ai.ask'), [
        'question' => 'كم إجمالي الإيرادات؟',
    ])->assertUnauthorized();
});

test('request to ask ai on disabled workspace is rejected with 403', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $owner->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => 'كم إجمالي الإيرادات؟',
        ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'المساعد الذكي غير مفعّل لهذه المنشأة.']);
});

test('request to ask ai when token is unconfigured returns 503', function () {
    AiConfiguration::removeKey(AiConfigurationService::KEY_API_KEY);
    config(['services.openai.api_key' => null, 'openai.api_key' => null]);

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => 'كم إجمالي الإيرادات؟',
        ]);

    $response->assertStatus(503)
        ->assertJson(['message' => 'المساعد الذكي غير متاح حالياً.']);
});

test('question exceeding 1000 characters is rejected with 422 validation error', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $longQuestion = str_repeat('سؤال طويل جداً ', 100);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => $longQuestion,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['question']);
});

test('rate limiter throttles requests exceeding 10 per minute with 429', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => ['content' => 'إجابة تجريبية'],
                ],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($owner)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->postJson(route('ai.ask'), [
                'question' => 'سؤال رقم '.$i,
            ])
            ->assertOk();
    }

    // 11th request in the same minute should be throttled
    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => 'السؤال الحادي عشر',
        ]);

    $response->assertStatus(429);
});

test('user without authorized workspace is forbidden from asking ai', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->postJson(route('ai.ask'), [
            'question' => 'ما هي عقود هذه المنشأة؟',
        ]);

    $response->assertStatus(403);
});

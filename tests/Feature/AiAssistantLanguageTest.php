<?php

declare(strict_types=1);

use App\Models\AiConfiguration;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AI\AiConfigurationService;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

test('arabic utf8 question is accepted by validation and generates response', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
        'currency' => 'SAR',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => 'مرحباً، إجمالي إيرادات هذا الشهر يبلغ 15,000 ر.س.',
                    ],
                ],
            ],
        ]),
    ]);

    $arabicQuestion = 'كم بلغت إجمالي الإيرادات المحصلة خلال الشهر الحالي لشهر أكتوبر؟';

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => $arabicQuestion,
        ]);

    $response->assertOk()
        ->assertJson([
            'answer' => 'مرحباً، إجمالي إيرادات هذا الشهر يبلغ 15,000 ر.س.',
        ]);
});

test('english question is accepted and generates response', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
        'currency' => 'USD',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => 'The total revenue for this month is $5,000.',
                    ],
                ],
            ],
        ]),
    ]);

    $englishQuestion = 'What is the total revenue collected this month?';

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => $englishQuestion,
        ]);

    $response->assertOk()
        ->assertJson([
            'answer' => 'The total revenue for this month is $5,000.',
        ]);
});

test('multi-round tool execution successfully calls tools and returns final answer', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
        'currency' => 'SAR',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    Payment::factory()->create([
        'workspace_id' => $workspace->id,
        'amount' => 12500,
        'paid_at' => Carbon::parse('2026-10-15 10:00:00'),
    ]);

    // Round 1: OpenAI decides to call tool get_monthly_revenue
    // Round 2: OpenAI receives tool output and returns final natural text
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_rev_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_monthly_revenue',
                                    'arguments' => json_encode(['month' => '2026-10']),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
        CreateResponse::fake([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => 'إجمالي الإيرادات المحصلة لشهر أكتوبر 2026 هو 12,500.00 ر.س.',
                    ],
                ],
            ],
        ]),
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->postJson(route('ai.ask'), [
            'question' => 'كم إيرادات أكتوبر 2026؟',
        ]);

    $response->assertOk()
        ->assertJson([
            'answer' => 'إجمالي الإيرادات المحصلة لشهر أكتوبر 2026 هو 12,500.00 ر.س.',
        ]);
});

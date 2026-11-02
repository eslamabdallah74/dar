<?php

declare(strict_types=1);

use App\Models\AiConfiguration;
use App\Models\User;
use App\Services\AI\AiConfigurationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can view ai configuration status and response never contains secret token', function () {
    $admin = User::factory()->admin()->create();

    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-secret-key-1234567890');
    AiConfiguration::setValue(AiConfigurationService::KEY_MODEL, 'gpt-4o');

    $response = $this->actingAs($admin)->get(route('admin.ai-configuration.show'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/AiConfiguration')
        ->where('is_configured', true)
        ->where('has_stored_key', true)
        ->where('model', 'gpt-4o')
        ->missing('token')
        ->missing('apiKey')
        ->missing('key')
    );

    // Also assert JSON endpoint never contains token string
    $jsonResponse = $this->actingAs($admin)->getJson(route('admin.ai-configuration.show'));
    $jsonResponse->assertOk()
        ->assertJson([
            'is_configured' => true,
            'has_stored_key' => true,
            'model' => 'gpt-4o',
        ]);

    expect($jsonResponse->getContent())->not->toContain('sk-test-secret-key-1234567890');
});

test('non-admin user cannot access admin ai configuration routes', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->getJson(route('admin.ai-configuration.show'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.ai-configuration.show'))->assertRedirect(route('dashboard'));
    $this->actingAs($user)->putJson(route('admin.ai-configuration.update'), [
        'token' => 'sk-test-fake-key-1234567890',
        'model' => 'gpt-4o-mini',
    ])->assertForbidden();
    $this->actingAs($user)->deleteJson(route('admin.ai-configuration.destroy'))->assertForbidden();
});

test('token is stored encrypted at rest so database raw value does not equal plaintext', function () {
    $admin = User::factory()->admin()->create();
    $plaintextToken = 'sk-super-secret-production-key-998877';

    Http::fake([
        'https://api.openai.com/v1/models' => Http::response(['data' => []], 200),
    ]);

    $response = $this->actingAs($admin)->put(route('admin.ai-configuration.update'), [
        'token' => $plaintextToken,
        'model' => 'gpt-4o-mini',
    ]);

    $response->assertSessionHasNoErrors();

    // Query raw DB value without model cast decryption
    /** @var stdClass $rawRow */
    $rawRow = DB::table('ai_configurations')->where('key', AiConfigurationService::KEY_API_KEY)->first();
    expect($rawRow)->not->toBeNull();
    expect($rawRow->value)->not->toBe($plaintextToken);
    expect($rawRow->value)->not->toContain('sk-super-secret');

    // Model decryption cast recovers plaintext correctly
    expect(AiConfiguration::getValue(AiConfigurationService::KEY_API_KEY))->toBe($plaintextToken);
});

test('token validation with provider is verified and invalid tokens are rejected with 422', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'https://api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
    ]);

    $response = $this->actingAs($admin)->putJson(route('admin.ai-configuration.update'), [
        'token' => 'sk-invalid-key-testing',
        'model' => 'gpt-4o-mini',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);

    expect(AiConfiguration::getValue(AiConfigurationService::KEY_API_KEY))->toBeNull();
});

test('empty or too short token is rejected with validation error', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->putJson(route('admin.ai-configuration.update'), [
        'token' => 'short',
        'model' => 'gpt-4o-mini',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('super admin can delete stored api key', function () {
    $admin = User::factory()->admin()->create();
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-stored-test-key-123456');

    expect(app(AiConfigurationService::class)->hasStoredKey())->toBeTrue();

    $response = $this->actingAs($admin)->delete(route('admin.ai-configuration.destroy'));

    $response->assertSessionHasNoErrors();
    expect(app(AiConfigurationService::class)->hasStoredKey())->toBeFalse();
    expect(AiConfiguration::getValue(AiConfigurationService::KEY_API_KEY))->toBeNull();
});

test('super admin can configure openrouter provider and key is validated against openrouter api', function () {
    $admin = User::factory()->admin()->create();
    $openRouterToken = 'sk-or-v1-super-secret-openrouter-key-998877';

    Http::fake([
        'https://openrouter.ai/api/v1/auth/key' => Http::response(['data' => ['label' => 'test-key']], 200),
    ]);

    $response = $this->actingAs($admin)->put(route('admin.ai-configuration.update'), [
        'provider' => 'openrouter',
        'token' => $openRouterToken,
        'model' => 'openai/gpt-4o-mini',
    ]);

    $response->assertSessionHasNoErrors();

    /** @var AiConfigurationService $service */
    $service = app(AiConfigurationService::class);
    expect($service->getProvider())->toBe('openrouter');
    expect($service->getApiKey())->toBe($openRouterToken);
    expect($service->getModel())->toBe('openai/gpt-4o-mini');
    expect($service->getBaseUrl())->toBe('https://openrouter.ai/api/v1');
});

<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiConfigurationService
{
    public const string KEY_PROVIDER = 'ai_provider';

    public const string KEY_API_KEY = 'openai_api_key';

    public const string KEY_OPENROUTER_API_KEY = 'openrouter_api_key';

    public const string KEY_MODEL = 'openai_model';

    public const string PROVIDER_OPENAI = 'openai';

    public const string PROVIDER_OPENROUTER = 'openrouter';

    public const string DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';

    public const string DEFAULT_OPENROUTER_MODEL = 'openai/gpt-4o-mini';

    public const string OPENAI_BASE_URI = 'https://api.openai.com/v1';

    public const string OPENROUTER_BASE_URI = 'https://openrouter.ai/api/v1';

    /**
     * @var array<int, string>
     */
    public const array SUPPORTED_PROVIDERS = [
        self::PROVIDER_OPENAI,
        self::PROVIDER_OPENROUTER,
    ];

    /**
     * @var array<int, string>
     */
    public const array OPENAI_MODELS = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-3.5-turbo',
    ];

    /**
     * @var array<int, string>
     */
    public const array OPENROUTER_MODELS = [
        'openai/gpt-4o-mini',
        'openai/gpt-4o',
        'deepseek/deepseek-chat',
        'meta-llama/llama-3.3-70b-instruct',
        'qwen/qwen-2.5-72b-instruct',
    ];

    /**
     * Retrieve the active AI provider (openai or openrouter).
     */
    public function getProvider(): string
    {
        $dbProvider = AiConfiguration::getValue(self::KEY_PROVIDER);
        if (is_string($dbProvider) && in_array($dbProvider, self::SUPPORTED_PROVIDERS, true)) {
            return $dbProvider;
        }

        return self::PROVIDER_OPENAI;
    }

    /**
     * Set the active AI provider.
     */
    public function saveProvider(string $provider): void
    {
        if (in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            AiConfiguration::setValue(self::KEY_PROVIDER, $provider);
        }
    }

    /**
     * Base URI for the active provider.
     */
    public function getBaseUrl(?string $provider = null): string
    {
        $activeProvider = $provider ?? $this->getProvider();

        if ($activeProvider === self::PROVIDER_OPENROUTER) {
            /** @var string|null $configUrl */
            $configUrl = config('services.openrouter.base_url');

            return is_string($configUrl) && trim($configUrl) !== '' ? trim($configUrl) : self::OPENROUTER_BASE_URI;
        }

        return self::OPENAI_BASE_URI;
    }

    /**
     * Retrieve effective API key for the active or given provider.
     */
    public function getApiKey(?string $provider = null): ?string
    {
        $activeProvider = $provider ?? $this->getProvider();
        $keyName = $activeProvider === self::PROVIDER_OPENROUTER ? self::KEY_OPENROUTER_API_KEY : self::KEY_API_KEY;

        $dbKey = AiConfiguration::getValue($keyName);
        if (is_string($dbKey) && trim($dbKey) !== '') {
            return trim($dbKey);
        }

        if ($activeProvider === self::PROVIDER_OPENROUTER) {
            /** @var string|null $openrouterKey */
            $openrouterKey = config('services.openrouter.api_key');
            if (is_string($openrouterKey) && trim($openrouterKey) !== '') {
                return trim($openrouterKey);
            }

            return null;
        }

        /** @var string|null $configKey */
        $configKey = config('services.openai.api_key') ?? config('openai.api_key');
        if (is_string($configKey) && trim($configKey) !== '') {
            return trim($configKey);
        }

        return null;
    }

    /**
     * Retrieve the effective model choice.
     */
    public function getModel(?string $provider = null): string
    {
        $activeProvider = $provider ?? $this->getProvider();

        $dbModel = AiConfiguration::getValue(self::KEY_MODEL);
        if (is_string($dbModel) && trim($dbModel) !== '') {
            $trimmed = trim($dbModel);
            if ($activeProvider === self::PROVIDER_OPENROUTER && in_array($trimmed, ['anthropic/claude-3.5-sonnet', 'google/gemini-flash-1.5'], true)) {
                return self::DEFAULT_OPENROUTER_MODEL;
            }

            return $trimmed;
        }

        if ($activeProvider === self::PROVIDER_OPENROUTER) {
            /** @var string|null $configModel */
            $configModel = config('services.openrouter.model');
            if (is_string($configModel) && trim($configModel) !== '') {
                return trim($configModel);
            }

            return self::DEFAULT_OPENROUTER_MODEL;
        }

        /** @var string|null $configModel */
        $configModel = config('services.openai.model');
        if (is_string($configModel) && trim($configModel) !== '') {
            return trim($configModel);
        }

        return self::DEFAULT_OPENAI_MODEL;
    }

    /**
     * Check if an active API key is configured.
     */
    public function isConfigured(?string $provider = null): bool
    {
        return $this->getApiKey($provider) !== null;
    }

    /**
     * Determine whether an API key is stored specifically in the database.
     */
    public function hasStoredKey(?string $provider = null): bool
    {
        $activeProvider = $provider ?? $this->getProvider();
        $keyName = $activeProvider === self::PROVIDER_OPENROUTER ? self::KEY_OPENROUTER_API_KEY : self::KEY_API_KEY;

        $value = AiConfiguration::getValue($keyName);

        return is_string($value) && trim($value) !== '';
    }

    /**
     * Store encrypted API key in database.
     */
    public function saveApiKey(string $token, ?string $provider = null): void
    {
        $activeProvider = $provider ?? $this->getProvider();
        $keyName = $activeProvider === self::PROVIDER_OPENROUTER ? self::KEY_OPENROUTER_API_KEY : self::KEY_API_KEY;

        AiConfiguration::setValue($keyName, trim($token));
    }

    /**
     * Store selected model in database.
     */
    public function saveModel(string $model): void
    {
        AiConfiguration::setValue(self::KEY_MODEL, trim($model));
    }

    /**
     * Remove the stored API key from database for active or specified provider.
     */
    public function removeApiKey(?string $provider = null): void
    {
        $activeProvider = $provider ?? $this->getProvider();
        $keyName = $activeProvider === self::PROVIDER_OPENROUTER ? self::KEY_OPENROUTER_API_KEY : self::KEY_API_KEY;

        AiConfiguration::removeKey($keyName);
    }

    /**
     * Supported models list for UI display based on provider.
     *
     * @return array<int, string>
     */
    public function supportedModels(?string $provider = null): array
    {
        $activeProvider = $provider ?? $this->getProvider();

        return $activeProvider === self::PROVIDER_OPENROUTER ? self::OPENROUTER_MODELS : self::OPENAI_MODELS;
    }

    /**
     * Validate an API key directly against the provider without logging the raw key.
     */
    public function validateTokenWithProvider(string $token, ?string $provider = null): bool
    {
        $activeProvider = $provider ?? $this->getProvider();

        try {
            if ($activeProvider === self::PROVIDER_OPENROUTER) {
                /** @var string $appName */
                $appName = config('app.name', 'Dar');
                /** @var string $appUrl */
                $appUrl = config('app.url', 'http://localhost');

                $response = Http::withToken(trim($token))
                    ->withHeaders([
                        'HTTP-Referer' => $appUrl,
                        'X-Title' => $appName,
                    ])
                    ->timeout(10)
                    ->get('https://openrouter.ai/api/v1/auth/key');

                return $response->successful();
            }

            $response = Http::withToken(trim($token))
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            return $response->successful();
        } catch (Throwable $exception) {
            Log::warning('AI provider token validation failed: '.$exception->getMessage());

            return false;
        }
    }
}

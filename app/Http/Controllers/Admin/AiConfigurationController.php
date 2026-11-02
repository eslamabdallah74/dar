<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiConfigurationRequest;
use App\Services\AI\AiConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiConfigurationController extends Controller
{
    public function __construct(
        protected AiConfigurationService $configurationService
    ) {}

    /**
     * Display the AI configuration status page without exposing any stored credentials.
     */
    public function show(Request $request): Response|JsonResponse
    {
        $provider = $this->configurationService->getProvider();

        $status = [
            'provider' => $provider,
            'supported_providers' => AiConfigurationService::SUPPORTED_PROVIDERS,
            'is_configured' => $this->configurationService->isConfigured($provider),
            'has_stored_key' => $this->configurationService->hasStoredKey($provider),
            'model' => $this->configurationService->getModel($provider),
            'supported_models' => $this->configurationService->supportedModels($provider),
            'openai_models' => AiConfigurationService::OPENAI_MODELS,
            'openrouter_models' => AiConfigurationService::OPENROUTER_MODELS,
        ];

        if ($request->wantsJson()) {
            return response()->json($status);
        }

        return Inertia::render('Admin/AiConfiguration', $status);
    }

    /**
     * Store or update AI configuration credentials.
     */
    public function update(AiConfigurationRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $rawProvider = $validated['provider'] ?? null;
        $provider = is_string($rawProvider) && in_array($rawProvider, AiConfigurationService::SUPPORTED_PROVIDERS, true)
            ? $rawProvider
            : $this->configurationService->getProvider();

        $this->configurationService->saveProvider($provider);

        $token = $validated['token'] ?? null;
        $model = $validated['model'] ?? null;

        if (is_string($token) && trim($token) !== '') {
            $trimmedToken = trim($token);
            $isValid = $this->configurationService->validateTokenWithProvider($trimmedToken, $provider);

            if (! $isValid) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'مفتاح API غير صالح أو تعذر التحقق منه مع مزود الخدمة.',
                        'errors' => ['token' => ['مفتاح API غير صالح أو تعذر التحقق منه مع مزود الخدمة.']],
                    ], 422);
                }

                return back()->withErrors(['token' => 'مفتاح API غير صالح أو تعذر التحقق منه مع مزود الخدمة.']);
            }

            $this->configurationService->saveApiKey($trimmedToken, $provider);
        }

        if (is_string($model) && trim($model) !== '') {
            $this->configurationService->saveModel(trim($model));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'تم حفظ إعدادات المساعد الذكي بنجاح.',
                'provider' => $this->configurationService->getProvider(),
                'is_configured' => $this->configurationService->isConfigured($provider),
                'has_stored_key' => $this->configurationService->hasStoredKey($provider),
                'model' => $this->configurationService->getModel($provider),
            ]);
        }

        return back()->with('success', 'تم حفظ إعدادات المساعد الذكي بنجاح.');
    }

    /**
     * Delete stored API key from the database.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $rawProvider = $request->input('provider');
        $provider = is_string($rawProvider) && in_array($rawProvider, AiConfigurationService::SUPPORTED_PROVIDERS, true)
            ? $rawProvider
            : $this->configurationService->getProvider();

        $this->configurationService->removeApiKey($provider);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'تم حذف مفتاح API بنجاح.',
                'provider' => $provider,
                'is_configured' => $this->configurationService->isConfigured($provider),
                'has_stored_key' => $this->configurationService->hasStoredKey($provider),
            ]);
        }

        return back()->with('success', 'تم حذف مفتاح API بنجاح.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentWorkspace;
use App\Http\Requests\AiAskRequest;
use App\Services\AI\AiConfigurationService;
use App\Services\AI\AiService;
use App\Services\AI\Exceptions\AiConfigurationException;
use App\Services\AI\Exceptions\AiExecutionException;
use App\Services\AI\Exceptions\AiProviderRateLimitException;
use App\Services\AI\Exceptions\AiProviderTimeoutException;
use App\Services\AI\Exceptions\AiWorkspaceDisabledException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiAssistantController extends Controller
{
    use ResolvesCurrentWorkspace;

    public function __construct(
        protected AiService $aiService,
        protected AiConfigurationService $configurationService
    ) {}

    /**
     * Display the AI Assistant interactive chat page.
     */
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $isConfigured = $this->configurationService->isConfigured();
        $isEnabled = (bool) $workspace->ai_assistant_enabled;
        $isAvailable = $isConfigured && $isEnabled;

        $reason = null;
        if (! $isConfigured) {
            $reason = 'المساعد الذكي غير متاح حالياً.';
        } elseif (! $isEnabled) {
            $reason = 'المساعد الذكي غير مفعّل لهذه المنشأة.';
        }

        return Inertia::render('AiAssistant/Index', [
            'aiAvailable' => $isAvailable,
            'reason' => $reason,
            'workspaceName' => $workspace->name,
            'currency' => $workspace->currencySymbol(),
        ]);
    }

    /**
     * Check current availability and activation status for the active workspace.
     */
    public function status(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $isConfigured = $this->configurationService->isConfigured();
        $isEnabled = (bool) $workspace->ai_assistant_enabled;
        $isAvailable = $isConfigured && $isEnabled;

        $reason = null;
        if (! $isConfigured) {
            $reason = 'المساعد الذكي غير متاح حالياً.';
        } elseif (! $isEnabled) {
            $reason = 'المساعد الذكي غير مفعّل لهذه المنشأة.';
        }

        return response()->json([
            'available' => $isAvailable,
            'configured' => $isConfigured,
            'enabled' => $isEnabled,
            'reason' => $reason,
        ]);
    }

    /**
     * Submit a natural-language question and retrieve generated response.
     */
    public function ask(AiAskRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $rawQuestion = $request->validated('question');
        $question = is_string($rawQuestion) ? trim($rawQuestion) : '';

        try {
            $answer = $this->aiService->ask($question, $workspace);

            return response()->json([
                'answer' => $answer,
            ]);
        } catch (AiConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (AiWorkspaceDisabledException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (AiProviderRateLimitException $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        } catch (AiProviderTimeoutException $exception) {
            return response()->json(['message' => $exception->getMessage()], 504);
        } catch (AiExecutionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        } catch (Throwable) {
            return response()->json(['message' => 'تعذر تنفيذ الطلب.'], 500);
        }
    }

    /**
     * Toggle the AI Assistant feature on or off for the workspace (Super Admin only).
     */
    public function updateSetting(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $this->authenticatedUser($request);

        abort_unless(
            $user->isAdmin(),
            403,
            'فقط مدير النظام (Super Admin) يمكنه تفعيل أو تعطيل المساعد الذكي للمنشأة.'
        );

        $validated = $request->validate([
            'ai_assistant_enabled' => ['required', 'boolean'],
        ]);

        $workspace->update([
            'ai_assistant_enabled' => (bool) $validated['ai_assistant_enabled'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'تم حفظ إعدادات المساعد الذكي بنجاح.',
                'ai_assistant_enabled' => (bool) $workspace->ai_assistant_enabled,
            ]);
        }

        return back()->with('success', 'تم حفظ إعدادات المساعد الذكي بنجاح.');
    }
}

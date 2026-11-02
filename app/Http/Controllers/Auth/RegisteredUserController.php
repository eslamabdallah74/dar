<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $planSlug = (string) $request->query('plan', '');
        $selectedPlan = $planSlug !== '' ? Plan::where('slug', $planSlug)->where('is_active', true)->first() : null;
        $plans = Plan::where('is_active', true)->orderBy('id')->get(['id', 'name', 'slug', 'monthly_price', 'parent_unit_limit', 'active_rental_limit']);

        $initialPlanSlug = $selectedPlan !== null ? $selectedPlan->slug : ($planSlug !== '' ? $planSlug : null);

        return Inertia::render('Auth/Register', [
            'initialPlanSlug' => $initialPlanSlug,
            'selectedPlan' => $selectedPlan ? [
                'id' => $selectedPlan->id,
                'name' => $selectedPlan->name,
                'slug' => $selectedPlan->slug,
                'monthly_price' => (float) $selectedPlan->monthly_price,
                'parent_unit_limit' => $selectedPlan->parent_unit_limit,
            ] : null,
            'plans' => $plans,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan' => ['nullable', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $workspaceName = $user->name."'s Workspace";

            $requestedPlanSlug = $validated['plan'] ?? null;
            $requestedPlan = $requestedPlanSlug
                ? Plan::where('slug', $requestedPlanSlug)->where('is_active', true)->first()
                : null;

            $trialPlan = Plan::where('slug', 'trial')->first() ?? Plan::first();
            $defaultParentLimit = $trialPlan ? ($trialPlan->parent_unit_limit ?? 1) : 1;
            $defaultActiveLimit = $trialPlan ? ($trialPlan->active_rental_limit ?? 1) : 1;

            $workspaceData = [
                'name' => $workspaceName,
                'slug' => Str::slug($workspaceName.'-'.Str::random(6)),
                'owner_user_id' => $user->id,
                'currency' => 'EGP',
                'parent_unit_limit' => $defaultParentLimit,
                'active_rental_limit' => $defaultActiveLimit,
            ];

            if ($requestedPlan) {
                $workspaceData['requested_plan_id'] = $requestedPlan->id;
                $workspaceData['plan_request_status'] = ($requestedPlan->slug === 'trial' ? 'approved' : 'pending');
                $workspaceData['plan_requested_at'] = now();
            }

            $workspace = Workspace::create($workspaceData);

            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            if ($trialPlan) {
                $workspace->subscriptions()->create([
                    'plan_id' => $trialPlan->id,
                    'activated_by' => $user->id,
                    'status' => 'active',
                    'starts_at' => now(),
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('properties.create');
    }
}

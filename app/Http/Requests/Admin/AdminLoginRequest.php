<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the admin request credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = (string) $this->input('email');
        $password = (string) $this->input('password');

        $this->bootstrapConfiguredAdmin($email, $password);

        if (! Auth::attempt(['email' => $email, 'password' => $password], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة، يرجى التأكد من البريد الإلكتروني وكلمة المرور.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isAdmin()) {
            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب ليس لديه صلاحيات مدير نظام للوصول إلى لوحة الإدارة.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the admin login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "تم تجاوز عدد المحاولات المسموح بها. يرجى المحاولة بعد {$seconds} ثانية.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip().'|admin');
    }

    /**
     * Bootstrap or sync configured admin from environment if credentials match .env.
     */
    protected function bootstrapConfiguredAdmin(string $email, string $password): void
    {
        $configuredEmail = config('admin.email');
        $configuredPassword = config('admin.password');

        if (! empty($configuredEmail) && ! empty($configuredPassword) && strlen($configuredPassword) >= 8 && Str::lower($email) === Str::lower($configuredEmail) && hash_equals((string) $configuredPassword, $password)) {
            $adminUser = User::firstOrNew(['email' => $configuredEmail]);
            $adminUser->name = config('admin.name', 'مدير النظام المركزي');
            $adminUser->password = Hash::make($configuredPassword);
            $adminUser->is_admin = true;
            $adminUser->email_verified_at = now();
            $adminUser->save();
        }
    }
}

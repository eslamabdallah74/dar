<?php

namespace App\Providers;

use App\Contracts\WhatsAppGatewayInterface;
use App\Models\User;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\MetaCloudApiGateway;
use App\Support\CurrentWorkspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class, fn () => new CurrentWorkspace);

        $this->app->bind(WhatsAppGatewayInterface::class, function () {
            return match (config('services.whatsapp.driver', 'log')) {
                'meta' => new MetaCloudApiGateway,
                default => new LogWhatsAppGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('admin-login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($email).'|'.$request->ip().'|admin')
            );
        });

        $this->configurePasswordResetMail();
    }

    private function configurePasswordResetMail(): void
    {
        ResetPassword::toMailUsing(function (mixed $notifiable, string $token): MailMessage {
            $expire = $this->passwordResetExpirationMinutes();
            $email = $this->resolveResetNotifiableEmail($notifiable);
            $userName = $this->resolveResetNotifiableName($notifiable);

            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            return (new MailMessage)
                ->subject('إعادة تعيين كلمة المرور — Nook Mate')
                ->view('emails.auth.reset-password', [
                    'user' => $notifiable,
                    'userName' => $userName,
                    'resetUrl' => $url,
                    'expire' => $expire,
                ])
                ->text('emails.auth.reset-password-text', [
                    'user' => $notifiable,
                    'userName' => $userName,
                    'resetUrl' => $url,
                    'expire' => $expire,
                ]);
        });
    }

    private function passwordResetExpirationMinutes(): int
    {
        $brokerConfig = config('auth.defaults.passwords', 'users');
        $broker = is_string($brokerConfig) ? $brokerConfig : 'users';

        $expireConfig = config("auth.passwords.{$broker}.expire", 60);

        if (is_numeric($expireConfig)) {
            return (int) $expireConfig;
        }

        return 60;
    }

    private function resolveResetNotifiableEmail(mixed $notifiable): string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'getEmailForPasswordReset')) {
            $rawEmail = $notifiable->getEmailForPasswordReset();

            return is_string($rawEmail) ? $rawEmail : '';
        }

        if ($notifiable instanceof User) {
            return $notifiable->email;
        }

        return '';
    }

    private function resolveResetNotifiableName(mixed $notifiable): ?string
    {
        if ($notifiable instanceof User) {
            return $notifiable->name !== '' ? $notifiable->name : null;
        }

        if (is_object($notifiable) && isset($notifiable->name) && is_string($notifiable->name) && $notifiable->name !== '') {
            return $notifiable->name;
        }

        return null;
    }
}

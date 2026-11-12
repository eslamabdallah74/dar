<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        @php
            $component = $page['component'] ?? '';
            $appName = config('app.name', 'Nook Mate');
            $canonicalUrl = url()->current();

            // Default metadata for landing / public pages
            $pageTitle = "{$appName} — إدارة ممتلكاتك، بكل سهولة";
            $pageDescription = "{$appName} — منصة سحابية متكاملة لإدارة وتأجير العقارات السكنية والشقق والغرف والأسرة بنظام الصناديق المتداخلة مع تذكيرات واتساب وسندات قبض فورية.";
            $robotsDirective = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

            if ($component === 'Welcome') {
                $pageTitle = "{$appName} — Find Your Spot. Rent the Space You Need.";
                $pageDescription = "{$appName} — Find Your Spot. Rent the Space You Need. منصة سحابية متكاملة لإدارة وتأجير العقارات السكنية والشقق والغرف والأسرة بنظام الصناديق المتداخلة مع تذكيرات واتساب وسندات قبض فورية.";
            } elseif (str_starts_with($component, 'Auth/')) {
                $robotsDirective = 'noindex, follow';
                if ($component === 'Auth/Login') {
                    $pageTitle = "تسجيل الدخول | {$appName}";
                    $pageDescription = "سجّل الدخول إلى مساحة عملك في {$appName} لإدارة العقارات والمستأجرين وعقود الإيجار والتحصيلات.";
                } elseif ($component === 'Auth/Register') {
                    $pageTitle = "إنشاء حساب جديد | {$appName}";
                    $pageDescription = "أنشئ حسابك المجاني في {$appName} وابدأ إدارة عقاراتك ووحداتك السكنية وتذكيرات واتساب فوراً.";
                } elseif ($component === 'Auth/ForgotPassword') {
                    $pageTitle = "استعادة كلمة المرور | {$appName}";
                    $pageDescription = "استعادة كلمة المرور لحسابك في منصة {$appName}.";
                } elseif ($component === 'Auth/ResetPassword') {
                    $pageTitle = "تعيين كلمة المرور الجديدة | {$appName}";
                    $pageDescription = "تعيين كلمة مرور جديدة لحسابك في منصة {$appName}.";
                } elseif ($component === 'Auth/VerifyEmail') {
                    $pageTitle = "تأكيد البريد الإلكتروني | {$appName}";
                    $pageDescription = "تأكيد عنوان بريدك الإلكتروني في منصة {$appName}.";
                }
            } elseif (str_starts_with($component, 'Admin/')) {
                $robotsDirective = 'noindex, nofollow';
                $pageTitle = "لوحة الإدارة المركزية | {$appName}";
                $pageDescription = "لوحة الإدارة المركزية لمنصة {$appName}.";
            } else {
                $robotsDirective = 'noindex, nofollow';
                $pageTitle = "لوحة التحكم | {$appName}";
                $pageDescription = "لوحة إدارة العقارات والإيجارات في {$appName}.";
            }
        @endphp

        <title inertia>{{ $pageTitle }}</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="{{ $pageTitle }}">
        <meta name="description" inertia="description" content="{{ $pageDescription }}">
        <meta name="keywords" content="Nook Mate, Find Your Spot, Rent the Space You Need, Rent by Bed, Rent by Room, Flat, إدارة عقارات, نظام لإدارة الإيجار, برنامج لإدارة الأملاك, عقود الإيجار, سند قبض إلكتروني, تذكير إيجار واتساب, سكن مشترك, إدارة شقق">
        <meta name="author" content="Nook Mate">
        <meta name="robots" inertia="robots" content="{{ $robotsDirective }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Favicons & Mobile Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo.png') }}">
        <meta name="theme-color" content="#163E3E">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Nook Mate">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:title" inertia="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" inertia="og:description" content="{{ $pageDescription }}">
        <meta property="og:image" content="{{ asset('images/logo.png') }}">
        <meta property="og:image:secure_url" content="{{ asset('images/logo.png') }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="512">
        <meta property="og:image:height" content="512">
        <meta property="og:image:alt" content="شعار Nook Mate — The Nested Boxes">
        <meta property="og:locale" content="ar_EG">
        <meta property="og:locale:alternate" content="en_US">

        <!-- Twitter / X -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" inertia="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" inertia="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ asset('images/logo.png') }}">
        <meta name="twitter:image:alt" content="شعار Nook Mate">

        <!-- LCP Discovery Preload for Hero Banner -->
        @if ($component === 'Welcome' || request()->is('/'))
            <link rel="preload" as="image" href="{{ asset('images/banner.webp') }}" type="image/webp" fetchpriority="high">
        @endif

        <!-- Optimized Non-Render-Blocking Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Montserrat:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Montserrat:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
        <noscript>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Montserrat:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
        </noscript>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <noscript>
            <div style="padding: 2rem; text-align: center; font-family: sans-serif;" dir="rtl">
                <h1>{{ $pageTitle }}</h1>
                <p>{{ $pageDescription }}</p>
            </div>
        </noscript>
    </body>
</html>

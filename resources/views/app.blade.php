<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <title inertia>{{ config('app.name', 'دار') }} — منصة إدارة وتأجير العقارات والأملاك</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="دار — منصة إدارة وتأجير العقارات والأملاك">
        <meta name="description" content="منصة دار السحابية المتكاملة لإدارة وتأجير العقارات، عقود الإيجار السكنية والتجارية، تحصيل الإيجارات وسندات القبض، تذكيرات واتساب التلقائية، ومتابعة الصيانة ونسب الإشغال.">
        <meta name="keywords" content="إدارة عقارات, نظام إدارة الإيجار, برنامج إدارة الأملاك, عقود الإيجار, سند قبض إلكتروني, تذكير إيجار واتساب, سكن مشترك, إدارة شقق, دار, Dar property management">
        <meta name="author" content="منصة دار">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <link rel="canonical" href="{{ url()->current() }}">

        <!-- Favicons & Mobile Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo.png') }}">
        <meta name="theme-color" content="#0F2747">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="دار">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="دار — منصة إدارة العقارات">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="دار — منصة إدارة وتأجير العقارات والأملاك">
        <meta property="og:description" content="منصة سحابية متكاملة لإدارة وتأجير العقارات، عقود الإيجار، المستأجرين وسندات القبض مع تذكيرات واتساب التلقائية.">
        <meta property="og:image" content="{{ asset('images/logo.png') }}">
        <meta property="og:image:secure_url" content="{{ asset('images/logo.png') }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1254">
        <meta property="og:image:height" content="1254">
        <meta property="og:image:alt" content="شعار منصة دار لإدارة العقارات">
        <meta property="og:locale" content="ar_EG">
        <meta property="og:locale:alternate" content="ar_SA">

        <!-- Twitter / X -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="دار — منصة إدارة وتأجير العقارات والأملاك">
        <meta name="twitter:description" content="منصة سحابية متكاملة لإدارة وتأجير العقارات، عقود الإيجار، المستأجرين وسندات القبض مع تذكيرات واتساب التلقائية.">
        <meta name="twitter:image" content="{{ asset('images/logo.png') }}">
        <meta name="twitter:image:alt" content="شعار منصة دار">

        <!-- Fonts: Cairo & IBM Plex Sans Arabic -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <title inertia>{{ config('app.name', 'Nook Mate') }} — Find Your Spot. Rent the Space You Need.</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="Nook Mate — Find Your Spot. Rent the Space You Need.">
        <meta name="description" content="Nook Mate — Find Your Spot. Rent the Space You Need. منصة ذكية متكاملة لإدارة وتأجير العقارات، الشقق، الغرف والأسرة بنظام الصناديق المتداخلة (The Nested Boxes System).">
        <meta name="keywords" content="Nook Mate, Find Your Spot, Rent the Space You Need, Rent by Bed, Rent by Room, Flat, إدارة عقارات, نظام لإدارة الإيجار, برنامج لإدارة الأملاك, عقود الإيجار, سند قبض إلكتروني, تذكير إيجار واتساب, سكن مشترك, إدارة شقق">
        <meta name="author" content="Nook Mate">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <link rel="canonical" href="{{ url()->current() }}">

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
        <meta property="og:site_name" content="Nook Mate — Find Your Spot. Rent the Space You Need.">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="Nook Mate — Find Your Spot. Rent the Space You Need.">
        <meta property="og:description" content="Nook Mate — Find Your Spot. Rent the Space You Need. منصة سحابية لإدارة وتأجير العقارات، عقود الإيجار، المستأجرين وسندات القبض.">
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
        <meta name="twitter:title" content="Nook Mate — Find Your Spot. Rent the Space You Need.">
        <meta name="twitter:description" content="Nook Mate — Find Your Spot. Rent the Space You Need. منصة سحابية لإدارة وتأجير العقارات وعقود الإيجار.">
        <meta name="twitter:image" content="{{ asset('images/logo.png') }}">
        <meta name="twitter:image:alt" content="شعار Nook Mate">

        <!-- Fonts: Montserrat, Poppins, IBM Plex Sans Arabic, Alexandria & JetBrains Mono -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Cairo:wght@400;600;700&family=Montserrat:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

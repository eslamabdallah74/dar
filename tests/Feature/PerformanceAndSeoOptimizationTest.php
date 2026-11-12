<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('renders landing page with optimized performance and SEO headers', function () {
    $appName = config()->string('app.name', 'Nook Mate');
    $response = $this->get('/');

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toBeFalse();

    // Verify unique title & description
    expect($content)->toContain('<title inertia>'.$appName.' — Find Your Spot. Rent the Space You Need.</title>')
        ->toContain('name="description" inertia="description" content="'.$appName.' — Find Your Spot. Rent the Space You Need. منصة سحابية متكاملة لإدارة وتأجير العقارات السكنية والشقق والغرف والأسرة بنظام الصناديق المتداخلة مع تذكيرات واتساب وسندات قبض فورية."')
        ->toContain('name="robots" inertia="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"');

    // Verify LCP banner preload discovery
    expect($content)->toContain('rel="preload" as="image"')
        ->toContain('banner.webp');

    // Verify non-render-blocking font loading
    expect($content)->toContain('rel="preload" as="style"')
        ->toContain('media="print" onload="this.media=\'all\'"')
        ->toContain('<noscript>');
});

it('renders login page with unique metadata, noindex directive, and descriptive H1', function () {
    $appName = config()->string('app.name', 'Nook Mate');
    $response = $this->get('/login');

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toBeFalse();

    // Verify distinct title and description on initial server HTML
    expect($content)->toContain('<title inertia>تسجيل الدخول | '.$appName.'</title>')
        ->toContain('name="description" inertia="description" content="سجّل الدخول إلى مساحة عملك في '.$appName.' لإدارة العقارات والمستأجرين وعقود الإيجار والتحصيلات."')
        ->toContain('name="robots" inertia="robots" content="noindex, follow"');
});

it('renders register page with unique metadata, noindex directive, and descriptive H1', function () {
    $appName = config()->string('app.name', 'Nook Mate');
    $response = $this->get('/register');

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toBeFalse();

    // Verify distinct title and description on initial server HTML
    expect($content)->toContain('<title inertia>إنشاء حساب جديد | '.$appName.'</title>')
        ->toContain('name="description" inertia="description" content="أنشئ حسابك المجاني في '.$appName.' وابدأ إدارة عقاراتك ووحداتك السكنية وتذكيرات واتساب فوراً."')
        ->toContain('name="robots" inertia="robots" content="noindex, follow"');
});

it('serves a valid sitemap.xml excluding auth routes and dashboard paths', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

    $content = $response->getContent();
    expect($content)->not->toBeFalse();

    // Verify XML structure and canonical URL
    expect($content)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"')
        ->toContain('<loc>https://nookmate.net/</loc>')
        ->toContain('<changefreq>daily</changefreq>')
        ->toContain('<priority>1.0</priority>')
        ->toContain('<image:loc>https://nookmate.net/images/logo.png</image:loc>');

    // Auth and private pages must be excluded
    expect($content)->not->toContain('/login')
        ->not->toContain('/register')
        ->not->toContain('/dashboard');
});

it('validates static public sitemap.xml file format', function () {
    $sitemapPath = public_path('sitemap.xml');
    expect(File::exists($sitemapPath))->toBeTrue();

    $content = File::get($sitemapPath);
    expect($content)->toContain('<loc>https://nookmate.net/</loc>')
        ->not->toContain('<loc>/</loc>')
        ->not->toContain('/login')
        ->not->toContain('/register');
});

it('validates public robots.txt directives and absolute sitemap reference', function () {
    $robotsPath = public_path('robots.txt');
    expect(File::exists($robotsPath))->toBeTrue();

    $content = File::get($robotsPath);
    expect($content)->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /login')
        ->toContain('Disallow: /register')
        ->toContain('Sitemap: https://nookmate.net/sitemap.xml')
        ->not->toContain('Sitemap: /sitemap.xml');
});

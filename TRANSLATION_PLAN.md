# Nook Mate — Full Internationalisation (i18n) Plan

> **Branch:** `feature/i18n-translation-system` (created from `production`)  
> **Date:** November 2026  
> **Scope:** Arabic (ar) + English (en) — Arabic is the primary/default language; English is added as a full peer.  
> **Goal:** A seamless bilingual experience with zero regression in functionality, UX quality, or automated WhatsApp messaging.

---

## Table of Contents

1. [Current State Audit](#1-current-state-audit)
2. [Architecture Decision — Where Language Lives](#2-architecture-decision--where-language-lives)
3. [The WhatsApp Problem (and the Right Solution)](#3-the-whatsapp-problem-and-the-right-solution)
4. [Frontend i18n — vue-i18n Setup](#4-frontend-i18n--vue-i18n-setup)
5. [Backend i18n — Laravel Translation Files](#5-backend-i18n--laravel-translation-files)
6. [Database Changes](#6-database-changes)
7. [RTL / LTR Layout Switching](#7-rtl--ltr-layout-switching)
8. [Font Strategy](#8-font-strategy)
9. [SEO & Meta Tags](#9-seo--meta-tags)
10. [Component-by-Component Translation Checklist](#10-component-by-component-translation-checklist)
11. [Known Issues, Edge Cases & Fixes](#11-known-issues-edge-cases--fixes)
12. [Implementation Phases](#12-implementation-phases)
13. [Testing Plan](#13-testing-plan)
14. [File & Code Reference Map](#14-file--code-reference-map)

---

## 1. Current State Audit

### 1.1 Language Coverage Today

| Layer | Current state |
|---|---|
| `app.blade.php` | `lang="ar" dir="rtl"` — hardcoded |
| All Vue layouts | `dir="rtl"` hardcoded on the root `<div>` |
| Vue pages | 72 / 88 `.vue` files contain Arabic Unicode text (~216 lines of hardcoded Arabic per file on average) |
| `lang/ar/` + `lang/ar.json` | Auth & password strings translated; nothing for app domain text |
| `lang/en/` | Only default Laravel stubs (`auth`, `pagination`, `passwords`, `validation`) |
| Backend flash messages | Hardcoded Arabic strings in every controller (`with('success', 'تم حفظ...')`) |
| WhatsApp templates | Hardcoded `_ar` suffix in template names and `'language' => ['code' => 'ar']` |
| Email bodies | Hardcoded Arabic prose in `LeaseExpiryReminderJob`, `CheckOverdueRentChargesJob` |
| `AppSidebar.vue` nav labels | Hardcoded Arabic |
| `HandleInertiaRequests.php` | `'role'` label hardcoded Arabic per user |
| `config/app.php` | `APP_LOCALE=en` default (mismatch — UI is fully Arabic) |
| OpenGraph locale | `og:locale` hardcoded `ar_EG` |

### 1.2 Scope of Work

**Frontend:** 88 Vue components/pages to translate (~2,800+ Arabic string occurrences).

**Backend:** 20+ controllers + 8 Jobs + 2 Mail classes with hardcoded Arabic flash messages, email copy, and WhatsApp template references.

**New infrastructure needed:**
- `vue-i18n` package + locale composable
- Language switcher UI component
- `locale` column on `users` table (per-user UI language)
- `whatsapp_message_language` column on `workspaces` table (independent of UI language)
- Laravel middleware to set app locale from user preference
- New lang files: `lang/en/*.php` and `lang/ar/*.php` for domain strings

---

## 2. Architecture Decision — Where Language Lives

This is the most important design question. Getting it wrong causes real bugs.

### 2.1 Two Separate Language Settings

There are **two distinct language contexts** that must be independent:

```
┌──────────────────────────────────────────────────────────────────┐
│  SETTING 1: User Interface Language (per user, in users table)   │
│  Controls: All page text, labels, menus, errors, flash messages  │
│  Stored: users.locale  (ar | en)                                  │
│  Resolved: Synchronously on every HTTP request via middleware     │
│  Frontend: vue-i18n $t() calls                                   │
│  Backend: App::setLocale($user->locale)                          │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│  SETTING 2: WhatsApp Message Language (per workspace)            │
│  Controls: Which WhatsApp template name & language code is used  │
│  Stored: workspaces.whatsapp_message_language  (ar | en)         │
│  Resolved: At job dispatch time, from workspace record           │
│  WHY SEPARATE: Jobs run asynchronously in queues — no session,   │
│  no request context, no way to read the user's browser language. │
│  The workspace owner may also want Arabic WhatsApp to renters    │
│  even when they themselves browse in English.                    │
└──────────────────────────────────────────────────────────────────┘
```

> [!IMPORTANT]  
> **Never** derive the WhatsApp template language from the HTTP session locale or the app locale set by middleware. Those do not exist when a queued job runs. Always read `Workspace::find($workspaceId)->whatsapp_message_language` directly inside the job or service.

### 2.2 UI Language Resolution Chain

```
Request arrives
     ↓
SetUserLocale middleware
     ↓
  user authenticated?
  ├── YES → App::setLocale($request->user()->locale ?? 'ar')
  └── NO  → App::setLocale('ar')   ← guests see Arabic by default
     ↓
Inertia shares { locale, dir } to all pages
     ↓
Vue app reads shared prop → initialises vue-i18n locale
```

### 2.3 Language Switcher UX Decision

- **For authenticated users:** Language preference is saved to `users.locale` on every switch (AJAX `POST /user/locale`). It persists forever.
- **For guests (landing, login, register):** Language stored in `localStorage` and sent as `Accept-Language` header fallback; not persisted to DB since they have no account.
- **Default for new users:** `ar` (Arabic) — matches existing UX and primary market.

---

## 3. The WhatsApp Problem (and the Right Solution)

### 3.1 Why Sessions Don't Work for Queued Jobs

WhatsApp messages are dispatched via `SendWhatsAppMessageJob` on the `messaging` queue. When this job runs:

1. There is **no HTTP request** in scope.
2. `Auth::user()` is not available.
3. `App::getLocale()` returns the **queue worker's default** (`en` from `.env`), not the user who triggered the job.
4. Reading from session/cache with a user key could be stale (user changed language after job was dispatched).

### 3.2 Current Template Issue

All 4 WhatsApp templates are hardcoded with `_ar` suffix:
- `rent_due_reminder_ar`
- `payment_receipt_instant_ar`
- `overdue_notice_ar`
- `contract_renewal_ar`

And the gateway hardcodes: `'language' => ['code' => 'ar']`

### 3.3 The Fix — Workspace-Level WhatsApp Language

**Step 1:** Add `whatsapp_message_language` column to `workspaces` table (string, default `'ar'`).

**Step 2:** Create a `WhatsAppMessageLanguage` PHP enum:
```php
enum WhatsAppMessageLanguage: string
{
    case Arabic = 'ar';
    case English = 'en';

    public function templateSuffix(): string
    {
        return match($this) {
            self::Arabic  => '_ar',
            self::English => '_en',
        };
    }

    public function metaLanguageCode(): string
    {
        return match($this) {
            self::Arabic  => 'ar',
            self::English => 'en_US',
        };
    }
}
```

**Step 3:** Update `WhatsAppService::queueRentDueReminder()` and siblings to resolve the template name dynamically:
```php
$workspace = Workspace::find($workspaceId);
$lang = WhatsAppMessageLanguage::from($workspace->whatsapp_message_language ?? 'ar');
$templateName = 'rent_due_reminder' . $lang->templateSuffix(); // rent_due_reminder_ar OR rent_due_reminder_en
```

**Step 4:** Update `MetaCloudApiGateway::sendTemplate()` to accept `languageCode` parameter:
```php
'language' => ['code' => $languageCode], // dynamic, not hardcoded 'ar'
```

**Step 5:** Add WhatsApp language selector in **Settings** page under a new "Messaging" tab — clearly labelled: _"Language used in WhatsApp messages sent to renters"_ (separate from UI language).

**Step 6:** You'll need to register English WhatsApp templates with Meta (same 4 templates, `_en` suffix). This is a business process, not code — but must be noted in the plan.

> [!WARNING]  
> Until English WhatsApp templates are approved by Meta, keep `whatsapp_message_language` defaulting to `'ar'` and disable the English option in the UI with a "Coming soon" badge to prevent sending to non-existent templates.

### 3.4 Email Messages (Also Queued)

The `EmailNotificationJob` and job-generated email bodies in `LeaseExpiryReminderJob` / `CheckOverdueRentChargesJob` have the same problem — hardcoded Arabic prose. Apply the same workspace-level language approach:

- Add `workspace->whatsapp_message_language` controls both WhatsApp and email notification language (or add a separate `notification_language` field — but keeping them together is simpler and less confusing).
- Extract email body text into `lang/ar/emails.php` and `lang/en/emails.php`.
- Use `app('translator')->get('emails.lease_expiry_body', [...], $lang->value)` with explicit locale — **never rely on `App::getLocale()`** in a job.

---

## 4. Frontend i18n — vue-i18n Setup

### 4.1 Package

```bash
npm install vue-i18n@10
```

> [!NOTE]  
> Use `vue-i18n@10` (Composition API mode). It is the correct version for Vue 3. Do not use `vue-i18n@9` (legacy mode).

### 4.2 Directory Structure

```
resources/
└── js/
    ├── i18n/
    │   ├── index.ts          ← creates and exports the i18n instance
    │   ├── ar.ts             ← all Arabic translations (flat or namespaced)
    │   └── en.ts             ← all English translations
    └── app.ts                ← registers .use(i18n)
```

### 4.3 `resources/js/i18n/index.ts`

```typescript
import { createI18n } from 'vue-i18n';
import ar from './ar';
import en from './en';

// Locale is injected by Inertia shared props before the app mounts.
// We read it from the page's initial data or fall back to 'ar'.
const savedLocale = (document.querySelector('[data-page]') as HTMLElement)
    ?.dataset?.page
    ? JSON.parse((document.querySelector('[data-page]') as HTMLElement).dataset.page!)
          .props?.locale ?? 'ar'
    : 'ar';

export const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'ar',
    messages: { ar, en },
});
```

### 4.4 `resources/js/app.ts` change

```typescript
import { i18n } from './i18n';
// ...
createApp({ render: () => h(App, props) })
    .use(plugin)
    .use(ZiggyVue)
    .use(i18n)        // ← add this
    .mount(el);
```

### 4.5 `useLocale` Composable

Create `resources/js/composables/useLocale.ts`:

```typescript
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';

export function useLocale() {
    const { locale } = useI18n();
    const page = usePage();

    const currentLocale = computed<'ar' | 'en'>(() => locale.value as 'ar' | 'en');
    const isRtl = computed(() => currentLocale.value === 'ar');
    const dir = computed(() => isRtl.value ? 'rtl' : 'ltr');

    function switchLocale(newLocale: 'ar' | 'en') {
        locale.value = newLocale;
        // Persist to backend for authenticated users
        router.post('/user/locale', { locale: newLocale }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Force Inertia to reload shared props so dir/locale propagate
                router.reload({ only: [] });
            },
        });
    }

    return { currentLocale, isRtl, dir, switchLocale };
}
```

### 4.6 Language File Structure (`en.ts` example)

```typescript
// resources/js/i18n/en.ts
export default {
    nav: {
        dashboard: 'Dashboard',
        properties: 'Properties & Units',
        renters: 'Renters',
        leases: 'Leases',
        payments: 'Payments',
        expenses: 'Expenses',
        reports: 'Financial Reports',
        maintenance: 'Maintenance',
        whatsapp: 'WhatsApp Messages',
        subscription: 'Subscription',
        settings: 'Settings',
        logout: 'Log Out',
    },
    auth: {
        login_title: 'Welcome back 👋',
        login_subtitle: 'Sign in to continue managing your properties, renters, and rent collections.',
        email: 'Email address',
        password: 'Password',
        forgot_password: 'Forgot password?',
        remember_me: 'Remember me',
        login_btn: 'Sign In',
        no_account: "Don't have an account?",
        register_link: 'Create one',
    },
    dashboard: {
        greeting: 'Good {timeOfDay}, {name}',
        subtitle: "Here's your workspace overview.",
        kpi: {
            properties: 'Properties',
            units: 'Units',
            occupancy: 'Occupancy',
            collected: 'Collected This Month',
        },
    },
    errors: {
        '404': { title: 'Page or Record Not Found', description: 'The page or resource you are looking for could not be found.' },
        '403': { title: 'Access Denied', description: 'You do not have permission to access this page.' },
        '503': { title: 'Under Maintenance', description: 'We are running updates. Please try again in a moment.' },
        '500': { title: 'Server Error', description: 'An unexpected error occurred. It has been logged for review.' },
    },
    common: {
        save: 'Save',
        cancel: 'Cancel',
        delete: 'Delete',
        edit: 'Edit',
        confirm: 'Confirm',
        back: 'Back',
        loading: 'Loading…',
        empty_state: 'No records found',
        search_placeholder: 'Search…',
    },
    // ... and so on for every section
};
```

Mirror all keys in `ar.ts` with Arabic values.

### 4.7 Usage in Templates

Replace all hardcoded strings:

```vue
<!-- BEFORE -->
<span>الرئيسية</span>

<!-- AFTER -->
<span>{{ $t('nav.dashboard') }}</span>
```

For component scripts:
```typescript
const { t } = useI18n();
const label = t('common.save');
```

---

## 5. Backend i18n — Laravel Translation Files

### 5.1 New Domain Lang Files

Create the following new language files (both `ar` and `en` variants):

| File | Contents |
|---|---|
| `lang/ar/workspace.php` | Workspace settings flash messages |
| `lang/en/workspace.php` | Same in English |
| `lang/ar/payments.php` | Payment flash messages, labels |
| `lang/en/payments.php` | Same in English |
| `lang/ar/leases.php` | Lease flash messages, labels |
| `lang/en/leases.php` | Same in English |
| `lang/ar/renters.php` | Renter flash messages |
| `lang/en/renters.php` | Same in English |
| `lang/ar/notifications.php` | Email bodies, subjects |
| `lang/en/notifications.php` | Same in English |
| `lang/ar/whatsapp.php` | WhatsApp UI labels (template names, status labels) |
| `lang/en/whatsapp.php` | Same in English |
| `lang/ar/errors.php` | HTTP error page messages |
| `lang/en/errors.php` | Same in English |

### 5.2 Replace Hardcoded Backend Strings

**Current (bad):**
```php
return back()->with('success', 'تم حفظ إعدادات وتفضيلات المنشأة بنجاح');
```

**After (correct):**
```php
return back()->with('success', __('workspace.settings_saved'));
```

> [!IMPORTANT]  
> Laravel's `__()` and `trans()` helpers use `App::getLocale()`, which **is correctly set** by the middleware for HTTP requests. This works fine for flash messages. Only queued jobs need special handling (see §3).

### 5.3 Locale Middleware

Create `app/Http/Middleware/SetUserLocale.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $request->user()?->locale ?? 'ar';
        $supported = ['ar', 'en'];

        App::setLocale(in_array($locale, $supported, true) ? $locale : 'ar');

        return $next($request);
    }
}
```

Register it in `bootstrap/app.php` **after** `auth` middleware.

### 5.4 Share Locale via Inertia

In `HandleInertiaRequests::share()`, add:

```php
'locale' => App::getLocale(),
'dir'    => App::getLocale() === 'ar' ? 'rtl' : 'ltr',
```

The Vue app reads these on every page load/navigation to initialise vue-i18n and apply the correct `dir` attribute.

---

## 6. Database Changes

### 6.1 `users` Table — UI Language

```php
// In the base users migration (active development mode — edit in-place)
$table->string('locale', 10)->default('ar');
```

Add to `User` model `$fillable` and `casts()`:
```php
protected $fillable = ['name', 'email', 'password', 'locale'];
```

### 6.2 `workspaces` Table — WhatsApp/Notification Language

```php
// In the base workspaces migration (active development mode — edit in-place)
$table->string('whatsapp_message_language', 10)->default('ar');
```

Add to `Workspace` model `$fillable`:
```php
'whatsapp_message_language',
```

### 6.3 New Route — Save UI Locale

```php
// routes/web.php — inside the auth middleware group
Route::post('/user/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', Rule::in(['ar', 'en'])],
    ]);
    $request->user()->update(['locale' => $validated['locale']]);
    return back();
})->name('user.locale');
```

### 6.4 Workspace WhatsApp Language — Settings Controller

Add `whatsapp_message_language` to `SettingsController::update()` validation and update logic.

---

## 7. RTL / LTR Layout Switching

### 7.1 The Problem

Every layout has `dir="rtl"` hardcoded:
- `app.blade.php`: `<html lang="ar" dir="rtl">`
- `AppLayout.vue`: `<div ... dir="rtl">`
- `GuestLayout.vue`: `dir="rtl"`
- `AuthenticatedLayout.vue`: `dir="rtl"`

### 7.2 The Fix

**`app.blade.php`** — make dynamic via Blade:
```blade
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
```

**Vue layouts** — bind `dir` from shared Inertia prop:
```vue
<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const dir = computed(() => page.props.dir ?? 'rtl');
</script>

<template>
    <div :dir="dir" class="min-h-screen ...">
```

> [!WARNING]  
> Do **not** bind `dir` on the `<html>` element from Vue — Inertia does not control the outer HTML document on subsequent navigations. Only Blade controls `<html>`. The `<html dir>` is set server-side. Vue layouts set `dir` on their root wrapper `<div>`, which is sufficient for CSS cascade.

### 7.3 Tailwind RTL Classes

The project uses many `text-right`, `pr-`, `pl-`, `border-r`, etc. These need to be replaced with **logical properties** to automatically flip with direction:

| Replace this | With this |
|---|---|
| `text-right` | `text-start` (or `text-end`) |
| `pr-4` | `ps-4` |
| `pl-4` | `pe-4` |
| `border-r` | `border-s` |
| `border-l` | `border-e` |
| `rounded-r-*` | `rounded-s-*` |
| `rounded-l-*` | `rounded-e-*` |
| `ml-auto` | `ms-auto` |
| `mr-auto` | `me-auto` |

> [!NOTE]  
> Tailwind CSS v3 supports logical properties (`ps-`, `pe-`, `ms-`, `me-`, `text-start`, `text-end`, `border-s`, `border-e`). This project uses Tailwind v3 (`"tailwindcss": "^3.2.1"`). These classes are safe to use.

This is a large find-and-replace but must be done systematically across all 88 `.vue` files. Some exceptions exist where `dir="ltr"` is intentionally set on individual elements (like phone numbers, money amounts in `font-mono`) — those should be preserved.

### 7.4 `dir="ltr"` Islands (Keep As-Is)

These elements should always stay LTR regardless of UI language:
- Phone numbers: `<span dir="ltr">+20 10 ...</span>`
- Money amounts with font-mono
- Date strings in ISO format
- The brand logo "Nook Mate" text in `GuestLayout.vue` (`dir="ltr"` already applied — keep)
- URLs and links
- Input `type="email"`, `type="tel"`, `type="number"` — set `dir="ltr"` explicitly

---

## 8. Font Strategy

### 8.1 Current Fonts

```
Arabic:  IBM Plex Sans Arabic, Alexandria, Cairo (loaded from Google Fonts)
Latin:   Montserrat, Poppins, JetBrains Mono (loaded from Google Fonts)
Default: Montserrat first, then IBM Plex Sans Arabic
```

### 8.2 Issue

`tailwind.config.js` has the `sans` font stack starting with `Montserrat` — a **Latin-only** font. For Arabic characters, the browser falls through to `IBM Plex Sans Arabic`. This is currently fine because the entire UI is Arabic, so Montserrat is only used for English-alphabet text (logo, brand name, numbers).

When the UI switches to English, `Montserrat` becomes the dominant body font, which is good.

### 8.3 No Changes Needed

The existing font stack already handles both directions well. No changes to `tailwind.config.js` are required. The only action is to ensure Arabic users get a good Arabic experience and English users get a good Latin experience — the current fallback chain handles this.

> [!TIP]  
> If you want to add `Inter` as a cleaner English-first sans-serif, it can be added after `Montserrat` in the stack. But this is optional and should be a separate PR.

---

## 9. SEO & Meta Tags

### 9.1 `app.blade.php` Hardcoded SEO Issues

The `<head>` in `app.blade.php` has fully hardcoded Arabic meta tags. These are shown to search engines and social scrapers for every URL including future English ones.

**Fix plan:**
- Move meta content to Inertia `Head` components in each page (already partially done in `Welcome.vue` using `<Head>` and `<meta>` tags).
- For the base `app.blade.php` defaults, make them conditional on `app()->getLocale()`:

```blade
<title inertia>{{ config('app.name') }} — {{ app()->getLocale() === 'ar' ? 'إدارة ممتلكاتك، بكل سهولة' : 'Manage Your Properties, Effortlessly' }}</title>
<meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_EG' : 'en_US' }}">
<meta property="og:locale:alternate" content="{{ app()->getLocale() === 'ar' ? 'en_US' : 'ar_EG' }}">
```

### 9.2 `Welcome.vue` JSON-LD

The landing page has an extensive JSON-LD structured data block entirely in Arabic. This needs bilingual versions. The `Welcome.vue` page should detect the current locale prop and render the appropriate JSON-LD content.

```vue
<component :is="'script'" type="application/ld+json">
    {{ JSON.stringify(locale === 'ar' ? arStructuredData : enStructuredData) }}
</component>
```

### 9.3 Hreflang Tags

For true bilingual SEO, add `hreflang` link tags. If the app serves the same URL in both languages (locale stored in DB, not URL), use the `x-default` approach:

```blade
<link rel="alternate" hreflang="ar" href="{{ url()->current() }}">
<link rel="alternate" hreflang="en" href="{{ url()->current() }}">
<link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">
```

> [!NOTE]  
> Since language is stored per-user (not URL-based), standard hreflang requirements are not strictly applicable. However, adding `x-default` with the canonical URL is still good practice.

---

## 10. Component-by-Component Translation Checklist

### Layouts

- [ ] `app.blade.php` — `lang`, `dir`, meta tags, hreflang
- [ ] `AppLayout.vue` — `dir` binding, hardcoded role labels, error display
- [ ] `GuestLayout.vue` — `dir` binding, footer text ("All rights reserved")
- [ ] `AuthenticatedLayout.vue` — `dir` binding

### Auth Pages

- [ ] `Login.vue` — all labels, placeholders, links
- [ ] `Register.vue` — all labels, placeholders, plan names
- [ ] `ForgotPassword.vue` — description, button, links
- [ ] `ResetPassword.vue` — labels, button
- [ ] `ConfirmPassword.vue` — description, button
- [ ] `VerifyEmail.vue` — description, links

### Core App Pages

- [ ] `Dashboard.vue` — greeting, KPI labels, all data passed from server via translations
- [ ] `Error.vue` — all 4 error variants (404, 403, 503, 500) + action buttons
- [ ] `Profile/Edit.vue` — section headers
- [ ] `Profile/Partials/UpdateProfileInformationForm.vue` — labels, buttons
- [ ] `Profile/Partials/UpdatePasswordForm.vue` — labels, buttons
- [ ] `Profile/Partials/DeleteUserForm.vue` — warning text, modal, button

### Domain Pages

- [ ] `Properties/Index.vue` — table headers, empty state, action buttons
- [ ] `Properties/Create.vue` — form labels, placeholders, section titles
- [ ] `Properties/Show.vue` — all labels, tabs, action buttons
- [ ] `Renters/Index.vue` — table headers, search, empty state
- [ ] `Renters/Show.vue` — all labels, sections
- [ ] `Leases/Index.vue` — table headers, status badges, filters
- [ ] `Leases/Create.vue` — form labels, sections
- [ ] `Leases/Show.vue` — all tabs, KPI labels, action buttons
- [ ] `Payments/Index.vue` — table headers, filters, totals
- [ ] `Expenses/Index.vue` — table headers, categories
- [ ] `Maintenance/Index.vue` — status labels, filters
- [ ] `Reports/Index.vue` — chart labels, table headers
- [ ] `WhatsApp/Index.vue` — template names, status labels, stat cards
- [ ] `Subscription/Index.vue` — plan names, feature lists, CTAs
- [ ] `Settings/Index.vue` — tab labels, form labels, new WhatsApp language setting
- [ ] `Welcome.vue` — entire landing page (1097 lines, most critical)

### Components

- [ ] `Layout/AppSidebar.vue` — nav labels (read from i18n instead of hardcoded arrays)
- [ ] `Layout/AppNavbar.vue` — search placeholder, role display, logout, workspace switcher
- [ ] `Common/EmptyState.vue` — props remain `title`/`description` but callers must pass `$t()` values
- [ ] `Common/GlobalToast.vue` — no text, but reads flash from backend (flash must be translated)
- [ ] `Common/ModalNewAction.vue` — all labels
- [ ] `Common/ModalRecordPayment.vue` — all labels
- [ ] `Common/ConfirmDialog.vue` — default button labels
- [ ] `Common/StatusBadge.vue` — status text values
- [ ] `Dashboard/*` — all 13 dashboard components
- [ ] `Leases/Show/*` — all 7 lease detail components
- [ ] `Leases/SecurityDeposit/*` — all 3 deposit components
- [ ] `Maintenance/MaintenanceDetailsDrawer.vue`
- [ ] `Properties/ModalUnitForm.vue`
- [ ] `Properties/PropertyCreateDrawer.vue`
- [ ] `Properties/UnitHierarchyTree.vue`

### Admin Pages

- [ ] `Admin/Login.vue`
- [ ] `Admin/Dashboard.vue`
- [ ] `Admin/Workspaces/Show.vue`

---

## 11. Known Issues, Edge Cases & Fixes

### 11.1 Issue: `HandleInertiaRequests` Hardcoded Role Labels

**Location:** `app/Http/Middleware/HandleInertiaRequests.php`

```php
// CURRENT (bad):
'role' => $user->isAdmin() ? 'مدير النظام (كامل الصلاحيات)' : 'مالك الحساب',
```

**Fix:** Return a role key, translate on the frontend:
```php
'role' => $user->isAdmin() ? 'admin' : 'owner',
```
```vue
// AppNavbar.vue:
{{ $t(`roles.${user.role}`) }}
```

### 11.2 Issue: Dashboard KPI Labels From Server

**Location:** `WorkspaceDashboardService.php` — the `kpis` array has `label` and `subtitle` values built as hardcoded Arabic strings.

**Fix options (choose one):**
- **Option A (recommended):** Remove `label`/`subtitle` from server data; compute them in Vue with `$t()`. Server only sends numbers.
- **Option B:** Keep label keys on server (`'label_key' => 'kpi.properties'`), translate on frontend.

Option A is cleaner and keeps translations entirely on the frontend.

### 11.3 Issue: `WhatsAppController` Template Name Labels Hardcoded

**Location:** `WhatsAppController::index()` — the `match($log->template_name)` block returns hardcoded Arabic labels.

**Fix:** Return the raw `template_name` from the server; resolve the display label using `$t('whatsapp.templates.rent_due_reminder_ar')` in Vue's `WhatsApp/Index.vue`.

### 11.4 Issue: `WhatsAppController` Connection Status Hardcoded

```php
'status_label' => $hasCredentials ? 'متصل ونشط' : 'بانتظار ضبط مفاتيح الربط (WHATSAPP_API_TOKEN)',
'provider' => 'WhatsApp Cloud API الرسمية (Meta)',
'sender_number' => ... 'غير مهيأ'
```

**Fix:** Return `'status' => $hasCredentials ? 'connected' : 'disconnected'` and translate in Vue:
```vue
{{ $t(`whatsapp.connection.${connection.status}`) }}
```

### 11.5 Issue: `DashboardController` Creates Workspace With Arabic Default Name

```php
Workspace::create([
    'name' => 'عقارات النور',  // ← hardcoded Arabic
    ...
```

**Fix:** Use a locale-aware default:
```php
'name' => __('workspace.default_name'),
```
With `lang/ar/workspace.php`: `'default_name' => 'مساحة العمل الجديدة'`  
And `lang/en/workspace.php`: `'default_name' => 'My Workspace'`

### 11.6 Issue: `AppNavbar.vue` Hardcoded Fallback Role

```typescript
const user = computed(() => page.props.auth?.user ?? { 
    name: 'أحمد حسن',  // ← hardcoded Arabic name
    role: 'مالك المنشأة'
});
```

**Fix:** Use `$t('roles.owner')` as the fallback role string; use `$t('common.guest')` or the actual user name fallback.

### 11.7 Issue: `AppSidebar.vue` Nav Item Names Hardcoded

```typescript
const defaultTenantNavLinks = [
    { key: 'dashboard', name: 'الرئيسية', ... },
    // ...
]
```

**Fix:** Remove `name` from the data array; derive it in the template:
```vue
<span>{{ $t(`nav.${item.key}`) }}</span>
```

### 11.8 Issue: `LeaseExpiryReminderJob` Email Body — Arabic in Queue

```php
'mailSubject' => "تنبيه قرب موعد انتهاء عقد الإيجار - وحدة {$unitNumber}",
'contentBody' => "عزيزي المستأجر {$renter->name}...",
```

**Fix:** Use a Locale-aware translation with explicit locale:
```php
$notificationLang = $lease->workspace->whatsapp_message_language ?? 'ar';
$mailSubject = app('translator')->get(
    'notifications.lease_expiry_subject',
    ['unit' => $unitNumber],
    $notificationLang
);
```

### 11.9 Issue: `GenericNotificationMail` Has No i18n

**Location:** `app/Mail/GenericNotificationMail.php` — renders via a Blade view which is currently unknown. Ensure that view uses `__()` with the locale resolved from the job context (passed as a parameter, not from `App::getLocale()`).

### 11.10 Issue: `MetaCloudApiGateway` Hardcoded Language Code

```php
'language' => ['code' => 'ar'],  // ← always Arabic
```

**Fix:** Update the `WhatsAppGatewayInterface::sendTemplate()` signature to include `string $languageCode = 'ar'` and pass it dynamically from `WhatsAppService`.

### 11.11 Issue: English Number Formatting

Arabic uses `١٢٣` (Arabic-Indic numerals) in some contexts, but the app currently uses Western numerals (`123`) everywhere. Keep Western numerals for the English UI — this is already correct. For Arabic UI, the current approach (Western numerals) is acceptable and already in production. No change needed here unless the product team requests Arabic-Indic numerals.

### 11.12 Issue: Date Formatting

Currently all dates are formatted as `d/m/Y` (e.g. `11/11/2026`). For English UI, consider changing to `M d, Y` (e.g. `Nov 11, 2026`) using Carbon's `translatedFormat()` with locale set.

**Fix:** In any server-side date formatting, use:
```php
$date->locale($workspace->whatsapp_message_language ?? 'ar')->isoFormat('DD MMM YYYY');
```

For the UI: render dates from the server as ISO strings and format them in Vue using `Intl.DateTimeFormat` with the current locale:
```typescript
const formatDate = (iso: string) =>
    new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-EG' : 'en-US', {
        day: '2-digit', month: 'short', year: 'numeric'
    }).format(new Date(iso));
```

### 11.13 Issue: Currency Symbol & Formatting

The app shows currency via `$workspace->currency` (e.g., `EGP`). For Arabic: `١٢٣ ج.م` or `١٢٣ جنيه`. For English: `EGP 123` or `123 EGP`.

**Fix:** Use `Intl.NumberFormat` on the frontend:
```typescript
const formatMoney = (amount: number, currency: string) =>
    new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-EG' : 'en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
```

The existing `workspace.currencySymbol()` accessor returns `'ج.م'` for EGP — this is an Arabic-only string. Extend it to return locale-aware strings or rely on `Intl.NumberFormat` on the frontend entirely.

### 11.14 Issue: Language Switcher Must Not Break Active Forms

If a user switches language mid-form (e.g., while filling a lease creation form), Inertia's `router.reload()` will reset the page. 

**Fix:** The language switcher should warn the user if there are unsaved changes (`useForm().isDirty` check) before switching. Or, switch locale entirely client-side (just update vue-i18n locale without a page reload) and only save to backend silently.

### 11.15 Issue: `app.blade.php` Hardcoded Font Loading for Arabic

The Google Fonts URL loads `IBM Plex Sans Arabic`, `Alexandria`, `Cairo` — these are Arabic-specific. For English users, these fonts add unnecessary payload. This is a performance consideration, not a correctness issue.

**Fix (optional / Phase 2):** Serve different font subsets based on locale via `loadFont` logic. For now, keeping both font families loaded is acceptable since they're loaded from Google CDN and cached.

### 11.16 Issue: RTL-Specific Tailwind Classes on 88 Files

Finding and replacing `text-right` → `text-start`, `pr-` → `ps-`, etc., across 88 files is tedious and error-prone.

**Fix approach:**
1. Use a custom ESLint rule or a one-time script to find the patterns.
2. Test each changed page in both RTL and LTR before merging.
3. Keep a list of intentional LTR islands (phone numbers, amounts) that must NOT be changed.

### 11.17 Issue: `useConfirmDialog` Has Hardcoded Default Labels

The `confirmLabel` and `cancelLabel` defaults in `useConfirmDialog.ts` are likely Arabic. After translation, callers should always pass translated strings explicitly:
```typescript
const { confirm } = useConfirmDialog();
await confirm({
    title: t('common.confirm_delete'),
    confirmLabel: t('common.delete'),
    cancelLabel: t('common.cancel'),
});
```

### 11.18 Issue: Renter's `name` Field — Arabic Names in English UI

Renter names are entered by the user and stored in Arabic (or any language). These should always display as entered, regardless of UI locale. No change needed for names — they are user data, not UI strings.

### 11.19 Issue: Input Placeholder Direction

For English locale, `placeholder` text should be LTR. For Arabic locale, RTL. Bind `dir` on input elements:
```vue
<input :dir="dir" :placeholder="$t('common.search_placeholder')" />
```

---

## 12. Implementation Phases

### Phase 1 — Infrastructure (No User-Visible Change)

**Goal:** Set up all translation machinery without touching a single UI string yet.

1. Add `locale` column to `users` table (migration in-place).
2. Add `whatsapp_message_language` column to `workspaces` table (migration in-place).
3. Add `WhatsAppMessageLanguage` PHP enum.
4. Create `SetUserLocale` middleware and register it.
5. Update `HandleInertiaRequests` to share `locale` and `dir`.
6. Install `vue-i18n@10`.
7. Create `resources/js/i18n/index.ts`, `ar.ts` (empty), `en.ts` (empty).
8. Register vue-i18n in `app.ts`.
9. Create `useLocale` composable.
10. Add `POST /user/locale` route.
11. Add `SettingsController` support for `whatsapp_message_language`.
12. Update `MetaCloudApiGateway::sendTemplate()` to accept dynamic language code.
13. Update `WhatsAppService` queue methods to resolve template name from workspace.
14. Create empty domain lang files (`lang/ar/*.php`, `lang/en/*.php`).

**Tests:** Unit test for `SetUserLocale` middleware, `WhatsAppMessageLanguage` enum, and `WhatsAppService` template name resolution.

---

### Phase 2 — Backend Translations

**Goal:** Translate all server-sent strings (flash messages, email bodies).

1. Fill all `lang/ar/*.php` and `lang/en/*.php` files with translated strings.
2. Replace all hardcoded Arabic strings in controllers with `__('key')` calls.
3. Fix `HandleInertiaRequests` role labels.
4. Fix `DashboardController` default workspace name.
5. Fix email bodies in `LeaseExpiryReminderJob`, `CheckOverdueRentChargesJob`, `EmailNotificationJob` with explicit locale parameter.
6. Fix `WhatsAppController` — replace hardcoded template name labels and status labels.

**Tests:** Feature tests for each controller verifying flash messages in both `ar` and `en` locales.

---

### Phase 3 — Frontend Translations (Core Layouts & Auth)

**Goal:** Translate the foundation — layouts, auth pages, error page.

1. Update `app.blade.php` for dynamic `lang`/`dir`.
2. Update `AppLayout.vue`, `GuestLayout.vue`, `GuestLayout.vue` for dynamic `dir`.
3. Add Language Switcher component (`LanguageSwitcher.vue`) in AppNavbar.
4. Translate `AppSidebar.vue` nav labels.
5. Translate all 6 Auth pages.
6. Translate `Error.vue`.
7. Translate `Profile/Edit.vue` and its partials.
8. Run `npm run lint:check` and `npm run typecheck` — fix all errors.

---

### Phase 4 — Frontend Translations (Domain Pages)

**Goal:** Translate all 20+ domain pages and 40+ components.

1. Translate in order: `Dashboard.vue`, `Properties/`, `Renters/`, `Leases/`, `Payments/`, `Expenses/`, `Maintenance/`, `Reports/`, `WhatsApp/`, `Subscription/`, `Settings/`.
2. For each page: extract Arabic strings → add to `ar.ts` and `en.ts` → replace with `$t()` calls.
3. Replace directional Tailwind classes (`text-right` → `text-start`, etc.) as you go.
4. Fix date/currency formatting to use `Intl` APIs.

---

### Phase 5 — Welcome / Landing Page

**Goal:** Translate the 1097-line landing page (biggest single file).

1. Extract all Arabic strings (~216 occurrences) into `ar.ts` under `landing.*` namespace.
2. Write English equivalents in `en.ts`.
3. Add locale-aware JSON-LD structured data.
4. Add language switcher in landing navbar.
5. Update `<Head>` meta tags for bilingual SEO.
6. Test all FAQ accordions, pricing cards, feature sections in both locales.

---

### Phase 6 — RTL/LTR Systematic Fix

**Goal:** Ensure layout does not break when switching direction.

1. Run systematic replacement of directional classes across all 88 files.
2. Visual regression testing in both RTL (ar) and LTR (en) for every page.
3. Verify "LTR islands" (phone numbers, amounts, dates) are unaffected.

---

### Phase 7 — QA, Polish & Launch

1. Full E2E test run in both locales.
2. Test language switch during active form — verify warning appears.
3. Test WhatsApp template name resolution for both `ar` and `en` workspace setting.
4. Test queued jobs (dispatch manually, verify correct template name logged).
5. Test email notifications in both locales.
6. Run `composer quality` and `npm run lint:check && npm run typecheck`.
7. Submit PR to `production`.

---

## 13. Testing Plan

### Unit Tests

| Test | What it verifies |
|---|---|
| `WhatsAppMessageLanguage` enum | `templateSuffix()` and `metaLanguageCode()` return correct values |
| `SetUserLocale` middleware | Sets locale from `user->locale`, defaults to `ar` for guests |
| `WhatsAppService::queueRentDueReminder()` | Uses workspace language, not `App::getLocale()` |
| `MetaCloudApiGateway::sendTemplate()` | Sends correct `language.code` parameter |

### Feature Tests

| Test | What it verifies |
|---|---|
| `POST /user/locale` | Saves locale to `users.locale`, validates only `ar`/`en` |
| `PUT /settings` with `whatsapp_message_language` | Saves to workspace |
| Flash messages in English locale | Controller flash messages use `__()` and return English |
| Flash messages in Arabic locale | Same, returns Arabic |
| Language switcher in nav | Renders for both authenticated and guest users |
| `Error.vue` in both locales | 404/403/503/500 messages correct in both languages |

### Manual Visual Regression Checklist

- [ ] Landing page in AR + EN — full scroll, all sections readable
- [ ] Login/Register in AR + EN — form validates, errors show in correct language
- [ ] Dashboard in AR + EN — KPIs readable, charts accessible
- [ ] Leases index + create + show in both locales
- [ ] Settings page — both tabs, WhatsApp language selector visible
- [ ] Language switch while form is dirty — warning appears
- [ ] Mobile sidebar nav — labels translate correctly
- [ ] RTL layout in AR — no layout breaks
- [ ] LTR layout in EN — no layout breaks, money/phone LTR islands intact

---

## 14. File & Code Reference Map

| File | Key change |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | Add `locale` column |
| `database/migrations/2026_01_01_000010_create_workspaces_table.php` | Add `whatsapp_message_language` column |
| `app/Models/User.php` | Add `locale` to `$fillable` |
| `app/Models/Workspace.php` | Add `whatsapp_message_language` to `$fillable` |
| `app/Enums/WhatsAppMessageLanguage.php` | **NEW** — enum for WA language |
| `app/Http/Middleware/SetUserLocale.php` | **NEW** — sets app locale per user |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `locale`, `dir`; fix role key |
| `app/Http/Controllers/SettingsController.php` | Add `whatsapp_message_language` |
| `app/Http/Controllers/DashboardController.php` | Fix default workspace name |
| `app/Http/Controllers/WhatsAppController.php` | Remove hardcoded Arabic labels |
| `app/Services/WhatsAppService.php` | Resolve template name from workspace language |
| `app/Services/WhatsApp/MetaCloudApiGateway.php` | Dynamic language code parameter |
| `app/Jobs/LeaseExpiryReminderJob.php` | Use workspace notification language |
| `app/Jobs/CheckOverdueRentChargesJob.php` | Use workspace notification language |
| `app/Jobs/EmailNotificationJob.php` | Accept locale parameter |
| `lang/ar/*.php` | **NEW** — domain translation files |
| `lang/en/*.php` | **NEW** — English domain translation files |
| `lang/ar.json` | Extend with any additional auth strings |
| `lang/en.json` | **NEW** — English auth JSON translations |
| `resources/views/app.blade.php` | Dynamic `lang`/`dir`, meta tags |
| `resources/js/app.ts` | Register vue-i18n |
| `resources/js/i18n/index.ts` | **NEW** — i18n instance |
| `resources/js/i18n/ar.ts` | **NEW** — all Arabic UI strings |
| `resources/js/i18n/en.ts` | **NEW** — all English UI strings |
| `resources/js/composables/useLocale.ts` | **NEW** — locale switcher composable |
| `resources/js/Components/Layout/AppSidebar.vue` | Nav labels via `$t()` |
| `resources/js/Components/Layout/AppNavbar.vue` | Add language switcher, fix role label |
| `resources/js/Layouts/AppLayout.vue` | Dynamic `dir` binding |
| `resources/js/Layouts/GuestLayout.vue` | Dynamic `dir` binding |
| `resources/js/Pages/Welcome.vue` | Full landing page translation + JSON-LD |
| `resources/js/Pages/Error.vue` | Translated error messages |
| `resources/js/Pages/Settings/Index.vue` | Add WhatsApp language tab |
| All 88 `.vue` files | Systematic string extraction + directional class replacement |
| `routes/web.php` | Add `POST /user/locale` route |
| `package.json` | Add `vue-i18n@10` dependency |

---

## Summary Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       LANGUAGE ARCHITECTURE                              │
│                                                                          │
│  ┌──────────────┐      HTTP Request         ┌─────────────────────┐    │
│  │   Browser    │ ──────────────────────▶  │  SetUserLocale      │    │
│  │  (AR or EN)  │                           │  Middleware          │    │
│  └──────────────┘                           │  reads users.locale │    │
│         │                                   └────────┬────────────┘    │
│         │                                            │                  │
│         │                                   App::setLocale(locale)     │
│         │                                            │                  │
│         │                                   ┌────────▼────────────┐    │
│         │                                   │ HandleInertia       │    │
│         │                                   │ shares: locale, dir │    │
│         │                                   └────────┬────────────┘    │
│         │                                            │                  │
│         │ ◀──── Inertia page + {locale, dir} ───────┘                  │
│         │                                                               │
│         ▼                                                               │
│  ┌──────────────┐                                                       │
│  │  vue-i18n    │  $t('nav.dashboard') → "Dashboard" or "الرئيسية"    │
│  │  + useLocale │  :dir="dir"  →  "ltr" or "rtl"                      │
│  └──────────────┘                                                       │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                   QUEUE WORKERS (No HTTP Context)               │   │
│  │                                                                 │   │
│  │  SendWhatsAppMessageJob                                         │   │
│  │    └─▶ WhatsAppService                                          │   │
│  │          └─▶ Workspace::find(id)->whatsapp_message_language     │   │
│  │                └─▶ WhatsAppMessageLanguage::from(lang)          │   │
│  │                      └─▶ templateName = 'rent_due_reminder_ar'  │   │
│  │                                      OR 'rent_due_reminder_en'  │   │
│  │                └─▶ MetaCloudApiGateway::sendTemplate(           │   │
│  │                        phone, templateName, params, langCode    │   │
│  │                    )                                            │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

> **This plan was produced from a full audit of 88 Vue components, 20+ PHP controllers, 8 queue jobs, the WhatsApp service stack, all existing migration files, and the Inertia middleware layer.**
>
> Implement phase by phase. Never mix infrastructure changes with string extraction PRs — keep them in separate commits to make reviewing and reverting manageable.

<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RenterController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WhatsAppController;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/sitemap.xml', function () {
    $baseUrl = url('/');
    $now = now()->toAtomString();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    $xml .= '<url>';
    $xml .= '<loc>'.$baseUrl.'</loc>';
    $xml .= '<lastmod>'.$now.'</lastmod>';
    $xml .= '<changefreq>daily</changefreq>';
    $xml .= '<priority>1.0</priority>';
    $xml .= '<image:image>';
    $xml .= '<image:loc>'.$baseUrl.'/images/logo.png</image:loc>';
    $xml .= '<image:title>دار — منصة إدارة وتأجير العقارات</image:title>';
    $xml .= '</image:image>';
    $xml .= '</url>';
    $xml .= '<url>';
    $xml .= '<loc>'.$baseUrl.'/login</loc>';
    $xml .= '<changefreq>monthly</changefreq>';
    $xml .= '<priority>0.8</priority>';
    $xml .= '</url>';
    $xml .= '<url>';
    $xml .= '<loc>'.$baseUrl.'/register</loc>';
    $xml .= '<changefreq>monthly</changefreq>';
    $xml .= '<priority>0.9</priority>';
    $xml .= '</url>';
    $xml .= '</urlset>';

    return response($xml, 200, [
        'Content-Type' => 'application/xml; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/workspaces/switch', function (Request $request) {
        $validated = $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);

        $user = $request->user();
        $workspace = Workspace::where('id', $validated['workspace_id'])
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id));
            })
            ->firstOrFail();

        $request->session()->put('active_workspace_id', $workspace->id);
        app(CurrentWorkspace::class)->set($workspace);

        return back()->with('success', 'تم التبديل إلى مساحة العمل: '.$workspace->name);
    })->name('workspaces.switch');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // 04 / العقارات والوحدات
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/create', fn () => redirect()->route('properties.index', ['new' => 1]))->name('properties.create');
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::post('/properties/{property}/units', [PropertyController::class, 'storeUnit'])->name('properties.units.store');

    // 05 / المستأجرون
    Route::get('/renters', [RenterController::class, 'index'])->name('renters.index');
    Route::post('/renters', [RenterController::class, 'store'])->name('renters.store');
    Route::get('/renters/{renter}', [RenterController::class, 'show'])->name('renters.show');
    Route::put('/renters/{renter}', [RenterController::class, 'update'])->name('renters.update');

    // 06 / العقود
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/leases/check-conflict', [LeaseController::class, 'checkConflict'])->name('leases.check-conflict');
    Route::post('/leases', [LeaseController::class, 'store'])->name('leases.store');

    // 07 / الاستحقاقات والدفعات
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');

    // 08 / الصيانة والمصروفات
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::patch('/maintenance/{maintenance}/status', [MaintenanceController::class, 'updateStatus'])->name('maintenance.update-status');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // 09 / تذكيرات WhatsApp
    Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp/test-send', [WhatsAppController::class, 'testSend'])->name('whatsapp.test-send');

    // 10 / الاشتراك والإعدادات
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

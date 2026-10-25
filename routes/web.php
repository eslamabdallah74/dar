<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RenterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityDepositController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WhatsAppWebhookController;
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
    $xml .= '<image:title>Nook Mate — Find Your Spot. Rent the Space You Need.</image:title>';
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
    Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::post('/properties/{property}/units', [PropertyController::class, 'storeUnit'])->name('properties.units.store');
    Route::put('/properties/{property}/units/{unit}', [PropertyController::class, 'updateUnit'])->name('properties.units.update');
    Route::delete('/properties/{property}/units/{unit}', [PropertyController::class, 'destroyUnit'])->name('properties.units.destroy');

    // 05 / المستأجرون
    Route::get('/renters', [RenterController::class, 'index'])->name('renters.index');
    Route::post('/renters', [RenterController::class, 'store'])->name('renters.store');
    Route::post('/renters/check-phone', [RenterController::class, 'checkPhone'])->name('renters.check-phone');
    Route::get('/renters/{renter}', [RenterController::class, 'show'])->name('renters.show');
    Route::put('/renters/{renter}', [RenterController::class, 'update'])->name('renters.update');
    Route::post('/renters/{renter}/documents', [RenterController::class, 'storeDocument'])->name('renters.documents.store');
    Route::get('/renters/{renter}/documents/{document}/download', [RenterController::class, 'downloadDocument'])->name('renters.documents.download');
    Route::get('/renters/{renter}/id-card/{side}', [RenterController::class, 'viewIdCard'])->where('side', 'front|back')->name('renters.id-card');
    Route::delete('/renters/{renter}/documents/{document}', [RenterController::class, 'destroyDocument'])->name('renters.documents.destroy');

    // 06 / العقود
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/leases/check-conflict', [LeaseController::class, 'checkConflict'])->name('leases.check-conflict');
    Route::post('/leases', [LeaseController::class, 'store'])->name('leases.store');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::get('/leases/{lease}/contract-image', [LeaseController::class, 'viewContractImage'])->name('leases.contract-image');
    Route::post('/leases/{lease}/contract-image', [LeaseController::class, 'uploadContractImage'])->name('leases.contract-image.store');
    Route::delete('/leases/{lease}/contract-image', [LeaseController::class, 'deleteContractImage'])->name('leases.contract-image.destroy');
    Route::post('/leases/{lease}/extend', [LeaseController::class, 'extend'])->name('leases.extend');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');

    // تأمين الوحدة (Security Deposits)
    Route::post('/leases/{lease}/security-deposit/payment', [SecurityDepositController::class, 'recordPayment'])->name('leases.security-deposit.payment');
    Route::post('/leases/{lease}/security-deposit/payments/{payment}/reverse', [SecurityDepositController::class, 'reversePayment'])->name('leases.security-deposit.payment.reverse');
    Route::post('/leases/{lease}/security-deposit/start-settlement', [SecurityDepositController::class, 'startSettlement'])->name('leases.security-deposit.start-settlement');
    Route::post('/leases/{lease}/security-deposit/deductions', [SecurityDepositController::class, 'recordDeduction'])->name('leases.security-deposit.deduction');
    Route::match(['put', 'patch', 'post'], '/leases/{lease}/security-deposit/deductions/{deduction}', [SecurityDepositController::class, 'updateDeduction'])->name('leases.security-deposit.deductions.update');
    Route::delete('/leases/{lease}/security-deposit/deductions/{deduction}', [SecurityDepositController::class, 'deleteDeduction'])->name('leases.security-deposit.deduction.delete');
    Route::get('/leases/{lease}/security-deposit/statement', [SecurityDepositController::class, 'showStatement'])->name('leases.security-deposit.statement');
    Route::get('/leases/{lease}/security-deposit/export', [SecurityDepositController::class, 'export'])->name('leases.security-deposit.export');
    Route::post('/leases/{lease}/security-deposit/refunds', [SecurityDepositController::class, 'recordRefund'])->name('leases.security-deposit.refund');
    Route::post('/leases/{lease}/security-deposit/pending-refund', [SecurityDepositController::class, 'recordPendingRefund'])->name('leases.security-deposit.pending-refund');
    Route::post('/leases/{lease}/security-deposit/settle', [SecurityDepositController::class, 'settle'])->name('leases.security-deposit.settle');

    // 07 / الاستحقاقات والدفعات
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'showReceipt'])->name('payments.receipt');
    Route::get('/payments/{payment}/attachment', [PaymentController::class, 'downloadAttachment'])->name('payments.attachment');
    Route::post('/online-payments/checkout', [OnlinePaymentController::class, 'checkout'])->name('online-payments.checkout');
    Route::get('/online-payments/callback', [OnlinePaymentController::class, 'callback'])->name('online-payments.callback');

    // 08 / الصيانة والمصروفات
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::put('/maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
    Route::patch('/maintenance/{maintenance}/status', [MaintenanceController::class, 'updateStatus'])->name('maintenance.update-status');
    Route::post('/maintenance/{maintenance}/attachments', [MaintenanceController::class, 'storeAttachments'])->name('maintenance.attachments.store');
    Route::get('/maintenance/{maintenance}/attachments/{attachment}/preview', [MaintenanceController::class, 'previewAttachment'])->name('maintenance.attachments.preview');
    Route::get('/maintenance/{maintenance}/attachments/{attachment}/download', [MaintenanceController::class, 'downloadAttachment'])->name('maintenance.attachments.download');
    Route::delete('/maintenance/{maintenance}/attachments/{attachment}', [MaintenanceController::class, 'destroyAttachment'])->name('maintenance.attachments.destroy');
    Route::post('/maintenance/{maintenance}/notes', [MaintenanceController::class, 'storeNote'])->name('maintenance.notes.store');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('/expenses/{expense}/payment', [ExpenseController::class, 'recordPayment'])->name('expenses.payment.record');
    Route::post('/expenses/{expense}/payment/reverse', [ExpenseController::class, 'reversePayment'])->name('expenses.payment.reverse');

    // 09 / تذكيرات WhatsApp
    Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp/test-send', [WhatsAppController::class, 'testSend'])
        ->middleware('throttle:5,1')
        ->name('whatsapp.test-send');

    // 10 / التقارير والتصدير المالي
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/collections/export', [ReportController::class, 'exportCollections'])->name('reports.collections.export');
    Route::get('/reports/payments/export', [ReportController::class, 'exportPayments'])->name('reports.payments.export');
    Route::get('/reports/expenses/export', [ReportController::class, 'exportExpenses'])->name('reports.expenses.export');
    Route::get('/reports/leases/export', [ReportController::class, 'exportLeases'])->name('reports.leases.export');

    // 11 / الاشتراك والإعدادات
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// -------------------------------------------------------------
// Platform Central Admin Panel Routes (لوحة الإدارة المركزية)
// -------------------------------------------------------------
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Guest Auth Routes
    Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');

    // Admin Protected Routes
    Route::middleware(['admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Control Actions
        Route::post('/workspaces/{workspace}/impersonate', [AdminDashboardController::class, 'impersonateWorkspace'])->name('workspaces.impersonate');
        Route::put('/workspaces/{workspace}', [AdminDashboardController::class, 'updateWorkspace'])->name('workspaces.update');
        Route::delete('/workspaces/{workspace}', [AdminDashboardController::class, 'destroyWorkspace'])->name('workspaces.destroy');

        Route::post('/users', [AdminDashboardController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [AdminDashboardController::class, 'updateUser'])->name('users.update');
        Route::patch('/users/{user}/toggle-admin', [AdminDashboardController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::delete('/users/{user}', [AdminDashboardController::class, 'destroyUser'])->name('users.destroy');

        Route::post('/workspaces/{workspace}/subscription', [AdminDashboardController::class, 'updateSubscription'])->name('workspaces.subscription');
        Route::patch('/leases/{lease}/status', [AdminDashboardController::class, 'updateLeaseStatus'])->name('leases.status');
        Route::patch('/maintenance/{maintenance}/status', [AdminDashboardController::class, 'updateMaintenanceStatus'])->name('maintenance.status');
    });
});

// Meta WhatsApp Cloud API Official Webhooks
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp.handle');

// Payment Gateway Official Webhook
Route::post('/webhooks/payments', [OnlinePaymentController::class, 'webhook'])->name('webhooks.payments');

require __DIR__.'/auth.php';

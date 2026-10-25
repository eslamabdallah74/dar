<?php

use App\Contracts\WhatsAppGatewayInterface;
use App\Jobs\BaseJob;
use App\Jobs\CheckOverdueRentChargesJob;
use App\Jobs\DataCleanupJob;
use App\Jobs\FinalizeSettlementJob;
use App\Jobs\GenerateAnalyticsReportJob;
use App\Jobs\GenerateRentChargesJob;
use App\Jobs\LeaseExpiryReminderJob;
use App\Jobs\NotifyMaintenanceRequestCreatedJob;
use App\Jobs\ProcessDepositDeductionJob;
use App\Jobs\ProcessDepositPaymentJob;
use App\Jobs\ProcessDepositRefundJob;
use App\Mail\GenericNotificationMail;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ReportService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار السحابي',
        'slug' => 'dar-queue-test-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النيل السكني',
        'property_type' => 'building',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'code' => '101',
        'name' => 'شقة 101 فاخرة',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'أحمد إبراهيم',
        'phone' => '+201012345678',
        'email' => 'ahmed.renter@example.com',
        'national_id' => '29001011234567',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => Carbon::now()->subMonths(2)->startOfMonth(),
        'ends_on' => Carbon::now()->addDays(30),
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'status' => 'active',
    ]);
});

test('base job defines default retry, backoff, and without overlapping middleware', function () {
    $job = new class extends BaseJob
    {
        public function uniqueId(): string
        {
            return 'custom-test-lock-id';
        }

        public function handle(): void {}
    };

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(120);
    expect($job->backoff())->toBe([5, 30, 120]);

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

test('whatsapp gateway interface is properly bound and sends template messages', function () {
    $gateway = app(WhatsAppGatewayInterface::class);
    expect($gateway)->toBeInstanceOf(WhatsAppGatewayInterface::class);

    $result = $gateway->sendTemplate('+201012345678', 'payment_receipt_instant_ar', ['5000']);
    expect($result['success'])->toBeTrue();
    expect($result['status'])->toBeIn(['sent', 'delivered']);
});

test('whatsapp service enforces strict idempotency using deduplication key', function () {
    $service = app(WhatsAppService::class);
    $dedupKey = 'test_key_'.uniqid();

    $log1 = $service->send(
        workspaceId: $this->workspace->id,
        renterId: $this->renter->id,
        phone: $this->renter->phone,
        templateName: 'rent_due_reminder_ar',
        parameters: ['name' => 'أحمد', 'amount' => '5,000'],
        deduplicationKey: $dedupKey
    );

    expect($log1)->not->toBeNull();
    expect($log1->status)->toBeIn(['sent', 'delivered']);

    // Second call with same deduplication key must not create a duplicate log
    $log2 = $service->send(
        workspaceId: $this->workspace->id,
        renterId: $this->renter->id,
        phone: $this->renter->phone,
        templateName: 'rent_due_reminder_ar',
        parameters: ['name' => 'أحمد', 'amount' => '5,000'],
        deduplicationKey: $dedupKey
    );

    expect($log2->id)->toBe($log1->id);
    expect(MessageLog::where('deduplication_key', $dedupKey)->count())->toBe(1);
});

test('whatsapp service respects renter opt out', function () {
    $optedOutRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر ملغي الاشتراك',
        'phone' => '+201199999999',
        'whatsapp_opted_out_at' => now(),
    ]);

    $service = app(WhatsAppService::class);
    $log = $service->send(
        workspaceId: $this->workspace->id,
        renterId: $optedOutRenter->id,
        phone: $optedOutRenter->phone,
        templateName: 'rent_due_reminder_ar',
        parameters: [],
        deduplicationKey: 'opt_out_test_'.uniqid()
    );

    expect($log)->toBeNull();
});

test('generate rent charges job creates rent charge idempotently without duplicate charges', function () {
    $today = Carbon::today();
    $periodStart = $today->copy()->startOfMonth()->toDateString();

    // Run job first time
    $job = new GenerateRentChargesJob($today);
    $job->handle();

    $chargesCount = RentCharge::where('lease_id', $this->lease->id)
        ->where('period_start', $periodStart)
        ->count();

    expect($chargesCount)->toBe(1);

    // Run job second time - must NOT duplicate
    $job->handle();

    $chargesCountAfterSecondRun = RentCharge::where('lease_id', $this->lease->id)
        ->where('period_start', $periodStart)
        ->count();

    expect($chargesCountAfterSecondRun)->toBe(1);
});

test('check overdue rent charges job identifies unpaid charges and dispatches reminders', function () {
    Mail::fake();

    // Create an overdue charge
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => Carbon::now()->subMonths(1)->startOfMonth(),
        'period_end' => Carbon::now()->subMonths(1)->endOfMonth(),
        'due_date' => Carbon::now()->subDays(5),
        'amount' => 5000.00,
    ]);

    $job = new CheckOverdueRentChargesJob;
    $job->handle(app(WhatsAppService::class));

    // Must have sent email
    Mail::assertSent(GenericNotificationMail::class);
});

test('lease expiry reminder job triggers notifications for 30 day milestone', function () {
    Mail::fake();

    $this->lease->update([
        'ends_on' => Carbon::today()->addDays(30),
    ]);

    $job = new LeaseExpiryReminderJob;
    $job->handle(app(WhatsAppService::class));

    Mail::assertSent(GenericNotificationMail::class);
});

test('recording security deposit payment dispatches process deposit payment job', function () {
    Bus::fake([ProcessDepositPaymentJob::class]);

    $this->actingAs($this->user);

    $response = $this->post(route('leases.security-deposit.payment', $this->lease), [
        'amount' => 5000.00,
        'payment_method' => 'cash',
        'reference' => 'TEST-DEP-001',
    ]);

    $response->assertSessionHasNoErrors();
    Bus::assertDispatched(ProcessDepositPaymentJob::class);
});

test('process deposit payment job sends receipts without errors', function () {
    Mail::fake();

    $payment = SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-JOB-01',
    ]);

    $job = new ProcessDepositPaymentJob($payment->id);
    $job->handle(app(WhatsAppService::class));

    Mail::assertSent(GenericNotificationMail::class);
});

test('process deposit deduction job sends notification email', function () {
    Mail::fake();

    $deduction = SecurityDepositDeduction::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 800.00,
        'category' => 'fixture_damage',
        'description' => 'إصلاح مقبض الباب المكسور',
        'status' => 'confirmed',
        'deducted_at' => now(),
    ]);

    $job = new ProcessDepositDeductionJob($deduction->id);
    $job->handle();

    Mail::assertSent(GenericNotificationMail::class);
});

test('process deposit refund job sends notification email', function () {
    Mail::fake();

    $refund = SecurityDepositRefund::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 4000.00,
        'refunded_at' => now(),
        'payment_method' => 'bank_transfer',
        'reference' => 'REF-JOB-01',
    ]);

    $job = new ProcessDepositRefundJob($refund->id);
    $job->handle();

    Mail::assertSent(GenericNotificationMail::class);
});

test('finalize settlement job dispatches final breakdown statement', function () {
    Mail::fake();

    $settlement = SecurityDepositSettlement::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'settled_by' => $this->user->id,
        'status' => 'settled',
        'inspection_category' => 'no_issues',
        'total_received' => 10000.00,
        'total_deductions' => 0.00,
        'total_refunded' => 10000.00,
        'refundable_balance' => 0.00,
        'settled_at' => now(),
    ]);

    $job = new FinalizeSettlementJob($settlement->id);
    $job->handle();

    Mail::assertSent(GenericNotificationMail::class);
});

test('notify maintenance request created job alerts property owner', function () {
    Mail::fake();

    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'reported_by_renter_id' => $this->renter->id,
        'title' => 'تسريب مياه في الحمام',
        'description' => 'يوجد تسريب في صنبور المياه الرئيسي',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    $job = new NotifyMaintenanceRequestCreatedJob($maintenance->id);
    $job->handle();

    Mail::assertSent(GenericNotificationMail::class);
});

test('generate analytics report job computes workspace metrics', function () {
    $reportService = app(ReportService::class);
    $job = new GenerateAnalyticsReportJob('occupancy', $this->workspace->id);
    $job->handle($reportService);

    $summary = $reportService->generate('occupancy', $this->workspace->id);
    expect($summary['metrics']['total_properties'])->toBeGreaterThanOrEqual(1);
    expect($summary['metrics']['total_units'])->toBeGreaterThanOrEqual(1);
    expect($summary['metrics']['occupancy_rate_percent'])->toBeGreaterThanOrEqual(0);
});

test('data cleanup job purges old delivered logs without errors', function () {
    // Old delivered log
    MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recipient_phone' => $this->renter->phone,
        'template_name' => 'test',
        'scheduled_at' => now()->subDays(100),
        'sent_at' => now()->subDays(100),
        'status' => 'delivered',
        'deduplication_key' => 'cleanup_test_'.uniqid(),
        'created_at' => now()->subDays(100),
    ]);

    $job = new DataCleanupJob;
    $job->handle();

    expect(MessageLog::where('created_at', '<', now()->subDays(90))->count())->toBe(0);
});

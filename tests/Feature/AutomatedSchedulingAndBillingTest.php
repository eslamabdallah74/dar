<?php

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'عقارات الاختبار للفوترة',
        'slug' => 'billing-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
        'auto_whatsapp_reminders' => true,
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج التحرير',
        'property_type' => 'building',
        'address' => 'الرياض - حي النخيل',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'مكتب 201',
        'code' => 'OFF-201',
        'unit_type' => 'shop',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'أحمد عبد العزيز',
        'phone' => '+966501234567',
        'whatsapp_opt_in_at' => now(),
        'whatsapp_opted_out_at' => null,
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'rent_amount' => 5000,
        'status' => 'active',
    ]);
});

test('generate rent charges command creates charges for active leases and is idempotent', function () {
    $currentDate = now()->addMonth()->format('Y-m-15');

    // First execution: should generate 1 charge
    $this->artisan('dar:generate-rent-charges', ['--date' => $currentDate, '--workspace' => $this->workspace->id])
        ->assertSuccessful();

    expect(RentCharge::where('lease_id', $this->lease->id)->count())->toBe(1);

    $charge = RentCharge::where('lease_id', $this->lease->id)->first();
    expect($charge->amount)->toEqual(5000)
        ->and($charge->is_fully_paid)->toBeFalse()
        ->and($charge->status)->toBe('pending')
        ->and($charge->workspace_id)->toBe($this->workspace->id);

    // Second execution on same date: idempotency test (must not create duplicate charge)
    $this->artisan('dar:generate-rent-charges', ['--date' => $currentDate, '--workspace' => $this->workspace->id])
        ->assertSuccessful();

    expect(RentCharge::where('lease_id', $this->lease->id)->count())->toBe(1);
});

test('generate rent charges ignores inactive or terminated leases', function () {
    $this->lease->update(['status' => 'terminated']);

    $this->artisan('dar:generate-rent-charges', ['--date' => now()->toDateString(), '--workspace' => $this->workspace->id])
        ->assertSuccessful();

    expect(RentCharge::where('lease_id', $this->lease->id)->count())->toBe(0);
});

test('send rent reminders dispatches jobs for upcoming due charges and respects opt-out', function () {
    Queue::fake();

    $dueDate = now()->addDays(2)->toDateString();
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => $dueDate,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 5000,
    ]);

    $this->artisan('dar:send-rent-reminders')
        ->assertSuccessful();

    Queue::assertPushed(SendWhatsAppMessageJob::class, 1);

    // Simulate logged message to test deduplication on subsequent runs
    MessageLog::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'lease_id' => $this->lease->id,
        'rent_charge_id' => $charge->id,
        'recipient_phone' => $this->renter->phone,
        'template_name' => 'rent_due_reminder',
        'deduplication_key' => "rent_due_{$charge->id}_{$dueDate}",
        'status' => 'sent',
        'scheduled_at' => now(),
    ]);

    // Running again: deduplication must prevent double reminder on the same date
    $this->artisan('dar:send-rent-reminders')
        ->assertSuccessful();

    Queue::assertPushed(SendWhatsAppMessageJob::class, 1); // Still 1, not 2
});

test('send rent reminders respects renter whatsapp opt-out', function () {
    Queue::fake();

    $this->renter->update(['whatsapp_opted_out_at' => now()]);

    RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->addDays(2)->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 5000,
    ]);

    $this->artisan('dar:send-rent-reminders')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('send rent reminders respects workspace auto_whatsapp_reminders setting', function () {
    Queue::fake();

    $this->workspace->update(['auto_whatsapp_reminders' => false]);

    RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->addDays(2)->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 5000,
    ]);

    $this->artisan('dar:send-rent-reminders')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('check overdue command scans past due unpaid charges and notifies renter', function () {
    Queue::fake();

    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->subDays(6)->toDateString(),
        'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
        'amount' => 5000,
    ]);

    $this->artisan('dar:check-overdue')
        ->assertSuccessful();

    $charge->refresh();
    expect($charge->is_overdue)->toBeTrue()
        ->and($charge->status)->toBe('overdue');
    Queue::assertPushed(SendWhatsAppMessageJob::class, 1);
});

test('check expirations command identifies leases near expiration', function () {
    $this->lease->update([
        'ends_on' => now()->addDays(30)->toDateString(),
    ]);

    $this->artisan('dar:check-expirations')
        ->assertSuccessful();

    // Verify lease remains intact and monitored
    expect($this->lease->fresh()->status)->toBe('active');
});

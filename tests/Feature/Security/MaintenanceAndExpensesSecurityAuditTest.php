<?php

use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    // Workspace A (Owner A)
    $this->userA = User::factory()->create(['name' => 'المالك أ']);
    $this->workspaceA = Workspace::create([
        'name' => 'مساحة العمل أ',
        'slug' => 'ws-a-'.$this->userA->id,
        'owner_user_id' => $this->userA->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspaceA->id,
        'user_id' => $this->userA->id,
        'role' => 'owner',
    ]);

    // Workspace B (Owner B)
    $this->userB = User::factory()->create(['name' => 'المالك ب']);
    $this->workspaceB = Workspace::create([
        'name' => 'مساحة العمل ب',
        'slug' => 'ws-b-'.$this->userB->id,
        'owner_user_id' => $this->userB->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->userB->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::firstOrCreate(
        ['slug' => 'all-inclusive'],
        [
            'name' => 'الباقة الشاملة',
            'monthly_price' => 299.00,
            'unit_limit' => 50,
            'is_active' => true,
        ]
    );

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceA->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->userA->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceB->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->userB->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspaceA);

    $this->propertyA = Property::create([
        'workspace_id' => $this->workspaceA->id,
        'name' => 'عقار أ',
        'property_type' => 'building',
    ]);

    $this->unitA1 = Unit::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'name' => 'شقة أ-1',
        'unit_type' => 'apartment',
        'rent_amount' => 6000,
        'status' => 'occupied',
    ]);

    $this->propertyA2 = Property::create([
        'workspace_id' => $this->workspaceA->id,
        'name' => 'عقار أ-2',
        'property_type' => 'building',
    ]);

    $this->unitA2 = Unit::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA2->id,
        'name' => 'شقة أ-2-1',
        'unit_type' => 'apartment',
        'rent_amount' => 7000,
        'status' => 'occupied',
    ]);

    // Setup entities in Workspace B
    app(CurrentWorkspace::class)->set($this->workspaceB);

    $this->propertyB = Property::create([
        'workspace_id' => $this->workspaceB->id,
        'name' => 'عقار ب',
        'property_type' => 'building',
    ]);

    $this->unitB1 = Unit::create([
        'workspace_id' => $this->workspaceB->id,
        'property_id' => $this->propertyB->id,
        'name' => 'شقة ب-1',
        'unit_type' => 'apartment',
        'rent_amount' => 8000,
        'status' => 'occupied',
    ]);

    $this->maintenanceB = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceB->id,
        'property_id' => $this->propertyB->id,
        'unit_id' => $this->unitB1->id,
        'title' => 'عطل خاص بالمساحة ب',
        'description' => 'وصف خاص بالمساحة ب',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $this->expenseB = Expense::create([
        'workspace_id' => $this->workspaceB->id,
        'property_id' => $this->propertyB->id,
        'unit_id' => $this->unitB1->id,
        'maintenance_request_id' => $this->maintenanceB->id,
        'category' => 'صيانة وتشغيل',
        'title' => $this->maintenanceB->title,
        'amount' => 500.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'payment_status' => 'unpaid',
        'recorded_by' => $this->userB->id,
    ]);

    $fileB = UploadedFile::fake()->image('b_secret.png');
    $pathB = $fileB->storeAs("maintenance/{$this->workspaceB->id}/{$this->maintenanceB->id}", 'b_secret.png', 'local');

    $this->attachmentB = $this->maintenanceB->attachments()->create([
        'workspace_id' => $this->workspaceB->id,
        'file_path' => $pathB,
        'original_filename' => 'b_secret.png',
        'mime_type' => 'image/png',
        'file_size' => 1024,
        'uploaded_by' => $this->userB->id,
    ]);

    $this->noteB = $this->maintenanceB->notes()->create([
        'workspace_id' => $this->workspaceB->id,
        'note' => 'ملاحظة سرية للمالك ب',
        'created_by' => $this->userB->id,
    ]);

    // Reset back to Workspace A as default
    app(CurrentWorkspace::class)->set($this->workspaceA);
});

test('user cannot access or manipulate another owners maintenance request across any endpoint', function () {
    // 1. Show endpoint
    $res = $this->actingAs($this->userA)->getJson(route('maintenance.show', $this->maintenanceB->id));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 2. Update endpoint
    $res = $this->actingAs($this->userA)->put(route('maintenance.update', $this->maintenanceB->id), [
        'property_id' => $this->propertyA->id,
        'title' => 'محاولة اختراق التعديل',
    ]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 3. Status update endpoint
    $res = $this->actingAs($this->userA)->patch(route('maintenance.update-status', $this->maintenanceB->id), [
        'status' => 'completed',
    ]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 4. Attachments upload endpoint
    $photo = UploadedFile::fake()->image('hacker.jpg');
    $res = $this->actingAs($this->userA)->post(route('maintenance.attachments.store', $this->maintenanceB->id), [
        'files' => [$photo],
    ]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 5. Notes store endpoint
    $res = $this->actingAs($this->userA)->post(route('maintenance.notes.store', $this->maintenanceB->id), [
        'note' => 'ملاحظة غير مصرح بها',
    ]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 6. Attachment preview & download
    $res = $this->actingAs($this->userA)->get(route('maintenance.attachments.preview', [$this->maintenanceB->id, $this->attachmentB->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $this->actingAs($this->userA)->get(route('maintenance.attachments.download', [$this->maintenanceB->id, $this->attachmentB->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 7. Attachment deletion
    $res = $this->actingAs($this->userA)->delete(route('maintenance.attachments.destroy', [$this->maintenanceB->id, $this->attachmentB->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Verify attachment still exists on disk
    expect(Storage::disk('local')->exists($this->attachmentB->file_path))->toBeTrue();
});

test('user cannot update or record payment on another owners expense', function () {
    // 1. Update expense
    $res = $this->actingAs($this->userA)->put(route('expenses.update', $this->expenseB->id), [
        'amount' => 9999.00,
        'category' => 'احتيال',
    ]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 2. Record payment on expense
    $res = $this->actingAs($this->userA)->post(route('expenses.payment.record', $this->expenseB->id));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Verify expense in B was not altered
    $this->expenseB->refresh();
    expect((float) $this->expenseB->amount)->toBe(500.00)
        ->and($this->expenseB->payment_status)->toBe('unpaid');
});

test('property and unit relationship is strictly enforced preventing unit property mismatches', function () {
    // Attempting to create ticket with Property A and Unit A2 (which belongs to Property A2, NOT Property A)
    $response = $this->actingAs($this->userA)->post(route('maintenance.store'), [
        'property_id' => $this->propertyA->id,
        'unit_id' => $this->unitA2->id,
        'title' => 'طلب صيانة مع وحدة لا تنتمي للعقار',
    ]);

    $response->assertSessionHasErrors('unit_id');
    expect(MaintenanceRequest::where('title', 'طلب صيانة مع وحدة لا تنتمي للعقار')->count())->toBe(0);

    // Attempting to create ticket with Property B (from another workspace)
    $response = $this->actingAs($this->userA)->post(route('maintenance.store'), [
        'property_id' => $this->propertyB->id,
        'title' => 'طلب صيانة مع عقار من مساحة أخرى',
    ]);

    $response->assertSessionHasErrors('property_id');
});

test('model guards prevent unlinking or altering maintenance_request_id and origin on expenses', function () {
    $maintenanceA = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'unit_id' => $this->unitA1->id,
        'title' => 'عطل تكييف',
        'description' => 'التكييف معطل',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $expenseA = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'unit_id' => $this->unitA1->id,
        'maintenance_request_id' => $maintenanceA->id,
        'category' => 'صيانة وتشغيل',
        'title' => 'عطل تكييف',
        'amount' => null,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'recorded_by' => $this->userA->id,
    ]);

    // Attempting to change maintenance_request_id to null throws DomainException
    expect(fn () => $expenseA->update(['maintenance_request_id' => null]))
        ->toThrow(DomainException::class, 'لا يمكن فك أو تغيير ربط المصروف بطلب الصيانة بعد إنشائه.');

    $expenseA->refresh();

    // Attempting to change origin throws DomainException
    expect(fn () => $expenseA->update(['origin' => 'manual']))
        ->toThrow(DomainException::class, 'لا يمكن تعديل مصدر المصروف بعد تسجيله.');
});

test('deleting an expense linked to maintenance is blocked by model guard', function () {
    $maintenanceA = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'title' => 'سباكة الممر',
        'description' => 'تسريب',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $expenseA = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'maintenance_request_id' => $maintenanceA->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenanceA->title,
        'amount' => 200.0,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'recorded_by' => $this->userA->id,
    ]);

    expect(fn () => $expenseA->delete())
        ->toThrow(DomainException::class, 'لا يمكن حذف مصروف مرتبط بطلب صيانة نشط');
});

test('deleting a maintenance request with active financial expense is blocked', function () {
    $maintenanceA = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'title' => 'إصلاح واجهة',
        'description' => 'دهانات',
        'status' => 'completed',
        'reported_at' => now(),
    ]);

    Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'maintenance_request_id' => $maintenanceA->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenanceA->title,
        'amount' => 1500.0,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'recorded',
        'recorded_by' => $this->userA->id,
    ]);

    expect(fn () => $maintenanceA->delete())
        ->toThrow(DomainException::class, 'لا يمكن حذف طلب صيانة مسجل له تكلفة مالية');
});

test('user cannot record payment on an expense before an amount is recorded', function () {
    $maintenanceA = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'title' => 'صيانة بلا تكلفة',
        'description' => 'فارغة',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $expenseA = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'maintenance_request_id' => $maintenanceA->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenanceA->title,
        'amount' => null, // No amount
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'payment_status' => 'unpaid',
        'recorded_by' => $this->userA->id,
    ]);

    $response = $this->actingAs($this->userA)
        ->post(route('expenses.payment.record', $expenseA->id));

    $response->assertSessionHasErrors('amount');
    $expenseA->refresh();
    expect($expenseA->isPaid())->toBeFalse();
});

test('user can record and reverse payment on an expense with valid amount', function () {
    $expenseA = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'category' => 'صيانة وتشغيل',
        'title' => 'فاتورة سباكة مدفوعة',
        'amount' => 450.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'recorded',
        'payment_status' => 'unpaid',
        'recorded_by' => $this->userA->id,
    ]);

    // Record payment
    $response = $this->actingAs($this->userA)
        ->post(route('expenses.payment.record', $expenseA->id));

    $response->assertSessionHas('success');
    $expenseA->refresh();
    expect($expenseA->payment_status)->toBe('paid')
        ->and($expenseA->paid_at)->not->toBeNull()
        ->and($expenseA->isPaid())->toBeTrue();

    // Reverse payment
    $response = $this->actingAs($this->userA)
        ->post(route('expenses.payment.reverse', $expenseA->id));

    $response->assertSessionHas('success');
    $expenseA->refresh();
    expect($expenseA->payment_status)->toBe('unpaid')
        ->and($expenseA->paid_at)->toBeNull()
        ->and($expenseA->isPaid())->toBeFalse();
});

test('deleting an expense endpoint blocks linked maintenance expenses and permits manual expenses', function () {
    // 1. Linked maintenance expense cannot be deleted via endpoint
    $maintenanceA = MaintenanceRequest::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'title' => 'عطل إضاءة',
        'description' => 'إضاءة',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $linkedExpense = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'maintenance_request_id' => $maintenanceA->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenanceA->title,
        'amount' => 100.0,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'recorded_by' => $this->userA->id,
    ]);

    $response = $this->actingAs($this->userA)
        ->delete(route('expenses.destroy', $linkedExpense->id));

    $response->assertSessionHasErrors('expense');
    expect(Expense::find($linkedExpense->id))->not->toBeNull();

    // 2. Manual expense can be deleted by its owner
    $manualExpense = Expense::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->propertyA->id,
        'category' => 'أدوات نظافة',
        'title' => 'شراء مساحيق',
        'amount' => 150.0,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'recorded',
        'recorded_by' => $this->userA->id,
    ]);

    $response = $this->actingAs($this->userA)
        ->delete(route('expenses.destroy', $manualExpense->id));

    $response->assertSessionHas('success');
    expect(Expense::find($manualExpense->id))->toBeNull();

    // 3. User A cannot delete User B's manual expense
    app(CurrentWorkspace::class)->set($this->workspaceB);
    $bManualExpense = Expense::create([
        'workspace_id' => $this->workspaceB->id,
        'property_id' => $this->propertyB->id,
        'category' => 'أدوات مكتبية',
        'title' => 'أوراق طابعة',
        'amount' => 200.0,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'recorded',
        'recorded_by' => $this->userB->id,
    ]);

    app(CurrentWorkspace::class)->set($this->workspaceA);
    $response = $this->actingAs($this->userA)
        ->delete(route('expenses.destroy', $bManualExpense->id));

    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
    expect(Expense::withoutGlobalScopes()->find($bManualExpense->id))->not->toBeNull();
});

<?php

use App\Models\Expense;
use App\Models\MaintenanceAttachment;
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
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار والمرفقات',
        'slug' => 'dar-maint-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
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
        'workspace_id' => $this->workspace->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->user->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النرجس',
        'property_type' => 'building',
        'address' => 'التجمع الخامس، القاهرة',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'unit_type' => 'apartment',
        'rent_amount' => 5000,
        'status' => 'occupied',
    ]);
});

test('maintenance details endpoint returns complete structured json with attachments notes and history', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح تسريب مياه الحمام الرئيسي',
        'description' => 'تسريب تحت المغسلة بحاجة إلى سباك متخصص',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => 350.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'payment_status' => 'unpaid',
        'recorded_by' => $this->user->id,
    ]);

    $file = UploadedFile::fake()->image('damage.jpg');
    $path = $file->storeAs("maintenance/{$this->workspace->id}/{$maintenance->id}", 'damage.jpg', 'local');

    $attachment = $maintenance->attachments()->create([
        'workspace_id' => $this->workspace->id,
        'file_path' => $path,
        'original_filename' => 'damage.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'uploaded_by' => $this->user->id,
    ]);

    $note = $maintenance->notes()->create([
        'workspace_id' => $this->workspace->id,
        'note' => 'تم الاتفاق مع السباك على الحضور غدًا صباحًا',
        'created_by' => $this->user->id,
    ]);

    activity('maintenance')
        ->performedOn($maintenance)
        ->causedBy($this->user)
        ->withProperties(['test' => true])
        ->log('تم تسجيل فحص أولي للمشكلة');

    $response = $this->actingAs($this->user)
        ->getJson(route('maintenance.show', $maintenance->id));

    $response->assertOk()
        ->assertJson([
            'id' => $maintenance->id,
            'title' => 'إصلاح تسريب مياه الحمام الرئيسي',
            'property_name' => 'برج النرجس',
            'unit_name' => 'شقة 101',
            'expense' => [
                'id' => $expense->id,
                'amount' => 350.0,
                'has_amount' => true,
                'payment_status' => 'unpaid',
            ],
        ]);

    $json = $response->json();
    expect($json['attachments'])->toHaveCount(1)
        ->and($json['attachments'][0]['original_filename'])->toBe('damage.jpg')
        ->and($json['attachments'][0]['is_image'])->toBeTrue()
        ->and($json['notes'])->toHaveCount(1)
        ->and($json['notes'][0]['note'])->toBe('تم الاتفاق مع السباك على الحضور غدًا صباحًا')
        ->and(count($json['activities']))->toBeGreaterThanOrEqual(1);
});

test('user can upload valid photos and pdf attachments to an existing maintenance request', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح المكيف',
        'description' => 'المكيف لا يبرد',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $photo = UploadedFile::fake()->image('aircon.png');
    $invoice = UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->post(route('maintenance.attachments.store', $maintenance->id), [
            'files' => [$photo, $invoice],
        ]);

    $response->assertSessionHasNoErrors();
    expect($maintenance->attachments()->count())->toBe(2);

    $attachments = $maintenance->attachments()->get();
    $imageAtt = $attachments->firstOrFail('original_filename', 'aircon.png');
    $pdfAtt = $attachments->firstOrFail('original_filename', 'invoice.pdf');

    expect($imageAtt->isImage())->toBeTrue()
        ->and(Storage::disk('local')->exists($imageAtt->file_path))->toBeTrue()
        ->and($pdfAtt->isPdf())->toBeTrue()
        ->and(Storage::disk('local')->exists($pdfAtt->file_path))->toBeTrue();

    // Verify activity logged
    $activity = Activity::where('subject_type', MaintenanceRequest::class)
        ->where('subject_id', $maintenance->id)
        ->where('description', 'like', '%تم رفع مرفق جديد%')
        ->first();
    expect($activity)->not->toBeNull();
});

test('attachment validation rejects disallowed file types and files exceeding size limit', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'فحص كهرباء',
        'description' => 'فحص القواطع الكهربائية',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    // Disallowed extension/mime (.txt and script)
    $badFile = UploadedFile::fake()->create('malicious.sh', 10, 'application/x-sh');

    $response = $this->actingAs($this->user)
        ->post(route('maintenance.attachments.store', $maintenance->id), [
            'files' => [$badFile],
        ]);

    $response->assertSessionHasErrors(['files.0']);
    expect($maintenance->attachments()->count())->toBe(0);

    // File exceeding 10MB
    $oversizedFile = UploadedFile::fake()->create('huge_doc.pdf', 11000, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->post(route('maintenance.attachments.store', $maintenance->id), [
            'files' => [$oversizedFile],
        ]);

    $response->assertSessionHasErrors(['files.0']);
    expect($maintenance->attachments()->count())->toBe(0);
});

test('user can preview and download authorized attachment with proper headers', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة مصعد',
        'description' => 'صيانة دورية للمصعد',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $file = UploadedFile::fake()->image('elevator.jpg');
    $path = $file->storeAs("maintenance/{$this->workspace->id}/{$maintenance->id}", 'elevator.jpg', 'local');

    $attachment = $maintenance->attachments()->create([
        'workspace_id' => $this->workspace->id,
        'file_path' => $path,
        'original_filename' => 'elevator.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
        'uploaded_by' => $this->user->id,
    ]);

    // Preview
    $previewResponse = $this->actingAs($this->user)
        ->get(route('maintenance.attachments.preview', [$maintenance->id, $attachment->id]));

    $previewResponse->assertOk();
    expect($previewResponse->headers->get('content-type'))->toBe('image/jpeg');

    // Download
    $downloadResponse = $this->actingAs($this->user)
        ->get(route('maintenance.attachments.download', [$maintenance->id, $attachment->id]));

    $downloadResponse->assertOk();
    expect($downloadResponse->headers->get('content-disposition'))->toContain('attachment; filename=elevator.jpg');
});

test('unauthorized user cannot preview download or delete attachments belonging to another workspace', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة أخرى غير مصرح لها',
        'slug' => 'other-ws-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);

    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة خاصة',
        'description' => 'وصف صيانة خاصة',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $file = UploadedFile::fake()->image('private.jpg');
    $directory = "maintenance/{$this->workspace->id}/{$maintenance->id}";
    $file->storeAs($directory, 'private.jpg', 'local');
    $path = "{$directory}/private.jpg";

    $attachment = $maintenance->attachments()->create([
        'workspace_id' => $this->workspace->id,
        'file_path' => $path,
        'original_filename' => 'private.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'uploaded_by' => $this->user->id,
    ]);

    // Unauthorized user switches to other workspace
    app(CurrentWorkspace::class)->set($otherWorkspace);

    // Preview blocked (403 or 404 due to workspace scope)
    $res = $this->actingAs($otherUser)
        ->get(route('maintenance.attachments.preview', [$maintenance->id, $attachment->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Download blocked
    $res = $this->actingAs($otherUser)
        ->get(route('maintenance.attachments.download', [$maintenance->id, $attachment->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // Delete blocked
    $res = $this->actingAs($otherUser)
        ->delete(route('maintenance.attachments.destroy', [$maintenance->id, $attachment->id]));
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // File must still exist
    expect(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(MaintenanceAttachment::withoutGlobalScopes()->find($attachment->id))->not->toBeNull();
});

test('user can delete an attachment removing it from disk and database', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة إنارة المدخل',
        'description' => 'استبدال اللمبات التالفة في المدخل',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $file = UploadedFile::fake()->image('bulb.png');
    $directory = "maintenance/{$this->workspace->id}/{$maintenance->id}";
    $file->storeAs($directory, 'bulb.png', 'local');
    $path = "{$directory}/bulb.png";

    $attachment = $maintenance->attachments()->create([
        'workspace_id' => $this->workspace->id,
        'file_path' => $path,
        'original_filename' => 'bulb.png',
        'mime_type' => 'image/png',
        'file_size' => 1024,
        'uploaded_by' => $this->user->id,
    ]);

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $response = $this->actingAs($this->user)
        ->delete(route('maintenance.attachments.destroy', [$maintenance->id, $attachment->id]));

    $response->assertSessionHas('success');
    expect(MaintenanceAttachment::find($attachment->id))->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();

    // Verify activity logged
    $activity = Activity::where('subject_type', MaintenanceRequest::class)
        ->where('subject_id', $maintenance->id)
        ->where('description', 'like', '%تم حذف المرفق%')
        ->first();
    expect($activity)->not->toBeNull();
});

test('user can add progress notes without overwriting description', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'إصلاح سخان المياه',
        'description' => 'الوصف الأصلي: السخان لا يعمل',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('maintenance.notes.store', $maintenance->id), [
            'note' => 'تم شراء قطعة الغيار من المحل المعتمد بتكلفة 180 جنيه',
        ]);

    $response->assertSessionHasNoErrors();

    // Verify original description remains untouched
    $maintenance->refresh();
    expect($maintenance->description)->toBe('الوصف الأصلي: السخان لا يعمل');

    // Verify note is created
    expect($maintenance->notes()->count())->toBe(1);
    $note = $maintenance->notes()->firstOrFail();
    expect($note->note)->toBe('تم شراء قطعة الغيار من المحل المعتمد بتكلفة 180 جنيه')
        ->and($note->created_by)->toBe($this->user->id);

    // Verify activity logged
    $activity = Activity::where('subject_type', MaintenanceRequest::class)
        ->where('subject_id', $maintenance->id)
        ->where('description', 'like', '%تمت إضافة ملاحظة تقدم جديدة%')
        ->first();
    expect($activity)->not->toBeNull();
});

test('creating maintenance request can include initial attachments atomically', function () {
    $photo1 = UploadedFile::fake()->image('leak1.jpg');
    $photo2 = UploadedFile::fake()->image('leak2.png');

    $response = $this->actingAs($this->user)
        ->post(route('maintenance.store'), [
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'title' => 'تسريب ماسورة المطبخ',
            'description' => 'تسريب تحت الحوض',
            'attachments' => [$photo1, $photo2],
        ]);

    $response->assertSessionHasNoErrors();

    $maintenance = MaintenanceRequest::where('title', 'تسريب ماسورة المطبخ')->firstOrFail();
    expect($maintenance->attachments()->count())->toBe(2);

    foreach ($maintenance->attachments as $attachment) {
        expect(Storage::disk('local')->exists($attachment->file_path))->toBeTrue();
    }
});

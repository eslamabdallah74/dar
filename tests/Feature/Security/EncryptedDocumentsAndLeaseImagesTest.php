<?php

use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Services\EncryptedFileService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    // Tenant A
    $this->userA = User::factory()->create();
    $this->workspaceA = Workspace::create([
        'name' => 'شركة الأفق للعقارات',
        'slug' => 'horizon-'.uniqid(),
        'owner_user_id' => $this->userA->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspaceA->id,
        'user_id' => $this->userA->id,
        'role' => 'owner',
    ]);

    // Tenant B
    $this->userB = User::factory()->create();
    $this->workspaceB = Workspace::create([
        'name' => 'شركة الديار العالمية',
        'slug' => 'diyar-'.uniqid(),
        'owner_user_id' => $this->userB->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->userB->id,
        'role' => 'owner',
    ]);

    $plan = Plan::create([
        'name' => 'باقة النمو',
        'slug' => 'growth-'.uniqid(),
        'monthly_price' => 500.00,
        'unit_limit' => 50,
        'is_active' => true,
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceA->id,
        'plan_id' => $plan->id,
        'activated_by' => $this->userA->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspaceA->id,
        'name' => 'برج النيل السكني',
        'property_type' => 'building',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspaceA->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspaceA->id,
        'name' => 'طارق كمال الرفاعي',
        'phone' => '01011223344',
        'national_id' => '29501011234567',
    ]);

    app(CurrentWorkspace::class)->set($this->workspaceA);
});

test('renter ID card images are encrypted on disk and decrypted when served to authorized user', function () {
    $front = UploadedFile::fake()->image('id_front.jpg', 600, 400);
    $back = UploadedFile::fake()->image('id_back.png', 600, 400);

    $this->actingAs($this->userA)->post(route('renters.store'), [
        'name' => 'محمود عزت عبد الله',
        'phone' => '01099887766',
        'national_id' => '29801011234567',
        'id_card_front' => $front,
        'id_card_back' => $back,
    ]);

    $renter = Renter::withoutGlobalScopes()->where('phone', '01099887766')->firstOrFail();

    // Verify files exist in private local disk
    $this->assertNotNull($renter->id_card_front);
    $this->assertNotNull($renter->id_card_back);
    Storage::disk('local')->assertExists($renter->id_card_front);
    Storage::disk('local')->assertExists($renter->id_card_back);

    // Verify stored content is encrypted ciphertext (NOT plaintext image bytes)
    expect(EncryptedFileService::isEncrypted($renter->id_card_front))->toBeTrue();
    expect(EncryptedFileService::isEncrypted($renter->id_card_back))->toBeTrue();

    $rawDiskBytes = Storage::disk('local')->get($renter->id_card_front);
    $this->assertNotNull($rawDiskBytes);
    // Ciphertext contains serialized base64-encoded IV and MAC envelope
    expect(base64_decode($rawDiskBytes, true))->json()->toHaveKeys(['iv', 'value', 'mac'])
        ->and(Crypt::decrypt($rawDiskBytes))->toBe($front->getContent());

    // Verify serving through authorized route decrypts the stream to the client
    $response = $this->actingAs($this->userA)->get(route('renters.id-card', [$renter->id, 'front']));
    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('lease creation allows uploading optional contract image which is encrypted on disk', function () {
    $contractFile = UploadedFile::fake()->image('signed_contract.jpg', 1200, 1600);

    $response = $this->actingAs($this->userA)->post(route('leases.store'), [
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 6000,
        'security_deposit_amount' => 6000,
        'contract_image' => $contractFile,
    ]);

    $response->assertRedirect(route('leases.index'));

    $lease = Lease::where('unit_id', $this->unit->id)->firstOrFail();
    $this->assertNotNull($lease->contract_image);

    // Verify file is stored in local storage and is encrypted
    Storage::disk('local')->assertExists($lease->contract_image);
    expect(EncryptedFileService::isEncrypted($lease->contract_image))->toBeTrue();

    // Verify authorized user can view the contract image
    $viewResponse = $this->actingAs($this->userA)->get(route('leases.contract-image', $lease));
    $viewResponse->assertStatus(200);
    expect($viewResponse->headers->get('Content-Type'))->toContain('image/jpeg');
    expect($viewResponse->headers->get('Cache-Control'))->toContain('no-store');
});

test('cross-workspace user cannot access another tenant encrypted lease contract image (BOLA/IDOR protection)', function () {
    $contractFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

    $this->actingAs($this->userA)->post(route('leases.store'), [
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 5000,
        'contract_image' => $contractFile,
    ]);

    $lease = Lease::where('unit_id', $this->unit->id)->first();

    // Switch to User B in Workspace B
    app(CurrentWorkspace::class)->set($this->workspaceB);

    $crossResponse = $this->actingAs($this->userB)->get(route('leases.contract-image', $lease));
    expect(in_array($crossResponse->status(), [403, 404], true))->toBeTrue();
});

test('authorized user can upload or replace contract image on existing lease', function () {
    $lease = Lease::create([
        'workspace_id' => $this->workspaceA->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 4000,
        'billing_anchor_day' => 1,
        'first_due_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect($lease->contract_image)->toBeNull();

    $newFile = UploadedFile::fake()->image('updated_contract.png', 800, 1000);

    $response = $this->actingAs($this->userA)->post(route('leases.contract-image.store', $lease), [
        'contract_image' => $newFile,
    ]);

    $response->assertRedirect();

    $lease->refresh();
    $this->assertNotNull($lease->contract_image);
    Storage::disk('local')->assertExists($lease->contract_image);
    expect(EncryptedFileService::isEncrypted($lease->contract_image))->toBeTrue();

    // Test deleting the contract image
    $deleteResponse = $this->actingAs($this->userA)->delete(route('leases.contract-image.destroy', $lease));
    $deleteResponse->assertRedirect();

    $lease->refresh();
    expect($lease->contract_image)->toBeNull();
});

test('encrypted file service gracefully handles unencrypted legacy files', function () {
    $plainText = 'Sample legacy contract document plain content';
    $legacyPath = 'leases/legacy/contract_plain.txt';

    Storage::disk('local')->put($legacyPath, $plainText);

    // EncryptedFileService::get should return the plain content without throwing DecryptException
    $retrieved = EncryptedFileService::get($legacyPath);
    expect($retrieved)->toBe($plainText);
    expect(EncryptedFileService::isEncrypted($legacyPath))->toBeFalse();

    // EncryptedFileService::response should stream it cleanly
    $response = EncryptedFileService::response($legacyPath);
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe($plainText);
});

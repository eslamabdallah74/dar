<?php

use App\Models\Renter;
use App\Models\RenterDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'عقارات المستندات',
        'slug' => 'docs-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'سعد الحارثي',
        'phone' => '+966512345678',
        'national_id' => '1029384756',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

test('user can upload a renter document to private storage', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('id_card_scan.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->user)->post(route('renters.documents.store', $this->renter), [
        'title' => 'بطاقة الهوية الوطنية',
        'document_type' => 'national_id',
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(RenterDocument::where('renter_id', $this->renter->id)->count())->toBe(1);

    $doc = RenterDocument::where('renter_id', $this->renter->id)->first();
    expect($doc->title)->toBe('بطاقة الهوية الوطنية')
        ->and($doc->document_type)->toBe('national_id')
        ->and($doc->original_filename)->toBe('id_card_scan.pdf');

    Storage::disk('local')->assertExists($doc->storage_path);
});

test('authorized user can download a renter document', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('contract.pdf', 300, 'application/pdf');
    $path = $file->store('renter_documents/'.$this->workspace->id, 'local');

    $doc = RenterDocument::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'uploaded_by' => $this->user->id,
        'title' => 'عقد الإيجار الموقع',
        'document_type' => 'other',
        'storage_path' => $path,
        'original_filename' => 'contract.pdf',
        'file_size' => 300 * 1024,
    ]);

    $response = $this->actingAs($this->user)->get(route('renters.documents.download', [$this->renter, $doc]));

    $response->assertStatus(200);
    $response->assertHeader('content-disposition');
});

test('unauthorized cross-workspace user cannot download private documents', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('secret_id.pdf', 200, 'application/pdf');
    $path = $file->store('renter_documents/'.$this->workspace->id, 'local');

    $doc = RenterDocument::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'uploaded_by' => $this->user->id,
        'title' => 'مستند خاص',
        'document_type' => 'national_id',
        'storage_path' => $path,
        'original_filename' => 'secret_id.pdf',
    ]);

    // Another user in another workspace
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة منافسة',
        'slug' => 'competitor-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'status' => 'active',
    ]);
    $otherWorkspace->members()->create([
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($otherWorkspace);

    $response = $this->actingAs($otherUser)->get(route('renters.documents.download', [$this->renter, $doc]));

    // Blocked by tenant isolation (404 via global workspace scope or 403 authorization)
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('user can delete a renter document and file is purged from storage', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('old_receipt.pdf', 150, 'application/pdf');
    $path = $file->store('renter_documents/'.$this->workspace->id, 'local');

    $doc = RenterDocument::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'uploaded_by' => $this->user->id,
        'title' => 'إيصال ملغي',
        'document_type' => 'other',
        'storage_path' => $path,
        'original_filename' => 'old_receipt.pdf',
    ]);

    Storage::disk('local')->assertExists($path);

    $response = $this->actingAs($this->user)->delete(route('renters.documents.destroy', [$this->renter, $doc]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(RenterDocument::where('id', $doc->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});

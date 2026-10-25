<?php

use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'شركة الأملاك للعقارات',
        'slug' => 'amlaka-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::create([
        'name' => 'خطة تجريبية',
        'slug' => 'test-plan-'.uniqid(),
        'monthly_price' => 100,
        'unit_limit' => 50,
        'is_active' => true,
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->user->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

test('uploading SVG as property cover image is rejected to prevent stored XSS', function () {
    $svgFile = UploadedFile::fake()->create('xss_attack.svg', 50, 'image/svg+xml');

    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'برج الاختبار الأمني',
        'property_type' => 'building',
        'cover_image_file' => $svgFile,
    ]);

    $response->assertSessionHasErrors('cover_image_file');
    expect(Property::where('name', 'برج الاختبار الأمني')->exists())->toBeFalse();
});

test('uploading valid raster image JPEG or PNG is accepted', function () {
    $imageFile = UploadedFile::fake()->image('safe_cover.jpg');

    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'برج النخبة الآمن',
        'property_type' => 'building',
        'cover_image_file' => $imageFile,
    ]);

    $response->assertSessionHasNoErrors();
    $property = Property::where('name', 'برج النخبة الآمن')->first();
    expect($property)->not->toBeNull();
    expect($property->cover_image)->not->toBeNull();
});

test('updating property with an SVG file is rejected', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج قائم',
        'property_type' => 'building',
    ]);

    $svgFile = UploadedFile::fake()->create('update_xss.svg', 50, 'image/svg+xml');

    $response = $this->actingAs($this->user)->put(route('properties.update', $property), [
        'name' => 'برج قائم محدث',
        'property_type' => 'building',
        'cover_image_file' => $svgFile,
    ]);

    $response->assertSessionHasErrors('cover_image_file');
});

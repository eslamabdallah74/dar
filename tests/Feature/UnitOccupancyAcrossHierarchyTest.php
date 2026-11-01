<?php

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'سكن الطلاب',
        'slug' => 'student-housing-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);
    WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'role' => 'owner']);
    app(CurrentWorkspace::class)->set($this->workspace);

    $this->property = Property::create(['workspace_id' => $this->workspace->id, 'name' => 'شقة المعادي', 'property_type' => 'apartment']);
    $unit = fn (array $attributes): Unit => Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'is_rentable' => true,
        ...$attributes,
    ]);

    // Apartment > room with beds (one bed leased), a room leased as a whole (with a bed), and a room under maintenance.
    $this->apartment = $unit(['name' => 'شقة 102', 'code' => 'APT-102', 'unit_type' => 'apartment', 'is_rentable' => false]);
    $this->roomWithLeasedBed = $unit(['name' => 'غرفة 1', 'unit_type' => 'room', 'parent_unit_id' => $this->apartment->id]);
    $this->leasedBed = $unit(['name' => 'سرير 1', 'unit_type' => 'bed', 'parent_unit_id' => $this->roomWithLeasedBed->id]);
    $this->leasedRoom = $unit(['name' => 'غرفة 2', 'unit_type' => 'room', 'parent_unit_id' => $this->apartment->id]);
    $this->bedInLeasedRoom = $unit(['name' => 'سرير 2', 'unit_type' => 'bed', 'parent_unit_id' => $this->leasedRoom->id]);
    $this->roomUnderMaintenance = $unit(['name' => 'غرفة 3', 'unit_type' => 'room', 'parent_unit_id' => $this->apartment->id]);

    $lease = fn (Unit $leasedUnit, string $gender): Lease => Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $leasedUnit->id,
        'renter_id' => Renter::factory()->create(['workspace_id' => $this->workspace->id, 'gender' => $gender])->id,
        'starts_on' => now()->subMonth()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'status' => 'active',
    ]);
    $lease($this->leasedBed, 'male');
    $lease($this->leasedRoom, 'female');

    MaintenanceRequest::factory()->create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->roomUnderMaintenance->id,
        'status' => 'open',
    ]);
});

test('property page reports each unit occupancy through its ancestors and descendants', function () {
    $response = $this->actingAs($this->user)->get(route('properties.show', $this->property));

    $response->assertOk();
    $unitProps = $response->inertiaProps('units');
    $this->assertIsArray($unitProps);
    $units = collect($unitProps)->keyBy('id');
    expect($units->get($this->leasedBed->id))->toMatchArray(['status' => 'occupied', 'status_label' => 'مؤجرة', 'can_rent' => false])
        ->and($units->get($this->bedInLeasedRoom->id))->toMatchArray(['status' => 'occupied', 'status_label' => 'محجوز (ضمن عقد الغرفة)', 'can_rent' => false])
        ->and($units->get($this->roomWithLeasedBed->id))->toMatchArray([
            'status' => 'occupied',
            'status_label' => 'مؤجرة (بالأسِرّة)',
            'can_rent' => false,
            'can_subdivide' => true,
            'allowed_child_type' => 'bed',
        ])
        ->and($units->get($this->leasedRoom->id))->toMatchArray(['status' => 'occupied', 'can_subdivide' => false])
        ->and($units->get($this->roomUnderMaintenance->id))->toMatchArray(['status' => 'maintenance', 'status_label' => 'تحت الصيانة', 'can_rent' => true])
        ->and($units->get($this->apartment->id))->toMatchArray([
            'status' => 'occupied',
            'status_label' => 'مشغولة (غرف/أسرّة)',
            'floor_name' => 'الدور الأول',
            'can_subdivide' => true,
            'allowed_child_label' => 'إضافة أوضة',
        ])
        ->and($units->get($this->leasedBed->id))->toHaveKey('apartment_gender.status', 'mixed')
        ->and($response->inertiaProps('property'))->toHaveKey('apartment_gender.status', 'mixed');
});

test('lease form labels each rentable unit by the leases above and below it', function () {
    $response = $this->actingAs($this->user)->get(route('leases.create'));

    $response->assertOk();
    $unitProps = $response->inertiaProps('units');
    $this->assertIsArray($unitProps);
    $units = collect($unitProps)->keyBy('id');
    expect($units->has($this->apartment->id))->toBeFalse()
        ->and($units->get($this->leasedBed->id))->toMatchArray(['is_occupied' => true, 'availability_status' => 'مشغولة حالياً'])
        ->and($units->get($this->roomWithLeasedBed->id))->toMatchArray(['is_occupied' => true, 'availability_status' => 'بها أسرّة مؤجرة'])
        ->and($units->get($this->bedInLeasedRoom->id))->toMatchArray(['is_occupied' => true, 'availability_status' => 'الغرفة مؤجرة بالكامل'])
        ->and($units->get($this->roomUnderMaintenance->id))->toMatchArray(['is_occupied' => false, 'availability_status' => 'شاغرة'])
        ->and($units->get($this->roomUnderMaintenance->id))->toHaveKey('apartment_gender.status', 'mixed');
});

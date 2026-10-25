<?php

declare(strict_types=1);

use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Rules\EgyptianPhoneNumber;
use App\Rules\PhoneNumber;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_user_id' => $this->user->id,
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);
    app(CurrentWorkspace::class)->set($this->workspace);
});

describe('Egyptian phone number rule unit checks', function () {
    test('accepts valid Egyptian mobile numbers across all 4 major carriers', function (string $phone) {
        $validator = Validator::make(['phone' => $phone], [
            'phone' => ['required', new EgyptianPhoneNumber],
        ]);

        expect($validator->passes())->toBeTrue();
    })->with([
        'Vodafone (010)' => '01012345678',
        'Etisalat (011)' => '01198765432',
        'Orange (012)' => '01234567890',
        'WE (015)' => '01511223344',
        'International with +20' => '+201012345678',
        'International with 0020' => '00201012345678',
        'International with 20' => '201012345678',
        'International with optional zero' => '+2001012345678',
        'Formatted with spaces' => '010 1234 5678',
        'Formatted with international spaces' => '+20 101 234 5678',
        'Formatted with hyphens' => '010-1234-5678',
    ]);

    test('rejects non-Egyptian and invalid phone numbers', function (mixed $invalidPhone) {
        expect(EgyptianPhoneNumber::isValid($invalidPhone))->toBeFalse();
    })->with([
        'Saudi number' => '+966501234567',
        'US number' => '+12025550123',
        'Invalid carrier prefix 013' => '01312345678',
        'Invalid carrier prefix 014' => '01412345678',
        'Too short (9 digits)' => '010123456',
        'Too long (13 digits)' => '0101234567890',
        'Alpha characters' => '0101234abcd',
        'Empty string' => '',
        'Random string' => 'not-a-number',
    ]);

    test('PhoneNumber factory forwards to Egyptian rule', function () {
        $validValidator = Validator::make(['phone' => '01012345678'], [
            'phone' => [PhoneNumber::egyptian()],
        ]);
        expect($validValidator->passes())->toBeTrue();

        $invalidValidator = Validator::make(['phone' => '0501234567'], [
            'phone' => [new PhoneNumber('EG')],
        ]);
        expect($invalidValidator->fails())->toBeTrue();
    });

    test('normalizes Egyptian numbers correctly', function () {
        expect(EgyptianPhoneNumber::normalize('+20 101 234 5678'))->toBe('01012345678');
        expect(EgyptianPhoneNumber::normalize('010 1234 5678', international: true))->toBe('+201012345678');
        expect(EgyptianPhoneNumber::normalize('00201012345678'))->toBe('01012345678');
    });
});

describe('Renter validation', function () {
    test('creates a renter with valid name, Egyptian phone, and email', function () {
        $response = $this->actingAs($this->user)
            ->from(route('renters.index'))
            ->post(route('renters.store'), [
                'name' => 'أحمد محمود علي',
                'phone' => '01012345678',
                'email' => 'ahmed@example.com',
                'national_id' => '29801011234567',
            ]);

        $response->assertRedirect(route('renters.index'));
        $this->assertDatabaseHas('renters', [
            'workspace_id' => $this->workspace->id,
            'name' => 'أحمد محمود علي',
            'phone' => '01012345678',
            'email' => 'ahmed@example.com',
        ]);
    });

    test('rejects renter creation when name has fewer than 3 characters', function () {
        $response = $this->actingAs($this->user)->post(route('renters.store'), [
            'name' => 'أح',
            'phone' => '01012345678',
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseMissing('renters', [
            'workspace_id' => $this->workspace->id,
            'phone' => '01012345678',
        ]);
    });

    test('rejects renter creation when phone is not a valid Egyptian number', function () {
        $response = $this->actingAs($this->user)->post(route('renters.store'), [
            'name' => 'عمر خالد حسن',
            'phone' => '+966501234567',
        ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertDatabaseMissing('renters', [
            'workspace_id' => $this->workspace->id,
            'name' => 'عمر خالد حسن',
        ]);
    });

    test('rejects renter creation when email is invalid', function () {
        $response = $this->actingAs($this->user)->post(route('renters.store'), [
            'name' => 'سامي يوسف النجار',
            'phone' => '01122334455',
            'email' => 'invalid-email-address',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('renters', [
            'workspace_id' => $this->workspace->id,
            'name' => 'سامي يوسف النجار',
        ]);
    });

    test('rejects renter update when name is shorter than 3 characters', function () {
        $renter = Renter::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'حسام الدين',
            'phone' => '01234567890',
        ]);

        $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
            'name' => 'حس',
            'phone' => '01234567890',
        ]);

        $response->assertSessionHasErrors(['name']);
        expect($renter->fresh()->name)->toBe('حسام الدين');
    });

    test('rejects renter update with non-Egyptian phone number', function () {
        $renter = Renter::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'حسام الدين',
            'phone' => '01234567890',
        ]);

        $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
            'name' => 'حسام الدين',
            'phone' => '0501112233',
        ]);

        $response->assertSessionHasErrors(['phone']);
        expect($renter->fresh()->phone)->toBe('01234567890');
    });

    test('check-phone endpoint rejects non-Egyptian phone with 422', function () {
        $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
            'phone' => '+966500000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    });

    test('check-phone endpoint accepts valid Egyptian phone', function () {
        $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
            'phone' => '01012345678',
        ]);

        $response->assertOk();
        $response->assertJson(['exists' => false]);
    });
});

describe('Property validation', function () {
    test('rejects property creation when name is shorter than 3 characters', function () {
        $response = $this->actingAs($this->user)->post(route('properties.store'), [
            'name' => 'بر',
            'property_type' => 'building',
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseMissing('properties', [
            'workspace_id' => $this->workspace->id,
            'name' => 'بر',
        ]);
    });

    test('rejects property creation with invalid Egyptian guard phone', function () {
        $response = $this->actingAs($this->user)->post(route('properties.store'), [
            'name' => 'برج الأندلس',
            'property_type' => 'building',
            'guard_name' => 'عم محمد',
            'guard_phone' => '0501234567',
        ]);

        $response->assertSessionHasErrors(['guard_phone']);
    });

    test('accepts property creation with valid Egyptian guard phone and min 3 char guard name', function () {
        $response = $this->actingAs($this->user)->post(route('properties.store'), [
            'name' => 'برج الأندلس',
            'property_type' => 'building',
            'guard_name' => 'عم محمد',
            'guard_phone' => '01099887766',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'workspace_id' => $this->workspace->id,
            'name' => 'برج الأندلس',
            'guard_phone' => '01099887766',
        ]);
    });
});

describe('Settings validation', function () {
    test('rejects workspace name with fewer than 3 characters', function () {
        $response = $this->actingAs($this->user)->put(route('settings.update'), [
            'name' => 'نو',
        ]);

        $response->assertSessionHasErrors(['name']);
    });

    test('rejects non-Egyptian workspace contact phone', function () {
        $response = $this->actingAs($this->user)->put(route('settings.update'), [
            'name' => 'شركة العقارات الحديثة',
            'phone' => '0555555555',
        ]);

        $response->assertSessionHasErrors(['phone']);
    });

    test('accepts valid workspace name and Egyptian phone', function () {
        $response = $this->actingAs($this->user)->put(route('settings.update'), [
            'name' => 'شركة العقارات الحديثة',
            'phone' => '01011223344',
        ]);

        $response->assertRedirect();
        expect($this->workspace->fresh()->name)->toBe('شركة العقارات الحديثة');
        expect($this->workspace->fresh()->phone)->toBe('01011223344');
    });
});

describe('User registration validation', function () {
    test('rejects registration with name shorter than 3 characters', function () {
        $response = $this->post(route('register'), [
            'name' => 'عا',
            'email' => 'aa@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseMissing('users', ['email' => 'aa@example.com']);
    });

    test('accepts registration with valid name >= 3 chars, email, and password', function () {
        $response = $this->post(route('register'), [
            'name' => 'أحمد منصور',
            'email' => 'ahmed.mansour@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'ahmed.mansour@example.com']);
    });
});

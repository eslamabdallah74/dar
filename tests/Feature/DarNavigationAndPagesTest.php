<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'عقارات النور',
        'slug' => 'workspace-test-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور',
        'property_type' => 'building',
        'address' => 'القاهرة - المعادي',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'محمد علي',
        'phone' => '0501234567',
        'national_id' => '1092837465',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
        'rent_amount' => 3200.00,
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'status' => 'active',
    ]);
});

test('properties pages render correctly for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('properties', 1)
            ->has('stats')
        );

    $this->actingAs($this->user)
        ->get("/properties/{$this->property->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Show')
            ->where('property.name', 'عمارة النور')
            ->has('units', 1)
        );
});

test('renters directory and profile page render correctly', function () {
    $this->actingAs($this->user)
        ->get('/renters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renters/Index')
            ->has('renters', 1)
        );

    $this->actingAs($this->user)
        ->get("/renters/{$this->renter->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renters/Show')
            ->where('renter.name', 'محمد علي')
        );
});

test('leases list and create page with conflict check work correctly', function () {
    $this->actingAs($this->user)
        ->get('/leases')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leases/Index')
            ->has('leases', 1)
        );

    $this->actingAs($this->user)
        ->get('/leases/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leases/Create')
            ->has('units')
            ->has('renters')
        );

    // Conflict detection endpoint: should detect overlapping dates for the same unit
    $response = $this->actingAs($this->user)
        ->postJson('/leases/check-conflict', [
            'unit_id' => $this->unit->id,
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonths(3)->toDateString(),
        ]);

    $response->assertOk()
        ->assertJson([
            'has_conflict' => true,
        ]);
});

test('payments index renders and records a payment correctly', function () {
    $this->actingAs($this->user)
        ->get('/payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/Index')
            ->has('charges')
            ->has('payments')
        );

    $this->actingAs($this->user)
        ->post('/payments', [
            'renter_id' => $this->renter->id,
            'amount' => 3200.00,
            'payment_method' => 'bank_transfer',
            'paid_at' => now()->toDateString(),
            'reference' => 'REC-TEST-999',
            'notes' => 'حوالة بنكية تجريبية',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('payments', [
        'reference' => 'REC-TEST-999',
        'amount' => 3200.00,
    ]);
});

test('maintenance and expenses pages render and accept submissions', function () {
    $this->actingAs($this->user)
        ->get('/maintenance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Maintenance/Index')
        );

    $this->actingAs($this->user)
        ->post('/maintenance', [
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'title' => 'فحص تكييف',
            'description' => 'صوت مرتفع في الوحدة الداخلية',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('maintenance_requests', [
        'title' => 'فحص تكييف',
    ]);

    // تأكيد أن إدخال البيانات سهل والوصف اختياري
    $this->actingAs($this->user)
        ->post('/maintenance', [
            'property_id' => $this->property->id,
            'title' => 'عطل إضاءة المدخل',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('maintenance_requests', [
        'title' => 'عطل إضاءة المدخل',
        'description' => '',
    ]);

    $this->actingAs($this->user)
        ->get('/expenses')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Expenses/Index')
        );

    $this->actingAs($this->user)
        ->post('/expenses', [
            'property_id' => $this->property->id,
            'category' => 'صيانة وتشغيل',
            'amount' => 450.00,
            'expense_date' => now()->toDateString(),
            'notes' => 'شراء قطع غيار',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('expenses', [
        'notes' => 'شراء قطع غيار',
    ]);
});

test('whatsapp, subscription and settings pages render correctly', function () {
    $this->actingAs($this->user)
        ->get('/whatsapp')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WhatsApp/Index')
            ->has('templates')
        );

    $this->actingAs($this->user)
        ->get('/subscription')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subscription/Index')
            ->has('plans')
            ->has('current_plan', fn (Assert $currentPlan) => $currentPlan
                ->missing('trial_days_remaining')
                ->where('renewal_date', null)
                ->etc()
            )
        );

    $this->actingAs($this->user)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->has('workspace')
            ->where('workspace.currency', 'EGP')
            ->has('preferences')
        );
});

test('workspace currency defaults to EGP and can be updated through settings', function () {
    expect($this->workspace->currency)->toBe('EGP');

    $this->actingAs($this->user)
        ->put('/settings', [
            'name' => 'عقارات دار مصر',
            'currency' => 'EGP',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('workspaces', [
        'id' => $this->workspace->id,
        'name' => 'عقارات دار مصر',
        'currency' => 'EGP',
    ]);
});

test('dashboard shares workspace info and quick renters list with active leases', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('renters')
            ->where('workspace.currency', 'EGP')
            ->has('quickRenters')
        );
});

test('sitemap xml endpoint returns valid xml with public links and logo', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    expect($response->getContent())
        ->toContain('<loc>')
        ->toContain('/images/logo.png')
        ->toContain('/login')
        ->toContain('/register');
});

test('landing page loads successfully with seo tags and logo image', function () {
    $response = $this->get('/');
    $response->assertOk();
    $response->assertSee('images/logo.png');
    $appName = config('app.name');
    $response->assertSee("{$appName} — Find Your Spot. Rent the Space You Need.");
});

test('payments and expenses pages show amounts in the workspace currency symbol', function (string $pageUrl) {
    $this->workspace->update(['currency' => 'SAR']);

    $this->actingAs($this->user)
        ->get($pageUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('currency_symbol', 'ر.س'));
})->with(['payments' => '/payments', 'expenses' => '/expenses']);

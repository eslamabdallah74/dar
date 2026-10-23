<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RentalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. User & Workspace
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'أحمد حسن',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name' => 'عقارات النور',
                'slug' => 'al-nour-real-estate',
                'currency' => 'EGP',
            ]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => 'owner']
        );

        // 2. Properties: "عمارة النور" (القاهرة - المعادي) و "مجمع الياسمين السكني" (التجمع الخامس)
        $propertyAlNour = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'عمارة النور'],
            [
                'property_type' => 'building',
                'address' => 'المعادي، شارع النصر، القاهرة',
            ]
        );

        $propertyAlyasmin = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'مجمع الياسمين'],
            [
                'property_type' => 'compound',
                'address' => 'التجمع الخامس، شارع التسعين، القاهرة الجديدة',
            ]
        );

        // 3. Units for "عمارة النور" (12 units across 3 floors)
        $unitsAlNour = [];
        $unitNames = [
            ['code' => 'APT-101', 'name' => 'شقة 101 (الدور الأول)', 'type' => 'apartment'],
            ['code' => 'APT-102', 'name' => 'شقة 102 (الدور الأول)', 'type' => 'apartment'],
            ['code' => 'APT-103', 'name' => 'شقة 103 (الدور الأول)', 'type' => 'apartment'],
            ['code' => 'APT-104', 'name' => 'شقة 104 (الدور الأول)', 'type' => 'apartment'],
            ['code' => 'APT-201', 'name' => 'شقة 201 (الدور الثاني)', 'type' => 'apartment'],
            ['code' => 'APT-202', 'name' => 'شقة 202 (الدور الثاني)', 'type' => 'apartment'],
            ['code' => 'APT-203', 'name' => 'شقة 203 (الدور الثاني)', 'type' => 'apartment'],
            ['code' => 'APT-204', 'name' => 'شقة 204 (الدور الثاني)', 'type' => 'apartment'],
            ['code' => 'APT-301', 'name' => 'شقة 301 (الدور الثالث - روف)', 'type' => 'apartment'],
            ['code' => 'APT-302', 'name' => 'شقة 302 (الدور الثالث - روف)', 'type' => 'apartment'],
            ['code' => 'APT-303', 'name' => 'شقة 303 (الدور الثالث)', 'type' => 'apartment'],
            ['code' => 'APT-304', 'name' => 'شقة 304 (الدور الثالث)', 'type' => 'apartment'],
        ];

        foreach ($unitNames as $u) {
            $unitsAlNour[$u['code']] = Unit::firstOrCreate(
                ['workspace_id' => $workspace->id, 'property_id' => $propertyAlNour->id, 'code' => $u['code']],
                [
                    'name' => $u['name'],
                    'unit_type' => $u['type'],
                    'is_rentable' => true,
                ]
            );
        }

        // 4. Renters (including Mohammed Ali from Figma)
        $renterMohammed = Renter::firstOrCreate(
            ['workspace_id' => $workspace->id, 'national_id' => '1092837465'],
            [
                'name' => 'محمد علي',
                'phone' => '0501234567',
                'email' => 'mohammed.ali@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(3),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'مستأجر منتظم في السداد، شقة 101 عمارة النور',
            ]
        );

        $renterKhaled = Renter::firstOrCreate(
            ['workspace_id' => $workspace->id, 'national_id' => '1083746251'],
            [
                'name' => 'خالد العتيبي',
                'phone' => '0558765432',
                'email' => 'khaled.otb@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(6),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'لديه دفعة متأخرة مستحقة هذا الأسبوع',
            ]
        );

        $renterSarah = Renter::firstOrCreate(
            ['workspace_id' => $workspace->id, 'national_id' => '1074628391'],
            [
                'name' => 'سارة الشمري',
                'phone' => '0543219876',
                'email' => 'sarah.sh@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(2),
                'whatsapp_opt_in_source' => 'app',
                'notes' => 'عقد ينتهي خلال 30 يوم',
            ]
        );

        $renterOmar = Renter::firstOrCreate(
            ['workspace_id' => $workspace->id, 'national_id' => '1065928374'],
            [
                'name' => 'عمر القحطاني',
                'phone' => '0567891234',
                'email' => 'omar.qht@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(4),
                'whatsapp_opt_in_source' => 'contract_signature',
            ]
        );

        $renterFahad = Renter::firstOrCreate(
            ['workspace_id' => $workspace->id, 'national_id' => '1058273645'],
            [
                'name' => 'فهد الدوسري',
                'phone' => '0531987465',
                'email' => 'fahad.d@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(1),
                'whatsapp_opt_in_source' => 'contract_signature',
            ]
        );

        // 5. Leases
        // Mohammed Ali: active lease in APT-101 (Rent 3,200 SAR/month)
        $leaseMohammed = Lease::firstOrCreate(
            ['workspace_id' => $workspace->id, 'unit_id' => $unitsAlNour['APT-101']->id, 'renter_id' => $renterMohammed->id],
            [
                'starts_on' => now()->subMonths(4)->startOfMonth()->toDateString(),
                'ends_on' => now()->addMonths(8)->endOfMonth()->toDateString(),
                'rent_amount' => 3200.00,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => 1,
                'first_due_date' => now()->subMonths(4)->startOfMonth()->toDateString(),
                'status' => 'active',
                'notes' => 'عقد سنوي دفع شهري - وديعة 3,000 ج.م مستلمة',
            ]
        );

        // Khaled: active lease in APT-102 (Rent 2,800 SAR/month)
        $leaseKhaled = Lease::firstOrCreate(
            ['workspace_id' => $workspace->id, 'unit_id' => $unitsAlNour['APT-102']->id, 'renter_id' => $renterKhaled->id],
            [
                'starts_on' => now()->subMonths(5)->startOfMonth()->toDateString(),
                'ends_on' => now()->addMonths(7)->endOfMonth()->toDateString(),
                'rent_amount' => 2800.00,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => 1,
                'first_due_date' => now()->subMonths(5)->startOfMonth()->toDateString(),
                'status' => 'active',
            ]
        );

        // Sarah: active lease in APT-103 (Expiring soon, Rent 3,000 SAR/month)
        $leaseSarah = Lease::firstOrCreate(
            ['workspace_id' => $workspace->id, 'unit_id' => $unitsAlNour['APT-103']->id, 'renter_id' => $renterSarah->id],
            [
                'starts_on' => now()->subMonths(11)->startOfMonth()->toDateString(),
                'ends_on' => now()->addDays(20)->toDateString(),
                'rent_amount' => 3000.00,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => 1,
                'first_due_date' => now()->subMonths(11)->startOfMonth()->toDateString(),
                'status' => 'active',
                'notes' => 'طلب تجديد العقد قيد المراجعة',
            ]
        );

        // Omar: active lease in APT-201 (Rent 3,500 SAR/month)
        $leaseOmar = Lease::firstOrCreate(
            ['workspace_id' => $workspace->id, 'unit_id' => $unitsAlNour['APT-201']->id, 'renter_id' => $renterOmar->id],
            [
                'starts_on' => now()->subMonths(2)->startOfMonth()->toDateString(),
                'ends_on' => now()->addMonths(10)->endOfMonth()->toDateString(),
                'rent_amount' => 3500.00,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => 1,
                'first_due_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
                'status' => 'active',
            ]
        );

        // Fahad: active lease in APT-202 (Rent 3,100 SAR/month)
        $leaseFahad = Lease::firstOrCreate(
            ['workspace_id' => $workspace->id, 'unit_id' => $unitsAlNour['APT-202']->id, 'renter_id' => $renterFahad->id],
            [
                'starts_on' => now()->subMonths(3)->startOfMonth()->toDateString(),
                'ends_on' => now()->addMonths(9)->endOfMonth()->toDateString(),
                'rent_amount' => 3100.00,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => 1,
                'first_due_date' => now()->subMonths(3)->startOfMonth()->toDateString(),
                'status' => 'active',
            ]
        );

        // 6. Rent Charges & Payments
        // For Mohammed: paid for previous months and current month
        $currentMonthStart = now()->startOfMonth()->toDateString();
        $currentMonthEnd = now()->endOfMonth()->toDateString();

        $chargeMohammed = RentCharge::firstOrCreate(
            ['lease_id' => $leaseMohammed->id, 'period_start' => $currentMonthStart],
            [
                'workspace_id' => $workspace->id,
                'period_end' => $currentMonthEnd,
                'due_date' => now()->startOfMonth()->toDateString(),
                'amount' => 3200.00,
            ]
        );

        $paymentMohammed = Payment::firstOrCreate(
            ['workspace_id' => $workspace->id, 'renter_id' => $renterMohammed->id, 'reference' => 'PAY-2026-001'],
            [
                'recorded_by' => $user->id,
                'amount' => 3200.00,
                'paid_at' => now()->subDays(2),
                'payment_method' => 'bank_transfer',
                'notes' => 'تحويل بنكي - مصرف الراجحي',
            ]
        );

        PaymentAllocation::firstOrCreate(
            ['payment_id' => $paymentMohammed->id, 'rent_charge_id' => $chargeMohammed->id],
            [
                'workspace_id' => $workspace->id,
                'amount' => 3200.00,
            ]
        );

        // Khaled: overdue charge for current month
        $chargeKhaled = RentCharge::firstOrCreate(
            ['lease_id' => $leaseKhaled->id, 'period_start' => $currentMonthStart],
            [
                'workspace_id' => $workspace->id,
                'period_end' => $currentMonthEnd,
                'due_date' => now()->subDays(5)->toDateString(),
                'amount' => 2800.00,
            ]
        );

        // Sarah: paid
        $chargeSarah = RentCharge::firstOrCreate(
            ['lease_id' => $leaseSarah->id, 'period_start' => $currentMonthStart],
            [
                'workspace_id' => $workspace->id,
                'period_end' => $currentMonthEnd,
                'due_date' => now()->startOfMonth()->toDateString(),
                'amount' => 3000.00,
            ]
        );
        $paymentSarah = Payment::firstOrCreate(
            ['workspace_id' => $workspace->id, 'renter_id' => $renterSarah->id, 'reference' => 'PAY-2026-002'],
            [
                'recorded_by' => $user->id,
                'amount' => 3000.00,
                'paid_at' => now()->subDays(4),
                'payment_method' => 'bank_transfer',
                'notes' => 'سداد عبر مدى',
            ]
        );
        PaymentAllocation::firstOrCreate(
            ['payment_id' => $paymentSarah->id, 'rent_charge_id' => $chargeSarah->id],
            ['workspace_id' => $workspace->id, 'amount' => 3000.00]
        );

        // 7. Maintenance Requests
        MaintenanceRequest::firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'صيانة تكييف غرفة النوم الرئيسية'],
            [
                'property_id' => $propertyAlNour->id,
                'unit_id' => $unitsAlNour['APT-101']->id,
                'reported_by_renter_id' => $renterMohammed->id,
                'description' => 'المكيف يصدر صوتاً مرتفعاً والتبريد ضعيف، مطلوب فني لفحص الفريون.',
                'status' => 'in_progress',
                'reported_at' => now()->subDays(3),
            ]
        );

        MaintenanceRequest::firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'تسريب مياه في محبس المغسلة'],
            [
                'property_id' => $propertyAlNour->id,
                'unit_id' => $unitsAlNour['APT-202']->id,
                'reported_by_renter_id' => $renterFahad->id,
                'description' => 'تسريب مياه بسيط تحت حوض المطبخ يحتاج استبدال جلدة المحبس.',
                'status' => 'open',
                'reported_at' => now()->subDay(),
            ]
        );

        MaintenanceRequest::firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'فحص دوري لمصعد العمارة'],
            [
                'property_id' => $propertyAlNour->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'description' => 'الصيانة الشهرية المجدولة مع شركة المصاعد.',
                'status' => 'closed',
                'reported_at' => now()->subDays(10),
                'resolved_at' => now()->subDays(9),
            ]
        );

        // 8. Operating Expenses
        Expense::firstOrCreate(
            ['workspace_id' => $workspace->id, 'notes' => 'فاتورة كهرباء العداد المشترك (المصعد والمداخل)'],
            [
                'property_id' => $propertyAlNour->id,
                'recorded_by' => $user->id,
                'category' => 'كهرباء ومياه',
                'amount' => 680.00,
                'expense_date' => now()->subDays(5)->toDateString(),
            ]
        );

        Expense::firstOrCreate(
            ['workspace_id' => $workspace->id, 'notes' => 'عقد الصيانة الوقائية للمصعد لشهر أكتوبر'],
            [
                'property_id' => $propertyAlNour->id,
                'recorded_by' => $user->id,
                'category' => 'صيانة وتشغيل',
                'amount' => 400.00,
                'expense_date' => now()->subDays(8)->toDateString(),
            ]
        );

        Expense::firstOrCreate(
            ['workspace_id' => $workspace->id, 'notes' => 'رسوم خدمات النظافة وجمع النفايات الدورية'],
            [
                'property_id' => $propertyAlNour->id,
                'recorded_by' => $user->id,
                'category' => 'نظافة وخدمات',
                'amount' => 350.00,
                'expense_date' => now()->subDays(12)->toDateString(),
            ]
        );

        // 9. WhatsApp Reminder Logs
        MessageLog::firstOrCreate(
            ['workspace_id' => $workspace->id, 'deduplication_key' => 'due_rem_khaled_'.now()->format('Y_m')],
            [
                'renter_id' => $renterKhaled->id,
                'lease_id' => $leaseKhaled->id,
                'rent_charge_id' => $chargeKhaled->id,
                'recipient_phone' => $renterKhaled->phone,
                'template_name' => 'rent_due_reminder_ar',
                'scheduled_at' => now()->subDays(4),
                'sent_at' => now()->subDays(4),
                'status' => 'delivered',
                'attempts' => 1,
                'provider_cost' => 0.045,
                'provider_cost_currency' => 'USD',
            ]
        );

        MessageLog::firstOrCreate(
            ['workspace_id' => $workspace->id, 'deduplication_key' => 'receipt_mohammed_'.now()->format('Y_m')],
            [
                'renter_id' => $renterMohammed->id,
                'lease_id' => $leaseMohammed->id,
                'rent_charge_id' => $chargeMohammed->id,
                'recipient_phone' => $renterMohammed->phone,
                'template_name' => 'payment_receipt_instant_ar',
                'scheduled_at' => now()->subDays(2),
                'sent_at' => now()->subDays(2),
                'status' => 'delivered',
                'attempts' => 1,
                'provider_cost' => 0.045,
                'provider_cost_currency' => 'USD',
            ]
        );
    }
}

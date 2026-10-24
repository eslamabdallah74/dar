<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\RenterDocument;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

class RentalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------------
        // 0. Ensure Subscription Plans exist
        // -------------------------------------------------------------
        $this->call(PlanSeeder::class);

        $growthPlan = Plan::where('slug', 'growth')->first();
        $proPlan = Plan::where('slug', 'pro')->first();

        // -------------------------------------------------------------
        // 1. Users
        // -------------------------------------------------------------
        $primaryUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'أحمد حسن',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $accountantUser = User::updateOrCreate(
            ['email' => 'abdullah.kh@al-nour.com.eg'],
            [
                'name' => 'عبدالله خالد (المحاسب المالي)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $secondaryOwner = User::updateOrCreate(
            ['email' => 'saad.tamimi@example.com'],
            [
                'name' => 'سعد التميمي',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // -------------------------------------------------------------
        // 2. Workspaces & Memberships
        // -------------------------------------------------------------
        // Primary Workspace (Dar Real Estate - Egypt portfolio)
        $workspace = Workspace::updateOrCreate(
            ['slug' => 'al-nour-real-estate'],
            [
                'name' => 'عقارات النور',
                'owner_user_id' => $primaryUser->id,
                'currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'rent_reminder_days_before' => 5,
            ]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $primaryUser->id],
            ['role' => 'owner']
        );

        // Secondary Workspace (Saudi portfolio)
        $secondaryWorkspace = Workspace::updateOrCreate(
            ['slug' => 'al-ofoq-real-estate'],
            [
                'name' => 'مجموعة الأفق العقارية',
                'owner_user_id' => $secondaryOwner->id,
                'currency' => 'SAR',
                'timezone' => 'Asia/Riyadh',
                'status' => 'active',
                'rent_reminder_days_before' => 3,
            ]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'user_id' => $secondaryOwner->id],
            ['role' => 'owner']
        );

        // -------------------------------------------------------------
        // 3. Workspace Subscriptions
        // -------------------------------------------------------------
        if ($growthPlan) {
            WorkspaceSubscription::updateOrCreate(
                ['workspace_id' => $workspace->id, 'plan_id' => $growthPlan->id],
                [
                    'activated_by' => $primaryUser->id,
                    'status' => 'active',
                    'starts_at' => now()->subMonths(6),
                    'ends_at' => now()->addMonths(6),
                    'provider_reference' => 'SUB-DAR-GROWTH-2026',
                ]
            );
        }

        if ($proPlan) {
            WorkspaceSubscription::updateOrCreate(
                ['workspace_id' => $secondaryWorkspace->id, 'plan_id' => $proPlan->id],
                [
                    'activated_by' => $secondaryOwner->id,
                    'status' => 'active',
                    'starts_at' => now()->subMonths(3),
                    'ends_at' => now()->addMonths(9),
                    'provider_reference' => 'SUB-DAR-PRO-2026',
                ]
            );
        }

        // -------------------------------------------------------------
        // 4. Properties (Primary Workspace)
        // -------------------------------------------------------------
        $propBuilding = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'عمارة النور السكنية'],
            [
                'property_type' => 'building',
                'address' => 'المعادي، شارع النصر تقاطع اللاسلكي، القاهرة',
            ]
        );

        $propYasminVillas = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'فلل الياسمين'],
            [
                'property_type' => 'building',
                'address' => 'التجمع الخامس، الحي الخامس بالقرب من شارع التسعين، القاهرة الجديدة',
            ]
        );

        $propTower = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'برج الأندلس'],
            [
                'property_type' => 'building',
                'address' => 'مدينة نصر، شارع عباس العقاد، القاهرة',
            ]
        );

        $propVilla = Property::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'فيلا المعمورة'],
            [
                'property_type' => 'villa',
                'address' => 'الشروق، المنطقة الأولى، القاهرة',
            ]
        );

        // Secondary Workspace Properties (Riyadh, Saudi Arabia)
        $propRiyadh = Property::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'name' => 'برج العليا التنفيذي'],
            [
                'property_type' => 'building',
                'address' => 'حي العليا، طريق الملك فهد، الرياض',
            ]
        );

        $propNakheel = Property::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'name' => 'وحدات النخيل'],
            [
                'property_type' => 'building',
                'address' => 'حي النخيل، طريق الإمام سعود بن عبدالعزيز، الرياض',
            ]
        );

        $propHittin = Property::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'name' => 'فيلا حطين الفاخرة'],
            [
                'property_type' => 'villa',
                'address' => 'حي حطين النموذجي، شمال الرياض',
            ]
        );

        // -------------------------------------------------------------
        // 5. Units
        // -------------------------------------------------------------
        // A. Units for عمارة النور (12 apartments across 3 floors)
        $unitsAlNour = [];
        $buildingUnitsData = [
            ['code' => 'APT-101', 'name' => 'شقة 101 (الدور الأول - واجهة بحرية)', 'type' => 'apartment'],
            ['code' => 'APT-102', 'name' => 'شقة 102 (الدور الأول - واجهة داخلية)', 'type' => 'apartment'],
            ['code' => 'APT-103', 'name' => 'شقة 103 (الدور الأول - واجهة غربية)', 'type' => 'apartment'],
            ['code' => 'APT-104', 'name' => 'شقة 104 (الدور الأول - واجهة شرقية)', 'type' => 'apartment'],
            ['code' => 'APT-201', 'name' => 'شقة 201 (الدور الثاني - واجهة بحرية)', 'type' => 'apartment'],
            ['code' => 'APT-202', 'name' => 'شقة 202 (الدور الثاني - واجهة داخلية)', 'type' => 'apartment'],
            ['code' => 'APT-203', 'name' => 'شقة 203 (الدور الثاني - واجهة غربية)', 'type' => 'apartment'],
            ['code' => 'APT-204', 'name' => 'شقة 204 (الدور الثاني - واجهة شرقية)', 'type' => 'apartment'],
            ['code' => 'APT-301', 'name' => 'شقة 301 (الدور الثالث - روف مع تراس)', 'type' => 'apartment'],
            ['code' => 'APT-302', 'name' => 'شقة 302 (الدور الثالث - روف مع حديقة)', 'type' => 'apartment'],
            ['code' => 'APT-303', 'name' => 'شقة 303 (الدور الثالث - ملحق علوي)', 'type' => 'apartment'],
            ['code' => 'APT-304', 'name' => 'شقة 304 (الدور الثالث - استوديو مفروش)', 'type' => 'apartment'],
        ];

        foreach ($buildingUnitsData as $u) {
            $unitsAlNour[$u['code']] = Unit::firstOrCreate(
                ['workspace_id' => $workspace->id, 'property_id' => $propBuilding->id, 'code' => $u['code']],
                [
                    'name' => $u['name'],
                    'unit_type' => $u['type'],
                    'is_rentable' => true,
                ]
            );
        }

        // B. Units for فلل الياسمين (6 units: villas & duplexes)
        $unitsYasminVillas = [];
        $yasminVillaUnitsData = [
            ['code' => 'VIL-01', 'name' => 'فيلا 01 (تاون هاوس زاوية بحديقة)', 'type' => 'apartment'],
            ['code' => 'VIL-02', 'name' => 'فيلا 02 (تاون هاوس وسطي بحديقة)', 'type' => 'apartment'],
            ['code' => 'DUP-01', 'name' => 'دوبلكس 01 (أرضي بحديقة خاصة ومسبح)', 'type' => 'apartment'],
            ['code' => 'DUP-02', 'name' => 'دوبلكس 02 (أرضي بحديقة خاصة)', 'type' => 'apartment'],
            ['code' => 'DUP-03', 'name' => 'دوبلكس 03 (أول وثانٍ مع روف علوي)', 'type' => 'apartment'],
            ['code' => 'DUP-04', 'name' => 'دوبلكس 04 (أول وثانٍ مع روف علوي)', 'type' => 'apartment'],
        ];

        foreach ($yasminVillaUnitsData as $u) {
            $unitsYasminVillas[$u['code']] = Unit::firstOrCreate(
                ['workspace_id' => $workspace->id, 'property_id' => $propYasminVillas->id, 'code' => $u['code']],
                [
                    'name' => $u['name'],
                    'unit_type' => $u['type'],
                    'is_rentable' => true,
                ]
            );
        }

        // C. Units for برج الأندلس (2 luxury apartments + 2 commercial shops)
        $unitsTower = [];
        $towerUnitsData = [
            ['code' => 'APT-501', 'name' => 'شقة 501 (إطلالة بانورامية كاملة)', 'type' => 'apartment'],
            ['code' => 'APT-502', 'name' => 'شقة 502 (مفروشة بالكامل الترا سوبر لوكس)', 'type' => 'apartment'],
            ['code' => 'SHP-01', 'name' => 'محل تجاري 1 (معرض تجاري واجهة رئيسية)', 'type' => 'other'],
            ['code' => 'SHP-02', 'name' => 'محل تجاري 2 (صيدلية مجهزة ترخيص تجاري)', 'type' => 'other'],
        ];

        foreach ($towerUnitsData as $u) {
            $unitsTower[$u['code']] = Unit::firstOrCreate(
                ['workspace_id' => $workspace->id, 'property_id' => $propTower->id, 'code' => $u['code']],
                [
                    'name' => $u['name'],
                    'unit_type' => $u['type'],
                    'is_rentable' => true,
                ]
            );
        }

        // D. Units for فيلا المعمورة (Hierarchical units to test scopeBillableLeaves)
        $villaMain = Unit::firstOrCreate(
            ['workspace_id' => $workspace->id, 'property_id' => $propVilla->id, 'code' => 'VIL-MAIN'],
            [
                'name' => 'فيلا المعمورة الكاملة',
                'unit_type' => 'apartment',
                'is_rentable' => false, // Parent container unit
            ]
        );

        $suiteWest = Unit::firstOrCreate(
            ['workspace_id' => $workspace->id, 'property_id' => $propVilla->id, 'code' => 'SUITE-A'],
            [
                'parent_unit_id' => $villaMain->id,
                'name' => 'جناح المعمورة الغربي (مدخل مستقل وحديقة خاصة)',
                'unit_type' => 'room',
                'is_rentable' => true,
            ]
        );

        $suiteEast = Unit::firstOrCreate(
            ['workspace_id' => $workspace->id, 'property_id' => $propVilla->id, 'code' => 'SUITE-B'],
            [
                'parent_unit_id' => $villaMain->id,
                'name' => 'جناح المعمورة الشرقي (مدخل مستقل وتراس علوي)',
                'unit_type' => 'room',
                'is_rentable' => true,
            ]
        );

        // Secondary Workspace Units
        $saudiUnitsList = [
            ['property_id' => $propRiyadh->id, 'code' => 'STE-101', 'name' => 'جناح تنفيذي 101', 'unit_type' => 'apartment'],
            ['property_id' => $propRiyadh->id, 'code' => 'STE-102', 'name' => 'جناح تنفيذي 102', 'unit_type' => 'apartment'],
            ['property_id' => $propRiyadh->id, 'code' => 'STE-201', 'name' => 'جناح تنفيذي 201', 'unit_type' => 'apartment'],
            ['property_id' => $propRiyadh->id, 'code' => 'OFC-301', 'name' => 'مكتب تجاري 301', 'unit_type' => 'other'],
            ['property_id' => $propNakheel->id, 'code' => 'VIL-01', 'name' => 'فيلا تاون هاوس 1', 'unit_type' => 'apartment'],
            ['property_id' => $propNakheel->id, 'code' => 'VIL-02', 'name' => 'فيلا تاون هاوس 2', 'unit_type' => 'apartment'],
            ['property_id' => $propNakheel->id, 'code' => 'APT-01', 'name' => 'شقة دوبلكس راقية 01', 'unit_type' => 'apartment'],
            ['property_id' => $propHittin->id, 'code' => 'VIL-H1', 'name' => 'فيلا حطين المستقلة', 'unit_type' => 'other'],
        ];

        $unitsSaudi = [];
        foreach ($saudiUnitsList as $su) {
            $unitsSaudi[$su['code']] = Unit::firstOrCreate(
                ['workspace_id' => $secondaryWorkspace->id, 'code' => $su['code']],
                [
                    'property_id' => $su['property_id'],
                    'name' => $su['name'],
                    'unit_type' => $su['unit_type'],
                    'is_rentable' => true,
                ]
            );
        }

        // -------------------------------------------------------------
        // 6. Renters (Primary Workspace)
        // -------------------------------------------------------------
        $rentersData = [
            [
                'national_id' => '29010150102345',
                'name' => 'محمد علي إبراهيم',
                'phone' => '01012345678',
                'email' => 'mohammed.ali@example.com',
                'notes' => 'مستأجر شقة 101، منتظم جداً في السداد، يعمل مهندس استشاري',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29508120104567',
                'name' => 'سارة إبراهيم الشناوي',
                'phone' => '01234567890',
                'email' => 'sarah.elshinawy@example.com',
                'notes' => 'مستأجرة شقة 102، سداد دوري عبر إنستاباي InstaPay',
                'source' => 'app',
            ],
            [
                'national_id' => '28803150107890',
                'name' => 'عمر خالد عبد الرحمن',
                'phone' => '01512345678',
                'email' => 'omar.khaled@example.com',
                'notes' => 'مستأجر شقة 103، لديه دفعة مستحقة ومتأخرة عن هذا الشهر',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29204190103456',
                'name' => 'د. طارق محمود عبد الله',
                'phone' => '01098765432',
                'email' => 'tarek.mahmoud@example.com',
                'notes' => 'طبيب استشاري جراحة بمستشفى السلام الدولي، مستأجر شقة 201',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28912250108765',
                'name' => 'د. منى فاروق حسنين',
                'phone' => '01155443322',
                'email' => 'mona.farouk@example.com',
                'notes' => 'أستاذة جامعية بكلية الهندسة جامعة القاهرة، مستأجرة شقة 202',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29406080101234',
                'name' => 'أحمد ممدوح العطار',
                'phone' => '01066778899',
                'email' => 'a.attar@example.com',
                'notes' => 'مدير مالي بشركة استيراد وتصدير، مستأجر شقة 203',
                'source' => 'app',
            ],
            [
                'national_id' => '29609140109012',
                'name' => 'نورهان سامي الشرقاوي',
                'phone' => '01555667788',
                'email' => 'nourhan.s@example.com',
                'notes' => 'مستأجرة شقة 204، عقدها ينتهي خلال 12 يوماً ومطلوب التجديد',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29111300105678',
                'name' => 'كريم يوسف النجار',
                'phone' => '01277889900',
                'email' => 'karim.najjar@example.com',
                'notes' => 'محامي بالاستئناف العالي ومجلس الدولة، مستأجر شقة 104',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29009090102143',
                'name' => 'زياد أيمن بدر',
                'phone' => '01011223344',
                'email' => 'zeyad.badr@example.com',
                'notes' => 'مستأجر شقة 301 الروف، مهتم بنظافة التراس',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28705200104321',
                'name' => 'م. شريف هشام الديب',
                'phone' => '01022334455',
                'email' => 'sherif.eldeeb@example.com',
                'notes' => 'رئيس مجلس إدارة شركة مقاولات، مستأجر فيلا 01 بفلل الياسمين',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29302170108901',
                'name' => 'رانيا عادل زكي',
                'phone' => '01122334466',
                'email' => 'rania.adel@example.com',
                'notes' => 'مستأجرة فيلا 02 بفلل الياسمين، تم سداد وديعة التأمين كاملة',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28607110103456',
                'name' => 'حسام الدين مصطفى',
                'phone' => '01222334477',
                'email' => 'hossam.m@example.com',
                'notes' => 'خبير تقني بشركة اتصالات، مستأجر دوبلكس 01',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29701050106789',
                'name' => 'ياسمين أشرف خليل',
                'phone' => '01566778899',
                'email' => 'yasmine.khalil@example.com',
                'notes' => 'مستأجرة دوبلكس 02، عقد ينتهي خلال 20 يوماً',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28504040107654',
                'name' => 'محمد جمال الدين (شركة تكنولوجيا)',
                'phone' => '01044556677',
                'email' => 'm.gamal@tech-solutions.eg',
                'notes' => 'عقد تجاري معرض ومقر تقني - محل تجاري 1 برج الأندلس',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28308180109876',
                'name' => 'د. وائل رضوان (صيدلية الشفاء)',
                'phone' => '01133445566',
                'email' => 'dr.wael@shifa-pharmacy.eg',
                'notes' => 'عقد تجاري لصيدلية مجهزة - محل تجاري 2 برج الأندلس',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29107220101122',
                'name' => 'عمرو نبيل سليمان',
                'phone' => '01288990011',
                'email' => 'amr.nabil@example.com',
                'notes' => 'مستأجر جناح المعمورة الغربي المستقل',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '29804200105566',
                'name' => 'هبة الله عصام سالم',
                'phone' => '01077889911',
                'email' => 'heba.essam@example.com',
                'notes' => 'مستأجرة جناح المعمورة الشرقي، عقد ينتهي خلال 28 يوماً',
                'source' => 'contract_signature',
            ],
            [
                'national_id' => '28905160103344',
                'name' => 'مصطفى كمال الدين',
                'phone' => '01544332211',
                'email' => 'm.kamal@example.com',
                'notes' => 'مستأجر سابق انتهى عقده وسلّم الوحدة بحالة ممتازة',
                'source' => 'contract_signature',
            ],
        ];

        $renters = [];
        foreach ($rentersData as $r) {
            $renters[$r['national_id']] = Renter::updateOrCreate(
                ['workspace_id' => $workspace->id, 'national_id' => $r['national_id']],
                [
                    'name' => $r['name'],
                    'phone' => $r['phone'],
                    'email' => $r['email'],
                    'whatsapp_opt_in_at' => now()->subMonths(4),
                    'whatsapp_opt_in_source' => $r['source'],
                    'whatsapp_opt_in_recorded_by' => $primaryUser->id,
                    'notes' => $r['notes'],
                ]
            );

            // Seed Renter Documents
            RenterDocument::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'renter_id' => $renters[$r['national_id']]->id,
                    'document_type' => 'national_id',
                ],
                [
                    'storage_path' => 'documents/renters/'.$renters[$r['national_id']]->id.'/national_id_card.pdf',
                    'uploaded_by' => $primaryUser->id,
                ]
            );

            RenterDocument::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'renter_id' => $renters[$r['national_id']]->id,
                    'document_type' => 'employment_letter',
                ],
                [
                    'storage_path' => 'documents/renters/'.$renters[$r['national_id']]->id.'/employment_verification.pdf',
                    'uploaded_by' => $primaryUser->id,
                ]
            );
        }

        // Secondary Workspace Renters
        $saudiRenter1 = Renter::updateOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'national_id' => '1083746251'],
            [
                'name' => 'خالد بن فهد العتيبي',
                'phone' => '0558765432',
                'email' => 'khaled.otb@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(3),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'مستأجر جناح تنفيذي 101 برج العليا بالرياض',
            ]
        );

        $saudiRenter2 = Renter::updateOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'national_id' => '1058273645'],
            [
                'name' => 'سلطان بن عبدالعزيز الدوسري',
                'phone' => '0531987465',
                'email' => 'sultan.d@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(2),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'مستأجر جناح تنفيذي 102 برج العليا',
            ]
        );

        $saudiRenter3 = Renter::updateOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'national_id' => '1029384756'],
            [
                'name' => 'فيصل بن محمد القحطاني',
                'phone' => '0504123890',
                'email' => 'faisal.q@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(4),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'مستأجر فيلا تاون هاوس 1 بوحدات النخيل',
            ]
        );

        $saudiRenter4 = Renter::updateOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'national_id' => '1098765432'],
            [
                'name' => 'نورة بنت عبدالله السبيعي',
                'phone' => '0546781234',
                'email' => 'noura.s@example.com',
                'whatsapp_opt_in_at' => now()->subMonths(1),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'مستأجرة شقة دوبلكس 01 بوحدات النخيل',
            ]
        );

        $saudiRenter5 = Renter::updateOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'national_id' => '7001928374'],
            [
                'name' => 'شركة الرواد للحلول التقنية',
                'phone' => '0567890123',
                'email' => 'info@alrowad-tech.sa',
                'whatsapp_opt_in_at' => now()->subMonths(5),
                'whatsapp_opt_in_source' => 'contract_signature',
                'notes' => 'عقد تجاري للمكتب 301 برج العليا',
            ]
        );

        // -------------------------------------------------------------
        // 7. Leases, Charges, Payments & Allocations
        // -------------------------------------------------------------
        $leasesConfig = [
            // Active Ongoing Leases (Stable)
            [
                'unit' => $unitsAlNour['APT-101'],
                'renter' => $renters['29010150102345'], // محمد علي
                'rent' => 4500.00,
                'starts' => now()->subMonths(5)->startOfMonth(),
                'ends' => now()->addMonths(7)->endOfMonth(),
                'status' => 'active',
                'notes' => 'عقد سنوي دفع شهري منتظم - تأمين شهرين مستلم',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsAlNour['APT-102'],
                'renter' => $renters['29508120104567'], // سارة الشناوي
                'rent' => 4200.00,
                'starts' => now()->subMonths(6)->startOfMonth(),
                'ends' => now()->addMonths(6)->endOfMonth(),
                'status' => 'active',
                'notes' => 'سداد عبر إنستاباي - دفعة جزئية هذا الشهر',
                'current_status' => 'partial', // 2,000 paid out of 4,200
            ],
            [
                'unit' => $unitsAlNour['APT-103'],
                'renter' => $renters['28803150107890'], // عمر خالد
                'rent' => 4000.00,
                'starts' => now()->subMonths(4)->startOfMonth(),
                'ends' => now()->addMonths(8)->endOfMonth(),
                'status' => 'active',
                'notes' => 'متأخر في سداد دفعة الشهر الحالي',
                'current_status' => 'overdue', // 0 paid, overdue 10 days
            ],
            [
                'unit' => $unitsAlNour['APT-104'],
                'renter' => $renters['29111300105678'], // كريم النجار
                'rent' => 4300.00,
                'starts' => now()->subMonths(3)->startOfMonth(),
                'ends' => now()->addMonths(9)->endOfMonth(),
                'status' => 'active',
                'notes' => 'عقد موثق رسمياً - سداد شهري بالتحويل البنكي',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsAlNour['APT-201'],
                'renter' => $renters['29204190103456'], // طارق محمود
                'rent' => 4600.00,
                'starts' => now()->subMonths(8)->startOfMonth(),
                'ends' => now()->addMonths(4)->endOfMonth(),
                'status' => 'active',
                'notes' => 'طبيب استشاري، سداد مستمر عبر شيكات بنكية',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsAlNour['APT-202'],
                'renter' => $renters['28912250108765'], // منى فاروق
                'rent' => 4800.00,
                'starts' => now()->subMonths(2)->startOfMonth(),
                'ends' => now()->addMonths(10)->endOfMonth(),
                'status' => 'active',
                'notes' => 'عقد جديد، شقة مجددة بالكامل',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsAlNour['APT-203'],
                'renter' => $renters['29406080101234'], // أحمد ممدوح
                'rent' => 4500.00,
                'starts' => now()->subMonths(7)->startOfMonth(),
                'ends' => now()->addMonths(5)->endOfMonth(),
                'status' => 'active',
                'notes' => 'تحويل بنكي في اليوم الأول من كل شهر ميلادي',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsAlNour['APT-301'],
                'renter' => $renters['29009090102143'], // زياد بدر
                'rent' => 5500.00,
                'starts' => now()->subMonths(5)->startOfMonth(),
                'ends' => now()->addMonths(7)->endOfMonth(),
                'status' => 'active',
                'notes' => 'شقة روف، ملحق بها حديقة خاصة وتراس مفتوح',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsYasminVillas['VIL-01'],
                'renter' => $renters['28705200104321'], // شريف الديب
                'rent' => 14000.00,
                'starts' => now()->subMonths(6)->startOfMonth(),
                'ends' => now()->addMonths(6)->endOfMonth(),
                'status' => 'active',
                'notes' => 'تاون هاوس زاوية بفلل الياسمين، دفع ربع سنوي',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsYasminVillas['VIL-02'],
                'renter' => $renters['29302170108901'], // رانيا زكي
                'rent' => 13500.00,
                'starts' => now()->subMonths(4)->startOfMonth(),
                'ends' => now()->addMonths(8)->endOfMonth(),
                'status' => 'active',
                'notes' => 'فيلا تاون هاوس، وديعة تأمين شهرين مودعة بالحساب',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsYasminVillas['DUP-01'],
                'renter' => $renters['28607110103456'], // حسام مصطفى
                'rent' => 9000.00,
                'starts' => now()->subMonths(7)->startOfMonth(),
                'ends' => now()->addMonths(5)->endOfMonth(),
                'status' => 'active',
                'notes' => 'دوبلكس أرضي بحديقة، صيانة الحديقة مسؤولية المستأجر',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsTower['SHP-01'],
                'renter' => $renters['28504040107654'], // شركة التكنولوجيا (محمد جمال)
                'rent' => 16000.00,
                'starts' => now()->subMonths(10)->startOfMonth(),
                'ends' => now()->addMonths(2)->endOfMonth(),
                'status' => 'active',
                'notes' => 'عقد إيجار تجاري - مقر معرض وبرمجيات',
                'current_status' => 'paid',
            ],
            [
                'unit' => $suiteWest,
                'renter' => $renters['29107220101122'], // عمرو نبيل
                'rent' => 2800.00,
                'starts' => now()->subMonths(3)->startOfMonth(),
                'ends' => now()->addMonths(9)->endOfMonth(),
                'status' => 'active',
                'notes' => 'استوديو مستقل داخل فيلا المعمورة',
                'current_status' => 'paid',
            ],

            // Expiring Soon Leases (Ending in 10-30 days to light up Dashboard Expiring Leases!)
            [
                'unit' => $unitsAlNour['APT-204'],
                'renter' => $renters['29609140109012'], // نورهان الشرقاوي
                'rent' => 4300.00,
                'starts' => now()->subMonths(11)->startOfMonth(),
                'ends' => now()->addDays(12),
                'status' => 'active',
                'notes' => 'ينتهي خلال 12 يوماً - تم إرسال إشعار التجديد عبر واتساب',
                'current_status' => 'due_soon', // Unpaid upcoming
            ],
            [
                'unit' => $unitsYasminVillas['DUP-02'],
                'renter' => $renters['29701050106789'], // ياسمين خليل
                'rent' => 9200.00,
                'starts' => now()->subMonths(11)->startOfMonth(),
                'ends' => now()->addDays(20),
                'status' => 'active',
                'notes' => 'ينتهي خلال 20 يوماً - المفاوضات جارية لزيادة القيمة الإيجارية بنسبة 10%',
                'current_status' => 'paid',
            ],
            [
                'unit' => $suiteEast,
                'renter' => $renters['29804200105566'], // هبة عصام
                'rent' => 2700.00,
                'starts' => now()->subMonths(11)->startOfMonth(),
                'ends' => now()->addDays(28),
                'status' => 'active',
                'notes' => 'ينتهي خلال 28 يوماً - تم الاتفاق على التجديد لمدة سنة إضافية',
                'current_status' => 'paid',
            ],

            // Past Expired Lease (History)
            [
                'unit' => $unitsAlNour['APT-303'],
                'renter' => $renters['28905160103344'], // مصطفى كمال
                'rent' => 3800.00,
                'starts' => now()->subMonths(16)->startOfMonth(),
                'ends' => now()->subMonths(4)->endOfMonth(),
                'status' => 'expired',
                'notes' => 'عقد منتهي وتم تسليم الوحدة وإبراء الذمة المالية بالكامل',
                'current_status' => 'none',
            ],

            // Draft Lease (Future)
            [
                'unit' => $unitsTower['SHP-02'],
                'renter' => $renters['28308180109876'], // د. وائل رضوان
                'rent' => 15000.00,
                'starts' => now()->addMonth()->startOfMonth(),
                'ends' => now()->addMonths(13)->endOfMonth(),
                'status' => 'draft',
                'notes' => 'مسودة عقد صيدلية - بانتظار استكمال التراخيص التجارية للتوقيع',
                'current_status' => 'none',
            ],
        ];

        $paymentCounter = 1;
        $allLeases = [];

        foreach ($leasesConfig as $cfg) {
            $lease = Lease::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'unit_id' => $cfg['unit']->id,
                    'renter_id' => $cfg['renter']->id,
                ],
                [
                    'starts_on' => $cfg['starts']->toDateString(),
                    'ends_on' => $cfg['ends']->toDateString(),
                    'rent_amount' => $cfg['rent'],
                    'billing_interval' => 'monthly',
                    'billing_anchor_day' => 1,
                    'first_due_date' => $cfg['starts']->toDateString(),
                    'status' => $cfg['status'],
                    'notes' => $cfg['notes'],
                ]
            );

            $allLeases[] = $lease;

            // Generate monthly rent charges and payments for the lease period
            if (in_array($cfg['status'], ['active', 'expired'])) {
                // Determine months between start and min(ends, now + 1 month)
                $chargeMonth = Carbon::parse($cfg['starts'])->startOfMonth();
                $lastChargeMonth = now()->addMonth()->startOfMonth();
                $leaseEndMonth = Carbon::parse($cfg['ends'])->startOfMonth();

                if ($lastChargeMonth->gt($leaseEndMonth)) {
                    $lastChargeMonth = $leaseEndMonth;
                }

                while ($chargeMonth->lte($lastChargeMonth)) {
                    $periodStart = $chargeMonth->copy()->startOfMonth()->toDateString();
                    $periodEnd = $chargeMonth->copy()->endOfMonth()->toDateString();
                    $dueDate = $chargeMonth->copy()->startOfMonth()->toDateString();

                    $charge = RentCharge::firstOrCreate(
                        ['lease_id' => $lease->id, 'period_start' => $periodStart],
                        [
                            'workspace_id' => $workspace->id,
                            'period_end' => $periodEnd,
                            'due_date' => $dueDate,
                            'amount' => $cfg['rent'],
                        ]
                    );

                    $isCurrentMonth = $chargeMonth->isCurrentMonth();
                    $isPastMonth = $chargeMonth->lt(now()->startOfMonth());

                    // Handle payment creation
                    if ($isPastMonth) {
                        // All past charges are 100% paid
                        $payRef = sprintf('PAY-%04d-%04d', now()->year, $paymentCounter++);
                        $payment = Payment::firstOrCreate(
                            ['workspace_id' => $workspace->id, 'reference' => $payRef],
                            [
                                'renter_id' => $cfg['renter']->id,
                                'recorded_by' => $primaryUser->id,
                                'amount' => $cfg['rent'],
                                'paid_at' => $chargeMonth->copy()->addDays(rand(1, 5))->setTime(10, rand(10, 50)),
                                'payment_method' => $paymentCounter % 3 === 0 ? 'cash' : 'bank_transfer',
                                'notes' => $paymentCounter % 3 === 0 ? 'سداد نقدي في الخزينة - سند يدوي' : 'تحويل بنكي - البنك الأهلي المصري',
                            ]
                        );

                        PaymentAllocation::firstOrCreate(
                            ['payment_id' => $payment->id, 'rent_charge_id' => $charge->id],
                            ['workspace_id' => $workspace->id, 'amount' => $cfg['rent']]
                        );
                    } elseif ($isCurrentMonth) {
                        if ($cfg['current_status'] === 'paid') {
                            $payRef = sprintf('PAY-%04d-%04d', now()->year, $paymentCounter++);
                            $payment = Payment::firstOrCreate(
                                ['workspace_id' => $workspace->id, 'reference' => $payRef],
                                [
                                    'renter_id' => $cfg['renter']->id,
                                    'recorded_by' => $primaryUser->id,
                                    'amount' => $cfg['rent'],
                                    'paid_at' => now()->startOfMonth()->addDays(rand(1, 4))->setTime(11, 30),
                                    'payment_method' => 'bank_transfer',
                                    'notes' => 'تحويل بنكي - سداد إيجار شهر '.now()->translatedFormat('F Y'),
                                ]
                            );

                            PaymentAllocation::firstOrCreate(
                                ['payment_id' => $payment->id, 'rent_charge_id' => $charge->id],
                                ['workspace_id' => $workspace->id, 'amount' => $cfg['rent']]
                            );
                        } elseif ($cfg['current_status'] === 'partial') {
                            // Partial payment: 2,000 paid
                            $partialAmount = 2000.00;
                            $payRef = sprintf('PAY-%04d-%04d', now()->year, $paymentCounter++);
                            $payment = Payment::firstOrCreate(
                                ['workspace_id' => $workspace->id, 'reference' => $payRef],
                                [
                                    'renter_id' => $cfg['renter']->id,
                                    'recorded_by' => $primaryUser->id,
                                    'amount' => $partialAmount,
                                    'paid_at' => now()->startOfMonth()->addDays(2)->setTime(14, 15),
                                    'payment_method' => 'bank_transfer',
                                    'notes' => 'سداد جزئي عبر إنستاباي، والمتبقي متفق عليه خلال أسبوع',
                                ]
                            );

                            PaymentAllocation::firstOrCreate(
                                ['payment_id' => $payment->id, 'rent_charge_id' => $charge->id],
                                ['workspace_id' => $workspace->id, 'amount' => $partialAmount]
                            );
                        } elseif ($cfg['current_status'] === 'overdue') {
                            // Update due date to 10 days ago so overdue scope picks it up immediately
                            $charge->update(['due_date' => now()->subDays(10)->toDateString()]);
                        } elseif ($cfg['current_status'] === 'due_soon') {
                            // Due in 3 days
                            $charge->update(['due_date' => now()->addDays(3)->toDateString()]);
                        }
                    }

                    $chargeMonth->addMonth();
                }
            }
        }

        // Secondary Workspace Leases, Historical Payments & Dues
        $saudiLeasesConfig = [
            [
                'unit' => $unitsSaudi['STE-101'],
                'renter' => $saudiRenter1,
                'rent' => 5000.00,
                'starts' => now()->subMonths(5)->startOfMonth(),
                'ends' => now()->addMonths(7)->endOfMonth(),
                'status' => 'active',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsSaudi['STE-102'],
                'renter' => $saudiRenter2,
                'rent' => 6000.00,
                'starts' => now()->subMonths(2)->startOfMonth(),
                'ends' => now()->addMonths(10)->endOfMonth(),
                'status' => 'active',
                'current_status' => 'due_soon',
            ],
            [
                'unit' => $unitsSaudi['VIL-01'],
                'renter' => $saudiRenter3,
                'rent' => 14000.00,
                'starts' => now()->subMonths(4)->startOfMonth(),
                'ends' => now()->addMonths(8)->endOfMonth(),
                'status' => 'active',
                'current_status' => 'paid',
            ],
            [
                'unit' => $unitsSaudi['APT-01'],
                'renter' => $saudiRenter4,
                'rent' => 4500.00,
                'starts' => now()->subMonths(3)->startOfMonth(),
                'ends' => now()->addMonths(9)->endOfMonth(),
                'status' => 'active',
                'current_status' => 'overdue',
            ],
            [
                'unit' => $unitsSaudi['OFC-301'],
                'renter' => $saudiRenter5,
                'rent' => 9000.00,
                'starts' => now()->subMonths(11)->startOfMonth(),
                'ends' => now()->addDays(18), // Expiring in 18 days!
                'status' => 'active',
                'current_status' => 'paid',
            ],
        ];

        $saudiPayCount = 1;
        foreach ($saudiLeasesConfig as $scfg) {
            $sLease = Lease::firstOrCreate(
                [
                    'workspace_id' => $secondaryWorkspace->id,
                    'unit_id' => $scfg['unit']->id,
                    'renter_id' => $scfg['renter']->id,
                ],
                [
                    'starts_on' => $scfg['starts']->toDateString(),
                    'ends_on' => $scfg['ends']->toDateString(),
                    'rent_amount' => $scfg['rent'],
                    'billing_interval' => 'monthly',
                    'billing_anchor_day' => 1,
                    'first_due_date' => $scfg['starts']->toDateString(),
                    'status' => 'active',
                    'notes' => 'عقد موثق عبر منصة إيجار بالرياض',
                ]
            );

            // Seed charges from lease start to current month
            $sMonth = $scfg['starts']->copy()->startOfMonth();
            while ($sMonth <= now()->startOfMonth()) {
                $isCurrent = $sMonth->isSameMonth(now());
                $sCharge = RentCharge::firstOrCreate(
                    [
                        'lease_id' => $sLease->id,
                        'period_start' => $sMonth->toDateString(),
                    ],
                    [
                        'workspace_id' => $secondaryWorkspace->id,
                        'period_end' => $sMonth->copy()->endOfMonth()->toDateString(),
                        'due_date' => $sMonth->toDateString(),
                        'amount' => $scfg['rent'],
                    ]
                );

                if (! $isCurrent || $scfg['current_status'] === 'paid') {
                    // Paid
                    $spRef = sprintf('PAY-SAR-%04d-%03d', $sMonth->year, $saudiPayCount++);
                    $sPayment = Payment::firstOrCreate(
                        ['workspace_id' => $secondaryWorkspace->id, 'reference' => $spRef],
                        [
                            'renter_id' => $scfg['renter']->id,
                            'recorded_by' => $secondaryOwner->id,
                            'amount' => $scfg['rent'],
                            'paid_at' => $sMonth->copy()->addDays(2)->setTime(10, 30),
                            'payment_method' => 'bank_transfer',
                            'notes' => 'سداد عبر تحويل بنكي سريع مصرف الراجحي',
                        ]
                    );

                    PaymentAllocation::firstOrCreate(
                        ['payment_id' => $sPayment->id, 'rent_charge_id' => $sCharge->id],
                        ['workspace_id' => $secondaryWorkspace->id, 'amount' => $scfg['rent']]
                    );
                } elseif ($scfg['current_status'] === 'overdue') {
                    $sCharge->update(['due_date' => now()->subDays(8)->toDateString()]);
                } elseif ($scfg['current_status'] === 'due_soon') {
                    $sCharge->update(['due_date' => now()->addDays(4)->toDateString()]);
                }

                $sMonth->addMonth();
            }
        }

        // Secondary Workspace Maintenance
        MaintenanceRequest::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'title' => 'عطل تكييف مركزي في جناح 102'],
            [
                'property_id' => $propRiyadh->id,
                'unit_id' => $unitsSaudi['STE-102']->id,
                'reported_by_renter_id' => $saudiRenter2->id,
                'description' => 'التكييف المركزي يصدر صوتاً عالياً ولا يبرد بالشكل المطلوب.',
                'status' => 'open',
                'reported_at' => now()->subDays(2),
            ]
        );

        MaintenanceRequest::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'title' => 'فحص مضخة الري واللاندسكيب - وحدات النخيل'],
            [
                'property_id' => $propNakheel->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'description' => 'فحص دوري لمضخات ري المسطحات الخضراء وشبكة التنقيط.',
                'status' => 'in_progress',
                'reported_at' => now()->subDays(4),
            ]
        );

        // Secondary Workspace Expenses
        Expense::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'notes' => 'صيانة دورية للمصاعد وأنظمة السلامة برج العليا'],
            [
                'property_id' => $propRiyadh->id,
                'recorded_by' => $secondaryOwner->id,
                'category' => 'maintenance',
                'amount' => 1800.00,
                'expense_date' => now()->subDays(5)->toDateString(),
            ]
        );

        Expense::firstOrCreate(
            ['workspace_id' => $secondaryWorkspace->id, 'notes' => 'فاتورة الكهرباء الشهرية للمناطق المشتركة برج العليا'],
            [
                'property_id' => $propRiyadh->id,
                'recorded_by' => $secondaryOwner->id,
                'category' => 'كهرباء ومياه',
                'amount' => 3200.00,
                'expense_date' => now()->subDays(7)->toDateString(),
            ]
        );

        // Secondary Workspace Message Logs
        $saudiMessages = [
            ['renter' => $saudiRenter1, 'status' => 'delivered', 'template' => 'payment_receipt_instant_ar', 'days_ago' => 2],
            ['renter' => $saudiRenter2, 'status' => 'delivered', 'template' => 'rent_due_reminder_ar', 'days_ago' => 3],
            ['renter' => $saudiRenter3, 'status' => 'delivered', 'template' => 'payment_receipt_instant_ar', 'days_ago' => 1],
            ['renter' => $saudiRenter4, 'status' => 'delivered', 'template' => 'overdue_notice_ar', 'days_ago' => 5],
            ['renter' => $saudiRenter5, 'status' => 'sent', 'template' => 'contract_renewal_ar', 'days_ago' => 1],
        ];

        foreach ($saudiMessages as $idx => $sm) {
            $dedupKey = sprintf('msg_log_%d_%s_%s', $secondaryWorkspace->id, $sm['renter']->id, $sm['template'].'_'.$idx);

            MessageLog::firstOrCreate(
                ['workspace_id' => $secondaryWorkspace->id, 'deduplication_key' => $dedupKey],
                [
                    'renter_id' => $sm['renter']->id,
                    'recipient_phone' => $sm['renter']->phone,
                    'template_name' => $sm['template'],
                    'provider_message_id' => 'SM_SAR_'.strtoupper(bin2hex(random_bytes(10))),
                    'status' => $sm['status'],
                    'provider_cost' => 0.045000,
                    'provider_cost_currency' => 'USD',
                    'scheduled_at' => now()->subDays($sm['days_ago'])->setTime(10, 0),
                    'sent_at' => now()->subDays($sm['days_ago'])->setTime(10, 1),
                    'created_at' => now()->subDays($sm['days_ago']),
                    'updated_at' => now()->subDays($sm['days_ago']),
                ]
            );
        }

        // -------------------------------------------------------------
        // 8. Maintenance Requests
        // -------------------------------------------------------------
        $maintenanceData = [
            [
                'property_id' => $propBuilding->id,
                'unit_id' => $unitsAlNour['APT-102']->id,
                'reported_by_renter_id' => $renters['29508120104567']->id, // سارة الشناوي
                'title' => 'تسريب مياه في محبس المغسلة الرئيسي',
                'description' => 'يوجد تسريب مياه مستمر أسفل حوض الحمام الرئيسي يحتاج إلى تغيير قلب المحبس والجلد العازلة.',
                'status' => 'open',
                'reported_at' => now()->subDays(2),
                'resolved_at' => null,
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => $unitsAlNour['APT-201']->id,
                'reported_by_renter_id' => $renters['29204190103456']->id, // طارق محمود
                'title' => 'عطل في لوحة مفاتيح التكييف المركزي',
                'description' => 'المكيف لا يعمل عن طريق الثرموستات الجداري، يصدر إشارة خطأ E-4 ويحتاج فحص دائرة التحكم.',
                'status' => 'in_progress',
                'reported_at' => now()->subDays(3),
                'resolved_at' => null,
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'title' => 'فحص دوري لمصعد العمارة واستبدال سيور',
                'description' => 'الصيانة الشهرية المجدولة مع شركة المصاعد المعتمدة (شندلر)، تم فحص الفرامل واستبدال حزام الأمان.',
                'status' => 'closed',
                'reported_at' => now()->subDays(12),
                'resolved_at' => now()->subDays(10),
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'title' => 'إصلاح إنارة المدخل الرئيسي والسلالم',
                'description' => 'استبدال 8 كشافات ليد موفرة تالفة بالممر الخارجي وبئر السلم بالدورين الأول والثاني.',
                'status' => 'closed',
                'reported_at' => now()->subDays(15),
                'resolved_at' => now()->subDays(14),
            ],
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'title' => 'صيانة وبرمجة بوابة الجراج الإلكترونية',
                'description' => 'حساسات الأمان لا تستجيب لريموتات السكان عند الدخول، تم إعادة معايرة المحرك وبرمجة الأجهزة.',
                'status' => 'in_progress',
                'reported_at' => now()->subDays(4),
                'resolved_at' => null,
            ],
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => $unitsYasminVillas['DUP-01']->id,
                'reported_by_renter_id' => $renters['28607110103456']->id, // حسام مصطفى
                'title' => 'تسريب مياه بسيط تحت حوض المطبخ',
                'description' => 'تسريب في ماسورة صرف حوض المطبخ عند استخدام الغسالة.',
                'status' => 'open',
                'reported_at' => now()->subDay(),
                'resolved_at' => null,
            ],
            [
                'property_id' => $propTower->id,
                'unit_id' => $unitsTower['SHP-01']->id,
                'reported_by_renter_id' => $renters['28504040107654']->id, // محمد جمال
                'title' => 'صيانة دورية لتبريد مقر المعرض التجاري',
                'description' => 'تنظيف الفلاتر وفحص ضغط غاز الفريون قبل موسم التخفيضات.',
                'status' => 'closed',
                'reported_at' => now()->subDays(8),
                'resolved_at' => now()->subDays(7),
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'reported_by_renter_id' => null,
                'title' => 'فحص ضغط مضخة المياه العلوية',
                'description' => 'شكوى من ضعف وصول المياه إلى شقق الروف والدور الأخير، جاري استبدال بالونة الضغط.',
                'status' => 'in_progress',
                'reported_at' => now()->subDay(),
                'resolved_at' => null,
            ],
        ];

        $maintenanceRequests = [];
        foreach ($maintenanceData as $md) {
            $maintenanceRequests[] = MaintenanceRequest::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'title' => $md['title'],
                ],
                [
                    'property_id' => $md['property_id'],
                    'unit_id' => $md['unit_id'],
                    'reported_by_renter_id' => $md['reported_by_renter_id'],
                    'description' => $md['description'],
                    'status' => $md['status'],
                    'reported_at' => $md['reported_at'],
                    'resolved_at' => $md['resolved_at'],
                ]
            );
        }

        // -------------------------------------------------------------
        // 9. Operating & Maintenance Expenses
        // -------------------------------------------------------------
        $expensesData = [
            // Maintenance category (linked or property-wide)
            [
                'property_id' => $propBuilding->id,
                'unit_id' => $unitsAlNour['APT-102']->id,
                'maintenance_request_id' => $maintenanceRequests[0]->id,
                'category' => 'maintenance',
                'amount' => 380.00,
                'expense_date' => now()->subDays(2)->toDateString(),
                'notes' => 'أتعاب سباك وشراء محابس وقطع غيار ألمانية لشقة 102',
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => $unitsAlNour['APT-201']->id,
                'maintenance_request_id' => $maintenanceRequests[1]->id,
                'category' => 'maintenance',
                'amount' => 750.00,
                'expense_date' => now()->subDays(3)->toDateString(),
                'notes' => 'فحص ثرموستات التكييف وشحن فريون R410A لشقة 201',
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => $maintenanceRequests[2]->id,
                'category' => 'maintenance',
                'amount' => 1200.00,
                'expense_date' => now()->subDays(10)->toDateString(),
                'notes' => 'فاتورة عقد الصيانة الشهرية لمصعد عمارة النور - شركة شندلر',
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => $maintenanceRequests[3]->id,
                'category' => 'maintenance',
                'amount' => 450.00,
                'expense_date' => now()->subDays(14)->toDateString(),
                'notes' => 'شراء كشافات ولمبات LED موفرة لإنارة المداخل وبئر السلم',
            ],
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => null,
                'maintenance_request_id' => $maintenanceRequests[4]->id,
                'category' => 'maintenance',
                'amount' => 850.00,
                'expense_date' => now()->subDays(4)->toDateString(),
                'notes' => 'صيانة لوحة تحكم وبرمجة ريموتات بوابة الجراج بفلل الياسمين',
            ],

            // Operating: Utilities (كهرباء ومياه)
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'كهرباء ومياه',
                'amount' => 1480.00,
                'expense_date' => now()->subDays(5)->toDateString(),
                'notes' => 'فاتورة كهرباء العداد المشترك (المصعد والمداخل والإنارة الخارجية)',
            ],
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'كهرباء ومياه',
                'amount' => 620.00,
                'expense_date' => now()->subDays(8)->toDateString(),
                'notes' => 'فاتورة المياه المجمعة لعمارة النور لشهر سبتمبر',
            ],
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'كهرباء ومياه',
                'amount' => 2100.00,
                'expense_date' => now()->subDays(6)->toDateString(),
                'notes' => 'استهلاك كهرباء إنارة المساحات المشتركة بفلل الياسمين',
            ],

            // Operating: Cleaning & Services (نظافة وخدمات)
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'نظافة وخدمات',
                'amount' => 600.00,
                'expense_date' => now()->subDays(11)->toDateString(),
                'notes' => 'أتعاب شركة النظافة الدورية ومكافحة الحشرات لعمارة النور',
            ],
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'نظافة وخدمات',
                'amount' => 1100.00,
                'expense_date' => now()->subDays(9)->toDateString(),
                'notes' => 'أعمال العناية بالحدائق والري الدوري بفلل الياسمين',
            ],

            // Operating: Security (أمن وحراسة)
            [
                'property_id' => $propYasminVillas->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'أمن وحراسة',
                'amount' => 3500.00,
                'expense_date' => now()->subDays(3)->toDateString(),
                'notes' => 'رواتب وأتعاب طاقم الحراسة والأمن بفلل الياسمين لشهر أكتوبر',
            ],

            // Operating: Admin & Licensing (مصاريف إدارية)
            [
                'property_id' => $propBuilding->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'إدارية وتراخيص',
                'amount' => 380.00,
                'expense_date' => now()->subDays(7)->toDateString(),
                'notes' => 'اشتراك خط إنترنت فايبر مخصص لكاميرات المراقبة السحابية',
            ],
            [
                'property_id' => $propTower->id,
                'unit_id' => null,
                'maintenance_request_id' => null,
                'category' => 'إدارية وتراخيص',
                'amount' => 550.00,
                'expense_date' => now()->subDays(13)->toDateString(),
                'notes' => 'فحص وتجديد ترخيص أنظمة إطفاء الحريق والدفاع المدني ببرج الأندلس',
            ],
        ];

        foreach ($expensesData as $exp) {
            Expense::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'notes' => $exp['notes'],
                ],
                [
                    'property_id' => $exp['property_id'],
                    'unit_id' => $exp['unit_id'],
                    'maintenance_request_id' => $exp['maintenance_request_id'],
                    'recorded_by' => $primaryUser->id,
                    'category' => $exp['category'],
                    'amount' => $exp['amount'],
                    'expense_date' => $exp['expense_date'],
                ]
            );
        }

        // -------------------------------------------------------------
        // 10. WhatsApp Message Logs
        // -------------------------------------------------------------
        $messagesData = [
            [
                'renter' => $renters['29010150102345'], // محمد علي
                'template' => 'rent_due_reminder_ar',
                'days_ago' => 6,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29010150102345'],
                'template' => 'payment_receipt_instant_ar',
                'days_ago' => 4,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29508120104567'], // سارة الشناوي
                'template' => 'rent_due_reminder_ar',
                'days_ago' => 5,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29508120104567'],
                'template' => 'payment_receipt_instant_ar',
                'days_ago' => 2,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['28803150107890'], // عمر خالد
                'template' => 'rent_due_reminder_ar',
                'days_ago' => 12,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['28803150107890'],
                'template' => 'overdue_notice_ar',
                'days_ago' => 4,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29609140109012'], // نورهان الشرقاوي
                'template' => 'contract_renewal_ar',
                'days_ago' => 3,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29204190103456'], // طارق محمود
                'template' => 'payment_receipt_instant_ar',
                'days_ago' => 3,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['28705200104321'], // شريف الديب
                'template' => 'payment_receipt_instant_ar',
                'days_ago' => 2,
                'status' => 'delivered',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29701050106789'], // ياسمين خليل
                'template' => 'contract_renewal_ar',
                'days_ago' => 1,
                'status' => 'sent',
                'cost' => 0.045000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29804200105566'], // هبة عصام
                'template' => 'rent_due_reminder_ar',
                'days_ago' => 0,
                'status' => 'pending',
                'cost' => 0.000000,
                'error_code' => null,
                'error_message' => null,
            ],
            [
                'renter' => $renters['29406080101234'], // أحمد العطار
                'template' => 'rent_due_reminder_ar',
                'days_ago' => 2,
                'status' => 'failed',
                'cost' => 0.000000,
                'error_code' => 'whatsapp_invalid_phone_number',
                'error_message' => 'الرقم غير مسجل في خدمة واتساب أو غير متاح',
            ],
        ];

        foreach ($messagesData as $idx => $m) {
            $dedupKey = sprintf('msg_log_%d_%s_%s', $workspace->id, $m['renter']->id, $m['template'].'_'.$idx);

            MessageLog::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'deduplication_key' => $dedupKey,
                ],
                [
                    'renter_id' => $m['renter']->id,
                    'recipient_phone' => $m['renter']->phone,
                    'template_name' => $m['template'],
                    'scheduled_at' => now()->subDays($m['days_ago'])->setTime(9, 30),
                    'sent_at' => $m['status'] === 'pending' ? null : now()->subDays($m['days_ago'])->setTime(9, 31),
                    'status' => $m['status'],
                    'provider_message_id' => $m['status'] === 'pending' ? null : 'wamid.HBg'.bin2hex(random_bytes(10)),
                    'error_code' => $m['error_code'],
                    'error_message' => $m['error_message'],
                    'attempts' => $m['status'] === 'pending' ? 0 : 1,
                    'provider_cost' => $m['cost'],
                    'provider_cost_currency' => 'USD',
                ]
            );
        }

        // -------------------------------------------------------------
        // 11. Activity Log (Audit Trail)
        // -------------------------------------------------------------
        if (class_exists(Activity::class)) {
            $activities = [
                ['description' => 'تم توثيق عقد إيجار جديد للوحدة APT-101 للمستأجر محمد علي إبراهيم', 'subject' => $allLeases[0] ?? null],
                ['description' => 'تم تسجيل سند قبض إلكتروني بمبلغ 4,500 ج.م للمستأجر محمد علي إبراهيم', 'subject' => $allLeases[0] ?? null],
                ['description' => 'تم فتح تذكرة صيانة طارئة: تسريب مياه في محبس المغسلة الرئيسي', 'subject' => $propBuilding],
                ['description' => 'تم تسجيل مصروف صيانة دورية لمصعد عمارة النور بمبلغ 1,200 ج.م', 'subject' => $propBuilding],
                ['description' => 'تم إرسال تذكير إيجار عبر WhatsApp للمستأجر عمر خالد عبد الرحمن', 'subject' => $workspace],
            ];

            foreach ($activities as $act) {
                Activity::firstOrCreate(
                    [
                        'log_name' => 'default',
                        'description' => $act['description'],
                    ],
                    [
                        'subject_type' => $act['subject'] ? get_class($act['subject']) : Workspace::class,
                        'subject_id' => $act['subject'] ? $act['subject']->id : $workspace->id,
                        'causer_type' => User::class,
                        'causer_id' => $primaryUser->id,
                        'properties' => ['ip' => '127.0.0.1'],
                        'created_at' => now()->subHours(rand(1, 48)),
                    ]
                );
            }
        }
    }
}

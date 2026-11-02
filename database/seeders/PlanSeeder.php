<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'باقة التجربة (Trial)',
                'slug' => 'trial',
                'monthly_price' => 0.00,
                'unit_limit' => 1,
                'parent_unit_limit' => 1,
                'active_rental_limit' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'باقة البداية (Starter)',
                'slug' => 'starter',
                'monthly_price' => 99.00,
                'unit_limit' => 3,
                'parent_unit_limit' => 3,
                'active_rental_limit' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'باقة النمو (Growth)',
                'slug' => 'growth',
                'monthly_price' => 199.00,
                'unit_limit' => 10,
                'parent_unit_limit' => 10,
                'active_rental_limit' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'باقة المحترفين (Pro)',
                'slug' => 'pro',
                'monthly_price' => 399.00,
                'unit_limit' => 25,
                'parent_unit_limit' => 25,
                'active_rental_limit' => 25,
                'is_active' => true,
            ],
            [
                'name' => 'باقة الأعمال (Business)',
                'slug' => 'business',
                'monthly_price' => 699.00,
                'unit_limit' => 50,
                'parent_unit_limit' => 50,
                'active_rental_limit' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'باقة الشركات اللامحدودة (Enterprise)',
                'slug' => 'enterprise',
                'monthly_price' => 0.00,
                'unit_limit' => null,
                'parent_unit_limit' => null,
                'active_rental_limit' => null,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}

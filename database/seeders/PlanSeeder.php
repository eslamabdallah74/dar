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
                'name' => 'باقة الانطلاق (Starter)',
                'slug' => 'starter',
                'monthly_price' => 0.00,
                'unit_limit' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'باقة النمو (Growth)',
                'slug' => 'growth',
                'monthly_price' => 199.00,
                'unit_limit' => 25,
                'is_active' => true,
            ],
            [
                'name' => 'باقة المحترفين (Pro)',
                'slug' => 'pro',
                'monthly_price' => 499.00,
                'unit_limit' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'باقة الشركات (Enterprise)',
                'slug' => 'enterprise',
                'monthly_price' => 999.00,
                'unit_limit' => null,
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

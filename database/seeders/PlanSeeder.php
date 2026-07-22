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
                'name' => 'Starter',
                'price' => 5000,
                'trial_days' => 14,
                'max_users' => 2,
                'max_products' => 100,
                'features' => ['PDF receipts', 'Email receipts', 'Basic reports'],
                'is_active' => true,
            ],
            [
                'name' => 'Growth',
                'price' => 12000,
                'trial_days' => 14,
                'max_users' => 5,
                'max_products' => null,
                'features' => ['Everything in Starter', 'WhatsApp receipts', 'Unlimited products', 'Team roles'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'price' => 25000,
                'trial_days' => 14,
                'max_users' => null,
                'max_products' => null,
                'features' => ['Everything in Growth', 'Unlimited team members', 'Priority support'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}

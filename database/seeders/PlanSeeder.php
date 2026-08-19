<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_cents' => 0,
                'rate_limit_per_minute' => 30,
                'level' => 0,
                'features' => ['articles' => true, 'videos' => false],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_cents' => 1500,
                'rate_limit_per_minute' => 120,
                'level' => 1,
                'features' => ['articles' => true, 'videos' => true],
            ],
            [
                'name' => 'Team',
                'slug' => 'team',
                'price_cents' => 4900,
                'rate_limit_per_minute' => 300,
                'level' => 2,
                'features' => ['articles' => true, 'videos' => true, 'team_seats' => true],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}

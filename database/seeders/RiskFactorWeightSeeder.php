<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RiskFactorWeight;
use App\Services\RiskRuleEvaluator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RiskFactorWeightSeeder extends Seeder
{
    public function run(): void
    {
        // Seeding must never replace an administrator's configured model.
        if (RiskFactorWeight::query()->exists()) {
            return;
        }
        $factors = config('risk_scoring.factors');
        $now = now();

        DB::transaction(function () use ($factors, $now): void {
            RiskFactorWeight::whereNotIn('factor_code', array_keys($factors))
                ->update(['is_active' => false, 'updated_at' => $now]);

            foreach ($factors as $code => $factor) {
                RiskFactorWeight::updateOrCreate(
                    ['factor_code' => $code],
                    [
                        'factor_name' => $factor['name'],
                        'rule' => RiskRuleEvaluator::defaults($code),
                        'factor_type' => $factor['type'],
                        'count_cap' => $factor['cap'],
                        'weight' => $factor['weight'],
                        'description' => $factor['description'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }
}

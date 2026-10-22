<?php

use App\Services\RiskRuleEvaluator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_factor_weights', function (Blueprint $table): void {
            $table->json('rule')->nullable();
        });
        foreach (DB::table('risk_factor_weights')->get() as $factor) {
            $rule = RiskRuleEvaluator::defaults($factor->factor_code);
            if ($rule !== null) {
                DB::table('risk_factor_weights')->where('id', $factor->id)->update(['rule' => json_encode($rule)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('risk_factor_weights', fn (Blueprint $table) => $table->dropColumn('rule'));
    }
};

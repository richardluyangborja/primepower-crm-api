<?php

namespace Database\Seeders;

use App\Models\SatisfactionSetting;
use Illuminate\Database\Seeder;

class SatisfactionSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'low_score_threshold' => '3.0',
            'at_risk_lookback_surveys' => '1',
        ];

        foreach ($defaults as $key => $value) {
            SatisfactionSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}

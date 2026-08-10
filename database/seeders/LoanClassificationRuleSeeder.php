<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LoanClassificationRule;
use Illuminate\Database\Seeder;

// Seeds the CBN prudential-guideline 5-bucket defaults (Performing / Watch-OLEM /
// Substandard / Doubtful / Lost) as editable data — an admin can retune the day
// thresholds or add custom buckets later without a deploy.
class LoanClassificationRuleSeeder extends Seeder
{
    private const RULES = [
        ['name' => 'performing',  'label' => 'Performing',  'min' => 0,   'max' => 0,    'color' => '#16a34a'],
        ['name' => 'watch',       'label' => 'Watch',       'min' => 1,   'max' => 30,   'color' => '#eab308'],
        ['name' => 'substandard', 'label' => 'Substandard', 'min' => 31,  'max' => 90,   'color' => '#f97316'],
        ['name' => 'doubtful',    'label' => 'Doubtful',    'min' => 91,  'max' => 180,  'color' => '#dc2626'],
        ['name' => 'lost',        'label' => 'Lost',        'min' => 181, 'max' => null, 'color' => '#7f1d1d'],
    ];

    public function run(): void
    {
        foreach (self::RULES as $index => $rule) {
            LoanClassificationRule::firstOrCreate(
                ['name' => $rule['name']],
                [
                    'label'             => $rule['label'],
                    'min_days_overdue'  => $rule['min'],
                    'max_days_overdue'  => $rule['max'],
                    'sort_order'        => $index + 1,
                    'color'             => $rule['color'],
                    'status'            => 'active',
                ],
            );
        }
    }
}

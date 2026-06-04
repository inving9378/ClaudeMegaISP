<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentoLevelsSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name'                    => 'Junior',
                'rank'                    => 1,
                'required_certifications' => json_encode(['count' => 1]),
                'base_salary'             => 3800.00,
                'active'                  => true,
            ],
            [
                'name'                    => 'Técnico',
                'rank'                    => 2,
                'required_certifications' => json_encode(['count' => 2]),
                'base_salary'             => 5200.00,
                'active'                  => true,
            ],
            [
                'name'                    => 'Senior',
                'rank'                    => 3,
                'required_certifications' => json_encode(['count' => 3]),
                'base_salary'             => 7000.00,
                'active'                  => true,
            ],
            [
                'name'                    => 'Expert',
                'rank'                    => 4,
                'required_certifications' => json_encode(['count' => 5]),
                'base_salary'             => 9500.00,
                'active'                  => true,
            ],
        ];

        foreach ($levels as $level) {
            DB::table('talento_levels')->updateOrInsert(
                ['rank' => $level['rank']],
                array_merge($level, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}

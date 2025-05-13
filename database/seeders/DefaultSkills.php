<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class DefaultSkills extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('skills')->truncate();

        $jsonPath = base_path('config/skills.json');

        if (file_exists($jsonPath)) {
            $raw = json_decode(file_get_contents($jsonPath), true);

            if (!is_array($raw)) {
                throw new \Exception("Invalid JSON in $jsonPath");
            }

            // Flatten the new schema: { <category_id>: [ { skill_name, description? }, ... ] }
            $data = [];
            foreach ($raw as $categoryId => $skills) {
                foreach ($skills as $skill) {
                    $entry = [
                        'skill_name' => $skill['skill_name'],
                        'category' => (int)$categoryId,
                    ];
                    if (isset($skill['description'])) {
                        $entry['description'] = $skill['description'];
                    }
                    $data[] = $entry;
                }
            }
        } else {
            // Fallback to hardcoded data
            $data = [
                ['skill_name' => 'Publicising events', 'category' => 1],
                ['skill_name' => 'Recruiting volunteers', 'category' => 1],
                ['skill_name' => 'Managing events', 'category' => 1],
                ['skill_name' => 'Finding venues', 'category' => 1],
    
                ['skill_name' => 'Software/OS', 'category' => 2],
                ['skill_name' => 'Changing a fuse', 'category' => 2],
                ['skill_name' => 'Using a multimeter', 'category' => 2],
                ['skill_name' => 'Laptop disassembly', 'category' => 2],
                ['skill_name' => 'Replacing PCB components', 'category' => 2],
                ['skill_name' => 'Headphones', 'category' => 2],
                ['skill_name' => 'Electronics safety', 'category' => 2],
                ['skill_name' => 'Replacing screens', 'category' => 2],
            ];
        }

        DB::table('skills')->insert($data);
    }
}

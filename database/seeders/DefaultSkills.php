<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DefaultSkills extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = $this->getSkillsData();

        try {
            $this->seedSkills($data);
        } catch (\Exception $e) {
            Log::error("Failed to seed skills: {$e->getMessage()}");
        }
    }

    /**
     * Get skills data from JSON file or fallback to defaults
     */
    private function getSkillsData(): array
    {
        $jsonPath = base_path('config/skills.json');

        if (file_exists($jsonPath)) {
            try {
                return $this->processJsonData($jsonPath);
            } catch (\Exception $e) {
                Log::warning("Failed to process skills JSON: {$e->getMessage()}. Using fallback data.");
                return $this->getFallbackData();
            }
        }

        return $this->getFallbackData();
    }

    /**
     * Process JSON data from file
     */
    private function processJsonData(string $jsonPath): array
    {
        $raw = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($raw)) {
            throw new \Exception("Invalid JSON in $jsonPath");
        }

        $data = [];
        foreach ($raw as $categoryId => $skills) {
            foreach ($skills as $skill) {
                $data[] = [
                    'skill_name' => $skill['skill_name'],
                    'category' => (int)$categoryId,
                    'description' => $skill['description'] ?? ''
                ];
            }
        }

        return $data;
    }

    /**
     * Get fallback skills data
     */
    private function getFallbackData(): array
    {
        return [
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

    /**
     * Seed skills to database based on configuration
     */
    private function seedSkills(array $data): void
    {
        $truncate = env('SEEDING_TRUNCATE_SKILLS', true);

        if ($truncate) {
            Log::info('Truncating and re-inserting all skills');
            DB::table('skills')->truncate();
            DB::table('skills')->insert($data);
            Log::info('Inserted ' . count($data) . ' skills');
            return;
        }

        Log::info('Merging new skills only');
        $added = 0;
        $modified = 0;

        foreach ($data as $entry) {
            $skill = DB::table('skills')->where('skill_name', $entry['skill_name'])->first();

            if (!$skill) {
                DB::table('skills')->insert($entry);
                $added++;
                continue;
            }

            if (empty($skill->description) && !empty($entry['description'])) {
                DB::table('skills')->where('id', $skill->id)->update(['description' => $entry['description']]);
                $modified++;
            }
        }

        Log::info("Added $added new skills and modified $modified skills");
    }
}

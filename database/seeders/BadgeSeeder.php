<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['slug' => 'getting-started',   'name' => 'Getting Started',    'icon' => '🌱', 'tier' => 'bronze', 'criteria_type' => 'enrolled_courses',    'criteria_value' => 1,   'description' => 'Enrolled in your first course',           'sort_order' => 1],
            ['slug' => 'halfway-there',     'name' => 'Halfway There',      'icon' => '🚀', 'tier' => 'bronze', 'criteria_type' => 'max_course_progress', 'criteria_value' => 50,  'description' => 'Reached 50% progress in a course',         'sort_order' => 2],
            ['slug' => 'course-champion',   'name' => 'Course Champion',    'icon' => '🏆', 'tier' => 'gold',   'criteria_type' => 'max_course_progress', 'criteria_value' => 100, 'description' => 'Completed a course end to end',            'sort_order' => 3],
            ['slug' => 'consistent',        'name' => 'Consistent Learner', 'icon' => '📚', 'tier' => 'silver', 'criteria_type' => 'graded_items_count',  'criteria_value' => 5,   'description' => 'Earned grades on 5 graded items',          'sort_order' => 4],
            ['slug' => 'high-achiever',     'name' => 'High Achiever',      'icon' => '⭐', 'tier' => 'gold',   'criteria_type' => 'avg_grade',           'criteria_value' => 80,  'description' => 'Maintained an average grade of 80% or higher', 'sort_order' => 5],
        ];

        foreach ($badges as $b) {
            Badge::updateOrCreate(['slug' => $b['slug']], $b);
        }
    }
}

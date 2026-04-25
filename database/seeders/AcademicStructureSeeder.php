<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\DegreeProgramme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $colleges = [
            ['id' => Str::uuid()->toString(), 'name' => 'College of Informatics and Virtual Education', 'code' => 'CIVE', 'description' => 'School of Computing and Information Technology'],
            ['id' => Str::uuid()->toString(), 'name' => 'College of Earth Sciences and Engineering', 'code' => 'COESE', 'description' => 'Geology, Mining, and Engineering programs'],
            ['id' => Str::uuid()->toString(), 'name' => 'College of Natural Sciences', 'code' => 'CONAS', 'description' => 'Mathematics, Physics, Chemistry, and Biology'],
            ['id' => Str::uuid()->toString(), 'name' => 'College of Humanities and Social Sciences', 'code' => 'CHSS', 'description' => 'Arts, Languages, and Social Sciences'],
            ['id' => Str::uuid()->toString(), 'name' => 'College of Business and Economics', 'code' => 'COBE', 'description' => 'Business, Accounting, and Economics'],
        ];

        foreach ($colleges as $college) {
            College::query()->firstOrNew(['code' => $college['code']])->forceFill($college)->save();
        }

        $programmes = [
            ['name' => 'BSc in Computer Science', 'code' => 'CS', 'college_code' => 'CIVE'],
            ['name' => 'BSc in Information Technology', 'code' => 'IT', 'college_code' => 'CIVE'],
            ['name' => 'BSc in Software Engineering', 'code' => 'SE', 'college_code' => 'CIVE'],
            ['name' => 'BSc in Computer Engineering', 'code' => 'CE', 'college_code' => 'CIVE'],
            ['name' => 'BSc in Telecommunications Engineering', 'code' => 'TE', 'college_code' => 'CIVE'],
            ['name' => 'BSc in Mining Engineering', 'code' => 'ME', 'college_code' => 'COESE'],
            ['name' => 'BSc in Petroleum Engineering', 'code' => 'PE', 'college_code' => 'COESE'],
            ['name' => 'BSc in Mathematics', 'code' => 'MTA', 'college_code' => 'CONAS'],
            ['name' => 'BSc in Physics', 'code' => 'PHY', 'college_code' => 'CONAS'],
            ['name' => 'BA in Economics', 'code' => 'ECO', 'college_code' => 'COBE'],
            ['name' => 'BA in Business Administration', 'code' => 'BA', 'college_code' => 'COBE'],
            ['name' => 'BA in Accounting', 'code' => 'ACC', 'college_code' => 'COBE'],
            ['name' => 'BA in Sociology', 'code' => 'SOC', 'college_code' => 'CHSS'],
            ['name' => 'BA in Linguistics', 'code' => 'LIN', 'college_code' => 'CHSS'],
        ];

        foreach ($programmes as $programme) {
            $college = College::where('code', $programme['college_code'])->first();
            if ($college) {
                DegreeProgramme::query()->firstOrNew(['code' => $programme['code']])->forceFill([
                    'id' => Str::uuid()->toString(),
                    'name' => $programme['name'],
                    'code' => $programme['code'],
                    'college_id' => $college->id,
                    'description' => null,
                ])->save();
            }
        }
    }
}

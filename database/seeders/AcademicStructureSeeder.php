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
            $model = College::firstOrNew(['code' => $college['code']]);
            $model->name = $college['name'];
            $model->description = $college['description'];
            // Only assign the primary key when creating — never mutate an existing
            // college's id, which would break degree_programmes.college_id FKs.
            if (! $model->exists) {
                $model->id = $college['id'];
            }
            $model->save();
        }

        $programmes = [
            // CIVE
            ['name' => 'Bachelor of Science in Computer Engineering', 'code' => 'BSC-CE', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Software Engineering', 'code' => 'BSC-SE', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Telecommunications Engineering', 'code' => 'BSC-TE', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Computer Networks and Information Security Engineering', 'code' => 'BSC-CNISE', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Cyber Security and Digital Forensics Engineering', 'code' => 'BSC-CSDFE', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Business Information Systems', 'code' => 'BSC-BIS', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Multimedia Technology and Animation', 'code' => 'BSC-MTA', 'college_code' => 'CIVE'],
            ['name' => 'Bachelor of Science in Instructional Design and Information Technology', 'code' => 'BSC-IDIT', 'college_code' => 'CIVE'],
            // CoESE
            ['name' => 'Bachelor of Science in Mining Engineering', 'code' => 'BSC-ME', 'college_code' => 'COESE'],
            ['name' => 'Bachelor of Science in Petroleum Engineering', 'code' => 'BSC-PE', 'college_code' => 'COESE'],
            ['name' => 'Bachelor of Science in Environmental Engineering', 'code' => 'BSC-EE', 'college_code' => 'COESE'],
            ['name' => 'Bachelor of Science in Renewable Energy Engineering', 'code' => 'BSC-REE', 'college_code' => 'COESE'],
            // CoNAS
            ['name' => 'Bachelor of Science in Mathematics', 'code' => 'BSC-MATH', 'college_code' => 'CONAS'],
            ['name' => 'Bachelor of Science in Physics', 'code' => 'BSC-PHY', 'college_code' => 'CONAS'],
            ['name' => 'Bachelor of Science in Chemistry', 'code' => 'BSC-CHEM', 'college_code' => 'CONAS'],
            ['name' => 'Bachelor of Science in Biology', 'code' => 'BSC-BIO', 'college_code' => 'CONAS'],
            ['name' => 'Bachelor of Science in Statistics', 'code' => 'BSC-STAT', 'college_code' => 'CONAS'],
            // CoBE
            ['name' => 'Bachelor of Arts in Economics', 'code' => 'BA-ECON', 'college_code' => 'COBE'],
            ['name' => 'Bachelor of Business Administration', 'code' => 'BBA', 'college_code' => 'COBE'],
            ['name' => 'Bachelor of Commerce in Accounting', 'code' => 'BCOM-ACC', 'college_code' => 'COBE'],
            ['name' => 'Bachelor of Commerce in Finance', 'code' => 'BCOM-FIN', 'college_code' => 'COBE'],
            // CHSS
            ['name' => 'Bachelor of Arts in Sociology', 'code' => 'BA-SOC', 'college_code' => 'CHSS'],
            ['name' => 'Bachelor of Arts in English', 'code' => 'BA-ENG', 'college_code' => 'CHSS'],
            ['name' => 'Bachelor of Arts in History', 'code' => 'BA-HIST', 'college_code' => 'CHSS'],
            ['name' => 'Bachelor of Arts in Linguistics', 'code' => 'BA-LING', 'college_code' => 'CHSS'],
        ];

        foreach ($programmes as $programme) {
            $college = College::where('code', $programme['college_code'])->first();
            if ($college) {
                $model = DegreeProgramme::firstOrNew(['code' => $programme['code']]);
                $model->name = $programme['name'];
                $model->college_id = $college->id;
                $model->description = null;
                // Assign a fresh id only on creation; keep existing rows' ids stable.
                if (! $model->exists) {
                    $model->id = Str::uuid()->toString();
                }
                $model->save();
            }
        }
    }
}

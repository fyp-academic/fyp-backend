<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AiAtRiskStudent;
use App\Models\AiContentRecommendation;
use App\Models\AiPerformanceSnapshot;
use App\Models\AiRecommendation;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\Category;
use App\Models\College;
use App\Models\Course;
use App\Models\DashboardEngagement;
use App\Models\DegreeProgramme;
use App\Models\Enrollment;
use App\Models\ForumDiscussion;
use App\Models\ForumPost;
use App\Models\GradeItem;
use App\Models\Instructor;
use App\Models\LearnerProfile;
use App\Models\LessonPage;
use App\Models\Notification;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserActivityCompletion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private array $collegeMap = [];
    private array $programmeMap = [];
    private ?string $mainInstructorId = null;
    private array $instructorIds = [];
    private array $studentIds = [];
    private array $studentProfiles = [];
    private array $courseIds = [];
    private array $richCourseIds = [];
    private array $genericCourseIds = [];
    private array $courseInstructor = [];
    private array $activityIds = [];
    private array $gradeItemIds = [];
    private array $sessionIds = [];
    private array $lessonActivityIds = [];
    private array $quizActivityIds = [];

    public function run(): void
    {
        $this->command->info('Seeding comprehensive UDOM demo data...');
        $this->resolveAcademicStructure();
        $this->createUsers();
        $this->createInstructorProfiles();
        $this->createCourses();
        $this->linkCoursesToProgrammes();
        $this->createSectionsAndActivities();
        $this->createLessonPages();
        $this->createQuizQuestions();
        $this->createEnrollments();
        $this->createForumData();
        $this->createAssignmentSubmissionsAndGrades();
        $this->createAttendanceData();
        $this->createDashboardEngagement();
        $this->createActivityCompletions();
        $this->createAiAtRiskData();
        $this->createAiPerformanceSnapshots();
        $this->createAiRecommendations();
        $this->createLearnerProfiles();
        $this->createNotifications();
        $this->command->info('UDOM demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('Main credentials:');
        $this->command->info('  HIGH   - john@demo.com   (password: @demo123)');
        $this->command->info('  MEDIUM - grace@demo.com   (password: @demo123)');
        $this->command->info('  AT-RISK- peter@demo.com   (password: @demo123)');
        $this->command->info('Instructor: sarah@demo.com  (password: @demo123)');
        $this->command->info('Admin:      admin@demo.com  (password: @demo123)');
    }

    private function resolveAcademicStructure(): void
    {
        foreach (['CIVE','COESE','CONAS','CHSS','COBE'] as $code) {
            $c = College::where('code', $code)->first();
            if (! $c) throw new \RuntimeException("College {$code} not found. Run AcademicStructureSeeder first.");
            $this->collegeMap[$code] = $c->id;
        }
        $codes = ['BSC-CE','BSC-SE','BSC-TE','BSC-CNISE','BSC-CSDFE','BSC-BIS','BSC-MTA','BSC-IDIT','BSC-ME','BSC-PE','BSC-EE','BSC-REE','BSC-MATH','BSC-PHY','BSC-CHEM','BSC-BIO','BSC-STAT','BA-ECON','BBA','BCOM-ACC','BCOM-FIN','BA-SOC','BA-ENG','BA-HIST','BA-LING'];
        foreach ($codes as $code) {
            $p = DegreeProgramme::where('code', $code)->first();
            if ($p) $this->programmeMap[$code] = $p->id;
        }
    }

    private function createUsers(): void
    {
        $now = now();
        $common = ['password' => Hash::make('@demo123'), 'email_verified_at' => $now, 'verification_code_expires_at' => $now, 'remember_token' => Str::random(10), 'institution' => 'University of Dodoma', 'country' => 'Tanzania', 'timezone' => 'Africa/Dar_es_Salaam', 'language' => 'en'];
        $admin = User::firstOrNew(['email' => 'admin@demo.com']);
        $admin->forceFill(array_merge($common, ['id' => $admin->id ?? Str::uuid()->toString(), 'name' => 'Mr. Frank Kavishe', 'initials' => 'FK', 'email' => 'admin@demo.com', 'role' => 'admin', 'department' => 'IT Administration', 'nationality' => 'Tanzanian', 'gender' => 'male']))->save();
        $insts = [
            ['sarah@demo.com','Dr. Sarah Mwakasege','SM','School of Computing','female',['BSC-CE','BSC-SE','BSC-TE','BSC-CNISE','BSC-CSDFE','BSC-BIS','BSC-MTA','BSC-IDIT']],
            ['james@demo.com','Prof. James Kikwete','JK','School of Earth Sciences','male',['BSC-ME','BSC-PE','BSC-EE','BSC-REE']],
            ['mary@demo.com','Dr. Mary Nyerere','MN','School of Natural Sciences','female',['BSC-MATH','BSC-PHY','BSC-CHEM','BSC-BIO','BSC-STAT']],
            ['david@demo.com','Mr. David Magufuli','DM','School of Business','male',['BA-ECON','BBA','BCOM-ACC','BCOM-FIN']],
            ['anna@demo.com','Dr. Anna Mwinyi','AM','School of Humanities','female',['BA-SOC','BA-ENG','BA-HIST','BA-LING']],
        ];
        foreach ($insts as $i) {
            $u = User::firstOrNew(['email' => $i[0]]);
            $u->forceFill(array_merge($common, ['id' => $u->id ?? Str::uuid()->toString(), 'name' => $i[1], 'initials' => $i[2], 'email' => $i[0], 'role' => 'instructor', 'department' => $i[3], 'nationality' => 'Tanzanian', 'gender' => $i[4], 'degree_programme_id' => null]))->save();
            $this->instructorIds[explode('@',$i[0])[0]] = $u->id;
        }
        $this->mainInstructorId = $this->instructorIds['sarah'];
        $students = [
            ['john@demo.com','John Mwale','UDOM/CIVE/BSC-CE/2022/001','BSC-CE',2,'male','+255712345001','Passionate about software engineering and AI.','high'],
            ['grace@demo.com','Grace Njau','UDOM/CIVE/BSC-SE/2022/045','BSC-SE',2,'female','+255712345045','Balancing studies with part-time work.','medium'],
            ['peter@demo.com','Peter Masanja','UDOM/CIVE/BSC-TE/2022/089','BSC-TE',2,'male','+255712345089','Struggling to keep up with coursework.','atrisk'],
            ['joseph@demo.com','Joseph Temu','UDOM/CIVE/BSC-CNISE/2022/012','BSC-CNISE',2,'male','+255712345012','Loves networking and cybersecurity.','high'],
            ['alice@demo.com','Alice Mushi','UDOM/CIVE/BSC-CSDFE/2022/034','BSC-CSDFE',2,'female','+255712345034','Interested in digital forensics.','medium'],
            ['brian@demo.com','Brian Olomi','UDOM/CIVE/BSC-BIS/2022/056','BSC-BIS',3,'male','+255712345056','Building mobile apps in Flutter.','medium'],
            ['catherine@demo.com','Catherine Lema','UDOM/CIVE/BSC-MTA/2022/078','BSC-MTA',2,'female','+255712345078','Hardware enthusiast and robotics fan.','high'],
            ['emmanuel@demo.com','Emmanuel Mrosso','UDOM/CONAS/BSC-MATH/2022/011','BSC-MATH',2,'male','+255712345011','Math olympiad winner. Loves pure maths.','high'],
            ['fatima@demo.com','Fatima Khatib','UDOM/CONAS/BSC-PHY/2022/033','BSC-PHY',2,'female','+255712345033','Studying applied physics and modeling.','medium'],
            ['george@demo.com','George Malecela','UDOM/CONAS/BSC-CHEM/2022/055','BSC-CHEM',3,'male','+255712345055','Aspiring chemist. Enjoys lab experiments.','medium'],
            ['halima@demo.com','Halima Mohamed','UDOM/COBE/BA-ECON/2022/010','BA-ECON',2,'female','+255712345010','Interested in development economics.','high'],
            ['isaac@demo.com','Isaac Mwambene','UDOM/COBE/BBA/2022/032','BBA',2,'male','+255712345032','Future entrepreneur. Loves marketing.','medium'],
            ['joyce@demo.com','Joyce Mrema','UDOM/COBE/BCOM-ACC/2022/054','BCOM-ACC',3,'female','+255712345054','Aspiring CPA. Detail-oriented.','high'],
            ['kelvin@demo.com','Kelvin Ndalahwa','UDOM/COESE/BSC-ME/2022/009','BSC-ME',2,'male','+255712345009','Interested in sustainable mining.','medium'],
            ['linda@demo.com','Linda Mgonja','UDOM/COESE/BSC-PE/2022/031','BSC-PE',2,'female','+255712345031','Petroleum engineering student.','medium'],
            ['marcus@demo.com','Marcus Shoo','UDOM/CHSS/BA-SOC/2022/008','BA-SOC',2,'male','+255712345008','Community development advocate.','high'],
            ['nancy@demo.com','Nancy Sumari','UDOM/CHSS/BA-LING/2022/030','BA-LING',2,'female','+255712345030','Linguistics and Swahili studies.','medium'],
            ['omar@demo.com','Omar Kigoda','UDOM/COESE/BSC-EE/2022/057','BSC-EE',3,'male','+255712345057','Environmental engineering enthusiast.','medium'],
            ['paul@demo.com','Paul Bomani','UDOM/COESE/BSC-REE/2022/099','BSC-REE',2,'male','+255712345099','Interested in renewable energy systems.','medium'],
            ['quinn@demo.com','Quinn Mwinyi','UDOM/CIVE/BSC-IDIT/2022/110','BSC-IDIT',2,'female','+255712345110','Top of class. Helps peers regularly.','high'],
        ];
        foreach ($students as $s) {
            $u = User::firstOrNew(['email' => $s[0]]);
            $initials = implode('', array_map(fn($n)=>$n[0], explode(' ', $s[1])));
            $u->forceFill(array_merge($common, ['id' => $u->id ?? Str::uuid()->toString(), 'name' => $s[1], 'initials' => $initials, 'email' => $s[0], 'role' => 'student', 'registration_number' => $s[2], 'degree_programme_id' => $this->programmeMap[$s[3]] ?? null, 'year_of_study' => $s[4], 'education_level' => 'undergraduate', 'nationality' => 'Tanzanian', 'gender' => $s[5], 'phone_number' => $s[6], 'bio' => $s[7]]))->save();
            $key = explode('@',$s[0])[0];
            $this->studentIds[$key] = $u->id;
            $this->studentProfiles[$u->id] = $s[8];
        }
    }

    private function createInstructorProfiles(): void
    {
        $data = ['sarah'=>['rank'=>'senior_lecturer','spec'=>'Artificial Intelligence','college'=>'CIVE'],'james'=>['rank'=>'professor','spec'=>'Mining Engineering','college'=>'COESE'],'mary'=>['rank'=>'lecturer','spec'=>'Applied Mathematics','college'=>'CONAS'],'david'=>['rank'=>'assistant_lecturer','spec'=>'Business Management','college'=>'COBE'],'anna'=>['rank'=>'senior_lecturer','spec'=>'Sociology','college'=>'CHSS']];
        foreach ($data as $key => $d) {
            $uid = $this->instructorIds[$key];
            $inst = Instructor::firstOrNew(['user_id' => $uid]);
            $inst->forceFill(['id' => $inst->id ?? Str::uuid()->toString(), 'user_id' => $uid, 'full_name' => User::find($uid)->name, 'gender' => User::find($uid)->gender, 'nationality' => 'Tanzanian', 'phone_number' => '+25571234'.rand(1000,9999), 'staff_id' => 'UDOM/STAFF/'.strtoupper($d['college']).'/'.rand(100,999), 'employment_type' => 'full-time', 'academic_rank' => $d['rank'], 'college_id' => $this->collegeMap[$d['college']] ?? null, 'date_of_employment' => Carbon::parse('2015-01-01')->addYears(rand(0,8)), 'highest_qualification' => 'PhD', 'field_of_specialization' => $d['spec'], 'awarding_institution' => 'University of Dodoma', 'year_of_graduation' => 2010+rand(0,10), 'bio' => 'Experienced academic with expertise in '.$d['spec'].'.', 'office_location' => $d['college'].' Block, Room '.rand(101,320), 'office_hours' => 'Mon-Fri 08:00 - 16:00', 'account_status' => 'active'])->save();
        }
    }

    private function createCourses(): void
    {
        $csCat = Category::firstOrNew(['name' => 'Computer Science']);
        $csCat->forceFill(['id' => $csCat->id ?? Str::uuid()->toString(), 'name' => 'Computer Science', 'description' => 'Core computer science courses at UDOM'])->save();
        $genCat = Category::firstOrNew(['name' => 'General Education']);
        $genCat->forceFill(['id' => $genCat->id ?? Str::uuid()->toString(), 'name' => 'General Education', 'description' => 'Courses across all UDOM colleges'])->save();
        $rich = [
            ['CS 101','Programming Fundamentals','Introduction to programming using Python and C.','2025-01-15','2025-05-30','sarah','CIVE',$csCat],
            ['CS 201','Data Structures and Algorithms','Arrays, linked lists, stacks, queues, trees, graphs.','2025-01-15','2025-05-30','sarah','CIVE',$csCat],
            ['CS 205','Database Systems','Relational database design, SQL, normalization.','2025-01-15','2025-05-30','sarah','CIVE',$csCat],
        ];
        foreach ($rich as $c) {
            $course = Course::firstOrNew(['short_name' => $c[0]]);
            $iid = $this->instructorIds[$c[5]];
            $course->forceFill(['id' => $course->id ?? Str::uuid()->toString(), 'name' => $c[1], 'short_name' => $c[0], 'description' => $c[2], 'category_id' => $c[7]->id, 'category_name' => $c[7]->name, 'college_id' => $this->collegeMap[$c[6]], 'instructor_id' => $iid, 'instructor_name' => User::find($iid)->name ?? 'Instructor', 'status' => 'active', 'visibility' => 'shown', 'format' => 'topics', 'start_date' => $c[3], 'end_date' => $c[4], 'language' => 'English', 'tags' => json_encode(['undergraduate',$c[6],'core']), 'max_students' => 120, 'enrolled_students' => 0])->save();
            $this->richCourseIds[] = $course->id;
            $this->courseIds[] = $course->id;
            $this->courseInstructor[$course->id] = $iid;
        }
        $generic = [
            ['IT 101','Introduction to Information Technology','IT fundamentals, hardware, software, networking.','2025-01-15','2025-05-30','sarah','CIVE',$csCat],
            ['SE 201','Software Engineering Principles','SDLC, Agile, requirements engineering.','2025-01-15','2025-05-30','sarah','CIVE',$csCat],
            ['CE 301','Digital Logic Design','Boolean algebra, combinational and sequential circuits.','2025-02-01','2025-06-15','sarah','CIVE',$csCat],
            ['MTA 101','Calculus I','Limits, derivatives, integrals, and applications.','2025-01-15','2025-05-30','mary','CONAS',$genCat],
            ['ECO 101','Principles of Economics','Microeconomics and macroeconomics fundamentals.','2025-01-15','2025-05-30','david','COBE',$genCat],
            ['SOC 101','Introduction to Sociology','Social structures, institutions, research methods.','2025-02-01','2025-06-15','anna','CHSS',$genCat],
            ['ME 201','Mine Ventilation','Underground ventilation systems and safety.','2025-01-15','2025-05-30','james','COESE',$genCat],
        ];
        foreach ($generic as $c) {
            $course = Course::firstOrNew(['short_name' => $c[0]]);
            $iid = $this->instructorIds[$c[5]];
            $course->forceFill(['id' => $course->id ?? Str::uuid()->toString(), 'name' => $c[1], 'short_name' => $c[0], 'description' => $c[2], 'category_id' => $c[7]->id, 'category_name' => $c[7]->name, 'college_id' => $this->collegeMap[$c[6]], 'instructor_id' => $iid, 'instructor_name' => User::find($iid)->name ?? 'Instructor', 'status' => 'active', 'visibility' => 'shown', 'format' => 'topics', 'start_date' => $c[3], 'end_date' => $c[4], 'language' => 'English', 'tags' => json_encode(['undergraduate',$c[6],'core']), 'max_students' => 100, 'enrolled_students' => 0])->save();
            $this->genericCourseIds[] = $course->id;
            $this->courseIds[] = $course->id;
            $this->courseInstructor[$course->id] = $iid;
        }
    }

    private function linkCoursesToProgrammes(): void
    {
        $civeProgs = ['BSC-CE','BSC-SE','BSC-TE','BSC-CNISE','BSC-CSDFE','BSC-BIS','BSC-MTA','BSC-IDIT'];
        $map = [
            'CS 101' => $civeProgs,
            'CS 201' => ['BSC-CE','BSC-SE','BSC-TE','BSC-CNISE','BSC-CSDFE'],
            'CS 205' => ['BSC-CE','BSC-SE','BSC-BIS','BSC-MTA','BSC-IDIT'],
            'IT 101' => $civeProgs,
            'SE 201' => ['BSC-SE','BSC-CE','BSC-CNISE','BSC-CSDFE'],
            'CE 301' => ['BSC-CE','BSC-TE','BSC-CNISE'],
            'MTA 101' => ['BSC-MATH','BSC-PHY','BSC-CHEM','BSC-BIO','BSC-STAT'],
            'ECO 101' => ['BA-ECON','BBA','BCOM-ACC','BCOM-FIN'],
            'SOC 101' => ['BA-SOC','BA-ENG','BA-HIST','BA-LING'],
            'ME 201' => ['BSC-ME','BSC-PE','BSC-EE','BSC-REE'],
        ];
        foreach ($map as $sn => $codes) {
            $course = Course::where('short_name', $sn)->first(); if (! $course) continue;
            foreach ($codes as $code) {
                $pid = $this->programmeMap[$code] ?? null; if (! $pid) continue;
                if (! DB::table('course_degree_programme')->where('course_id', $course->id)->where('degree_programme_id', $pid)->exists()) DB::table('course_degree_programme')->insert(['course_id' => $course->id, 'degree_programme_id' => $pid]);
            }
        }
        $instMap = ['sarah'=>$civeProgs,'james'=>['BSC-ME','BSC-PE','BSC-EE','BSC-REE'],'mary'=>['BSC-MATH','BSC-PHY','BSC-CHEM','BSC-BIO','BSC-STAT'],'david'=>['BA-ECON','BBA','BCOM-ACC','BCOM-FIN'],'anna'=>['BA-SOC','BA-ENG','BA-HIST','BA-LING']];
        foreach ($instMap as $key => $codes) {
            $iid = $this->instructorIds[$key];
            foreach ($codes as $code) {
                $pid = $this->programmeMap[$code] ?? null; if (! $pid) continue;
                if (! DB::table('degree_programme_instructor')->where('instructor_id', $iid)->where('degree_programme_id', $pid)->exists()) DB::table('degree_programme_instructor')->insert(['instructor_id' => $iid, 'degree_programme_id' => $pid]);
            }
        }
    }

    private function createSectionsAndActivities(): void
    {
        $richTitles = ['Week 1-4: Basics','Week 5-8: Intermediate','Week 9-12: Advanced'];
        foreach ($this->richCourseIds as $idx => $cid) {
            $iid = $this->courseInstructor[$cid];
            foreach ($richTitles as $sort => $title) {
                $sec = Section::firstOrNew(['course_id' => $cid, 'title' => $title]);
                $sec->forceFill(['id' => $sec->id ?? Str::uuid()->toString(), 'course_id' => $cid, 'title' => $title, 'summary' => 'Topics covered during '.strtolower($title), 'sort_order' => $sort, 'visible' => true])->save();
                foreach (['lesson','quiz','assign','forum'] as $a => $type) {
                    $name = match($type){'lesson'=>'Lecture '.($sort+1).'.'.($idx+1),'quiz'=>'Quiz '.($sort+1).'.'.($idx+1),'assign'=>'Assignment '.($sort+1).'.'.($idx+1),'forum'=>'Discussion Forum '.($sort+1).'.'.($idx+1)};
                    $this->makeActivity($cid, $sec->id, $iid, $type, $name, $sort, $a);
                }
            }
        }
        $genTitles = ['Part 1: Foundations','Part 2: Applications'];
        foreach ($this->genericCourseIds as $idx => $cid) {
            $iid = $this->courseInstructor[$cid];
            foreach ($genTitles as $sort => $title) {
                $sec = Section::firstOrNew(['course_id' => $cid, 'title' => $title]);
                $sec->forceFill(['id' => $sec->id ?? Str::uuid()->toString(), 'course_id' => $cid, 'title' => $title, 'summary' => 'Topics covered during '.strtolower($title), 'sort_order' => $sort, 'visible' => true])->save();
                foreach (['lesson','quiz','assign','forum'] as $a => $type) {
                    $name = match($type){'lesson'=>'Lecture '.($sort+1).'.'.($idx+1),'quiz'=>'Quiz '.($sort+1).'.'.($idx+1),'assign'=>'Assignment '.($sort+1).'.'.($idx+1),'forum'=>'Discussion Forum '.($sort+1).'.'.($idx+1)};
                    $this->makeActivity($cid, $sec->id, $iid, $type, $name, $sort, $a);
                }
            }
        }
    }

    private function makeActivity(string $cid, string $sid, string $iid, string $type, string $name, int $sSort, int $aSort): void
    {
        $act = Activity::firstOrNew(['section_id' => $sid, 'course_id' => $cid, 'name' => $name]);
        $act->forceFill(['id' => $act->id ?? Str::uuid()->toString(), 'section_id' => $sid, 'course_id' => $cid, 'type' => $type, 'name' => $name, 'description' => 'Activity for '.$name, 'due_date' => Carbon::parse('2025-01-15')->addWeeks($sSort*3+2+$aSort), 'visible' => true, 'grade_max' => 100, 'sort_order' => $aSort])->save();
        $this->activityIds[$cid][] = $act->id;
        if ($type === 'lesson') $this->lessonActivityIds[$cid][$sSort] = $act->id;
        if ($type === 'quiz') $this->quizActivityIds[$cid][$sSort] = $act->id;
        if ($type === 'assign') {
            $gi = GradeItem::firstOrNew(['course_id' => $cid, 'activity_id' => $act->id]);
            $gi->forceFill(['id' => $gi->id ?? Str::uuid()->toString(), 'course_id' => $cid, 'activity_id' => $act->id, 'activity_name' => $name, 'activity_type' => $type, 'grade_max' => 100])->save();
            $this->gradeItemIds[$cid][] = $gi->id;
        }
        if ($type === 'forum') {
            $disc = ForumDiscussion::firstOrNew(['activity_id' => $act->id, 'course_id' => $cid]);
            $disc->forceFill(['id' => $disc->id ?? Str::uuid()->toString(), 'activity_id' => $act->id, 'course_id' => $cid, 'user_id' => $iid, 'title' => 'General Discussion: '.$name, 'pinned' => $sSort===0, 'locked' => false, 'post_count' => 0])->save();
        }
    }

    private function createLessonPages(): void
    {
        $rich = [0=>[0=>[['Introduction to Programming','<h2>What is Programming?</h2><p>Programming is the process of creating instructions for computers.</p>'],['Variables and Data Types','<h2>Variables</h2><p>Variables are containers for storing data values.</p>'],['Basic Operators','<h2>Operators</h2><p>Arithmetic, comparison, and logical operators.</p>']],1=>[['Control Structures','<h2>If Statements</h2><p>Conditional statements allow programs to make decisions.</p>'],['Loops','<h2>For and While Loops</h2><p>Loops allow us to repeat actions efficiently.</p>'],['Functions','<h2>Defining Functions</h2><p>Functions are reusable blocks of code.</p>']],2=>[['File I/O','<h2>Reading and Writing Files</h2><p>Python makes it easy to work with files.</p>'],['Error Handling','<h2>Try-Except Blocks</h2><p>Handle errors gracefully.</p>']]],1=>[0=>[['Arrays and Memory','<h2>Arrays</h2><p>Arrays are contiguous blocks of memory.</p>'],['Linked Lists','<h2>Singly Linked List</h2><p>Nodes with data and next pointer.</p>']],1=>[['Stacks and Queues','<h2>Stack (LIFO) and Queue (FIFO)</h2><p>Core data structures.</p>'],['Binary Trees','<h2>Tree Terminology</h2><p>Root, leaf, height, BST.</p>'],['Heaps','<h2>Min Heap / Max Heap</h2><p>Complete binary trees for priority queues.</p>']],2=>[['Graphs','<h2>Graph Representations</h2><p>Adjacency matrix and list.</p>'],['Sorting Algorithms','<h2>Common Sorting</h2><p>Bubble, merge, quick sort complexities.</p>']]],2=>[0=>[['Introduction to Databases','<h2>DBMS</h2><p>Software to capture and analyze data.</p>'],['ER Diagrams','<h2>ER Model</h2><p>Entities, attributes, relationships.</p>']],1=>[['SQL Basics','<h2>SELECT Statement</h2><p>Retrieving data from tables.</p>'],['Normalization','<h2>Normal Forms</h2><p>1NF, 2NF, 3NF, BCNF.</p>'],['JOIN Operations','<h2>SQL JOINs</h2><p>Combining tables.</p>']],2=>[['Transactions','<h2>ACID Properties</h2><p>Atomicity, Consistency, Isolation, Durability.</p>'],['NoSQL Databases','<h2>Types of NoSQL</h2><p>Document, Key-Value, Graph.</p>']]]];
        foreach ($this->richCourseIds as $ci => $cid) {
            if (! isset($this->lessonActivityIds[$cid])) continue;
            foreach ($this->lessonActivityIds[$cid] as $si => $aid) {
                $pages = $rich[$ci][$si] ?? [];
                foreach ($pages as $pi => $p) LessonPage::firstOrNew(['activity_id'=>$aid,'title'=>$p[0]])->forceFill(['id'=>Str::uuid()->toString(),'activity_id'=>$aid,'title'=>$p[0],'content'=>$p[1],'page_type'=>'content','sort_order'=>$pi])->save();
            }
        }
        foreach ($this->genericCourseIds as $cid) {
            if (! isset($this->lessonActivityIds[$cid])) continue;
            foreach ($this->lessonActivityIds[$cid] as $si => $aid) {
                for ($i=0; $i<2; $i++) {
                    $title = 'Lecture Page '.($i+1);
                    LessonPage::firstOrNew(['activity_id'=>$aid,'title'=>$title])->forceFill(['id'=>Str::uuid()->toString(),'activity_id'=>$aid,'title'=>$title,'content'=>'<h2>'.$title.'</h2><p>Auto-generated content for this lesson section.</p>','page_type'=>'content','sort_order'=>$i])->save();
                }
            }
        }
    }

    private function createQuizQuestions(): void
    {
        $rich = [0=>[0=>[['What is the correct way to declare a variable in Python?',['int x = 5'=>0,'x = 5'=>1,'var x = 5'=>0,'let x = 5'=>0]],['Which is NOT a valid Python data type?',['int'=>0,'str'=>0,'array'=>1,'float'=>0]]],1=>[['Output of for i in range(3): print(i)?',['1 2 3'=>0,'0 1 2'=>1,'0 1 2 3'=>0,'Error'=>0]],['Keyword to define a function?',['function'=>0,'def'=>1,'func'=>0,'define'=>0]]],2=>[['Purpose of try-except?',['Optimize performance'=>0,'Handle exceptions'=>1,'Import modules'=>0,'Define classes'=>0]]]],1=>[0=>[['Time complexity of array access by index?',['O(1)'=>1,'O(n)'=>0,'O(log n)'=>0,'O(n2)'=>0]],['Singly linked list node contains?',['Data only'=>0,'Data and next pointer'=>1,'Data and both pointers'=>0,'Pointer only'=>0]]],1=>[['Which follows LIFO?',['Queue'=>0,'Stack'=>1,'Linked List'=>0,'Array'=>0]],['In BST, left child is?',['Greater'=>0,'Smaller'=>1,'Equal'=>0,'Random'=>0]]],2=>[['Worst-case O(n log n) sort?',['Bubble Sort'=>0,'Quick Sort'=>0,'Merge Sort'=>1,'Insertion Sort'=>0]]]],2=>[0=>[['What does DBMS stand for?',['Database Management System'=>1,'Data Backup Service'=>0,'Database Modeling Software'=>0,'Digital Business Suite'=>0]],['Diamond shape in ER diagram?',['Entity'=>0,'Attribute'=>0,'Relationship'=>1,'Key'=>0]]],1=>[['SQL keyword to retrieve data?',['GET'=>0,'RETRIEVE'=>0,'SELECT'=>1,'FETCH'=>0]],['Which NF eliminates transitive dependencies?',['1NF'=>0,'2NF'=>0,'3NF'=>1,'4NF'=>0]]],2=>[['ACID property for valid state transition?',['Atomicity'=>0,'Consistency'=>1,'Isolation'=>0,'Durability'=>0]]]]];
        foreach ($this->richCourseIds as $ci => $cid) {
            if (! isset($this->quizActivityIds[$cid])) continue;
            foreach ($this->quizActivityIds[$cid] as $si => $aid) {
                $qs = $rich[$ci][$si] ?? [];
                foreach ($qs as $qi => $q) {
                    $qq = QuizQuestion::firstOrNew(['activity_id'=>$aid,'question_text'=>$q[0]]);
                    $qq->forceFill(['id'=>$qq->id??Str::uuid()->toString(),'activity_id'=>$aid,'course_id'=>$cid,'type'=>'multiple_choice','question_text'=>$q[0],'default_mark'=>1,'shuffle_answers'=>true,'penalty'=>0.33])->save();
                    $ai = 0;
                    foreach ($q[1] as $text => $frac) QuizAnswer::firstOrNew(['question_id'=>$qq->id,'text'=>$text])->forceFill(['id'=>Str::uuid()->toString(),'question_id'=>$qq->id,'text'=>$text,'grade_fraction'=>$frac,'sort_order'=>$ai++])->save();
                }
            }
        }
        foreach ($this->genericCourseIds as $cid) {
            if (! isset($this->quizActivityIds[$cid])) continue;
            foreach ($this->quizActivityIds[$cid] as $si => $aid) {
                for ($i=0; $i<2; $i++) {
                    $qtext = 'Question '.($i+1).': What is the key concept discussed in this section?';
                    $qq = QuizQuestion::firstOrNew(['activity_id'=>$aid,'question_text'=>$qtext]);
                    $qq->forceFill(['id'=>$qq->id??Str::uuid()->toString(),'activity_id'=>$aid,'course_id'=>$cid,'type'=>'multiple_choice','question_text'=>$qtext,'default_mark'=>1,'shuffle_answers'=>true,'penalty'=>0.33])->save();
                    $answers = [['Option A (Correct)'=>1],['Option B'=>0],['Option C'=>0],['Option D'=>0]];
                    foreach ($answers as $ai => $a) foreach ($a as $text => $frac) QuizAnswer::firstOrNew(['question_id'=>$qq->id,'text'=>$text])->forceFill(['id'=>Str::uuid()->toString(),'question_id'=>$qq->id,'text'=>$text,'grade_fraction'=>$frac,'sort_order'=>$ai])->save();
                }
            }
        }
    }

    private function createEnrollments(): void
    {
        $pToC = [];
        foreach ($this->courseIds as $cid) {
            foreach (DB::table('course_degree_programme')->where('course_id', $cid)->pluck('degree_programme_id') as $pid) $pToC[$pid][] = $cid;
        }
        foreach ($this->studentIds as $key => $sid) {
            $profile = $this->studentProfiles[$sid];
            $pid = User::find($sid)->degree_programme_id;
            $courses = $pToC[$pid] ?? $this->courseIds;
            $progress = match($profile){'high'=>85.0,'medium'=>60.0,'atrisk'=>25.0};
            $lastAccess = match($profile){'high'=>now()->subDays(1),'medium'=>now()->subDays(4),'atrisk'=>now()->subDays(18)};
            foreach ($courses as $cid) {
                $en = Enrollment::firstOrNew(['user_id'=>$sid,'course_id'=>$cid]);
                $en->forceFill(['id'=>$en->id??Str::uuid()->toString(),'user_id'=>$sid,'course_id'=>$cid,'role'=>'student','status'=>'active','enrolled_date'=>'2025-01-10','last_access'=>$lastAccess,'progress'=>$progress])->save();
            }
        }
    }

    private function createForumData(): void
    {
        $discussions = ForumDiscussion::whereIn('course_id', $this->courseIds)->get();
        $highPosts = [['Great question!','I found this topic very interesting. Here is my take...'],['Additional resource','I read a paper that complements this lecture.'],['Clarification needed','Could you explain the difference more clearly?'],['My solution','Here is how I approached the assignment...'],['Reply','I think you are on the right track!']];
        $mediumPosts = [['Question','I am a bit confused. Can someone help?'],['Thanks','Thanks for the explanation!']];
        foreach ($discussions as $disc) {
            $iid = $this->courseInstructor[$disc->course_id] ?? $this->mainInstructorId;
            $topic = ForumPost::firstOrNew(['discussion_id'=>$disc->id,'user_id'=>$iid,'subject'=>'Welcome to '.$disc->title]);
            $topic->forceFill(['id'=>$topic->id??Str::uuid()->toString(),'discussion_id'=>$disc->id,'user_id'=>$iid,'subject'=>'Welcome to '.$disc->title,'content'=>'Please share your thoughts and questions.'])->save();
            foreach ($highPosts as $p) ForumPost::create(['id'=>Str::uuid()->toString(),'discussion_id'=>$disc->id,'user_id'=>$this->studentIds['john'],'parent_id'=>$topic->id,'subject'=>$p[0],'content'=>$p[1]]);
            foreach ($mediumPosts as $p) ForumPost::create(['id'=>Str::uuid()->toString(),'discussion_id'=>$disc->id,'user_id'=>$this->studentIds['grace'],'parent_id'=>$topic->id,'subject'=>$p[0],'content'=>$p[1]]);
            if (rand(0,1)===1) ForumPost::create(['id'=>Str::uuid()->toString(),'discussion_id'=>$disc->id,'user_id'=>$this->studentIds['peter'],'parent_id'=>$topic->id,'subject'=>'Struggling','content'=>'I am finding this really hard. Is there extra help?']);
            $others = array_diff_key($this->studentIds, array_flip(['john','grace','peter']));
            foreach (array_slice($others, 0, 3, true) as $sid) {
                if (rand(0,2)===0) continue;
                $name = explode(' ', User::find($sid)->name)[0];
                ForumPost::create(['id'=>Str::uuid()->toString(),'discussion_id'=>$disc->id,'user_id'=>$sid,'parent_id'=>$topic->id,'subject'=>'Re: '.$disc->title,'content'=>$name.' shared their thoughts on this topic.']);
            }
            $disc->update(['post_count'=>ForumPost::where('discussion_id',$disc->id)->count()]);
        }
    }

    private function createAssignmentSubmissionsAndGrades(): void
    {
        $assignments = Activity::where('type','assign')->whereIn('course_id',$this->courseIds)->get();
        $names = ['john'=>'John Mwale','grace'=>'Grace Njau','peter'=>'Peter Masanja'];
        foreach ($assignments as $as) {
            $gi = GradeItem::where('activity_id',$as->id)->first(); if (! $gi) continue;
            foreach ($this->studentIds as $key => $sid) {
                $profile = $this->studentProfiles[$sid];
                [$grade,$status,$subAt,$late,$attempts] = match($profile){'high'=>[88+rand(0,10),'graded',now()->subDays(rand(1,5)),false,1],'medium'=>[62+rand(0,12),'graded',now()->subDays(rand(3,10)),rand(0,3)===0,rand(1,2)],'atrisk'=>[rand(20,55),rand(0,2)===0?'submitted':'draft',now()->subDays(rand(10,25)),rand(0,1)===1,rand(1,2)]};
                if ($profile==='atrisk' && rand(0,2)===0) continue;
                $sub = AssignmentSubmission::firstOrNew(['activity_id'=>$as->id,'student_id'=>$sid]);
                $sub->forceFill(['id'=>$sub->id??Str::uuid()->toString(),'activity_id'=>$as->id,'student_id'=>$sid,'course_id'=>$as->course_id,'status'=>$status,'submission_text'=>'Submission for '.$as->name,'submitted_at'=>$subAt,'grade'=>$status==='graded'?$grade:null,'graded_by'=>$status==='graded'?$this->mainInstructorId:null,'graded_at'=>$status==='graded'?$subAt->copy()->addDays(2):null,'feedback'=>$status==='graded'?'Good effort. Keep improving.':null,'attempt_number'=>$attempts,'late'=>$late])->save();
                if ($status==='graded') {
                    $sname = $names[$key] ?? User::find($sid)->name;
                    StudentGrade::firstOrNew(['grade_item_id'=>$gi->id,'student_id'=>$sid])->forceFill(['id'=>Str::uuid()->toString(),'grade_item_id'=>$gi->id,'student_id'=>$sid,'student_name'=>$sname,'grade'=>$grade,'percentage'=>$grade,'feedback'=>match($profile){'high'=>'Excellent work!','medium'=>'Satisfactory but could be improved.','atrisk'=>'Needs significant improvement.'},'submitted_date'=>$subAt,'status'=>'released'])->save();
                }
            }
        }
    }

    private function createAttendanceData(): void
    {
        $activities = Activity::where('type','forum')->whereIn('course_id',$this->courseIds)->take(20)->get();
        foreach ($activities as $idx => $act) {
            $session = AttendanceSession::firstOrNew(['activity_id'=>$act->id,'course_id'=>$act->course_id,'session_date'=>Carbon::parse('2025-01-20')->addWeeks($idx)]);
            $session->forceFill(['id'=>$session->id??Str::uuid()->toString(),'activity_id'=>$act->id,'course_id'=>$act->course_id,'title'=>'Week '.($idx+1).' Lecture','description'=>'Regular lecture session','session_date'=>Carbon::parse('2025-01-20')->addWeeks($idx),'duration_minutes'=>120,'status'=>'closed'])->save();
            $this->sessionIds[] = $session->id;
            foreach ($this->studentIds as $key => $sid) {
                $profile = $this->studentProfiles[$sid];
                $status = match($profile){'high'=>(rand(0,20)===0?'late':'present'),'medium'=>match(rand(0,3)){0=>'absent',1=>'late',default=>'present'},'atrisk'=>match(rand(0,2)){0=>'present',1=>'late',default=>'absent'}};
                AttendanceLog::firstOrNew(['session_id'=>$session->id,'student_id'=>$sid])->forceFill(['id'=>Str::uuid()->toString(),'session_id'=>$session->id,'student_id'=>$sid,'status'=>$status,'remarks'=>$status==='absent'?'No show':($status==='late'?'Arrived 15 min late':null),'taken_by'=>$this->mainInstructorId])->save();
            }
        }
    }

    private function createDashboardEngagement(): void
    {
        for ($w=0; $w<8; $w++) {
            $weekOf = Carbon::parse('2025-01-13')->addWeeks($w);
            foreach ($this->courseIds as $cid) {
                foreach (['Mon','Tue','Wed','Thu','Fri'] as $day) {
                    $active = 0; $subs = 0;
                    foreach ($this->studentIds as $sid) {
                        $profile = $this->studentProfiles[$sid];
                        $prob = match($profile){'high'=>0.8,'medium'=>0.5,'atrisk'=>0.2};
                        if (rand(1,100)/100 <= $prob) $active++;
                        if (rand(1,100)/100 <= $prob*0.4) $subs++;
                    }
                    DashboardEngagement::firstOrNew(['course_id'=>$cid,'day_label'=>$day,'week_of'=>$weekOf])->forceFill(['id'=>Str::uuid()->toString(),'course_id'=>$cid,'day_label'=>$day,'active_students'=>$active,'submissions'=>$subs,'week_of'=>$weekOf])->save();
                }
            }
        }
    }

    private function createActivityCompletions(): void
    {
        foreach ($this->courseIds as $cid) {
            $acts = Activity::where('course_id', $cid)->pluck('id')->toArray();
            foreach ($this->studentIds as $sid) {
                $profile = $this->studentProfiles[$sid];
                $target = match($profile){'high'=>count($acts),'medium'=>intval(count($acts)*0.6),'atrisk'=>intval(count($acts)*0.25)};
                shuffle($acts);
                foreach (array_slice($acts, 0, $target) as $aid) {
                    UserActivityCompletion::firstOrNew(['user_id'=>$sid,'activity_id'=>$aid])->forceFill(['id'=>Str::uuid()->toString(),'user_id'=>$sid,'activity_id'=>$aid,'course_id'=>$cid,'completion_type'=>'view','completed_at'=>now()->subDays(rand(1,30))])->save();
                }
            }
        }
    }

    private function createAiAtRiskData(): void
    {
        foreach ($this->courseIds as $cid) {
            foreach ($this->studentIds as $key => $sid) {
                $profile = $this->studentProfiles[$sid];
                $name = User::find($sid)->name;
                [$progress,$lastAccess,$missed,$grade,$risk,$rec] = match($profile){
                    'high'=>[88,now()->subDays(1),1,90,'low','Keep up the excellent work. Consider mentoring peers.'],
                    'medium'=>[62,now()->subDays(4),4,68,'low','Join study groups. Schedule office hours for difficult topics.'],
                    'atrisk'=>[22,now()->subDays(18),12,35,'high','Urgent intervention required. Schedule counselling and academic advisor meeting immediately.']
                };
                AiAtRiskStudent::firstOrNew(['course_id'=>$cid,'student_id'=>$sid])->forceFill(['id'=>Str::uuid()->toString(),'course_id'=>$cid,'student_id'=>$sid,'student_name'=>$name,'progress'=>$progress,'last_access'=>$lastAccess,'missed_activities'=>$missed,'grade'=>$grade,'risk_level'=>$risk,'ai_recommendation'=>$rec,'detected_at'=>now()->subDays(rand(7,14))])->save();
            }
        }
    }

    private function createAiPerformanceSnapshots(): void
    {
        foreach ($this->courseIds as $cid) {
            for ($w=1; $w<=8; $w++) {
                AiPerformanceSnapshot::firstOrNew(['course_id'=>$cid,'week_label'=>'Week '.$w])->forceFill(['id'=>Str::uuid()->toString(),'course_id'=>$cid,'week_label'=>'Week '.$w,'avg_grade'=>65+rand(-5,5),'completion_rate'=>70+rand(-10,10),'engagement_score'=>60+rand(-15,15),'recorded_at'=>Carbon::parse('2025-01-13')->addWeeks($w)])->save();
            }
        }
    }

    private function createAiRecommendations(): void
    {
        $recs = [['Peer Tutoring Program','Encourage at-risk students to join peer tutoring sessions every Wednesday.','high','users','blue'],['Increase Assignment Frequency','Weekly low-stakes quizzes can improve engagement and catch struggling students early.','medium','clipboard-list','green'],['Remedial Classes','Offer Saturday remedial classes for students with grades below 40%.','high','book-open','red']];
        foreach ($this->courseIds as $cid) {
            foreach ($recs as $r) {
                AiRecommendation::firstOrNew(['course_id'=>$cid,'title'=>$r[0]])->forceFill(['id'=>Str::uuid()->toString(),'course_id'=>$cid,'title'=>$r[0],'description'=>$r[1],'impact_level'=>$r[2],'icon_name'=>$r[3],'color_scheme'=>$r[4],'generated_at'=>now()->subDays(rand(1,5))])->save();
            }
        }
    }

    private function createLearnerProfiles(): void
    {
        $profiles = ['high'=>['primary_profile'=>'achiever','secondary_profile'=>'thinker','is_mixed_profile'=>false,'h_score'=>78,'a_score'=>85,'t_score'=>92,'c_score'=>88,'drift_flag'=>false,'profile_note'=>'Strong autonomous learner with high technical capability.'],'medium'=>['primary_profile'=>'helper','secondary_profile'=>'achiever','is_mixed_profile'=>true,'mixed_blend_primary'=>60,'mixed_blend_secondary'=>40,'h_score'=>55,'a_score'=>62,'t_score'=>58,'c_score'=>60,'drift_flag'=>false,'profile_note'=>'Social learner who benefits from group study but needs consistency.'],'atrisk'=>['primary_profile'=>'disengaged','secondary_profile'=>null,'is_mixed_profile'=>false,'h_score'=>25,'a_score'=>30,'t_score'=>22,'c_score'=>28,'drift_flag'=>true,'drift_weeks_count'=>4,'profile_note'=>'Significant disengagement detected. Requires immediate intervention.']];
        foreach ($this->courseIds as $cid) {
            foreach ($this->studentIds as $key => $sid) {
                $profile = $this->studentProfiles[$sid];
                $data = $profiles[$profile];
                LearnerProfile::firstOrNew(['learner_id'=>$sid,'course_id'=>$cid])->forceFill(array_merge(['id'=>Str::uuid()->toString(),'learner_id'=>$sid,'course_id'=>$cid,'declared_preferences'=>json_encode(['visual','hands_on']),'lms_flags'=>json_encode(['new_enrolment'=>false,'first_login'=>false]),'pulse_consent'=>true,'pulse_consent_at'=>now()->subMonths(2),'drift_flagged_at'=>$data['drift_flag']?now()->subWeeks(3):null],$data))->save();
            }
        }
    }

    private function createNotifications(): void
    {
        $types = ['assignment_due','grade_released','forum_reply','announcement','course_enrollment'];
        foreach ($this->studentIds as $sid) {
            for ($i=0; $i<5; $i++) {
                $type = $types[array_rand($types)];
                Notification::create(['user_id'=>$sid,'type'=>$type,'channel'=>'in_app','title'=>ucfirst(str_replace('_',' ',$type)),'body'=>'This is a demo notification for '.$type.'.','payload'=>[],'status'=>rand(0,1)===0?'read':'sent','read_at'=>rand(0,1)===0?now()->subDays(rand(1,5)):null,'sent_at'=>now()->subDays(rand(1,10))]);
            }
        }
        foreach ($this->instructorIds as $iid) {
            for ($i=0; $i<3; $i++) {
                Notification::create(['user_id'=>$iid,'type'=>'student_at_risk','channel'=>'in_app','title'=>'Student At-Risk Alert','body'=>'A student in your course has been flagged as at-risk.','payload'=>[],'status'=>'sent','sent_at'=>now()->subDays(rand(1,5))]);
            }
        }
    }
}

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
use App\Models\LearnerProfile;
use App\Models\LessonPage;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\StudentGrade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private ?string $civeId = null;
    private ?string $csProgrammeId = null;
    private ?string $instructorId = null;
    private array $courseIds = [];
    private array $studentIds = [];
    private array $activityIds = [];
    private array $gradeItemIds = [];
    private array $sessionIds = [];
    private array $lessonActivityIds = [];
    private array $quizActivityIds = [];

    public function run(): void
    {
        $this->command->info('Seeding UDOM demo data...');

        // 1. Resolve College & Programme
        $this->resolveAcademicStructure();

        // 2. Create Users (1 admin, 1 instructor, 3 students)
        $this->createUsers();

        // 3. Create Category & Courses
        $this->createCourses();

        // 4. Link courses to degree programme
        $this->linkCoursesToProgramme();

        // 5. Create Sections & Activities (lessons, quizzes, assignments, forums)
        $this->createSectionsAndActivities();

        // 5b. Seed lesson pages and quiz questions
        $this->createLessonPages();
        $this->createQuizQuestionsAndAnswers();

        // 6. Enroll students
        $this->createEnrollments();

        // 7. Forum discussions & posts
        $this->createForumData();

        // 8. Assignment submissions & grades
        $this->createAssignmentSubmissionsAndGrades();

        // 9. Attendance sessions & logs
        $this->createAttendanceData();

        // 10. Dashboard engagement (daily activity)
        $this->createDashboardEngagement();

        // 11. AI at-risk students
        $this->createAiAtRiskData();

        // 12. AI performance snapshots
        $this->createAiPerformanceSnapshots();

        // 13. AI recommendations
        $this->createAiRecommendations();

        // 14. Learner profiles
        $this->createLearnerProfiles();

        $this->command->info('UDOM demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('Students:');
        $this->command->info('  HIGH   - john.mwale@demo.udom.ac.tz   (password: @Demo123)');
        $this->command->info('  MEDIUM - grace.njau@demo.udom.ac.tz   (password: @Demo123)');
        $this->command->info('  AT-RISK- peter.masanja@demo.udom.ac.tz (password: @Demo123)');
        $this->command->info('Instructor: dr.sarah@demo.udom.ac.tz (password: @Demo123)');
        $this->command->info('Admin:      admin.fk@demo.udom.ac.tz  (password: @Demo123)');
    }

    private function resolveAcademicStructure(): void
    {
        $cive = College::where('code', 'CIVE')->first();
        if (! $cive) {
            throw new \RuntimeException('College CIVE not found. Run AcademicStructureSeeder first.');
        }
        $this->civeId = $cive->id;

        $cs = DegreeProgramme::where('code', 'CS')->first();
        if (! $cs) {
            throw new \RuntimeException('Degree programme CS not found. Run AcademicStructureSeeder first.');
        }
        $this->csProgrammeId = $cs->id;
    }

    private function createUsers(): void
    {
        $now = now();
        $common = [
            'password' => Hash::make('@Demo123'),
            'email_verified_at' => $now,
            'verification_code_expires_at' => $now,
            'remember_token' => Str::random(10),
            'institution' => 'University of Dodoma',
            'country' => 'Tanzania',
            'timezone' => 'Africa/Dar_es_Salaam',
            'language' => 'en',
        ];

        // Admin
        $admin = User::firstOrNew(['email' => 'admin.fk@demo.udom.ac.tz']);
        $admin->forceFill(array_merge($common, [
            'id' => $admin->id ?? Str::uuid()->toString(),
            'name' => 'Mr. Frank Kavishe',
            'initials' => 'FK',
            'email' => 'admin.fk@demo.udom.ac.tz',
            'role' => 'admin',
            'department' => 'IT Administration',
            'nationality' => 'Tanzanian',
            'gender' => 'male',
        ]))->save();

        // Instructor (CIVE - Computer Science)
        $instructor = User::firstOrNew(['email' => 'dr.sarah@demo.udom.ac.tz']);
        $instructor->forceFill(array_merge($common, [
            'id' => $instructor->id ?? Str::uuid()->toString(),
            'name' => 'Dr. Sarah Mwakasege',
            'initials' => 'SM',
            'email' => 'dr.sarah@demo.udom.ac.tz',
            'role' => 'instructor',
            'department' => 'School of Computing',
            'nationality' => 'Tanzanian',
            'gender' => 'female',
            'degree_programme_id' => null,
        ]))->save();
        $this->instructorId = $instructor->id;

        // Student 1 - HIGH engagement
        $s1 = User::firstOrNew(['email' => 'john.mwale@demo.udom.ac.tz']);
        $s1->forceFill(array_merge($common, [
            'id' => $s1->id ?? Str::uuid()->toString(),
            'name' => 'John Mwale',
            'initials' => 'JM',
            'email' => 'john.mwale@demo.udom.ac.tz',
            'role' => 'student',
            'registration_number' => 'UDOM/CIVE/CS/2022/001',
            'degree_programme_id' => $this->csProgrammeId,
            'year_of_study' => 2,
            'education_level' => 'undergraduate',
            'nationality' => 'Tanzanian',
            'gender' => 'male',
            'phone_number' => '+255712345001',
            'bio' => 'Passionate about software engineering and AI. Active class participant.',
        ]))->save();
        $this->studentIds['high'] = $s1->id;

        // Student 2 - MEDIUM engagement
        $s2 = User::firstOrNew(['email' => 'grace.njau@demo.udom.ac.tz']);
        $s2->forceFill(array_merge($common, [
            'id' => $s2->id ?? Str::uuid()->toString(),
            'name' => 'Grace Njau',
            'initials' => 'GN',
            'email' => 'grace.njau@demo.udom.ac.tz',
            'role' => 'student',
            'registration_number' => 'UDOM/CIVE/CS/2022/045',
            'degree_programme_id' => $this->csProgrammeId,
            'year_of_study' => 2,
            'education_level' => 'undergraduate',
            'nationality' => 'Tanzanian',
            'gender' => 'female',
            'phone_number' => '+255712345045',
            'bio' => 'Computer Science student. Balancing studies with part-time work.',
        ]))->save();
        $this->studentIds['medium'] = $s2->id;

        // Student 3 - AT-RISK
        $s3 = User::firstOrNew(['email' => 'peter.masanja@demo.udom.ac.tz']);
        $s3->forceFill(array_merge($common, [
            'id' => $s3->id ?? Str::uuid()->toString(),
            'name' => 'Peter Masanja',
            'initials' => 'PM',
            'email' => 'peter.masanja@demo.udom.ac.tz',
            'role' => 'student',
            'registration_number' => 'UDOM/CIVE/CS/2022/089',
            'degree_programme_id' => $this->csProgrammeId,
            'year_of_study' => 2,
            'education_level' => 'undergraduate',
            'nationality' => 'Tanzanian',
            'gender' => 'male',
            'phone_number' => '+255712345089',
            'bio' => 'Struggling to keep up with coursework. Needs academic support.',
        ]))->save();
        $this->studentIds['atrisk'] = $s3->id;
    }

    private function createCourses(): void
    {
        $category = Category::firstOrNew(['name' => 'Computer Science']);
        $category->forceFill([
            'id' => $category->id ?? Str::uuid()->toString(),
            'name' => 'Computer Science',
            'description' => 'Core computer science courses at UDOM',
        ])->save();

        $courses = [
            [
                'name' => 'Programming Fundamentals',
                'short_name' => 'CS 101',
                'description' => 'Introduction to programming using Python and C. Covers variables, control structures, functions, and basic data structures.',
                'start_date' => '2025-01-15',
                'end_date' => '2025-05-30',
            ],
            [
                'name' => 'Data Structures and Algorithms',
                'short_name' => 'CS 201',
                'description' => 'Arrays, linked lists, stacks, queues, trees, graphs, sorting and searching algorithms.',
                'start_date' => '2025-01-15',
                'end_date' => '2025-05-30',
            ],
            [
                'name' => 'Database Systems',
                'short_name' => 'CS 205',
                'description' => 'Relational database design, SQL, normalization, transaction management, and NoSQL fundamentals.',
                'start_date' => '2025-01-15',
                'end_date' => '2025-05-30',
            ],
        ];

        foreach ($courses as $c) {
            $course = Course::firstOrNew(['short_name' => $c['short_name']]);
            $course->forceFill([
                'id' => $course->id ?? Str::uuid()->toString(),
                'name' => $c['name'],
                'short_name' => $c['short_name'],
                'description' => $c['description'],
                'category_id' => $category->id,
                'category_name' => $category->name,
                'college_id' => $this->civeId,
                'instructor_id' => $this->instructorId,
                'instructor_name' => 'Dr. Sarah Mwakasege',
                'status' => 'active',
                'visibility' => 'shown',
                'format' => 'topics',
                'start_date' => $c['start_date'],
                'end_date' => $c['end_date'],
                'language' => 'English',
                'tags' => json_encode(['undergraduate', 'CIVE', 'core']),
                'max_students' => 120,
                'enrolled_students' => 3,
            ])->save();
            $this->courseIds[] = $course->id;
        }
    }

    private function linkCoursesToProgramme(): void
    {
        foreach ($this->courseIds as $courseId) {
            $exists = DB::table('course_degree_programme')
                ->where('course_id', $courseId)
                ->where('degree_programme_id', $this->csProgrammeId)
                ->exists();
            if (! $exists) {
                DB::table('course_degree_programme')->insert([
                    'course_id' => $courseId,
                    'degree_programme_id' => $this->csProgrammeId,
                ]);
            }
        }

        // Also link instructor to degree programme
        $exists = DB::table('degree_programme_instructor')
            ->where('instructor_id', $this->instructorId)
            ->where('degree_programme_id', $this->csProgrammeId)
            ->exists();
        if (! $exists) {
            DB::table('degree_programme_instructor')->insert([
                'instructor_id' => $this->instructorId,
                'degree_programme_id' => $this->csProgrammeId,
            ]);
        }
    }

    private function createSectionsAndActivities(): void
    {
        $sectionTitles = ['Week 1-4: Basics', 'Week 5-8: Intermediate', 'Week 9-12: Advanced'];

        foreach ($this->courseIds as $idx => $courseId) {
            foreach ($sectionTitles as $sort => $title) {
                $section = Section::firstOrNew([
                    'course_id' => $courseId,
                    'title' => $title,
                ]);
                $section->forceFill([
                    'id' => $section->id ?? Str::uuid()->toString(),
                    'course_id' => $courseId,
                    'title' => $title,
                    'summary' => 'Topics covered during ' . strtolower($title),
                    'sort_order' => $sort,
                    'visible' => true,
                ])->save();

                // Create 4 activities per section: lesson, quiz, assignment, forum
                $activityTypes = ['lesson', 'quiz', 'assign', 'forum'];
                foreach ($activityTypes as $a => $type) {
                    $name = match ($type) {
                        'lesson' => 'Lecture ' . ($sort + 1) . '.' . ($idx + 1),
                        'quiz' => 'Quiz ' . ($sort + 1) . '.' . ($idx + 1),
                        'assign' => 'Assignment ' . ($sort + 1) . '.' . ($idx + 1),
                        'forum' => 'Discussion Forum ' . ($sort + 1) . '.' . ($idx + 1),
                    };

                    $activity = Activity::firstOrNew([
                        'section_id' => $section->id,
                        'course_id' => $courseId,
                        'name' => $name,
                    ]);
                    $activity->forceFill([
                        'id' => $activity->id ?? Str::uuid()->toString(),
                        'section_id' => $section->id,
                        'course_id' => $courseId,
                        'type' => $type,
                        'name' => $name,
                        'description' => 'Activity for ' . $name,
                        'due_date' => Carbon::parse('2025-01-15')->addWeeks($sort * 3 + 2 + $idx),
                        'visible' => true,
                        'grade_max' => 100,
                        'sort_order' => $a,
                    ])->save();

                    $this->activityIds[$courseId][] = $activity->id;

                    if ($type === 'lesson') {
                        $this->lessonActivityIds[$courseId][$sort] = $activity->id;
                    } elseif ($type === 'quiz') {
                        $this->quizActivityIds[$courseId][$sort] = $activity->id;
                    }

                    // Create grade item for assignments
                    if ($type === 'assign') {
                        $gi = GradeItem::firstOrNew([
                            'course_id' => $courseId,
                            'activity_id' => $activity->id,
                        ]);
                        $gi->forceFill([
                            'id' => $gi->id ?? Str::uuid()->toString(),
                            'course_id' => $courseId,
                            'activity_id' => $activity->id,
                            'activity_name' => $name,
                            'activity_type' => $type,
                            'grade_max' => 100,
                        ])->save();
                        $this->gradeItemIds[$courseId][] = $gi->id;
                    }

                    // Create forum discussion for forums
                    if ($type === 'forum') {
                        $disc = ForumDiscussion::firstOrNew([
                            'activity_id' => $activity->id,
                            'course_id' => $courseId,
                        ]);
                        $disc->forceFill([
                            'id' => $disc->id ?? Str::uuid()->toString(),
                            'activity_id' => $activity->id,
                            'course_id' => $courseId,
                            'user_id' => $this->instructorId,
                            'title' => 'General Discussion: ' . $name,
                            'pinned' => $sort === 0,
                            'locked' => false,
                            'post_count' => 0,
                        ])->save();
                    }
                }
            }
        }
    }

    private function createLessonPages(): void
    {
        $lessonContent = [
            // CS 101 - Programming Fundamentals
            0 => [
                0 => [
                    ['title' => 'Introduction to Programming', 'content' => '<h2>What is Programming?</h2><p>Programming is the process of creating instructions for computers to perform specific tasks. Python and C are popular languages for beginners.</p><h3>Key Concepts</h3><ul><li>Algorithms</li><li>Syntax</li><li>Variables</li></ul>'],
                    ['title' => 'Variables and Data Types', 'content' => '<h2>Variables</h2><p>Variables are containers for storing data values. In Python, you do not need to declare the type.</p><pre><code>x = 5\nname = "John"\npi = 3.14</code></pre>'],
                    ['title' => 'Basic Operators', 'content' => '<h2>Operators</h2><p>Operators are used to perform operations on variables and values.</p><ul><li>Arithmetic: +, -, *, /</li><li>Comparison: ==, !=, >, <</li><li>Logical: and, or, not</li></ul>'],
                ],
                1 => [
                    ['title' => 'Control Structures', 'content' => '<h2>If Statements</h2><p>Conditional statements allow programs to make decisions.</p><pre><code>if x > 0:\n    print("Positive")\nelif x == 0:\n    print("Zero")\nelse:\n    print("Negative")</code></pre>'],
                    ['title' => 'Loops', 'content' => '<h2>For and While Loops</h2><p>Loops allow us to repeat actions efficiently.</p><pre><code>for i in range(5):\n    print(i)\n\nwhile x < 10:\n    x += 1</code></pre>'],
                    ['title' => 'Functions', 'content' => '<h2>Defining Functions</h2><p>Functions are reusable blocks of code that perform a specific task.</p><pre><code>def greet(name):\n    return f"Hello, {name}!"\n\nprint(greet("Alice"))</code></pre>'],
                ],
                2 => [
                    ['title' => 'File I/O', 'content' => '<h2>Reading and Writing Files</h2><p>Python makes it easy to work with files.</p><pre><code>with open("data.txt", "r") as f:\n    content = f.read()</code></pre>'],
                    ['title' => 'Error Handling', 'content' => '<h2>Try-Except Blocks</h2><p>Handle errors gracefully to prevent program crashes.</p><pre><code>try:\n    result = 10 / 0\nexcept ZeroDivisionError:\n    print("Cannot divide by zero")</code></pre>'],
                ],
            ],
            // CS 201 - Data Structures and Algorithms
            1 => [
                0 => [
                    ['title' => 'Arrays and Memory', 'content' => '<h2>Arrays</h2><p>Arrays are contiguous blocks of memory storing elements of the same type.</p><p><b>Time Complexity:</b></p><ul><li>Access: O(1)</li><li>Search: O(n)</li><li>Insert: O(n)</li></ul>'],
                    ['title' => 'Linked Lists', 'content' => '<h2>Singly Linked List</h2><p>A linked list consists of nodes where each node contains data and a reference to the next node.</p><pre><code>class Node:\n    def __init__(self, data):\n        self.data = data\n        self.next = None</code></pre>'],
                ],
                1 => [
                    ['title' => 'Stacks and Queues', 'content' => '<h2>Stack (LIFO)</h2><p>Last In, First Out. Operations: push, pop, peek.</p><h2>Queue (FIFO)</h2><p>First In, First Out. Operations: enqueue, dequeue.</p>'],
                    ['title' => 'Binary Trees', 'content' => '<h2>Tree Terminology</h2><ul><li>Root: topmost node</li><li>Leaf: node with no children</li><li>Height: longest path from root to leaf</li></ul><p>Binary Search Tree (BST) allows O(log n) search on average.</p>'],
                    ['title' => 'Heaps', 'content' => '<h2>Min Heap / Max Heap</h2><p>A complete binary tree where parent is smaller (min-heap) or larger (max-heap) than children.</p><p>Used in priority queues and heap sort.</p>'],
                ],
                2 => [
                    ['title' => 'Graphs', 'content' => '<h2>Graph Representations</h2><ul><li>Adjacency Matrix</li><li>Adjacency List</li></ul><p>Graphs model networks, social connections, and maps.</p>'],
                    ['title' => 'Sorting Algorithms', 'content' => '<h2>Common Sorting Algorithms</h2><table><tr><th>Algorithm</th><th>Best</th><th>Average</th><th>Worst</th></tr><tr><td>Bubble Sort</td><td>O(n)</td><td>O(n²)</td><td>O(n²)</td></tr><tr><td>Merge Sort</td><td>O(n log n)</td><td>O(n log n)</td><td>O(n log n)</td></tr><tr><td>Quick Sort</td><td>O(n log n)</td><td>O(n log n)</td><td>O(n²)</td></tr></table>'],
                ],
            ],
            // CS 205 - Database Systems
            2 => [
                0 => [
                    ['title' => 'Introduction to Databases', 'content' => '<h2>DBMS</h2><p>A Database Management System (DBMS) is software that interacts with end users, applications, and the database itself to capture and analyze data.</p>'],
                    ['title' => 'ER Diagrams', 'content' => '<h2>Entity-Relationship Model</h2><p>Entities, attributes, and relationships are the core components.</p><ul><li>One-to-One (1:1)</li><li>One-to-Many (1:N)</li><li>Many-to-Many (M:N)</li></ul>'],
                ],
                1 => [
                    ['title' => 'SQL Basics', 'content' => '<h2>SELECT Statement</h2><pre><code>SELECT first_name, last_name\nFROM students\nWHERE gpa > 3.0\nORDER BY last_name;</code></pre>'],
                    ['title' => 'Normalization', 'content' => '<h2>Normal Forms</h2><ul><li><b>1NF:</b> All columns contain atomic values</li><li><b>2NF:</b> No partial dependency</li><li><b>3NF:</b> No transitive dependency</li><li><b>BCNF:</b> Every determinant is a candidate key</li></ul>'],
                    ['title' => 'JOIN Operations', 'content' => '<h2>SQL JOINs</h2><pre><code>SELECT s.name, c.title\nFROM students s\nINNER JOIN enrollments e ON s.id = e.student_id\nINNER JOIN courses c ON e.course_id = c.id;</code></pre>'],
                ],
                2 => [
                    ['title' => 'Transactions', 'content' => '<h2>ACID Properties</h2><ul><li><b>A</b>tomicity: all or nothing</li><li><b>C</b>onsistency: valid state transitions</li><li><b>I</b>solation: concurrent execution safety</li><li><b>D</b>urability: committed changes persist</li></ul>'],
                    ['title' => 'NoSQL Databases', 'content' => '<h2>Types of NoSQL</h2><ul><li>Document (MongoDB)</li><li>Key-Value (Redis)</li><li>Wide-Column (Cassandra)</li><li>Graph (Neo4j)</li></ul><p>NoSQL databases are designed for scale and flexibility.</p>'],
                ],
            ],
        ];

        foreach ($this->courseIds as $courseIdx => $courseId) {
            if (! isset($this->lessonActivityIds[$courseId])) {
                continue;
            }
            foreach ($this->lessonActivityIds[$courseId] as $sectionIdx => $activityId) {
                $pages = $lessonContent[$courseIdx][$sectionIdx] ?? [];
                foreach ($pages as $pageIdx => $page) {
                    LessonPage::firstOrNew([
                        'activity_id' => $activityId,
                        'title' => $page['title'],
                    ])->forceFill([
                        'id' => Str::uuid()->toString(),
                        'activity_id' => $activityId,
                        'title' => $page['title'],
                        'content' => $page['content'],
                        'page_type' => 'content',
                        'sort_order' => $pageIdx,
                    ])->save();
                }
            }
        }
    }

    private function createQuizQuestionsAndAnswers(): void
    {
        $quizData = [
            // CS 101
            0 => [
                0 => [
                    ['text' => 'What is the correct way to declare a variable in Python?', 'answers' => [['text' => 'int x = 5', 'fraction' => 0], ['text' => 'x = 5', 'fraction' => 1], ['text' => 'var x = 5', 'fraction' => 0], ['text' => 'let x = 5', 'fraction' => 0]]],
                    ['text' => 'Which of the following is NOT a valid Python data type?', 'answers' => [['text' => 'int', 'fraction' => 0], ['text' => 'str', 'fraction' => 0], ['text' => 'array', 'fraction' => 1], ['text' => 'float', 'fraction' => 0]]],
                ],
                1 => [
                    ['text' => 'What does the following Python code output? for i in range(3): print(i)', 'answers' => [['text' => '1 2 3', 'fraction' => 0], ['text' => '0 1 2', 'fraction' => 1], ['text' => '0 1 2 3', 'fraction' => 0], ['text' => 'Error', 'fraction' => 0]]],
                    ['text' => 'Which keyword is used to define a function in Python?', 'answers' => [['text' => 'function', 'fraction' => 0], ['text' => 'def', 'fraction' => 1], ['text' => 'func', 'fraction' => 0], ['text' => 'define', 'fraction' => 0]]],
                ],
                2 => [
                    ['text' => 'What is the purpose of the "try-except" block in Python?', 'answers' => [['text' => 'To optimize code performance', 'fraction' => 0], ['text' => 'To handle exceptions and errors', 'fraction' => 1], ['text' => 'To import modules', 'fraction' => 0], ['text' => 'To define classes', 'fraction' => 0]]],
                ],
            ],
            // CS 201
            1 => [
                0 => [
                    ['text' => 'What is the time complexity of accessing an element in an array by index?', 'answers' => [['text' => 'O(1)', 'fraction' => 1], ['text' => 'O(n)', 'fraction' => 0], ['text' => 'O(log n)', 'fraction' => 0], ['text' => 'O(n²)', 'fraction' => 0]]],
                    ['text' => 'In a singly linked list, each node contains:', 'answers' => [['text' => 'Data only', 'fraction' => 0], ['text' => 'Data and a pointer to the next node', 'fraction' => 1], ['text' => 'Data and pointers to both next and previous nodes', 'fraction' => 0], ['text' => 'Pointer only', 'fraction' => 0]]],
                ],
                1 => [
                    ['text' => 'Which data structure follows the LIFO principle?', 'answers' => [['text' => 'Queue', 'fraction' => 0], ['text' => 'Stack', 'fraction' => 1], ['text' => 'Linked List', 'fraction' => 0], ['text' => 'Array', 'fraction' => 0]]],
                    ['text' => 'In a Binary Search Tree (BST), the left child of a node is:', 'answers' => [['text' => 'Greater than the parent', 'fraction' => 0], ['text' => 'Smaller than the parent', 'fraction' => 1], ['text' => 'Equal to the parent', 'fraction' => 0], ['text' => 'Random value', 'fraction' => 0]]],
                ],
                2 => [
                    ['text' => 'Which sorting algorithm has the worst-case time complexity of O(n log n)?', 'answers' => [['text' => 'Bubble Sort', 'fraction' => 0], ['text' => 'Quick Sort', 'fraction' => 0], ['text' => 'Merge Sort', 'fraction' => 1], ['text' => 'Insertion Sort', 'fraction' => 0]]],
                ],
            ],
            // CS 205
            2 => [
                0 => [
                    ['text' => 'What does DBMS stand for?', 'answers' => [['text' => 'Database Management System', 'fraction' => 1], ['text' => 'Data Backup Management Service', 'fraction' => 0], ['text' => 'Database Modeling Software', 'fraction' => 0], ['text' => 'Digital Business Management Suite', 'fraction' => 0]]],
                    ['text' => 'In an ER diagram, a diamond shape represents:', 'answers' => [['text' => 'Entity', 'fraction' => 0], ['text' => 'Attribute', 'fraction' => 0], ['text' => 'Relationship', 'fraction' => 1], ['text' => 'Key', 'fraction' => 0]]],
                ],
                1 => [
                    ['text' => 'Which SQL keyword is used to retrieve data from a table?', 'answers' => [['text' => 'GET', 'fraction' => 0], ['text' => 'RETRIEVE', 'fraction' => 0], ['text' => 'SELECT', 'fraction' => 1], ['text' => 'FETCH', 'fraction' => 0]]],
                    ['text' => 'Which normal form eliminates transitive dependencies?', 'answers' => [['text' => '1NF', 'fraction' => 0], ['text' => '2NF', 'fraction' => 0], ['text' => '3NF', 'fraction' => 1], ['text' => '4NF', 'fraction' => 0]]],
                ],
                2 => [
                    ['text' => 'Which ACID property ensures that a transaction brings the database from one valid state to another?', 'answers' => [['text' => 'Atomicity', 'fraction' => 0], ['text' => 'Consistency', 'fraction' => 1], ['text' => 'Isolation', 'fraction' => 0], ['text' => 'Durability', 'fraction' => 0]]],
                ],
            ],
        ];

        foreach ($this->courseIds as $courseIdx => $courseId) {
            if (! isset($this->quizActivityIds[$courseId])) {
                continue;
            }
            foreach ($this->quizActivityIds[$courseId] as $sectionIdx => $activityId) {
                $questions = $quizData[$courseIdx][$sectionIdx] ?? [];
                foreach ($questions as $qIdx => $q) {
                    $question = QuizQuestion::firstOrNew([
                        'activity_id' => $activityId,
                        'question_text' => $q['text'],
                    ]);
                    $question->forceFill([
                        'id' => $question->id ?? Str::uuid()->toString(),
                        'activity_id' => $activityId,
                        'course_id' => $courseId,
                        'type' => 'multiple_choice',
                        'question_text' => $q['text'],
                        'default_mark' => 1,
                        'shuffle_answers' => true,
                        'penalty' => 0.33,
                    ])->save();

                    foreach ($q['answers'] as $aIdx => $a) {
                        QuizAnswer::firstOrNew([
                            'question_id' => $question->id,
                            'text' => $a['text'],
                        ])->forceFill([
                            'id' => Str::uuid()->toString(),
                            'question_id' => $question->id,
                            'text' => $a['text'],
                            'grade_fraction' => $a['fraction'],
                            'sort_order' => $aIdx,
                        ])->save();
                    }
                }
            }
        }
    }

    private function createEnrollments(): void
    {
        foreach ($this->studentIds as $profile => $studentId) {
            foreach ($this->courseIds as $courseId) {
                $progress = match ($profile) {
                    'high' => 85.0,
                    'medium' => 60.0,
                    'atrisk' => 25.0,
                };

                $enrollment = Enrollment::firstOrNew([
                    'user_id' => $studentId,
                    'course_id' => $courseId,
                ]);
                $enrollment->forceFill([
                    'id' => $enrollment->id ?? Str::uuid()->toString(),
                    'user_id' => $studentId,
                    'course_id' => $courseId,
                    'role' => 'student',
                    'status' => 'active',
                    'enrolled_date' => '2025-01-10',
                    'last_access' => match ($profile) {
                        'high' => now()->subDays(1),
                        'medium' => now()->subDays(4),
                        'atrisk' => now()->subDays(18),
                    },
                    'progress' => $progress,
                ])->save();
            }
        }
    }

    private function createForumData(): void
    {
        $discussions = ForumDiscussion::whereIn('course_id', $this->courseIds)->get();

        foreach ($discussions as $discussion) {
            // Base topic post by instructor
            $topic = ForumPost::firstOrNew([
                'discussion_id' => $discussion->id,
                'user_id' => $this->instructorId,
                'subject' => 'Welcome to ' . $discussion->title,
            ]);
            $topic->forceFill([
                'id' => $topic->id ?? Str::uuid()->toString(),
                'discussion_id' => $discussion->id,
                'user_id' => $this->instructorId,
                'subject' => 'Welcome to ' . $discussion->title,
                'content' => 'Please share your thoughts and questions about this topic.',
            ])->save();

            // High student: many posts and replies
            $highPosts = [
                ['subject' => 'Great question!', 'content' => 'I found this topic very interesting. Here is my take on it...'],
                ['subject' => 'Additional resource', 'content' => 'I read a paper that complements this lecture. Link attached.'],
                ['subject' => 'Clarification needed', 'content' => 'Could you explain the difference between X and Y more clearly?'],
                ['subject' => 'My solution', 'content' => 'Here is how I approached the assignment problem...'],
                ['subject' => 'Reply to Grace', 'content' => '@Grace Njau I think you are on the right track!'],
            ];
            foreach ($highPosts as $post) {
                ForumPost::create([
                    'id' => Str::uuid()->toString(),
                    'discussion_id' => $discussion->id,
                    'user_id' => $this->studentIds['high'],
                    'parent_id' => $topic->id,
                    'subject' => $post['subject'],
                    'content' => $post['content'],
                ]);
            }

            // Medium student: a few posts
            $mediumPosts = [
                ['subject' => 'Question', 'content' => 'I am a bit confused about this part. Can someone help?'],
                ['subject' => 'Thanks', 'content' => 'Thanks for the explanation, John!'],
            ];
            foreach ($mediumPosts as $post) {
                ForumPost::create([
                    'id' => Str::uuid()->toString(),
                    'discussion_id' => $discussion->id,
                    'user_id' => $this->studentIds['medium'],
                    'parent_id' => $topic->id,
                    'subject' => $post['subject'],
                    'content' => $post['content'],
                ]);
            }

            // At-risk student: very few posts
            if (rand(0, 1) === 1) {
                ForumPost::create([
                    'id' => Str::uuid()->toString(),
                    'discussion_id' => $discussion->id,
                    'user_id' => $this->studentIds['atrisk'],
                    'parent_id' => $topic->id,
                    'subject' => 'Struggling',
                    'content' => 'I am finding this really hard. Is there extra help available?',
                ]);
            }

            // Update post counts
            $count = ForumPost::where('discussion_id', $discussion->id)->count();
            $discussion->update(['post_count' => $count]);
        }
    }

    private function createAssignmentSubmissionsAndGrades(): void
    {
        $assignments = Activity::where('type', 'assign')
            ->whereIn('course_id', $this->courseIds)
            ->get();

        foreach ($assignments as $assignment) {
            $gradeItem = GradeItem::where('activity_id', $assignment->id)->first();
            if (! $gradeItem) {
                continue;
            }

            foreach ($this->studentIds as $profile => $studentId) {
                [$grade, $status, $submittedAt, $late, $attempts] = match ($profile) {
                    'high' => [88.0 + rand(0, 10), 'graded', now()->subDays(rand(1, 5)), false, 1],
                    'medium' => [62.0 + rand(0, 12), 'graded', now()->subDays(rand(3, 10)), rand(0, 3) === 0, rand(1, 2)],
                    'atrisk' => [rand(20, 55), rand(0, 2) === 0 ? 'submitted' : 'draft', now()->subDays(rand(10, 25)), rand(0, 1) === 1, rand(1, 2)],
                };

                // Sometimes at-risk doesn't submit at all
                if ($profile === 'atrisk' && rand(0, 2) === 0) {
                    continue;
                }

                $submission = AssignmentSubmission::firstOrNew([
                    'activity_id' => $assignment->id,
                    'student_id' => $studentId,
                ]);
                $submission->forceFill([
                    'id' => $submission->id ?? Str::uuid()->toString(),
                    'activity_id' => $assignment->id,
                    'student_id' => $studentId,
                    'course_id' => $assignment->course_id,
                    'status' => $status,
                    'submission_text' => 'Submission text for ' . $assignment->name,
                    'submitted_at' => $submittedAt,
                    'grade' => $status === 'graded' ? $grade : null,
                    'graded_by' => $status === 'graded' ? $this->instructorId : null,
                    'graded_at' => $status === 'graded' ? $submittedAt->copy()->addDays(2) : null,
                    'feedback' => $status === 'graded' ? 'Good effort. Keep improving.' : null,
                    'attempt_number' => $attempts,
                    'late' => $late,
                ])->save();

                // Create student grade record
                if ($status === 'graded') {
                    StudentGrade::firstOrNew([
                        'grade_item_id' => $gradeItem->id,
                        'student_id' => $studentId,
                    ])->forceFill([
                        'id' => Str::uuid()->toString(),
                        'grade_item_id' => $gradeItem->id,
                        'student_id' => $studentId,
                        'student_name' => match ($profile) {
                            'high' => 'John Mwale',
                            'medium' => 'Grace Njau',
                            'atrisk' => 'Peter Masanja',
                        },
                        'grade' => $grade,
                        'percentage' => $grade,
                        'feedback' => match ($profile) {
                            'high' => 'Excellent work!',
                            'medium' => 'Satisfactory but could be improved.',
                            'atrisk' => 'Needs significant improvement.',
                        },
                        'submitted_date' => $submittedAt,
                        'status' => 'released',
                    ])->save();
                }
            }
        }
    }

    private function createAttendanceData(): void
    {
        // Create 12 attendance sessions across 12 weeks
        $activities = Activity::where('type', 'forum')
            ->whereIn('course_id', $this->courseIds)
            ->take(12)
            ->get();

        foreach ($activities as $idx => $activity) {
            $session = AttendanceSession::firstOrNew([
                'activity_id' => $activity->id,
                'course_id' => $activity->course_id,
                'session_date' => Carbon::parse('2025-01-20')->addWeeks($idx),
            ]);
            $session->forceFill([
                'id' => $session->id ?? Str::uuid()->toString(),
                'activity_id' => $activity->id,
                'course_id' => $activity->course_id,
                'title' => 'Week ' . ($idx + 1) . ' Lecture',
                'description' => 'Regular lecture session',
                'session_date' => Carbon::parse('2025-01-20')->addWeeks($idx),
                'duration_minutes' => 120,
                'status' => 'closed',
            ])->save();
            $this->sessionIds[] = $session->id;

            foreach ($this->studentIds as $profile => $studentId) {
                $status = match ($profile) {
                    'high' => rand(0, 20) === 0 ? 'late' : 'present',  // 95% present
                    'medium' => match (rand(0, 3)) {
                        0 => 'absent',
                        1 => 'late',
                        default => 'present',
                    }, // ~75% present
                    'atrisk' => match (rand(0, 2)) {
                        0 => 'present',
                        1 => 'late',
                        default => 'absent',
                    }, // ~33% present
                };

                AttendanceLog::firstOrNew([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                ])->forceFill([
                    'id' => Str::uuid()->toString(),
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'remarks' => $status === 'absent' ? 'No show' : ($status === 'late' ? 'Arrived 15 min late' : null),
                    'taken_by' => $this->instructorId,
                ])->save();
            }
        }
    }

    private function createDashboardEngagement(): void
    {
        // Create 8 weeks of engagement data
        for ($w = 0; $w < 8; $w++) {
            $weekOf = Carbon::parse('2025-01-13')->addWeeks($w);
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

            foreach ($this->courseIds as $courseId) {
                foreach ($days as $day) {
                    // High student active almost every day
                    // Medium 3-4 days
                    // At-risk 1-2 days
                    $activeHigh = rand(0, 10) > 1 ? 1 : 0;
                    $activeMedium = rand(0, 10) > 4 ? 1 : 0;
                    $activeAtrisk = rand(0, 10) > 7 ? 1 : 0;

                    $activeStudents = $activeHigh + $activeMedium + $activeAtrisk;
                    $submissions = ($activeHigh * rand(0, 1)) + ($activeMedium * rand(0, 1)) + ($activeAtrisk * rand(0, 1));

                    DashboardEngagement::firstOrNew([
                        'course_id' => $courseId,
                        'day_label' => $day,
                        'week_of' => $weekOf,
                    ])->forceFill([
                        'id' => Str::uuid()->toString(),
                        'course_id' => $courseId,
                        'day_label' => $day,
                        'active_students' => $activeStudents,
                        'submissions' => $submissions,
                        'week_of' => $weekOf,
                    ])->save();
                }
            }
        }
    }

    private function createAiAtRiskData(): void
    {
        foreach ($this->courseIds as $courseId) {
            // HIGH - not at risk (skip or create low)
            // No record = not at risk, or create a low-risk record
            AiAtRiskStudent::firstOrNew([
                'course_id' => $courseId,
                'student_id' => $this->studentIds['high'],
            ])->forceFill([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'student_id' => $this->studentIds['high'],
                'student_name' => 'John Mwale',
                'progress' => 88.0,
                'last_access' => now()->subDays(1),
                'missed_activities' => 1,
                'grade' => 90.0,
                'risk_level' => 'low',
                'ai_recommendation' => 'Keep up the excellent work. Consider mentoring peers.',
                'detected_at' => now()->subDays(7),
            ])->save();

            // MEDIUM - low/moderate risk
            AiAtRiskStudent::firstOrNew([
                'course_id' => $courseId,
                'student_id' => $this->studentIds['medium'],
            ])->forceFill([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'student_id' => $this->studentIds['medium'],
                'student_name' => 'Grace Njau',
                'progress' => 62.0,
                'last_access' => now()->subDays(4),
                'missed_activities' => 4,
                'grade' => 68.0,
                'risk_level' => 'low',
                'ai_recommendation' => 'Join study groups. Schedule office hours for difficult topics.',
                'detected_at' => now()->subDays(10),
            ])->save();

            // AT-RISK - high risk
            AiAtRiskStudent::firstOrNew([
                'course_id' => $courseId,
                'student_id' => $this->studentIds['atrisk'],
            ])->forceFill([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'student_id' => $this->studentIds['atrisk'],
                'student_name' => 'Peter Masanja',
                'progress' => 22.0,
                'last_access' => now()->subDays(18),
                'missed_activities' => 12,
                'grade' => 35.0,
                'risk_level' => 'high',
                'ai_recommendation' => 'Urgent intervention required. Schedule counselling and academic advisor meeting immediately.',
                'detected_at' => now()->subDays(14),
            ])->save();
        }
    }

    private function createAiPerformanceSnapshots(): void
    {
        foreach ($this->courseIds as $courseId) {
            for ($w = 1; $w <= 8; $w++) {
                AiPerformanceSnapshot::firstOrNew([
                    'course_id' => $courseId,
                    'week_label' => 'Week ' . $w,
                ])->forceFill([
                    'id' => Str::uuid()->toString(),
                    'course_id' => $courseId,
                    'week_label' => 'Week ' . $w,
                    'avg_grade' => 65.0 + rand(-5, 5),
                    'completion_rate' => 70.0 + rand(-10, 10),
                    'engagement_score' => 60.0 + rand(-15, 15),
                    'recorded_at' => Carbon::parse('2025-01-13')->addWeeks($w),
                ])->save();
            }
        }
    }

    private function createAiRecommendations(): void
    {
        $recommendations = [
            [
                'title' => 'Peer Tutoring Program',
                'description' => 'Encourage at-risk students to join peer tutoring sessions every Wednesday.',
                'impact_level' => 'high',
                'icon_name' => 'users',
                'color_scheme' => 'blue',
            ],
            [
                'title' => 'Increase Assignment Frequency',
                'description' => 'Weekly low-stakes quizzes can improve engagement and catch struggling students early.',
                'impact_level' => 'medium',
                'icon_name' => 'clipboard-list',
                'color_scheme' => 'green',
            ],
            [
                'title' => 'Remedial Classes',
                'description' => 'Offer Saturday remedial classes for students with grades below 40%.',
                'impact_level' => 'high',
                'icon_name' => 'book-open',
                'color_scheme' => 'red',
            ],
        ];

        foreach ($this->courseIds as $courseId) {
            foreach ($recommendations as $rec) {
                AiRecommendation::firstOrNew([
                    'course_id' => $courseId,
                    'title' => $rec['title'],
                ])->forceFill([
                    'id' => Str::uuid()->toString(),
                    'course_id' => $courseId,
                    'title' => $rec['title'],
                    'description' => $rec['description'],
                    'impact_level' => $rec['impact_level'],
                    'icon_name' => $rec['icon_name'],
                    'color_scheme' => $rec['color_scheme'],
                    'generated_at' => now()->subDays(rand(1, 5)),
                ])->save();
            }
        }
    }

    private function createLearnerProfiles(): void
    {
        $profiles = [
            'high' => [
                'primary_profile' => 'achiever',
                'secondary_profile' => 'thinker',
                'is_mixed_profile' => false,
                'h_score' => 78,
                'a_score' => 85,
                't_score' => 92,
                'c_score' => 88,
                'drift_flag' => false,
                'profile_note' => 'Strong autonomous learner with high technical capability.',
            ],
            'medium' => [
                'primary_profile' => 'helper',
                'secondary_profile' => 'achiever',
                'is_mixed_profile' => true,
                'mixed_blend_primary' => 60,
                'mixed_blend_secondary' => 40,
                'h_score' => 55,
                'a_score' => 62,
                't_score' => 58,
                'c_score' => 60,
                'drift_flag' => false,
                'profile_note' => 'Social learner who benefits from group study but needs consistency.',
            ],
            'atrisk' => [
                'primary_profile' => 'disengaged',
                'secondary_profile' => null,
                'is_mixed_profile' => false,
                'h_score' => 25,
                'a_score' => 30,
                't_score' => 22,
                'c_score' => 28,
                'drift_flag' => true,
                'drift_weeks_count' => 4,
                'profile_note' => 'Significant disengagement detected. Requires immediate intervention.',
            ],
        ];

        foreach ($this->courseIds as $courseId) {
            foreach ($profiles as $profile => $data) {
                LearnerProfile::firstOrNew([
                    'learner_id' => $this->studentIds[$profile],
                    'course_id' => $courseId,
                ])->forceFill(array_merge([
                    'id' => Str::uuid()->toString(),
                    'learner_id' => $this->studentIds[$profile],
                    'course_id' => $courseId,
                    'declared_preferences' => json_encode(['visual', 'hands_on']),
                    'lms_flags' => json_encode(['new_enrolment' => false, 'first_login' => false]),
                    'pulse_consent' => true,
                    'pulse_consent_at' => now()->subMonths(2),
                    'drift_flagged_at' => $data['drift_flag'] ? now()->subWeeks(3) : null,
                ], $data))->save();
            }
        }
    }
}

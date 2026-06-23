<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\College;
use App\Models\ContentChunk;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\DegreeProgramme;
use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Models\LessonPage;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * LayoutPlayerDemoSeeder
 * ----------------------------------------------------------------------------
 * Presentation-ready demo of the adaptive learning system across all THREE
 * personalization layers — Adaptation, Presentation, and Navigation — using ONE
 * course and FOUR students, each profiled to land on a distinct layout player:
 *
 *   guided.student@demo.com     -> GuidedStepsPlayer      (guided_steps)
 *   visual.student@demo.com     -> VisualDiscoveryPlayer  (visual_discovery)
 *   focus.student@demo.com      -> DeepFocusPlayer        (deep_focus)
 *   narrative.student@demo.com  -> NarrativeExamplePlayer (narrative_example)
 *
 * Password for all four: @demo123
 *
 * Why this works (high-confidence, deterministic):
 *  - Each student's LearnerProfile.adaptation_mode_override LOCKS the player —
 *    PresentationAdaptationService::selectMode() honours the override first, so
 *    the correct player shows even when Gemini is enabled.
 *  - Realistic profile fields ALSO map to the same mode via the rule-based
 *    fallback (deriveModeFallback), so the demo is honest, not just forced.
 *  - The warm-up gate (PersonalizationContextService::isPersonalizationReady)
 *    is satisfied: enrolment is 21 days old AND each student has a graded quiz
 *    attempt on the course — so presentation/navigation actually activate.
 *  - The four players mount via AdaptiveActivityPanel (the video-activity path).
 *    A pre-seeded, COMPLETED CourseMaterial + content chunks guarantee adaptable
 *    content exists without depending on transcript extraction or a queue.
 *
 * Run standalone (does NOT change default seeding):
 *   php artisan db:seed --class=LayoutPlayerDemoSeeder
 */
class LayoutPlayerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Layout-Player demo (4 students, 1 course)...');

        $instructor = $this->resolveInstructor();

        // Isolated demo programme so the demo course never appears for real students.
        // Student course ACCESS is granted by degree programme (not enrolment), so the
        // four students + the course must share this programme.
        $college = College::where('code', 'CIVE')->first();
        if (! $college) {
            $college = new College();
            $college->forceFill([
                'id'   => Str::uuid()->toString(),
                'name' => 'College of Informatics and Virtual Education',
                'code' => 'CIVE',
            ])->save();
        }
        $programme = DegreeProgramme::where('code', 'DEMO-PROG')->first() ?? new DegreeProgramme();
        $programme->forceFill([
            'id'             => $programme->id ?? Str::uuid()->toString(),
            'college_id'     => $college->id,
            'name'           => 'Adaptive Learning Demo Programme',
            'code'           => 'DEMO-PROG',
            'description'    => 'Isolated programme used only by the layout-player demo.',
            'duration_years' => 1,
        ])->save();

        // ── 1. Course ────────────────────────────────────────────────────────
        $course = Course::firstOrNew(['short_name' => 'DEMO-AI']);
        $course->forceFill([
            'id'              => $course->id ?? Str::uuid()->toString(),
            'name'            => 'Adaptive Learning Demo: How Personalization Works',
            'short_name'      => 'DEMO-AI',
            'description'     => 'A one-module demo course showcasing AI personalization across adaptation, presentation, and navigation.',
            'summary'         => 'Demonstrates the four adaptive layout players and three-layer personalization.',
            'college_id'      => $college?->id,
            'instructor_id'   => $instructor->id,
            'instructor_name' => $instructor->name,
            'status'          => 'active',
            'visibility'      => 'shown',
            'format'          => 'topics',
            'language'        => 'English',
            'start_date'      => now()->subMonths(1)->toDateString(),
            'end_date'        => now()->addMonths(3)->toDateString(),
            'tags'            => json_encode(['demo', 'personalization', 'adaptive']),
            'max_students'    => 50,
            'enrolled_students' => 4,
        ])->save();

        // Link course → demo programme so enrolled students can actually open it.
        if (! DB::table('course_degree_programme')->where('course_id', $course->id)->where('degree_programme_id', $programme->id)->exists()) {
            DB::table('course_degree_programme')->insert([
                'course_id'           => $course->id,
                'degree_programme_id' => $programme->id,
            ]);
        }

        // ── 2. Section ───────────────────────────────────────────────────────
        $section = Section::firstOrNew(['course_id' => $course->id, 'title' => 'Module 01 — Understanding Personalization']);
        $section->forceFill([
            'id'         => $section->id ?? Str::uuid()->toString(),
            'course_id'  => $course->id,
            'title'      => 'Module 01 — Understanding Personalization',
            'summary'    => 'The same lesson, delivered four different ways.',
            'sort_order' => 0,
            'visible'    => true,
        ])->save();

        // ── 3a. The star: a strong TEXT lesson page (renders via the 4 players) ─
        $readingActivity = Activity::firstOrNew([
            'section_id' => $section->id,
            'course_id'  => $course->id,
            'name'       => 'Reading: How a Web Page Loads (HTTP Request Lifecycle)',
        ]);
        $readingActivity->forceFill([
            'id'          => $readingActivity->id ?? Str::uuid()->toString(),
            'section_id'  => $section->id,
            'course_id'   => $course->id,
            'type'        => 'lesson',
            'name'        => 'Reading: How a Web Page Loads (HTTP Request Lifecycle)',
            'description' => 'A single, media-free reading delivered in each learner\'s optimal layout.',
            'visible'     => true,
            'grade_max'   => 0,
            'sort_order'  => 0,
        ])->save();
        $this->seedTextLessonPage($readingActivity);

        // ── 3b. Demo lesson (video activity) — also mounts the adaptive players ─
        $lessonActivity = Activity::firstOrNew([
            'section_id' => $section->id,
            'course_id'  => $course->id,
            'name'       => 'Lesson: How Adaptive Learning Personalizes Your Experience',
        ]);
        $lessonActivity->forceFill([
            'id'          => $lessonActivity->id ?? Str::uuid()->toString(),
            'section_id'  => $section->id,
            'course_id'   => $course->id,
            'type'        => 'video',
            'name'        => 'Lesson: How Adaptive Learning Personalizes Your Experience',
            'description' => 'A short lesson with personalized notes rendered in each learner\'s optimal layout.',
            'visible'     => true,
            'grade_max'   => 0,
            'sort_order'  => 1,
            'settings'    => [
                // A stable, embeddable educational video (3Blue1Brown — neural networks).
                'videoUrl' => 'https://www.youtube.com/watch?v=aircAruvnKk',
            ],
        ])->save();

        // ── 4. Adaptable content: completed material + chunks ────────────────
        $this->seedAdaptableContent($course->id, $lessonActivity);

        // ── 5. Quiz activity — supplies warm-up evidence (a graded attempt) ──
        $quizActivity = Activity::firstOrNew([
            'section_id' => $section->id,
            'course_id'  => $course->id,
            'name'       => 'Knowledge Check (Week 1)',
        ]);
        $quizActivity->forceFill([
            'id'          => $quizActivity->id ?? Str::uuid()->toString(),
            'section_id'  => $section->id,
            'course_id'   => $course->id,
            'type'        => 'quiz',
            'name'        => 'Knowledge Check (Week 1)',
            'description' => 'A short check that establishes each learner\'s baseline.',
            'visible'     => true,
            'grade_max'   => 100,
            'sort_order'  => 2,
        ])->save();

        // ── 6. The four personas ─────────────────────────────────────────────
        // Each row: email, name, vark, pace_pref, modes, modality, pace,
        //           quizAvg, completion, hatc, override(player)
        $personas = [
            [
                'email' => 'guided.student@demo.com', 'name' => 'Gloria Guided',
                'vark' => 'read_write', 'pace_pref' => 'guided', 'modes' => ['structured'],
                'modality' => 'text', 'pace' => 'slow', 'quiz' => 45, 'completion' => 40,
                'hatc' => 'T', 'override' => 'guided_steps',
                'player' => 'GuidedStepsPlayer (numbered, signalled steps for novice/slow learners)',
            ],
            [
                'email' => 'visual.student@demo.com', 'name' => 'Victor Visual',
                'vark' => 'visual', 'pace_pref' => null, 'modes' => ['multimedia'],
                'modality' => 'visual', 'pace' => 'medium', 'quiz' => 72, 'completion' => 70,
                'hatc' => 'H', 'override' => 'visual_discovery',
                'player' => 'VisualDiscoveryPlayer (tables/headings emphasis for visual learners)',
            ],
            [
                'email' => 'focus.student@demo.com', 'name' => 'Faith Focus',
                'vark' => 'read_write', 'pace_pref' => 'accelerated', 'modes' => ['self_paced'],
                'modality' => 'text', 'pace' => 'fast', 'quiz' => 88, 'completion' => 92,
                'hatc' => 'A', 'override' => 'deep_focus',
                'player' => 'DeepFocusPlayer (dense academic prose for advanced/fast learners)',
            ],
            [
                'email' => 'narrative.student@demo.com', 'name' => 'Nathan Narrative',
                'vark' => 'kinesthetic', 'pace_pref' => null, 'modes' => ['classroom'],
                'modality' => 'example-based', 'pace' => 'medium', 'quiz' => 68, 'completion' => 65,
                'hatc' => 'C', 'override' => 'narrative_example',
                'player' => 'NarrativeExamplePlayer (example-first delivery)',
            ],
        ];

        foreach ($personas as $p) {
            $this->seedPersona($p, $course, $programme, $quizActivity);
        }

        $this->printSummary($course, $personas);
    }

    private function resolveInstructor(): User
    {
        $instructor = User::where('email', 'sarah@demo.com')->where('role', 'instructor')->first();
        if ($instructor) {
            return $instructor;
        }

        $instructor = User::firstOrNew(['email' => 'demo.instructor@demo.com']);
        $instructor->forceFill([
            'id'                => $instructor->id ?? Str::uuid()->toString(),
            'name'              => 'Dr. Demo Instructor',
            'email'             => 'demo.instructor@demo.com',
            'password'          => Hash::make('@demo123'),
            'role'              => 'instructor',
            'email_verified_at' => now(),
            'department'        => 'School of Computing',
            'institution'       => 'University of Dodoma',
            'country'           => 'Tanzania',
            'timezone'          => 'Africa/Dar_es_Salaam',
            'language'          => 'en',
        ])->save();

        return $instructor;
    }

    /**
     * One strong, media-free lesson page + its lesson_page chunks. Because the page
     * has no media, the student player delivers it through the four layout players —
     * the same content reshaped per learner (steps / tables / dense prose / example).
     */
    private function seedTextLessonPage(Activity $activity): void
    {
        // HTML kept for any non-personalized fallback render.
        $html = '<h2>How a Web Page Loads</h2>'
            . '<p>When you type a URL and press Enter, a precise sequence runs in well under a second. '
            . 'Understanding it explains everything from slow sites to login sessions.</p>'
            . '<h3>The request–response cycle</h3>'
            . '<ol>'
            . '<li><strong>DNS lookup</strong> — the domain name is translated into an IP address.</li>'
            . '<li><strong>TCP connection</strong> — your browser opens a connection to that server.</li>'
            . '<li><strong>HTTP request</strong> — the browser asks for the page with <code>GET /</code>.</li>'
            . '<li><strong>Server response</strong> — the server returns HTML, CSS, and JavaScript.</li>'
            . '<li><strong>Rendering</strong> — the browser paints the page and runs the scripts.</li>'
            . '</ol>'
            . '<h2>Key HTTP methods</h2>'
            . '<table><thead><tr><th>Method</th><th>Purpose</th><th>Changes data?</th></tr></thead>'
            . '<tbody>'
            . '<tr><td>GET</td><td>Retrieve a resource</td><td>No</td></tr>'
            . '<tr><td>POST</td><td>Create a resource</td><td>Yes</td></tr>'
            . '<tr><td>PUT</td><td>Replace a resource</td><td>Yes</td></tr>'
            . '<tr><td>DELETE</td><td>Remove a resource</td><td>Yes</td></tr>'
            . '</tbody></table>'
            . '<h3>A worked example</h3>'
            . '<p>Imagine submitting a login form. The browser sends a <strong>POST</strong> request with your '
            . 'credentials; the server verifies them and replies with a session token. From then on, each '
            . '<strong>GET</strong> request carries that token, so the server knows it is still you.</p>';

        $page = LessonPage::firstOrNew(['activity_id' => $activity->id, 'title' => 'How a Web Page Loads']);
        $page->forceFill([
            'id'         => $page->id ?? Str::uuid()->toString(),
            'activity_id'=> $activity->id,
            'title'      => 'How a Web Page Loads',
            'content'    => $html,
            'page_type'  => 'content',
            'sort_order' => 0,
        ])->save();

        // Markdown chunks (SafeMarkdown renders tables/lists/bold) — the players reshape these.
        $chunks = [
            "## How a Web Page Loads\n\n"
                . "When you type a URL and press Enter, a precise sequence runs in well under a second. "
                . "Understanding it explains everything from slow sites to login sessions.\n\n"
                . "### The request–response cycle\n\n"
                . "1. **DNS lookup** — the domain name is translated into an IP address.\n"
                . "2. **TCP connection** — your browser opens a connection to that server.\n"
                . "3. **HTTP request** — the browser asks for the page with `GET /`.\n"
                . "4. **Server response** — the server returns HTML, CSS, and JavaScript.\n"
                . "5. **Rendering** — the browser paints the page and runs the scripts.\n",
            "## Key HTTP methods\n\n"
                . "| Method | Purpose | Changes data? |\n"
                . "| --- | --- | --- |\n"
                . "| GET | Retrieve a resource | No |\n"
                . "| POST | Create a resource | Yes |\n"
                . "| PUT | Replace a resource | Yes |\n"
                . "| DELETE | Remove a resource | Yes |\n\n"
                . "### A worked example\n\n"
                . "Imagine submitting a login form. The browser sends a **POST** request with your credentials; "
                . "the server verifies them and replies with a session token. From then on, each **GET** request "
                . "carries that token, so the server knows it is still you.\n",
        ];

        foreach ($chunks as $i => $text) {
            $chunk = ContentChunk::firstOrNew([
                'content_source' => 'lesson_page',
                'content_id'     => $page->id,
                'chunk_index'    => $i,
            ]);
            $chunk->forceFill([
                'id'                  => $chunk->id ?? Str::uuid()->toString(),
                'content_source'      => 'lesson_page',
                'content_id'          => $page->id,
                'chunk_index'         => $i,
                'chunk_text'          => $text,
                'chunk_type'          => 'lecture',
                'semantic_role'       => $i === 0 ? 'concept' : 'example',
                'key_terms'           => $i === 0 ? ['DNS', 'TCP', 'HTTP request'] : ['GET', 'POST', 'session token'],
                'lesson_position_pct' => $i === 0 ? 25 : 80,
            ])->save();
        }
    }

    private function seedAdaptableContent(string $courseId, Activity $activity): void
    {
        $chunks = [
            [
                'role' => 'concept', 'pct' => 10,
                'terms' => ['personalization', 'adaptation', 'learner profile'],
                'text' => "## What Adaptive Learning Means\n\n"
                    . "Adaptive learning tailors the **same instructor content** to each learner without changing what is taught. "
                    . "The system builds a quiet learner profile from quiz performance, pace, and declared preferences, then adjusts how the lesson is *delivered*.\n\n"
                    . "There are three layers working together:\n\n"
                    . "- **Adaptation** — the wording and depth of the explanation.\n"
                    . "- **Presentation** — the visual layout and reading density.\n"
                    . "- **Navigation** — the order and emphasis of what comes next.",
            ],
            [
                'role' => 'explanation', 'pct' => 50,
                'terms' => ['presentation', 'layout player', 'cognitive load'],
                'text' => "## Why Different Layouts Help\n\n"
                    . "No single layout suits everyone. A novice benefits from **numbered, signalled steps**; a visual learner from **tables and headings**; "
                    . "an advanced learner from **dense, connected prose**; and an example-driven learner from a **worked example first**.\n\n"
                    . "Matching layout to the learner reduces unnecessary cognitive load, so attention goes to the ideas — not to fighting the format.",
            ],
            [
                'role' => 'example', 'pct' => 90,
                'terms' => ['navigation', 'mastery', 'feedback loop'],
                'text' => "## A Quick Example\n\n"
                    . "Imagine two students opening this very lesson. One is moving fast with strong scores; the other is just starting and finding it hard.\n\n"
                    . "1. The system reads each profile.\n"
                    . "2. It picks the best delivery for each — without telling them.\n"
                    . "3. It nudges each toward the next most useful activity.\n\n"
                    . "Both see the **same lesson**, yet each gets an experience that fits — that is personalization across all three layers.",
            ],
        ];

        $fullText = collect($chunks)->pluck('text')->implode("\n\n");

        $material = CourseMaterial::firstOrNew(['activity_id' => $activity->id]);
        $material->forceFill([
            'id'                => $material->id ?? Str::uuid()->toString(),
            'activity_id'       => $activity->id,
            'course_id'         => $courseId,
            'uploaded_by'       => $activity->course?->instructor_id,
            'title'             => 'Lesson notes: How adaptive learning works',
            'type'              => 'document',
            'extracted_text'    => $fullText,
            'word_count'        => str_word_count(strip_tags($fullText)),
            'processing_status' => 'completed', // suppresses the extraction job in ensureForActivity()
            'processing_error'  => null,
            'processed_at'      => now(),
        ])->save();

        foreach ($chunks as $i => $c) {
            $chunk = ContentChunk::firstOrNew([
                'content_source' => 'course_material',
                'content_id'     => $material->id,
                'chunk_index'    => $i,
            ]);
            $chunk->forceFill([
                'id'                  => $chunk->id ?? Str::uuid()->toString(),
                'content_source'      => 'course_material',
                'content_id'          => $material->id,
                'chunk_index'         => $i,
                'chunk_text'          => $c['text'],
                'chunk_type'          => 'lecture',
                'semantic_role'       => $c['role'],
                'key_terms'           => $c['terms'],
                'lesson_position_pct' => $c['pct'],
            ])->save();
        }
    }

    /**
     * @param array<string,mixed> $p
     */
    private function seedPersona(array $p, Course $course, ?DegreeProgramme $programme, Activity $quizActivity): void
    {
        // User
        $user = User::firstOrNew(['email' => $p['email']]);
        $user->forceFill([
            'id'                  => $user->id ?? Str::uuid()->toString(),
            'name'                => $p['name'],
            'email'               => $p['email'],
            'password'            => Hash::make('@demo123'),
            'role'                => 'student',
            'email_verified_at'   => now(),
            'degree_programme_id' => $programme?->id,
            'year_of_study'       => 2,
            'education_level'     => 'undergraduate',
            'vark_style'          => $p['vark'],
            'pace_preference'     => $p['pace_pref'],
            'preferred_modes'     => $p['modes'],
            'institution'         => 'University of Dodoma',
            'country'             => 'Tanzania',
            'timezone'            => 'Africa/Dar_es_Salaam',
            'language'            => 'en',
        ])->save();

        // Enrolment — 21 days old so the warm-up window has passed
        $enrol = Enrollment::firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
        $enrol->forceFill([
            'id'            => $enrol->id ?? Str::uuid()->toString(),
            'user_id'       => $user->id,
            'course_id'     => $course->id,
            'role'          => 'student',
            'status'        => 'active',
            'enrolled_date' => now()->subDays(21)->toDateString(),
            'last_access'   => now()->subDay(),
            'progress'      => (float) $p['completion'],
        ])->save();

        // Global student profile (fallback signals)
        $sp = StudentProfile::firstOrNew(['student_id' => $user->id]);
        $sp->forceFill([
            'id'                 => $sp->id ?? Str::uuid()->toString(),
            'student_id'         => $user->id,
            'pace'               => $p['pace'],
            'quiz_average'       => (float) $p['quiz'],
            'preferred_modality' => $p['modality'],
            'completion_rate'    => (float) $p['completion'],
            'weak_topics'        => $p['quiz'] < 50 ? ['Understanding Personalization'] : [],
        ])->save();

        // Course-scoped learner profile — the override LOCKS the player
        $lp = LearnerProfile::firstOrNew(['learner_id' => $user->id, 'course_id' => $course->id]);
        $lp->forceFill([
            'id'                       => $lp->id ?? Str::uuid()->toString(),
            'learner_id'               => $user->id,
            'course_id'                => $course->id,
            'primary_profile'          => $p['hatc'],
            'adaptation_mode_override' => $p['override'],
            'declared_preferences'     => [$p['modality']],
            'profile_note'             => 'Demo persona for ' . $p['override'] . '.',
        ])->save();

        // A graded quiz attempt → satisfies the warm-up gate AND sets course
        // quiz_average (drives knowledge_level / at-risk in the live pipeline).
        DB::table('quiz_attempts')->updateOrInsert(
            ['activity_id' => $quizActivity->id, 'student_id' => $user->id, 'attempt_number' => 1],
            [
                'id'           => (string) Str::uuid(),
                'course_id'    => $course->id,
                'status'       => 'graded',
                'started_at'   => now()->subDays(14),
                'submitted_at' => now()->subDays(14),
                'graded_at'    => now()->subDays(14),
                'time_spent'   => 600,
                'score'        => $p['quiz'],
                'max_score'    => 100,
                'updated_at'   => now(),
                'created_at'   => now()->subDays(14),
            ],
        );
    }

    /**
     * @param array<int,array<string,mixed>> $personas
     */
    private function printSummary(Course $course, array $personas): void
    {
        $this->command->info('');
        $this->command->info('✅ Layout-Player demo ready.');
        $this->command->info('Course: ' . $course->name . ' (' . $course->short_name . ')');
        $this->command->info('Password for all students: @demo123');
        $this->command->info('');
        $this->command->info('Open each student → My Courses → this course → Module 01 →');
        $this->command->info('"Reading: How a Web Page Loads" to see the SAME page in their layout player:');
        foreach ($personas as $p) {
            $this->command->info(sprintf('  • %-28s → %s', $p['email'], $p['player']));
        }
    }
}

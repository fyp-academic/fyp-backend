<?php
// app/Services/GeminiService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? '';
        $this->model  = config('services.gemini.model') ?? 'gemini-2.5-flash';

        if ($this->apiKey === '') {
            throw new \InvalidArgumentException(
                'Missing Gemini API key. Set GEMINI_API_KEY in .env and run: php artisan config:clear'
            );
        }   

        $this->endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Send a prompt with optional system instruction
     * This is my main method — everything calls this
     */
    public function generate(
        string $userMessage,
        string $systemInstruction = '',
        array  $options = []
    ): string {        
        // TODO: Added logging to see what's exactly being sent to Gemini
        Log::info('GeminiService::generate called', [
            'userMessage' => $userMessage,
            'systemInstruction' => $systemInstruction,
            'options' => $options,
        ]);
        
        // Multi-turn conversations are unique, so never serve them from cache.
        $history   = $options['history'] ?? [];
        $useCache  = ($options['cache'] ?? false) && empty($history);

        // Cache identical requests to protect free tier quota
        $cacheKey = 'gemini_' . md5($systemInstruction . $userMessage);

        if ($useCache) {
            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }
        }

        $payload = $this->buildPayload($userMessage, $systemInstruction, $options);

        try {
            $response = Http::timeout(30)
                ->post("{$this->endpoint}?key={$this->apiKey}", $payload);

            if ($response->failed()) {
                Log::error('Gemini API Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                // Handle rate limiting specifically
                if ($response->status() === 429) {
                    throw new \Exception('Rate limit reached. Please wait a moment.');
                }

                throw new \Exception("Gemini API failed with status: {$response->status()}");
            }

            $text = $response->json('candidates.0.content.parts.0.text', '');

            if ($useCache) {
                Cache::put($cacheKey, $text, now()->addMinutes(30));
            }

            return $text;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini connection failed', ['error' => $e->getMessage()]);
            throw new \Exception('Could not connect to AI service.');
        }
    }

    /**
     * Vision analysis — for webcam proctoring frames
     * Accepts base64 image string
     */
    public function analyzeImage(string $base64Image, string $prompt): array
    {
        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data'      => $base64Image,
                        ],
                    ],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 300,
            ],
        ];

        $response = Http::timeout(20)
            ->post("{$this->endpoint}?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \Exception('Gemini Vision failed: ' . $response->status());
        }

        $raw  = $response->json('candidates.0.content.parts.0.text', '{}');
        $clean = preg_replace('/```json|```/', '', $raw);

        return json_decode(trim($clean), true) ?? [];
    }

    /**
     * Generate quiz questions as structured JSON
     */
    public function generateQuizJson(string $prompt): array
    {
        $system = 'You are an academic quiz designer. 
Return ONLY a valid JSON array. 
No markdown formatting. No explanation text. No backticks. 
Start your response with [ and end with ]';

        $raw   = $this->generate($prompt, $system, ['temperature' => 0.8]);
        $clean = preg_replace('/```json|```/', '', $raw);

        return json_decode(trim($clean), true) ?? [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  INSTRUCTOR AI INSIGHTS — recommendations & suggestions
    //  (grounded in measured course performance; quota-friendly JSON)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Content/resource recommendations targeting a cohort's measured weaknesses.
     *
     * @return list<array<string,mixed>>  [{title, content_type, source, url, relevance_score}]
     */
    public function generateInstructorContentRecommendations(string $contextSummary, string $courseName): array
    {
        $system = 'You are an instructional-design assistant for a university LMS. '
            . 'Recommend concrete learning resources that address the measured weaknesses described. '
            . 'Return ONLY a valid JSON array — no markdown, no backticks, no commentary.';

        $prompt = <<<PROMPT
Course: {$courseName}

Measured performance signals:
{$contextSummary}

Recommend 4-6 learning resources (videos, articles, exercises, or internal review activities) that would most help this cohort improve on the WEAK areas above. Prefer reputable, well-known sources.

Return a JSON array where each item is exactly:
{"title": "<resource title>", "content_type": "video|article|exercise|quiz", "source": "<provider/site name or 'Internal'>", "url": "<https url or empty string>", "relevance_score": <integer 0-100, how directly it targets a measured weakness>}

Order by relevance_score descending. Return ONLY the JSON array.
PROMPT;

        $raw = $this->generate($prompt, $system, [
            'temperature'     => 0.5,
            'max_tokens'      => 1536,
            'thinking_budget' => 0,
        ]);

        return $this->decodeJsonArray($raw);
    }

    /**
     * Actionable pedagogical suggestions for the instructor, justified by the data.
     *
     * @return list<array<string,mixed>>  [{title, description, impact_level}]
     */
    public function generateInstructorSuggestions(string $contextSummary, string $courseName): array
    {
        $system = 'You are a pedagogy coach for a university instructor. '
            . 'Give specific, actionable teaching suggestions grounded in the measured data. '
            . 'Return ONLY a valid JSON array — no markdown, no backticks, no commentary.';

        $prompt = <<<PROMPT
Course: {$courseName}

Measured performance signals:
{$contextSummary}

Provide 3-5 actionable suggestions for the INSTRUCTOR to improve learning outcomes and engagement. Each suggestion must be directly justified by a signal above.

Return a JSON array where each item is exactly:
{"title": "<short action title>", "description": "<1-2 sentence concrete action>", "impact_level": "low|medium|high"}

Return ONLY the JSON array.
PROMPT;

        $raw = $this->generate($prompt, $system, [
            'temperature'     => 0.5,
            'max_tokens'      => 1024,
            'thinking_budget' => 0,
        ]);

        return $this->decodeJsonArray($raw);
    }

    /**
     * Tolerantly decode a JSON array from a model response that may be wrapped in
     * fences or an envelope object ({"items": [...]}). Returns [] on failure.
     *
     * @return list<array<string,mixed>>
     */
    private function decodeJsonArray(string $raw): array
    {
        $clean  = preg_replace('/```json|```/', '', $raw) ?? $raw;
        $parsed = json_decode(trim($clean), true);

        if (! is_array($parsed)) {
            return [];
        }
        if (array_is_list($parsed)) {
            return array_values(array_filter($parsed, 'is_array'));
        }
        // Envelope object — return the first list-valued property.
        foreach ($parsed as $value) {
            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
        }
        return [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  AI TUTOR WIDGET — Specialised generation methods
    // ═══════════════════════════════════════════════════════════════

    /**
     * Summarize a YouTube video using Gemini's video understanding.
     * Falls back to description-based summary when file_data isn't supported.
     */
    public function summarizeYouTubeVideo(string $youtubeUrl, array $studentProfile): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);

        $prompt = "You are a university tutor at the University of Dodoma, Tanzania.
Summarize this educational video for a student.
Use {$level}.
Structure your summary as:
1. **What This Video Covers** (2 sentences)
2. **Key Points** (bullet list, max 5 points, each under 15 words)
3. **Most Important Concept** (1 sentence)
Never exceed 200 words total.

Video URL: {$youtubeUrl}";

        $system = 'You are a patient, encouraging university tutor who adapts explanations to the student\'s level.';

        return $this->generate($prompt, $system, [
            'cache'      => true,
            'max_tokens' => 512,
        ]);
    }

    /**
     * Summarize extracted course material text, adapted to student level.
     */
    public function summarizeMaterial(string $title, string $type, string $extractedText, array $studentProfile): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);

        $system = "You are a university tutor in Tanzania summarizing course material.
Use {$level}.
Structure every summary exactly like this:
**What This Is About** (2 sentences max)
**Key Points** (5 bullet points max, each under 15 words)
**Most Important Concept** (1 sentence)
**What to Study Next** (1 sentence recommendation)
Never exceed 250 words. Never use jargon without explaining it.";

        $userMessage = "Summarize this course material for me.\n\nMaterial: {$title}\nType: {$type}\n\nContent:\n{$extractedText}";

        return $this->generate($userMessage, $system, ['cache' => true]);
    }

    /**
     * Generate spaced-repetition flashcards from course content.
     * Returns Markdown-formatted Q&A pairs.
     */
    public function generateFlashcards(string $content, string $courseName, array $studentProfile): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);

        $system = "You are a study coach creating flashcards for a university student.
Use {$level}.
Generate exactly 5 flashcards from the provided content.
Format each card as:
**Q:** [question]
**A:** [concise answer, max 2 sentences]
---
Make questions test understanding, not just recall.
Cover the most important concepts first.";

        $prompt = "Create flashcards from this content in {$courseName}:\n\n{$content}";

        return $this->generate($prompt, $system, ['cache' => true, 'max_tokens' => 1024]);
    }

    /**
     * Help a CS student debug code with Socratic guidance.
     * Gives hints, not direct answers.
     */
    public function debugCode(string $question, string $courseName, array $studentProfile, array $history = []): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);

        $system = "You are a patient CS tutor at the University of Dodoma.
The student is working on {$courseName}.
Use {$level}.
RULES:
1. Never give the complete corrected code directly
2. Identify the likely error type (syntax, logic, runtime)
3. Point to the specific line/area with the issue
4. Give a hint using a leading question (Socratic method)
5. If the student is very stuck (tms < 0.3), you may show a small corrected snippet
6. Always end with an encouraging next step";

        return $this->generate($question, $system, ['temperature' => 0.4, 'max_tokens' => 1024, 'history' => $history]);
    }

    /**
     * Provide emotional support and concrete next-step guidance.
     */
    public function motivateStudent(string $message, string $courseName, array $studentProfile, array $history = []): string
    {
        $enrolled  = ($studentProfile['enrolled'] ?? true) !== false;
        $riskLevel = $studentProfile['risk_level'] ?? 'unknown';
        $progress  = $studentProfile['progress'] ?? 'unknown';

        // Only feed real progress/risk into the prompt when the student is
        // actually enrolled — otherwise the AI would assert fabricated data.
        $dataLine = $enrolled
            ? "The student is studying {$courseName}. Their current progress: {$progress}. Risk level: {$riskLevel}."
            : "You do NOT have any course progress or risk data for this student (they are not enrolled in a specific course here). Do NOT mention progress percentages or risk levels — encourage them generally and, if helpful, suggest exploring the Course Catalog.";

        $system = "You are a supportive, empathetic university tutor and mentor.
{$dataLine}
RULES:
1. Acknowledge their feelings first (1-2 sentences)
2. Normalize the struggle — university is hard for everyone
3. Give ONE concrete, small action they can take RIGHT NOW
4. Reference their actual progress ONLY if you were given real data above — never invent numbers
5. Keep it warm but professional — you are a tutor, not a therapist
6. Never exceed 150 words";

        return $this->generate($message, $system, ['temperature' => 0.8, 'max_tokens' => 512, 'history' => $history]);
    }

    /**
     * Human, reasoning narration for the "find resources" intent.
     *
     * Decides honestly what to say based on whether we actually have a subject and
     * results. Never claims to have found resources that aren't there. Falls back
     * to a deterministic, context-aware sentence if the model call fails.
     *
     * @param string|null $subject              Topic/course the student wants resources for, or null.
     * @param string[]    $enrolledCourseNames  Names of the student's enrolled courses (may be empty).
     * @param array       $foundResources       Resources we are about to show (may be empty).
     */
    public function resourceResponse(
        ?string $subject,
        array $enrolledCourseNames,
        array $foundResources,
        array $studentProfile
    ): string {
        $hasSubject   = filled($subject);
        $hasResults   = count($foundResources) > 0;
        $hasCourses   = count($enrolledCourseNames) > 0;
        $courseList   = $hasCourses ? implode(', ', array_slice($enrolledCourseNames, 0, 6)) : '';
        $videoTitles  = $hasResults
            ? implode(' | ', array_map(fn ($r) => $r['title'] ?? '', array_slice($foundResources, 0, 5)))
            : '';

        $system = "You are a warm, thoughtful tutor at the University of Dodoma helping a student find learning resources.
You reason briefly out loud like a real person and you are scrupulously honest.
HARD RULES:
1. NEVER claim to have found videos/resources unless results are explicitly provided to you.
2. If there is NO subject and the student has NO enrolled courses: gently say you don't yet know what to look for, invite them to name a topic (give 1-2 concrete examples), and mention they can browse the Course Catalog to enrol. Do NOT invent topics for them.
3. If there is NO subject but the student HAS enrolled courses: name those courses and ask which one (or which specific topic) they'd like resources for.
4. If a subject AND results are provided: write a short, specific lead-in that names the subject and naturally introduces the videos below. Do not list the videos yourself.
5. If a subject is given but NO results were found: say so honestly and suggest how to refine the topic.
6. Conversational and human, 1-3 sentences, no bullet lists, no headings. Never exceed 70 words.";

        $context = "STUDENT SITUATION:\n"
            . '- Subject they want resources for: ' . ($hasSubject ? $subject : 'unknown / not specified') . "\n"
            . '- Enrolled courses: ' . ($hasCourses ? $courseList : 'none — the student is not enrolled in any course') . "\n"
            . '- Resources available to show: ' . ($hasResults ? $videoTitles : 'none') . "\n\n"
            . 'Write your reply following the rules.';

        try {
            $text = $this->generate($context, $system, ['temperature' => 0.6, 'max_tokens' => 200]);
            if (filled($text)) {
                return trim($text);
            }
        } catch (\Throwable $e) {
            // fall through to deterministic fallback
        }

        // Deterministic, context-aware fallback (model unavailable).
        if ($hasSubject && $hasResults) {
            return "Since you're looking into **{$subject}**, these should help — take a look:";
        }
        if ($hasSubject && !$hasResults) {
            return "I couldn't find solid videos for **{$subject}** right now. Try naming the exact topic (e.g. a specific concept) and I'll look again.";
        }
        if ($hasCourses) {
            return "Happy to find resources! Which of your courses should I focus on — {$courseList} — or is there a specific topic you have in mind?";
        }
        return "I'd love to find videos for you, but I don't yet know the topic — you're not enrolled in any course right now. Tell me what you'd like to learn (for example \"Python loops\" or \"photosynthesis\"), or browse the **Course Catalog** to enrol.";
    }

    /**
     * Socratic tutoring — explain a concept with adaptive depth.
     */
    public function tutorExplain(string $question, string $courseName, string $intent, array $studentProfile, array $history = []): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);
        $enrolmentNote = ($studentProfile['enrolled'] ?? true) === false
            ? "\n- The student is NOT enrolled in this course and you have NO progress/grade data for them — never state progress percentages or risk levels; speak in general terms."
            : '';

        $system = "You are a Socratic tutor at the University of Dodoma teaching {$courseName}.
Use {$level}.{$enrolmentNote}
IDENTITY & BOUNDARIES:
- You are an AI tutor embedded in the university's Learning Management System (LMS).
- You ONLY help with academic/learning questions related to the student's courses.
- If asked about system data (enrolled courses, instructors, grades, deadlines), say: 'I've checked your records — let me show you the exact data.' and provide ONLY factual answers. NEVER guess or fabricate numbers.
- If asked something completely unrelated to education or the LMS (e.g. sports, politics, cooking), politely redirect: 'I'm focused on helping you learn — try asking me about your courses!'
- NEVER pretend to know data you don't have. Say 'I don't have that information' when unsure.
RULES:
1. Start with a brief direct answer (2-3 sentences)
2. Then deepen with an analogy or example relevant to East African context when possible
3. If the student asks 'what is', give definition + example
4. If the student asks 'how does', give step-by-step process
5. End with a question that tests their understanding
6. Never exceed 300 words
7. For quiz-mode (restricted): only clarify terminology, never reveal answers";

        return $this->generate($question, $system, ['cache' => true, 'history' => $history]);
    }

    /**
     * Restricted mode handler — quiz-safe responses only.
     */
    public function restrictedResponse(string $question, string $courseName): string
    {
        $system = "You are an AI tutor assisting during a quiz in {$courseName}.
STRICT RULES:
1. You may ONLY clarify terminology or explain general concepts
2. NEVER give direct answers to quiz questions
3. NEVER solve problems step-by-step if they look like quiz questions
4. You may explain test-taking strategies (elimination, time management)
5. Keep responses under 100 words
6. If the student tries to get you to solve the question, politely decline";

        return $this->generate($question, $system, ['temperature' => 0.2, 'max_tokens' => 256]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  PERSONALISED AI NUDGE — for instructor → student outreach
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate a deeply personalised nudge message for a student.
     *
     * @param array $studentData  Full LMS profile collected by the caller
     * @return string             Formatted nudge message ready to display
     */
    public function generatePersonalizedNudge(array $studentData): string
    {
        $name        = $studentData['name']         ?? 'Student';
        $course      = $studentData['course_name']  ?? 'your course';
        $progress    = $studentData['progress']     ?? 0;
        $engagement  = $studentData['engagement_score'] ?? 'N/A';
        $streak      = $studentData['streak_days']  ?? 0;
        $inactive    = $studentData['inactive_days'] ?? 0;
        $risk        = $studentData['risk_level']   ?? 'unknown';
        $grade       = $studentData['current_grade'] ?? 'N/A';

        $vark        = $studentData['vark_style']        ?? null;
        $modes       = implode(', ', $studentData['preferred_modes'] ?? []);
        $pace        = $studentData['pace_preference']   ?? null;
        $interests   = implode(', ', $studentData['declared_interests'] ?? []);
        $supportNote = $studentData['support_notes']     ?? null;

        $strengths   = implode(', ', $studentData['strengths']       ?? []);
        $weaknesses  = implode(', ', $studentData['weak_areas']      ?? []);
        $loginRate   = $studentData['login_consistency']  ?? 'N/A';
        $contentComp = $studentData['content_completion'] ?? 'N/A';
        $assessAct   = $studentData['assessment_activity'] ?? 'N/A';
        $forumScore  = $studentData['forum_participation'] ?? 'N/A';
        $liveScore   = $studentData['live_session_score']  ?? 'N/A';
        $bounces     = $studentData['bounce_sessions']     ?? 0;
        $avgDuration = $studentData['avg_session_minutes'] ?? 'N/A';

        $courseDesc        = $studentData['course_description']  ?? null;
        $courseTopics      = implode(', ', $studentData['course_topics']      ?? []);
        $completedTopics   = implode(', ', $studentData['completed_topics']   ?? []);
        $incompleteTopics  = implode(', ', $studentData['incomplete_topics']  ?? []);

        $contextBlock = "
STUDENT PROFILE:
- Name: {$name}
- Course: {$course}
" . ($courseDesc ? "- Course description: {$courseDesc}\n" : '') . "
- Current progress: {$progress}%
- Current grade: {$grade}
- Engagement score: {$engagement}/100
- Risk level: {$risk}
- Learning streak: {$streak} consecutive days
- Days since last login: {$inactive}

COURSE CONTENT SYLLABUS:
- All course modules/topics/lectures: " . ($courseTopics ?: 'Not available') . "
- Topics/activities student has COMPLETED: " . ($completedTopics ?: 'None yet') . "
- Topics/activities student has NOT yet completed: " . ($incompleteTopics ?: 'All done') . "

DECLARED LEARNING PREFERENCES:
- Primary VARK style: " . ($vark ? ucfirst($vark) : 'Not declared') . "
- Preferred content modes: " . ($modes ?: 'Not specified') . "
- Preferred pace: " . ($pace ? ucfirst(str_replace('-', ' ', $pace)) : 'Not specified') . "
- Declared subject interests: " . ($interests ?: 'Not specified') . "
- Support context from student: " . ($supportNote ?: 'None provided') . "

ENGAGEMENT SIGNAL BREAKDOWN:
- Login consistency: {$loginRate}
- Content completion rate: {$contentComp}
- Assessment participation: {$assessAct}
- Forum participation: {$forumScore}
- Live session attendance: {$liveScore}
- Bounce sessions (< 2 min): {$bounces}
- Average session duration: {$avgDuration} minutes

ACADEMIC SIGNALS:
- Identified strengths: " . ($strengths ?: 'None detected yet') . "
- Weak areas: " . ($weaknesses ?: 'None detected yet') . "
";

        $system = "You are a university lecturer writing a short, private, personal message to one of your students. You have been closely watching their progress in the course and you know them well.

CRITICAL WRITING RULES — violating any of these will make the message useless:
1. DO NOT open with generic filler. Banned openers: 'Great to see', 'It\'s great to see', 'I noticed', 'According to your records', 'Based on the data', 'Your progress shows', 'I can see that', 'I hope', 'I wanted to reach out'. Instead, open with a direct, specific observation that sounds natural — like a lecturer who personally noticed something.
2. DO NOT sound like a computer reading a report. Write the way a caring, knowledgeable lecturer writes an email. Weave the numbers into natural sentences — do NOT list them mechanically.
3. Use the student\'s first name once, naturally, within the message.
4. You MUST cover exactly these 4 things in this order:
   a. A direct opening observation about where they stand — mention the course name and one key number (progress % or engagement score) woven into a natural sentence.
   b. One specific thing they are doing well — name the exact strength and explain what it shows about them as a learner.
   c. One specific topic from the INCOMPLETE TOPICS list that they need to focus on next — explain in plain language why this topic is foundational for the rest of the course, then give one concrete, actionable step tailored to their learning style.
   d. Exactly 3 learning resource recommendations, each on its own line, directly tied to the specific incomplete topics in this course. Format each bullet EXACTLY as:
      • [Type] — \"[Descriptive title]\" — [One sentence why it helps this topic and suits their learning style] → [URL]

      URL RULES (mandatory — never invent a specific video ID or article slug):
      - For Video: use a YouTube search URL → https://www.youtube.com/results?search_query=[topic+keywords]
      - For Article: use W3Schools, MDN, GeeksForGeeks, or Khan Academy direct page if topic fits (e.g. https://www.w3schools.com/...), otherwise → https://www.google.com/search?q=[topic+keywords]+tutorial
      - For Tutorial / Practice exercise / Interactive lab: use Codecademy, Khan Academy, W3Schools exercises, or similar → real base URLs only (e.g. https://www.codecademy.com, https://www.khanacademy.org, https://replit.com)
      - Replace spaces with + in search query parameters.
      - Types must be varied across the 3 bullets. Choose from: Video, Article, Tutorial, Practice exercise, Interactive lab.
5. Total message length: 220–280 words. Never shorter, never longer.
6. No salutation (no \'Dear\'), no sign-off (no \'Best regards\', no \'Sincerely\').
7. The student must not suspect this was written by anything other than their lecturer.";

        return $this->generate($contextBlock, $system, [
            'temperature' => 0.75,
            'max_tokens'  => 9999,
        ]);
    }

    // ── Proctoring: webcam frame analysis ────────────────────────────

    /**
     * Analyze a base64-encoded JPEG webcam frame for exam violations.
     * Returns: ['faces_detected'=>int, 'looking_away'=>bool, 'phone_detected'=>bool, 'violation'=>string|null]
     */
    public function analyzeWebcamFrame(string $base64Image): array
    {
        $prompt = 'You are a strict AI exam proctor. Carefully examine every part of this webcam image. '
            . 'Check ALL of the following points thoroughly: '
            . '1. Count EVERY human face or partial face visible anywhere in the image (foreground AND background). '
            . '2. Is the student visibly looking away from the monitor/screen for a significant duration (not a brief glance)? '
            . '3. Is any mobile phone, tablet, or additional screen visible in the image? '
            . '4. Is there any OTHER person visible — even partially, in the background, or reflected in a surface? '
            . '5. Is there any sign of unusual physical movement, motion blur, or a person suddenly entering/leaving the frame? '
            . 'Respond ONLY with a valid JSON object (no markdown, no explanation) with EXACTLY these fields: '
            . '{"faces_detected": <integer 0+>, "looking_away": <true|false>, "phone_detected": <true|false>, "background_person": <true|false>, "suspicious_movement": <true|false>, '
            . '"violation": <null or exactly one of: "no_face_detected","multiple_faces","looking_away","phone_detected","background_person","suspicious_movement">}. '
            . 'Violation priority (highest first): multiple_faces > no_face_detected > phone_detected > background_person > looking_away > suspicious_movement. '
            . 'Set multiple_faces if faces_detected >= 2. Set background_person if any other person is visible even partially. '
            . 'Do NOT be conservative — flag any clear violation you observe.';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text'        => $prompt],
                    ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $base64Image]],
                ],
            ]],
            'generationConfig' => ['temperature' => 0.05, 'maxOutputTokens' => 128],
        ];

        try {
            $response = Http::timeout(15)
                ->post("{$this->endpoint}?key={$this->apiKey}", $payload);

            $text = $response->json('candidates.0.content.parts.0.text') ?? '';
            $text = preg_replace('/```json\s*|\s*```/', '', $text);
            $result = json_decode(trim($text), true);

            return is_array($result) ? $result : [
                'faces_detected' => 1, 'looking_away' => false,
                'phone_detected' => false, 'violation' => null,
            ];
        } catch (\Throwable) {
            return ['faces_detected' => 1, 'looking_away' => false, 'phone_detected' => false, 'violation' => null];
        }
    }

    // ── Proctoring: AI-content detection ─────────────────────────────

    /**
     * Detect whether student-submitted text was AI-generated.
     * Returns: ['is_ai_generated'=>bool, 'confidence'=>float, 'indicators'=>string[], 'recommendation'=>string]
     */
    public function detectAiGeneratedContent(string $text): array
    {
        $fallback = ['is_ai_generated' => false, 'confidence' => 0.0, 'indicators' => [], 'recommendation' => 'Analysis inconclusive'];

        if (mb_strlen(trim($text)) < 80) {
            return array_merge($fallback, ['recommendation' => 'Text too short to analyse']);
        }

        $system = 'You are an academic integrity specialist detecting AI-generated student submissions. '
            . 'Look for: unnaturally uniform sentence length, absence of personal voice, hedging phrases typical of LLMs, '
            . 'overly perfect grammar/structure, no typos or colloquialisms, generic examples, lack of specific course knowledge, '
            . 'copy-paste patterns. Be accurate — penalise genuine plagiarism, not good writing.';

        $prompt = "Analyse this student submission. Respond ONLY with valid JSON — no markdown, no explanation:\n"
            . '{"is_ai_generated": <true|false>, "confidence": <0.0–1.0>, '
            . '"indicators": ["<specific phrase or pattern observed>", ...], '
            . '"recommendation": "<one sentence for instructor>"}'
            . "\n\nSUBMISSION:\n" . mb_substr($text, 0, 4000);

        try {
            $raw    = $this->generate($prompt, $system, ['temperature' => 0.05, 'max_tokens' => 512]);
            $raw    = preg_replace('/```json\s*|\s*```/', '', $raw ?? '');
            $parsed = json_decode(trim($raw), true);
            return is_array($parsed) ? $parsed : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    // ── Adaptive level helper ────────────────────────────────────────

    /**
     * Convert a Topic Mastery Score (0–1) to a natural-language instruction level.
     */
    private function adaptiveLevel(float $tms): string
    {
        if ($tms < 0.3) return 'very simple language, step by step, with real-world analogies';
        if ($tms < 0.5) return 'simple, beginner-friendly language with analogies';
        if ($tms < 0.7) return 'clear, moderate detail with some technical terms explained';
        if ($tms < 0.85) return 'technical, concise language';
        return 'advanced, dense, expert-level language — skip basics';
    }

    /**
     * Assemble the API payload
     */
    private function buildPayload(string $message, string $system, array $options): array
    {
        // Prepend prior conversation turns (multi-turn memory). Each entry is
        // ['role' => 'user'|'model', 'content' => string]. Invalid/empty entries
        // are skipped so a malformed history can never break the request.
        $contents = [];
        foreach ($options['history'] ?? [] as $turn) {
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string) ($turn['content'] ?? ''));
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }

        // Current user message
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
            ],
        ];

        // gemini-2.5-* are reasoning models whose "thinking" tokens count against
        // maxOutputTokens — left uncapped they can consume the whole budget and
        // truncate the real answer. Callers that need deterministic JSON/text pass
        // thinking_budget=0 to disable thinking. (See project memory.)
        if (array_key_exists('thinking_budget', $options)) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => (int) $options['thinking_budget'],
            ];
        }

        // Add system instruction if provided
        if (!empty($system)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        return $payload;
    }
}
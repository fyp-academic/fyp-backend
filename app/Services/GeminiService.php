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
        
        // Cache identical requests to protect free tier quota
        $cacheKey = 'gemini_' . md5($systemInstruction . $userMessage);

        if ($options['cache'] ?? false) {
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

            if ($options['cache'] ?? false) {
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
    public function debugCode(string $question, string $courseName, array $studentProfile): string
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

        return $this->generate($question, $system, ['temperature' => 0.4, 'max_tokens' => 1024]);
    }

    /**
     * Provide emotional support and concrete next-step guidance.
     */
    public function motivateStudent(string $message, string $courseName, array $studentProfile): string
    {
        $riskLevel = $studentProfile['risk_level'] ?? 'unknown';
        $progress  = $studentProfile['progress'] ?? 'unknown';

        $system = "You are a supportive, empathetic university tutor and mentor.
The student is studying {$courseName}.
Their current progress: {$progress}. Risk level: {$riskLevel}.
RULES:
1. Acknowledge their feelings first (1-2 sentences)
2. Normalize the struggle — university is hard for everyone
3. Give ONE concrete, small action they can take RIGHT NOW
4. Reference their actual progress if available — celebrate any wins
5. Keep it warm but professional — you are a tutor, not a therapist
6. Never exceed 150 words";

        return $this->generate($message, $system, ['temperature' => 0.8, 'max_tokens' => 512]);
    }

    /**
     * Socratic tutoring — explain a concept with adaptive depth.
     */
    public function tutorExplain(string $question, string $courseName, string $intent, array $studentProfile): string
    {
        $level = $this->adaptiveLevel($studentProfile['tms'] ?? 0.5);

        $system = "You are a Socratic tutor at the University of Dodoma teaching {$courseName}.
Use {$level}.
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

        return $this->generate($question, $system, ['cache' => true]);
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
- All course modules/topics: " . ($courseTopics ?: 'Not available') . "
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
        $payload = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $message]],
            ]],
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
            ],
        ];

        // Add system instruction if provided
        if (!empty($system)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        return $payload;
    }
}
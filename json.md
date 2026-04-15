# EduAI LMS — Complete Database JSON

All tables, records, and relationships that power the EduAI LMS UI exactly as designed.
Each section lists the **table name**, its **columns/types**, and the **full seed data** as JSON.

---

## Table of Contents

1. [users](#1-users)
2. [categories](#2-categories)
3. [courses](#3-courses)
4. [sections](#4-sections)
5. [activities](#5-activities)
6. [enrollments (participants)](#6-enrollments-participants)
7. [grade_items](#7-grade_items)
8. [student_grades](#8-student_grades)
9. [notifications](#9-notifications)
10. [conversations](#10-conversations)
11. [messages](#11-messages)
12. [quiz_questions](#12-quiz_questions)
13. [quiz_answers](#13-quiz_answers)
14. [ai_performance_snapshots](#14-ai_performance_snapshots)
15. [ai_skill_metrics](#15-ai_skill_metrics)
16. [ai_at_risk_students](#16-ai_at_risk_students)
17. [ai_recommendations](#17-ai_recommendations)
18. [ai_content_recommendations](#18-ai_content_recommendations)
19. [ai_generated_questions](#19-ai_generated_questions)
20. [user_preferences](#20-user_preferences)
21. [activity_performance (chart data)](#21-activity_performance-chart-data)
22. [dashboard_engagement](#22-dashboard_engagement)

---

## 1. `users`

**Columns:** `id`, `name`, `email`, `role`, `initials`, `department`, `institution`, `country`, `timezone`, `language`, `bio`, `join_date`, `last_access`, `enrolled_courses`

```json
[
  {
    "id": "user1",
    "name": "Dr. Sarah Johnson",
    "email": "sarah.johnson@university.edu",
    "role": "instructor",
    "initials": "SJ",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": "Experienced instructor in Computer Science and Programming. PhD from MIT. 10+ years teaching experience.",
    "join_date": "2019-09-01",
    "last_access": "Today",
    "enrolled_courses": 2
  },
  {
    "id": "user2",
    "name": "Prof. Michael Chen",
    "email": "m.chen@university.edu",
    "role": "instructor",
    "initials": "MC",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/Los_Angeles",
    "language": "English",
    "bio": "Professor of Machine Learning and AI. Research focus on deep learning and model optimization.",
    "join_date": "2017-01-15",
    "last_access": "Today",
    "enrolled_courses": 1
  },
  {
    "id": "user3",
    "name": "Dr. Emily Roberts",
    "email": "e.roberts@university.edu",
    "role": "instructor",
    "initials": "ER",
    "department": "Business",
    "institution": "University of Technology",
    "country": "United Kingdom",
    "timezone": "Europe/London",
    "language": "English",
    "bio": "Business strategy and management expert. MBA from Oxford, DBA from LSE.",
    "join_date": "2020-03-10",
    "last_access": "2 days ago",
    "enrolled_courses": 1
  },
  {
    "id": "user4",
    "name": "Prof. Alan Turing",
    "email": "a.turing@university.edu",
    "role": "instructor",
    "initials": "AT",
    "department": "Mathematics",
    "institution": "University of Technology",
    "country": "United Kingdom",
    "timezone": "Europe/London",
    "language": "English",
    "bio": "Professor of Pure Mathematics. Research in computational theory and applied mathematics.",
    "join_date": "2015-09-01",
    "last_access": "1 week ago",
    "enrolled_courses": 1
  },
  {
    "id": "admin1",
    "name": "Admin User",
    "email": "admin@university.edu",
    "role": "admin",
    "initials": "AU",
    "department": "Administration",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": "System administrator for EduAI LMS.",
    "join_date": "2015-01-01",
    "last_access": "Today",
    "enrolled_courses": 0
  },
  {
    "id": "p1",
    "name": "Alice Thompson",
    "email": "alice@university.edu",
    "role": "student",
    "initials": "AT",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "2 hours ago",
    "enrolled_courses": 3
  },
  {
    "id": "p2",
    "name": "Bob Martinez",
    "email": "bob@university.edu",
    "role": "student",
    "initials": "BM",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/Chicago",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "1 day ago",
    "enrolled_courses": 3
  },
  {
    "id": "p3",
    "name": "Carol White",
    "email": "carol@university.edu",
    "role": "student",
    "initials": "CW",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/Los_Angeles",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "3 hours ago",
    "enrolled_courses": 3
  },
  {
    "id": "p4",
    "name": "David Kim",
    "email": "david@university.edu",
    "role": "student",
    "initials": "DK",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "5 days ago",
    "enrolled_courses": 3
  },
  {
    "id": "p5",
    "name": "Emma Wilson",
    "email": "emma@university.edu",
    "role": "student",
    "initials": "EW",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/Denver",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "Today",
    "enrolled_courses": 3
  },
  {
    "id": "p6",
    "name": "Frank Lee",
    "email": "frank@university.edu",
    "role": "student",
    "initials": "FL",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "2 days ago",
    "enrolled_courses": 3
  },
  {
    "id": "p7",
    "name": "Grace Chen",
    "email": "grace@university.edu",
    "role": "student",
    "initials": "GC",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/Los_Angeles",
    "language": "English",
    "bio": "Teaching assistant for CS department.",
    "join_date": "2024-09-01",
    "last_access": "Today",
    "enrolled_courses": 3
  },
  {
    "id": "p8",
    "name": "Henry Adams",
    "email": "henry@university.edu",
    "role": "student",
    "initials": "HA",
    "department": "Computer Science",
    "institution": "University of Technology",
    "country": "United States",
    "timezone": "America/New_York",
    "language": "English",
    "bio": null,
    "join_date": "2025-09-01",
    "last_access": "1 week ago",
    "enrolled_courses": 3
  }
]
```

---

## 2. `categories`

**Columns:** `id`, `name`, `description`, `parent_id`, `id_number`, `course_count`, `child_count`

```json
[
  {
    "id": "cat1",
    "name": "Computer Science",
    "description": "CS and programming courses",
    "parent_id": null,
    "id_number": "CS001",
    "course_count": 8,
    "child_count": 2
  },
  {
    "id": "cat2",
    "name": "Web Development",
    "description": "Frontend and backend development",
    "parent_id": "cat1",
    "id_number": "CS-WEB",
    "course_count": 4,
    "child_count": 0
  },
  {
    "id": "cat3",
    "name": "Data Science",
    "description": "ML and data analytics",
    "parent_id": "cat1",
    "id_number": "CS-DS",
    "course_count": 3,
    "child_count": 0
  },
  {
    "id": "cat4",
    "name": "Mathematics",
    "description": "Pure and applied mathematics",
    "parent_id": null,
    "id_number": "MATH",
    "course_count": 5,
    "child_count": 1
  },
  {
    "id": "cat5",
    "name": "Calculus",
    "description": "Differential and integral calculus",
    "parent_id": "cat4",
    "id_number": "MATH-CALC",
    "course_count": 2,
    "child_count": 0
  },
  {
    "id": "cat6",
    "name": "Business",
    "description": "Business and management courses",
    "parent_id": null,
    "id_number": "BUS",
    "course_count": 6,
    "child_count": 0
  },
  {
    "id": "cat7",
    "name": "Sciences",
    "description": "Natural sciences",
    "parent_id": null,
    "id_number": "SCI",
    "course_count": 4,
    "child_count": 0
  }
]
```

---

## 3. `courses`

**Columns:** `id`, `name`, `short_name`, `description`, `category_id`, `category_name`, `instructor_id`, `instructor_name`, `enrolled_students`, `status`, `visibility`, `format`, `start_date`, `end_date`, `language`, `tags`, `max_students`

> Tags stored as a `text[]` (PostgreSQL array) or a JSON array.

```json
[
  {
    "id": "course1",
    "name": "Introduction to Python Programming",
    "short_name": "PYTH101",
    "description": "Learn Python from scratch with hands-on projects",
    "category_id": "cat2",
    "category_name": "Web Development",
    "instructor_id": "user1",
    "instructor_name": "Dr. Sarah Johnson",
    "enrolled_students": 142,
    "status": "active",
    "visibility": "shown",
    "format": "topics",
    "start_date": "2026-01-15",
    "end_date": "2026-06-15",
    "language": "English",
    "tags": ["python", "programming", "beginner"],
    "max_students": null
  },
  {
    "id": "course2",
    "name": "Advanced Machine Learning",
    "short_name": "ML401",
    "description": "Deep learning, neural networks, and model deployment",
    "category_id": "cat3",
    "category_name": "Data Science",
    "instructor_id": "user2",
    "instructor_name": "Prof. Michael Chen",
    "enrolled_students": 89,
    "status": "active",
    "visibility": "shown",
    "format": "weekly",
    "start_date": "2026-01-20",
    "end_date": "2026-05-20",
    "language": "English",
    "tags": ["ml", "ai", "deeplearning"],
    "max_students": null
  },
  {
    "id": "course3",
    "name": "Business Management Fundamentals",
    "short_name": "BUS101",
    "description": "Core concepts in business strategy and management",
    "category_id": "cat6",
    "category_name": "Business",
    "instructor_id": "user3",
    "instructor_name": "Dr. Emily Roberts",
    "enrolled_students": 215,
    "status": "active",
    "visibility": "shown",
    "format": "topics",
    "start_date": "2026-01-10",
    "end_date": "2026-05-10",
    "language": "English",
    "tags": ["business", "management", "strategy"],
    "max_students": null
  },
  {
    "id": "course4",
    "name": "Calculus I: Differential Calculus",
    "short_name": "CALC101",
    "description": "Limits, derivatives, and applications",
    "category_id": "cat5",
    "category_name": "Calculus",
    "instructor_id": "user4",
    "instructor_name": "Prof. Alan Turing",
    "enrolled_students": 178,
    "status": "draft",
    "visibility": "hidden",
    "format": "weekly",
    "start_date": "2026-03-01",
    "end_date": "2026-07-01",
    "language": "English",
    "tags": ["math", "calculus", "derivatives"],
    "max_students": null
  },
  {
    "id": "course5",
    "name": "React & TypeScript Mastery",
    "short_name": "REACT301",
    "description": "Build modern web apps with React and TypeScript",
    "category_id": "cat2",
    "category_name": "Web Development",
    "instructor_id": "user1",
    "instructor_name": "Dr. Sarah Johnson",
    "enrolled_students": 98,
    "status": "active",
    "visibility": "shown",
    "format": "topics",
    "start_date": "2026-02-01",
    "end_date": "2026-06-01",
    "language": "English",
    "tags": ["react", "typescript", "frontend"],
    "max_students": null
  }
]
```

---

## 4. `sections`

**Columns:** `id`, `course_id`, `title`, `summary`, `sort_order`, `visible`, `collapsed`

```json
[
  { "id": "sec0-c1",  "course_id": "course1", "title": "General",                       "summary": null, "sort_order": 0, "visible": true, "collapsed": false },
  { "id": "sec1-c1",  "course_id": "course1", "title": "Week 1: Introduction to Python", "summary": null, "sort_order": 1, "visible": true, "collapsed": false },
  { "id": "sec2-c1",  "course_id": "course1", "title": "Week 2: Control Flow",           "summary": null, "sort_order": 2, "visible": true, "collapsed": false },
  { "id": "sec3-c1",  "course_id": "course1", "title": "Week 3: Functions & Modules",    "summary": null, "sort_order": 3, "visible": true, "collapsed": false },
  { "id": "sec4-c1",  "course_id": "course1", "title": "Week 4: OOP Fundamentals",       "summary": null, "sort_order": 4, "visible": true, "collapsed": false },
  { "id": "sec0-c2",  "course_id": "course2", "title": "General",                       "summary": null, "sort_order": 0, "visible": true, "collapsed": false },
  { "id": "sec1-c2",  "course_id": "course2", "title": "Module 1: Foundations",          "summary": null, "sort_order": 1, "visible": true, "collapsed": false },
  { "id": "sec2-c2",  "course_id": "course2", "title": "Module 2: Neural Networks",      "summary": null, "sort_order": 2, "visible": true, "collapsed": false },
  { "id": "sec0-c3",  "course_id": "course3", "title": "General",                       "summary": null, "sort_order": 0, "visible": true, "collapsed": false },
  { "id": "sec1-c3",  "course_id": "course3", "title": "Introduction to Management",    "summary": null, "sort_order": 1, "visible": true, "collapsed": false },
  { "id": "sec0-c4",  "course_id": "course4", "title": "General",                       "summary": null, "sort_order": 0, "visible": true, "collapsed": false },
  { "id": "sec0-c5",  "course_id": "course5", "title": "General",                       "summary": null, "sort_order": 0, "visible": true, "collapsed": false },
  { "id": "sec1-c5",  "course_id": "course5", "title": "React Fundamentals",             "summary": null, "sort_order": 1, "visible": true, "collapsed": false }
]
```

---

## 5. `activities`

**Columns:** `id`, `section_id`, `course_id`, `type`, `name`, `description`, `due_date`, `visible`, `completion_status`, `grade_max`, `sort_order`, `settings`

> `type` enum: `quiz | assignment | forum | url | file | h5p | scorm | workshop | label | page`
> `completion_status` enum: `completed | incomplete | none`

```json
[
  { "id": "a1",  "section_id": "sec0-c1", "course_id": "course1", "type": "forum",      "name": "News and Announcements",          "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "a2",  "section_id": "sec1-c1", "course_id": "course1", "type": "page",       "name": "Course Introduction & Setup",     "description": null, "due_date": null,         "visible": true,  "completion_status": "completed", "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "a3",  "section_id": "sec1-c1", "course_id": "course1", "type": "file",       "name": "Python Installation Guide.pdf",   "description": null, "due_date": null,         "visible": true,  "completion_status": "completed", "grade_max": null, "sort_order": 1, "settings": {} },
  { "id": "a4",  "section_id": "sec1-c1", "course_id": "course1", "type": "quiz",       "name": "Quiz 1: Python Basics",           "description": null, "due_date": "2026-02-01", "visible": true,  "completion_status": "completed", "grade_max": 100,  "sort_order": 2, "settings": {} },
  { "id": "a5",  "section_id": "sec2-c1", "course_id": "course1", "type": "url",        "name": "Python Docs - Control Flow",      "description": null, "due_date": null,         "visible": true,  "completion_status": "incomplete","grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "a6",  "section_id": "sec2-c1", "course_id": "course1", "type": "assignment", "name": "Assignment 1: FizzBuzz",          "description": null, "due_date": "2026-02-08", "visible": true,  "completion_status": "incomplete","grade_max": 50,   "sort_order": 1, "settings": {} },
  { "id": "a7",  "section_id": "sec2-c1", "course_id": "course1", "type": "h5p",        "name": "Interactive: Python Loops",       "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 2, "settings": {} },
  { "id": "a8",  "section_id": "sec3-c1", "course_id": "course1", "type": "quiz",       "name": "Quiz 2: Functions",               "description": null, "due_date": "2026-02-15", "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 0, "settings": {} },
  { "id": "a9",  "section_id": "sec3-c1", "course_id": "course1", "type": "workshop",   "name": "Workshop: Peer Code Review",      "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 1, "settings": {} },
  { "id": "a10", "section_id": "sec4-c1", "course_id": "course1", "type": "scorm",      "name": "OOP Concepts - SCORM Package",    "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "b1",  "section_id": "sec0-c2", "course_id": "course2", "type": "forum",      "name": "Announcements",                   "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "b2",  "section_id": "sec1-c2", "course_id": "course2", "type": "quiz",       "name": "Pre-assessment Quiz",             "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 0, "settings": {} },
  { "id": "b3",  "section_id": "sec1-c2", "course_id": "course2", "type": "assignment", "name": "Assignment: Linear Regression",   "description": null, "due_date": "2026-02-10", "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 1, "settings": {} },
  { "id": "b4",  "section_id": "sec2-c2", "course_id": "course2", "type": "file",       "name": "NN Architecture Reference.pdf",   "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "b5",  "section_id": "sec2-c2", "course_id": "course2", "type": "quiz",       "name": "Quiz: Neural Networks",           "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 1, "settings": {} },
  { "id": "c1",  "section_id": "sec0-c3", "course_id": "course3", "type": "forum",      "name": "Discussion Forum",                "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "c2",  "section_id": "sec1-c3", "course_id": "course3", "type": "quiz",       "name": "Quiz 1",                          "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 0, "settings": {} },
  { "id": "d1",  "section_id": "sec0-c5", "course_id": "course5", "type": "forum",      "name": "Q&A Forum",                       "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": null, "sort_order": 0, "settings": {} },
  { "id": "d2",  "section_id": "sec1-c5", "course_id": "course5", "type": "assignment", "name": "Build a Todo App",                "description": null, "due_date": null,         "visible": true,  "completion_status": "none",      "grade_max": 100,  "sort_order": 0, "settings": {} }
]
```

---

## 6. `enrollments` (participants)

**Columns:** `id`, `user_id`, `course_id`, `role`, `enrolled_date`, `last_access`, `progress`, `groups`

> `role` enum: `student | instructor | teaching_assistant | observer`
> `groups` stored as `text[]` array.

```json
[
  { "id": "e1",  "user_id": "p1", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-15", "last_access": "2 hours ago", "progress": 85, "groups": ["Group A"] },
  { "id": "e2",  "user_id": "p2", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-15", "last_access": "1 day ago",   "progress": 62, "groups": ["Group B"] },
  { "id": "e3",  "user_id": "p3", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-16", "last_access": "3 hours ago", "progress": 91, "groups": ["Group A"] },
  { "id": "e4",  "user_id": "p4", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-18", "last_access": "5 days ago",  "progress": 23, "groups": ["Group B"] },
  { "id": "e5",  "user_id": "p5", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-20", "last_access": "Today",       "progress": 78, "groups": ["Group A"] },
  { "id": "e6",  "user_id": "p6", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-20", "last_access": "2 days ago",  "progress": 45, "groups": ["Group C"] },
  { "id": "e7",  "user_id": "p7", "course_id": "course1", "role": "teaching_assistant","enrolled_date": "2026-01-14", "last_access": "Today",       "progress": 100,"groups": [] },
  { "id": "e8",  "user_id": "p8", "course_id": "course1", "role": "student",           "enrolled_date": "2026-01-22", "last_access": "1 week ago",  "progress": 12, "groups": ["Group C"] },
  { "id": "e9",  "user_id": "p1", "course_id": "course2", "role": "student",           "enrolled_date": "2026-01-20", "last_access": "Today",       "progress": 70, "groups": [] },
  { "id": "e10", "user_id": "p2", "course_id": "course2", "role": "student",           "enrolled_date": "2026-01-20", "last_access": "2 days ago",  "progress": 55, "groups": [] },
  { "id": "e11", "user_id": "p3", "course_id": "course3", "role": "student",           "enrolled_date": "2026-01-10", "last_access": "Today",       "progress": 88, "groups": [] },
  { "id": "e12", "user_id": "p4", "course_id": "course3", "role": "student",           "enrolled_date": "2026-01-10", "last_access": "1 week ago",  "progress": 20, "groups": [] },
  { "id": "e13", "user_id": "p5", "course_id": "course5", "role": "student",           "enrolled_date": "2026-02-01", "last_access": "Today",       "progress": 60, "groups": [] },
  { "id": "e14", "user_id": "user1", "course_id": "course1", "role": "instructor",     "enrolled_date": "2026-01-01", "last_access": "Today",       "progress": 100,"groups": [] },
  { "id": "e15", "user_id": "user1", "course_id": "course5", "role": "instructor",     "enrolled_date": "2026-01-01", "last_access": "Today",       "progress": 100,"groups": [] },
  { "id": "e16", "user_id": "user2", "course_id": "course2", "role": "instructor",     "enrolled_date": "2026-01-01", "last_access": "Today",       "progress": 100,"groups": [] },
  { "id": "e17", "user_id": "user3", "course_id": "course3", "role": "instructor",     "enrolled_date": "2026-01-01", "last_access": "2 days ago",  "progress": 100,"groups": [] },
  { "id": "e18", "user_id": "user4", "course_id": "course4", "role": "instructor",     "enrolled_date": "2026-01-01", "last_access": "1 week ago",  "progress": 100,"groups": [] }
]
```

---

## 7. `grade_items`

**Columns:** `id`, `course_id`, `activity_id`, `activity_name`, `activity_type`, `grade_max`

```json
[
  {
    "id": "g1",
    "course_id": "course1",
    "activity_id": "a4",
    "activity_name": "Quiz 1: Python Basics",
    "activity_type": "quiz",
    "grade_max": 100
  },
  {
    "id": "g2",
    "course_id": "course1",
    "activity_id": "a6",
    "activity_name": "Assignment 1: FizzBuzz",
    "activity_type": "assignment",
    "grade_max": 50
  },
  {
    "id": "g3",
    "course_id": "course1",
    "activity_id": "a8",
    "activity_name": "Quiz 2: Functions",
    "activity_type": "quiz",
    "grade_max": 100
  }
]
```

---

## 8. `student_grades`

**Columns:** `id`, `grade_item_id`, `student_id`, `student_name`, `grade`, `percentage`, `feedback`, `submitted_date`, `status`

> `status` enum: `graded | submitted | not_submitted | late`

```json
[
  { "id": "sg1",  "grade_item_id": "g1", "student_id": "p1", "student_name": "Alice Thompson", "grade": 92,  "percentage": 92,  "feedback": null,              "submitted_date": "2026-02-01", "status": "graded" },
  { "id": "sg2",  "grade_item_id": "g1", "student_id": "p2", "student_name": "Bob Martinez",   "grade": 78,  "percentage": 78,  "feedback": null,              "submitted_date": "2026-02-01", "status": "graded" },
  { "id": "sg3",  "grade_item_id": "g1", "student_id": "p3", "student_name": "Carol White",    "grade": 95,  "percentage": 95,  "feedback": null,              "submitted_date": "2026-01-31", "status": "graded" },
  { "id": "sg4",  "grade_item_id": "g1", "student_id": "p4", "student_name": "David Kim",      "grade": null,"percentage": null,"feedback": null,              "submitted_date": null,         "status": "not_submitted" },
  { "id": "sg5",  "grade_item_id": "g1", "student_id": "p5", "student_name": "Emma Wilson",    "grade": 88,  "percentage": 88,  "feedback": null,              "submitted_date": "2026-02-01", "status": "graded" },
  { "id": "sg6",  "grade_item_id": "g2", "student_id": "p1", "student_name": "Alice Thompson", "grade": 48,  "percentage": 96,  "feedback": "Excellent work!", "submitted_date": "2026-02-07", "status": "graded" },
  { "id": "sg7",  "grade_item_id": "g2", "student_id": "p2", "student_name": "Bob Martinez",   "grade": 35,  "percentage": 70,  "feedback": null,              "submitted_date": "2026-02-08", "status": "graded" },
  { "id": "sg8",  "grade_item_id": "g2", "student_id": "p3", "student_name": "Carol White",    "grade": 50,  "percentage": 100, "feedback": null,              "submitted_date": "2026-02-06", "status": "graded" },
  { "id": "sg9",  "grade_item_id": "g2", "student_id": "p4", "student_name": "David Kim",      "grade": 22,  "percentage": 44,  "feedback": null,              "submitted_date": "2026-02-10", "status": "late" },
  { "id": "sg10", "grade_item_id": "g2", "student_id": "p5", "student_name": "Emma Wilson",    "grade": 43,  "percentage": 86,  "feedback": null,              "submitted_date": "2026-02-07", "status": "graded" },
  { "id": "sg11", "grade_item_id": "g3", "student_id": "p1", "student_name": "Alice Thompson", "grade": null,"percentage": null,"feedback": null,              "submitted_date": null,         "status": "not_submitted" },
  { "id": "sg12", "grade_item_id": "g3", "student_id": "p2", "student_name": "Bob Martinez",   "grade": null,"percentage": null,"feedback": null,              "submitted_date": null,         "status": "not_submitted" },
  { "id": "sg13", "grade_item_id": "g3", "student_id": "p3", "student_name": "Carol White",    "grade": 89,  "percentage": 89,  "feedback": null,              "submitted_date": "2026-02-15", "status": "graded" },
  { "id": "sg14", "grade_item_id": "g3", "student_id": "p4", "student_name": "David Kim",      "grade": null,"percentage": null,"feedback": null,              "submitted_date": null,         "status": "not_submitted" },
  { "id": "sg15", "grade_item_id": "g3", "student_id": "p5", "student_name": "Emma Wilson",    "grade": 76,  "percentage": 76,  "feedback": null,              "submitted_date": "2026-02-15", "status": "submitted" }
]
```

---

## 9. `notifications`

**Columns:** `id`, `user_id`, `title`, `message`, `timestamp`, `read`, `type`

> `type` enum: `info | warning | success | danger`
> `user_id` is the recipient (e.g., instructor `user1`)

```json
[
  {
    "id": "n1",
    "user_id": "user1",
    "title": "Quiz Submission",
    "message": "Alice Thompson submitted Quiz 1: Python Basics",
    "timestamp": "2 minutes ago",
    "read": false,
    "type": "info"
  },
  {
    "id": "n2",
    "user_id": "user1",
    "title": "Assignment Due Soon",
    "message": "Assignment 1: FizzBuzz is due in 24 hours",
    "timestamp": "1 hour ago",
    "read": false,
    "type": "warning"
  },
  {
    "id": "n3",
    "user_id": "user1",
    "title": "New Enrollment",
    "message": "5 new students enrolled in Introduction to Python",
    "timestamp": "3 hours ago",
    "read": false,
    "type": "success"
  },
  {
    "id": "n4",
    "user_id": "user1",
    "title": "Forum Post",
    "message": "Bob Martinez posted a question in Discussion Forum",
    "timestamp": "5 hours ago",
    "read": true,
    "type": "info"
  },
  {
    "id": "n5",
    "user_id": "user1",
    "title": "Grade Updated",
    "message": "Carol White's grade has been updated for Assignment 1",
    "timestamp": "1 day ago",
    "read": true,
    "type": "success"
  },
  {
    "id": "n6",
    "user_id": "user1",
    "title": "Course Published",
    "message": "React & TypeScript Mastery is now published",
    "timestamp": "2 days ago",
    "read": true,
    "type": "success"
  },
  {
    "id": "n7",
    "user_id": "user1",
    "title": "System Maintenance",
    "message": "Scheduled maintenance on Saturday, April 18, 2026",
    "timestamp": "3 days ago",
    "read": true,
    "type": "warning"
  }
]
```

---

## 10. `conversations`

**Columns:** `id`, `owner_user_id`, `participant_user_id`, `participant_name`, `participant_role`, `last_message`, `last_message_time`, `unread_count`, `course_id`

```json
[
  {
    "id": "conv1",
    "owner_user_id": "user1",
    "participant_user_id": "p1",
    "participant_name": "Alice Thompson",
    "participant_role": "Student",
    "last_message": "Thank you for the feedback on my assignment!",
    "last_message_time": "10:32 AM",
    "unread_count": 2,
    "course_id": "course1"
  },
  {
    "id": "conv2",
    "owner_user_id": "user1",
    "participant_user_id": "p2",
    "participant_name": "Bob Martinez",
    "participant_role": "Student",
    "last_message": "When is the next quiz scheduled?",
    "last_message_time": "Yesterday",
    "unread_count": 1,
    "course_id": "course1"
  },
  {
    "id": "conv3",
    "owner_user_id": "user1",
    "participant_user_id": "user2",
    "participant_name": "Prof. Michael Chen",
    "participant_role": "Instructor",
    "last_message": "Let's collaborate on the AI course materials",
    "last_message_time": "2 days ago",
    "unread_count": 0,
    "course_id": null
  }
]
```

---

## 11. `messages`

**Columns:** `id`, `conversation_id`, `sender_id`, `sender_name`, `content`, `timestamp`, `read`

```json
[
  { "id": "m1",  "conversation_id": "conv1", "sender_id": "user1", "sender_name": "Dr. Sarah Johnson",  "content": "Hi Alice, I reviewed your assignment. Great work overall!",        "timestamp": "10:15 AM",          "read": true  },
  { "id": "m2",  "conversation_id": "conv1", "sender_id": "p1",    "sender_name": "Alice Thompson",     "content": "Thank you so much! I worked really hard on it.",                   "timestamp": "10:20 AM",          "read": true  },
  { "id": "m3",  "conversation_id": "conv1", "sender_id": "p1",    "sender_name": "Alice Thompson",     "content": "Could you elaborate on the feedback for section 3?",                "timestamp": "10:30 AM",          "read": false },
  { "id": "m4",  "conversation_id": "conv1", "sender_id": "p1",    "sender_name": "Alice Thompson",     "content": "Thank you for the feedback on my assignment!",                     "timestamp": "10:32 AM",          "read": false },
  { "id": "m5",  "conversation_id": "conv2", "sender_id": "p2",    "sender_name": "Bob Martinez",       "content": "Professor, I have a question about this week's lecture.",           "timestamp": "Yesterday 3:00 PM", "read": true  },
  { "id": "m6",  "conversation_id": "conv2", "sender_id": "user1", "sender_name": "Dr. Sarah Johnson",  "content": "Sure, what's your question?",                                      "timestamp": "Yesterday 3:05 PM", "read": true  },
  { "id": "m7",  "conversation_id": "conv2", "sender_id": "p2",    "sender_name": "Bob Martinez",       "content": "When is the next quiz scheduled?",                                 "timestamp": "Yesterday 3:10 PM", "read": false },
  { "id": "m8",  "conversation_id": "conv3", "sender_id": "user2", "sender_name": "Prof. Michael Chen", "content": "Hi Sarah, would you like to collaborate on some AI course materials?","timestamp": "2 days ago",       "read": true  },
  { "id": "m9",  "conversation_id": "conv3", "sender_id": "user1", "sender_name": "Dr. Sarah Johnson",  "content": "That sounds great! Let's meet this week.",                          "timestamp": "2 days ago",        "read": true  },
  { "id": "m10", "conversation_id": "conv3", "sender_id": "user2", "sender_name": "Prof. Michael Chen", "content": "Let's collaborate on the AI course materials",                      "timestamp": "2 days ago",        "read": true  }
]
```

---

## 12. `quiz_questions`

**Columns:** `id`, `activity_id`, `course_id`, `type`, `question_text`, `category`, `default_mark`, `shuffle_answers`, `multiple_answers`, `correct_answer`, `penalty`, `hints`

> `type` enum: `multiple_choice | true_false | matching | short_answer | numerical | essay | calculated | drag_drop`

```json
[
  {
    "id": "qq1",
    "activity_id": "a4",
    "course_id": "course1",
    "type": "multiple_choice",
    "question_text": "Which of the following is the correct way to declare a variable in Python?",
    "category": "Python Basics",
    "default_mark": 1,
    "shuffle_answers": true,
    "multiple_answers": false,
    "correct_answer": null,
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq2",
    "activity_id": "a4",
    "course_id": "course1",
    "type": "true_false",
    "question_text": "Python is a statically typed programming language.",
    "category": "Python Basics",
    "default_mark": 1,
    "shuffle_answers": false,
    "multiple_answers": false,
    "correct_answer": "False",
    "penalty": 0,
    "hints": ["Think about how you declare variables in Python."]
  },
  {
    "id": "qq3",
    "activity_id": "a4",
    "course_id": "course1",
    "type": "short_answer",
    "question_text": "What function is used to display output to the console in Python?",
    "category": "Python Basics",
    "default_mark": 1,
    "shuffle_answers": false,
    "multiple_answers": false,
    "correct_answer": "print",
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq4",
    "activity_id": "a8",
    "course_id": "course1",
    "type": "essay",
    "question_text": "Explain the concept of recursion in Python and provide an example of a recursive function.",
    "category": "Functions",
    "default_mark": 5,
    "shuffle_answers": false,
    "multiple_answers": false,
    "correct_answer": null,
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq5",
    "activity_id": "a8",
    "course_id": "course1",
    "type": "matching",
    "question_text": "Match each Python built-in function to its correct description.",
    "category": "Functions",
    "default_mark": 3,
    "shuffle_answers": true,
    "multiple_answers": false,
    "correct_answer": null,
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq6",
    "activity_id": "a8",
    "course_id": "course1",
    "type": "numerical",
    "question_text": "What is the output of len([1, 2, 3, 4, 5])?",
    "category": "Functions",
    "default_mark": 1,
    "shuffle_answers": false,
    "multiple_answers": false,
    "correct_answer": "5",
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq7",
    "activity_id": "b2",
    "course_id": "course2",
    "type": "multiple_choice",
    "question_text": "Which of the following is a supervised learning algorithm?",
    "category": "ML Foundations",
    "default_mark": 1,
    "shuffle_answers": true,
    "multiple_answers": false,
    "correct_answer": null,
    "penalty": 0,
    "hints": []
  },
  {
    "id": "qq8",
    "activity_id": "b5",
    "course_id": "course2",
    "type": "multiple_choice",
    "question_text": "What is the activation function used in the output layer for binary classification?",
    "category": "Neural Networks",
    "default_mark": 1,
    "shuffle_answers": true,
    "multiple_answers": false,
    "correct_answer": null,
    "penalty": 0,
    "hints": []
  }
]
```

---

## 13. `quiz_answers`

**Columns:** `id`, `question_id`, `text`, `grade_fraction`, `feedback`

> `grade_fraction`: `1.0` = 100% correct, `0` = wrong, negative = penalty

```json
[
  { "id": "qa1",  "question_id": "qq1", "text": "x = 10",              "grade_fraction": 1.0, "feedback": "Correct! This is the standard way to assign a variable in Python." },
  { "id": "qa2",  "question_id": "qq1", "text": "int x = 10;",         "grade_fraction": 0,   "feedback": "Incorrect. That is Java/C# syntax, not Python." },
  { "id": "qa3",  "question_id": "qq1", "text": "var x = 10",          "grade_fraction": 0,   "feedback": "Incorrect. That is JavaScript syntax." },
  { "id": "qa4",  "question_id": "qq1", "text": "declare x = 10",      "grade_fraction": 0,   "feedback": "Incorrect. Python does not use a 'declare' keyword." },
  { "id": "qa5",  "question_id": "qq5", "text": "print()",             "grade_fraction": 1.0, "feedback": null },
  { "id": "qa6",  "question_id": "qq5", "text": "len()",               "grade_fraction": 1.0, "feedback": null },
  { "id": "qa7",  "question_id": "qq5", "text": "range()",             "grade_fraction": 1.0, "feedback": null },
  { "id": "qa8",  "question_id": "qq7", "text": "Linear Regression",   "grade_fraction": 1.0, "feedback": "Correct! Linear Regression is a supervised learning algorithm." },
  { "id": "qa9",  "question_id": "qq7", "text": "K-Means Clustering",  "grade_fraction": 0,   "feedback": "Incorrect. K-Means is an unsupervised algorithm." },
  { "id": "qa10", "question_id": "qq7", "text": "DBSCAN",              "grade_fraction": 0,   "feedback": "Incorrect. DBSCAN is unsupervised." },
  { "id": "qa11", "question_id": "qq7", "text": "PCA",                 "grade_fraction": 0,   "feedback": "Incorrect. PCA is a dimensionality reduction technique." },
  { "id": "qa12", "question_id": "qq8", "text": "Sigmoid",             "grade_fraction": 1.0, "feedback": "Correct! Sigmoid outputs a value between 0 and 1, ideal for binary classification." },
  { "id": "qa13", "question_id": "qq8", "text": "ReLU",               "grade_fraction": 0,   "feedback": "Incorrect. ReLU is typically used in hidden layers." },
  { "id": "qa14", "question_id": "qq8", "text": "Softmax",             "grade_fraction": 0,   "feedback": "Incorrect. Softmax is used for multi-class classification output." },
  { "id": "qa15", "question_id": "qq8", "text": "Tanh",               "grade_fraction": 0,   "feedback": "Incorrect. Tanh is used in hidden layers or RNNs." }
]
```

---

## 14. `ai_performance_snapshots`

Weekly trend data shown on the **AI Insights → Performance Analysis → Weekly Trends** chart.

**Columns:** `id`, `course_id`, `week_label`, `avg_grade`, `completion_rate`, `engagement_score`, `recorded_at`

```json
[
  { "id": "ps1", "course_id": "course1", "week_label": "W1", "avg_grade": 72, "completion_rate": 88, "engagement_score": 91, "recorded_at": "2026-01-19" },
  { "id": "ps2", "course_id": "course1", "week_label": "W2", "avg_grade": 68, "completion_rate": 82, "engagement_score": 85, "recorded_at": "2026-01-26" },
  { "id": "ps3", "course_id": "course1", "week_label": "W3", "avg_grade": 74, "completion_rate": 78, "engagement_score": 79, "recorded_at": "2026-02-02" },
  { "id": "ps4", "course_id": "course1", "week_label": "W4", "avg_grade": 71, "completion_rate": 85, "engagement_score": 88, "recorded_at": "2026-02-09" },
  { "id": "ps5", "course_id": "course1", "week_label": "W5", "avg_grade": 79, "completion_rate": 91, "engagement_score": 93, "recorded_at": "2026-02-16" },
  { "id": "ps6", "course_id": "course1", "week_label": "W6", "avg_grade": 76, "completion_rate": 87, "engagement_score": 86, "recorded_at": "2026-02-23" },
  { "id": "ps7", "course_id": "course1", "week_label": "W7", "avg_grade": 82, "completion_rate": 93, "engagement_score": 95, "recorded_at": "2026-03-02" }
]
```

---

## 15. `ai_skill_metrics`

Radar chart data shown on **AI Insights → Skill Distribution**.

**Columns:** `id`, `course_id`, `skill_label`, `score`, `full_mark`, `recorded_at`

```json
[
  { "id": "sm1", "course_id": "course1", "skill_label": "Quiz Performance",   "score": 78, "full_mark": 100, "recorded_at": "2026-03-02" },
  { "id": "sm2", "course_id": "course1", "skill_label": "Assignment Quality", "score": 82, "full_mark": 100, "recorded_at": "2026-03-02" },
  { "id": "sm3", "course_id": "course1", "skill_label": "Forum Participation","score": 65, "full_mark": 100, "recorded_at": "2026-03-02" },
  { "id": "sm4", "course_id": "course1", "skill_label": "Completion Rate",    "score": 89, "full_mark": 100, "recorded_at": "2026-03-02" },
  { "id": "sm5", "course_id": "course1", "skill_label": "Timeliness",         "score": 72, "full_mark": 100, "recorded_at": "2026-03-02" },
  { "id": "sm6", "course_id": "course1", "skill_label": "Peer Collaboration", "score": 58, "full_mark": 100, "recorded_at": "2026-03-02" }
]
```

---

## 16. `ai_at_risk_students`

Students flagged by the AI engine on the **AI Insights → At-Risk Students** tab.

**Columns:** `id`, `course_id`, `student_id`, `student_name`, `progress`, `last_access`, `missed_activities`, `grade`, `risk_level`, `ai_recommendation`, `detected_at`

> `risk_level` enum: `high | medium | low`

```json
[
  {
    "id": "ar1",
    "course_id": "course1",
    "student_id": "p4",
    "student_name": "David Kim",
    "progress": 23,
    "last_access": "5 days ago",
    "missed_activities": 4,
    "grade": 32,
    "risk_level": "high",
    "ai_recommendation": "Schedule an immediate intervention meeting. Consider offering extended deadlines and additional support materials.",
    "detected_at": "2026-04-13"
  },
  {
    "id": "ar2",
    "course_id": "course1",
    "student_id": "p8",
    "student_name": "Henry Adams",
    "progress": 12,
    "last_access": "1 week ago",
    "missed_activities": 7,
    "grade": 18,
    "risk_level": "high",
    "ai_recommendation": "Schedule an immediate intervention meeting. Consider offering extended deadlines and additional support materials.",
    "detected_at": "2026-04-13"
  },
  {
    "id": "ar3",
    "course_id": "course1",
    "student_id": "p6",
    "student_name": "Frank Lee",
    "progress": 45,
    "last_access": "2 days ago",
    "missed_activities": 2,
    "grade": 58,
    "risk_level": "medium",
    "ai_recommendation": "Send an engagement reminder with resources. Monitor their progress over the next 3 days.",
    "detected_at": "2026-04-13"
  },
  {
    "id": "ar4",
    "course_id": "course1",
    "student_id": "p2",
    "student_name": "Bob Martinez",
    "progress": 62,
    "last_access": "1 day ago",
    "missed_activities": 1,
    "grade": 71,
    "risk_level": "low",
    "ai_recommendation": "Send an engagement reminder with resources. Monitor their progress over the next 3 days.",
    "detected_at": "2026-04-13"
  }
]
```

---

## 17. `ai_recommendations`

Pedagogical suggestions shown on **AI Insights → AI Suggestions** tab.

**Columns:** `id`, `course_id`, `title`, `description`, `impact_level`, `icon_name`, `color_scheme`, `generated_at`

> `impact_level` enum: `high | medium | urgent | low`

```json
[
  {
    "id": "rec1",
    "course_id": "course1",
    "title": "Increase Quiz Frequency",
    "description": "Students with weekly quizzes show 23% better retention. Consider adding 2 more micro-quizzes to Week 3.",
    "impact_level": "high",
    "icon_name": "Zap",
    "color_scheme": "purple",
    "generated_at": "2026-04-13"
  },
  {
    "id": "rec2",
    "course_id": "course1",
    "title": "Add Video Content to Weak Topics",
    "description": "Students struggle with OOP concepts. Adding short video explanations could improve understanding by ~30%.",
    "impact_level": "medium",
    "icon_name": "Lightbulb",
    "color_scheme": "amber",
    "generated_at": "2026-04-13"
  },
  {
    "id": "rec3",
    "course_id": "course1",
    "title": "Enable Peer Review",
    "description": "Workshop activities with peer review improve grades by 18% on average. Consider enabling it for Assignment 2.",
    "impact_level": "high",
    "icon_name": "Users",
    "color_scheme": "blue",
    "generated_at": "2026-04-13"
  },
  {
    "id": "rec4",
    "course_id": "course1",
    "title": "Send Engagement Reminders",
    "description": "4 students haven't accessed the course in 3+ days. Automated reminders can reduce dropout by 40%.",
    "impact_level": "urgent",
    "icon_name": "AlertTriangle",
    "color_scheme": "red",
    "generated_at": "2026-04-13"
  }
]
```

---

## 18. `ai_content_recommendations`

Resources listed under **AI Insights → Performance → AI Content Recommendations**.

**Columns:** `id`, `course_id`, `title`, `content_type`, `relevance_score`, `source`, `url`, `generated_at`

> `content_type` enum: `Video | Article | Interactive | Quiz Bank | Document`

```json
[
  {
    "id": "cr1",
    "course_id": "course1",
    "title": "Python Classes Deep Dive",
    "content_type": "Video",
    "relevance_score": 98,
    "source": "YouTube",
    "url": "https://youtube.com",
    "generated_at": "2026-04-13"
  },
  {
    "id": "cr2",
    "course_id": "course1",
    "title": "Object-Oriented Programming in Python",
    "content_type": "Article",
    "relevance_score": 94,
    "source": "Real Python",
    "url": "https://realpython.com",
    "generated_at": "2026-04-13"
  },
  {
    "id": "cr3",
    "course_id": "course1",
    "title": "Practice: OOP Exercises",
    "content_type": "Interactive",
    "relevance_score": 91,
    "source": "H5P",
    "url": null,
    "generated_at": "2026-04-13"
  },
  {
    "id": "cr4",
    "course_id": "course1",
    "title": "Python OOP Quiz Pack",
    "content_type": "Quiz Bank",
    "relevance_score": 89,
    "source": "Internal",
    "url": null,
    "generated_at": "2026-04-13"
  }
]
```

---

## 19. `ai_generated_questions`

Questions produced by the GPT question generator on **AI Insights → Generate Questions**.

**Columns:** `id`, `course_id`, `activity_id`, `topic`, `question_text`, `question_type`, `difficulty`, `status`, `generated_at`

> `question_type` enum: `Essay | Multiple Choice | True/False | Short Answer | Matching | Numerical`
> `difficulty` enum: `Easy | Medium | Hard`
> `status` enum: `generated | added_to_bank | dismissed`

```json
[
  {
    "id": "gq1",
    "course_id": "course1",
    "activity_id": null,
    "topic": "Object-Oriented Programming",
    "question_text": "What is the difference between a class and an object in Python?",
    "question_type": "Essay",
    "difficulty": "Medium",
    "status": "generated",
    "generated_at": "2026-04-13T10:00:00Z"
  },
  {
    "id": "gq2",
    "course_id": "course1",
    "activity_id": null,
    "topic": "Object-Oriented Programming",
    "question_text": "Which of the following is a correct way to define a class in Python?",
    "question_type": "Multiple Choice",
    "difficulty": "Easy",
    "status": "generated",
    "generated_at": "2026-04-13T10:00:00Z"
  },
  {
    "id": "gq3",
    "course_id": "course1",
    "activity_id": null,
    "topic": "Object-Oriented Programming",
    "question_text": "In Python, the __init__ method is always called automatically when a new class object is created.",
    "question_type": "True/False",
    "difficulty": "Easy",
    "status": "generated",
    "generated_at": "2026-04-13T10:00:00Z"
  },
  {
    "id": "gq4",
    "course_id": "course1",
    "activity_id": null,
    "topic": "Object-Oriented Programming",
    "question_text": "Write a Python class representing a BankAccount with deposit and withdraw methods.",
    "question_type": "Essay",
    "difficulty": "Hard",
    "status": "generated",
    "generated_at": "2026-04-13T10:00:00Z"
  }
]
```

---

## 20. `user_preferences`

Toggle settings shown on **Profile → Preferences**.

**Columns:** `id`, `user_id`, `preference_key`, `preference_label`, `description`, `enabled`

```json
[
  {
    "id": "pref1",
    "user_id": "user1",
    "preference_key": "email_notifications",
    "preference_label": "Email notifications",
    "description": "Receive emails when students submit assignments",
    "enabled": true
  },
  {
    "id": "pref2",
    "user_id": "user1",
    "preference_key": "forum_subscriptions",
    "preference_label": "Forum subscriptions",
    "description": "Get notified of new forum posts in my courses",
    "enabled": true
  },
  {
    "id": "pref3",
    "user_id": "user1",
    "preference_key": "grading_reminders",
    "preference_label": "Grading reminders",
    "description": "Remind me of ungraded submissions after 48 hours",
    "enabled": true
  },
  {
    "id": "pref4",
    "user_id": "user1",
    "preference_key": "ai_suggestions",
    "preference_label": "AI suggestions",
    "description": "Show AI-generated insights and recommendations",
    "enabled": true
  }
]
```

---

## 21. `activity_performance` (chart data)

Horizontal bar chart data on **AI Insights → Performance → Activity Performance**.

**Columns:** `id`, `course_id`, `activity_name`, `avg_score_percentage`, `grade_max`

```json
[
  { "id": "ap1", "course_id": "course1", "activity_name": "Quiz 1",       "avg_score_percentage": 85, "grade_max": 100 },
  { "id": "ap2", "course_id": "course1", "activity_name": "Assignment 1", "avg_score_percentage": 78, "grade_max": 50  },
  { "id": "ap3", "course_id": "course1", "activity_name": "Quiz 2",       "avg_score_percentage": 71, "grade_max": 100 },
  { "id": "ap4", "course_id": "course1", "activity_name": "Forum",        "avg_score_percentage": 90, "grade_max": 10  }
]
```

---

## 22. `dashboard_engagement`

Weekly engagement area chart on the **Dashboard → Weekly Engagement** widget.

**Columns:** `id`, `course_id`, `day_label`, `active_students`, `submissions`, `week_of`

```json
[
  { "id": "de1", "course_id": "course1", "day_label": "Mon", "active_students": 120, "submissions": 45, "week_of": "2026-04-07" },
  { "id": "de2", "course_id": "course1", "day_label": "Tue", "active_students": 138, "submissions": 60, "week_of": "2026-04-07" },
  { "id": "de3", "course_id": "course1", "day_label": "Wed", "active_students": 105, "submissions": 38, "week_of": "2026-04-07" },
  { "id": "de4", "course_id": "course1", "day_label": "Thu", "active_students": 156, "submissions": 72, "week_of": "2026-04-07" },
  { "id": "de5", "course_id": "course1", "day_label": "Fri", "active_students": 144, "submissions": 55, "week_of": "2026-04-07" },
  { "id": "de6", "course_id": "course1", "day_label": "Sat", "active_students": 89,  "submissions": 30, "week_of": "2026-04-07" },
  { "id": "de7", "course_id": "course1", "day_label": "Sun", "active_students": 67,  "submissions": 22, "week_of": "2026-04-07" }
]
```

---

## Entity Relationship Summary

```
users ──────────────────────────────────────────────────────────────────┐
  │                                                                      │
  ├─< enrollments >────── courses ──────< sections ──────< activities   │
  │                           │                                          │
  │                           ├──< grade_items ──< student_grades       │
  │                           │                       └── users         │
  │                           ├──< ai_performance_snapshots             │
  │                           ├──< ai_skill_metrics                     │
  │                           ├──< ai_at_risk_students >── users        │
  │                           ├──< ai_recommendations                   │
  │                           ├──< ai_content_recommendations           │
  │                           ├──< ai_generated_questions               │
  │                           ├──< activity_performance                 │
  │                           └──< dashboard_engagement                 │
  │                                                                      │
  ├──< notifications                                                     │
  ├──< user_preferences                                                  │
  ├──< conversations >── users                                          │
  └──< messages >────── conversations                                   │
                                                                         │
categories ──────────────────────────────────> courses ─────────────────┘
  └─< categories (self-referential parent_id)
```

---

## Supabase Table Creation Notes

| Table | Primary Key | Foreign Keys | Notable Types |
|---|---|---|---|
| `users` | `id (text)` | — | `role: text check` |
| `categories` | `id (text)` | `parent_id → categories.id` | — |
| `courses` | `id (text)` | `category_id`, `instructor_id → users.id` | `tags: text[]`, `status/visibility/format: text check` |
| `sections` | `id (text)` | `course_id → courses.id` | — |
| `activities` | `id (text)` | `section_id`, `course_id` | `type/completion_status: text check`, `settings: jsonb` |
| `enrollments` | `id (text)` | `user_id → users.id`, `course_id → courses.id` | `role: text check`, `groups: text[]` |
| `grade_items` | `id (text)` | `course_id`, `activity_id → activities.id` | — |
| `student_grades` | `id (text)` | `grade_item_id → grade_items.id`, `student_id → users.id` | `status: text check` |
| `notifications` | `id (text)` | `user_id → users.id` | `type: text check` |
| `conversations` | `id (text)` | `owner_user_id`, `participant_user_id → users.id` | — |
| `messages` | `id (text)` | `conversation_id → conversations.id`, `sender_id → users.id` | — |
| `quiz_questions` | `id (text)` | `activity_id → activities.id`, `course_id` | `type: text check`, `hints: text[]` |
| `quiz_answers` | `id (text)` | `question_id → quiz_questions.id` | `grade_fraction: numeric` |
| `ai_performance_snapshots` | `id (text)` | `course_id → courses.id` | — |
| `ai_skill_metrics` | `id (text)` | `course_id → courses.id` | — |
| `ai_at_risk_students` | `id (text)` | `course_id`, `student_id → users.id` | `risk_level: text check` |
| `ai_recommendations` | `id (text)` | `course_id → courses.id` | `impact_level: text check` |
| `ai_content_recommendations` | `id (text)` | `course_id → courses.id` | `content_type: text check` |
| `ai_generated_questions` | `id (text)` | `course_id → courses.id` | `status/difficulty/question_type: text check` |
| `user_preferences` | `id (text)` | `user_id → users.id` | `enabled: boolean` |
| `activity_performance` | `id (text)` | `course_id → courses.id` | — |
| `dashboard_engagement` | `id (text)` | `course_id → courses.id` | — |
| `learner_profiles` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `primary_profile check (H/A/T/C)`, `declared_preferences: text[]`, `lms_flags: jsonb` |
| `behavioral_signals` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `submission_timing check`, `navigation_pattern check`, `normalised_values/colour_flags/raw_data: jsonb` |
| `cognitive_signals` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `revisit_flag: bool`, `normalised_values/colour_flags/raw_data: jsonb` |
| `emotional_signals` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `mood_drift_flag: bool`, `badge_earned: bool`, `colour_flags/raw_data: jsonb` |
| `risk_scores` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `tier: int2 check (0-3)`, `anomaly_flag: bool`, `signal_breakdown: jsonb` |
| `interventions` | `id (text)` | `learner_id → users.id`, `course_id → courses.id`, `facilitator_id → users.id` | `channel check`, `outcome check` |
| `feedback_evaluations` | `id (text)` | `intervention_id → interventions.id`, `learner_id → users.id` | `outcome_label check`, `re_threshold_adjustment: jsonb` |
| `profile_drift_logs` | `id (text)` | `learner_id → users.id`, `course_id → courses.id` | `drift_severity check`, `resolution check` |

---

## 23–30. Learner Analytics Pipeline Tables (L0 → FL)

> See the full JSON seed data, column definitions, and ER diagram embedded below each table header. The pipeline follows: **L0 (Declared Profile) → L1 (Behavioural) → L2 (Cognitive) → L3 (Emotional) → RE (Risk Engine) → IE (Interventions) → FL (Feedback Loop)**.

### Profile Types (L0)
- **H** = Heutagogical / Self-Directed: open navigation, reflection journals, nonlinear autonomy
- **A** = Activist / Social Learner: peer review, cohort grouping, forum-heavy
- **T** = Theorist / Task-Oriented: structured pathway, measurable outcomes, deadline-aware
- **C** = Conformist / Structured: structured pathway, deadline reminders, instructor-led

### Risk Tiers (RE)
| Tier | Label | Score Range | Action |
|------|-------|-------------|--------|
| 0 | GREEN | 0–25 | Monitor passively |
| 1 | AMBER | 25–50 | Auto-nudge via IE template |
| 2 | ORANGE | 50–75 | Facilitator outreach within 48hrs |
| 3 | RED | 75–100 | Immediate pastoral escalation |

### Signal Colour Flags
| Colour | Meaning |
|--------|---------|
| green | Within healthy baseline range |
| amber | Below threshold — monitor |
| orange | Significant drop — action recommended |
| red | Critical — contributes heavily to RE score |

### Learner Profiles Seed (L0 — `learner_profiles`)

```json
[
  { "id": "lp1", "learner_id": "p1", "course_id": "course1", "primary_profile": "H", "secondary_profile": "T", "is_mixed_profile": false, "h_score": 14, "a_score": 5, "t_score": 11, "c_score": 7, "declared_preferences": ["self-directed resolution","reflective processing","intrinsic curiosity","nonlinear autonomy"], "lms_flags": {"open_navigation":true,"structured_pathway":false,"reflection_journals":true,"peer_review":false,"cohort_grouping":false,"self_assessment":true,"deadline_reminders":false}, "pulse_consent": true, "pulse_consent_at": "2026-01-15T09:00:00Z", "drift_flag": false, "drift_weeks_count": 0, "drift_flagged_at": null, "created_at": "2026-01-15T09:02:00Z", "updated_at": "2026-01-15T09:02:00Z" },
  { "id": "lp2", "learner_id": "p2", "course_id": "course1", "primary_profile": "H", "secondary_profile": "C", "is_mixed_profile": false, "h_score": 13, "a_score": 7, "t_score": 9, "c_score": 10, "declared_preferences": ["self-determination","nonlinear autonomy","self-evaluation","feedback-on-demand"], "lms_flags": {"open_navigation":true,"structured_pathway":false,"reflection_journals":false,"peer_review":false,"cohort_grouping":false,"self_assessment":true,"deadline_reminders":false}, "pulse_consent": true, "pulse_consent_at": "2026-01-15T09:05:00Z", "drift_flag": false, "drift_weeks_count": 0, "drift_flagged_at": null, "created_at": "2026-01-15T09:06:00Z", "updated_at": "2026-01-15T09:06:00Z" },
  { "id": "lp3", "learner_id": "p3", "course_id": "course1", "primary_profile": "T", "secondary_profile": "H", "is_mixed_profile": false, "h_score": 10, "a_score": 6, "t_score": 15, "c_score": 8, "declared_preferences": ["structured tasks","clear objectives","analytical reasoning","measurable outcomes"], "lms_flags": {"open_navigation":false,"structured_pathway":true,"reflection_journals":false,"peer_review":false,"cohort_grouping":false,"self_assessment":true,"deadline_reminders":true}, "pulse_consent": true, "pulse_consent_at": "2026-01-16T08:30:00Z", "drift_flag": false, "drift_weeks_count": 0, "drift_flagged_at": null, "created_at": "2026-01-16T08:31:00Z", "updated_at": "2026-01-16T08:31:00Z" },
  { "id": "lp4", "learner_id": "p4", "course_id": "course1", "primary_profile": "C", "secondary_profile": "T", "is_mixed_profile": false, "h_score": 7, "a_score": 8, "t_score": 11, "c_score": 14, "declared_preferences": ["structured pathway","cohort grouping","deadline reminders","instructor-led guidance"], "lms_flags": {"open_navigation":false,"structured_pathway":true,"reflection_journals":false,"peer_review":true,"cohort_grouping":true,"self_assessment":false,"deadline_reminders":true}, "pulse_consent": true, "pulse_consent_at": "2026-01-18T10:00:00Z", "drift_flag": true, "drift_weeks_count": 2, "drift_flagged_at": "2026-02-09T01:07:00Z", "created_at": "2026-01-18T10:01:00Z", "updated_at": "2026-02-09T01:07:00Z" },
  { "id": "lp5", "learner_id": "p5", "course_id": "course1", "primary_profile": "H", "secondary_profile": "A", "is_mixed_profile": false, "h_score": 15, "a_score": 12, "t_score": 8, "c_score": 6, "declared_preferences": ["self-directed resolution","intrinsic curiosity","peer collaboration","reflective processing"], "lms_flags": {"open_navigation":true,"structured_pathway":false,"reflection_journals":true,"peer_review":true,"cohort_grouping":false,"self_assessment":true,"deadline_reminders":false}, "pulse_consent": true, "pulse_consent_at": "2026-01-20T09:00:00Z", "drift_flag": false, "drift_weeks_count": 0, "drift_flagged_at": null, "created_at": "2026-01-20T09:01:00Z", "updated_at": "2026-01-20T09:01:00Z" },
  { "id": "lp6", "learner_id": "p6", "course_id": "course1", "primary_profile": "T", "secondary_profile": "C", "is_mixed_profile": false, "h_score": 8, "a_score": 7, "t_score": 14, "c_score": 11, "declared_preferences": ["structured tasks","measurable outcomes","step-by-step guidance","deadline awareness"], "lms_flags": {"open_navigation":false,"structured_pathway":true,"reflection_journals":false,"peer_review":false,"cohort_grouping":true,"self_assessment":false,"deadline_reminders":true}, "pulse_consent": true, "pulse_consent_at": "2026-01-20T10:00:00Z", "drift_flag": true, "drift_weeks_count": 1, "drift_flagged_at": "2026-02-16T01:06:00Z", "created_at": "2026-01-20T10:01:00Z", "updated_at": "2026-02-16T01:06:00Z" },
  { "id": "lp7", "learner_id": "p7", "course_id": "course1", "primary_profile": "A", "secondary_profile": "H", "is_mixed_profile": false, "h_score": 11, "a_score": 15, "t_score": 9, "c_score": 7, "declared_preferences": ["peer collaboration","group discussion","social learning","community building"], "lms_flags": {"open_navigation":false,"structured_pathway":false,"reflection_journals":false,"peer_review":true,"cohort_grouping":true,"self_assessment":false,"deadline_reminders":false}, "pulse_consent": true, "pulse_consent_at": "2026-01-14T08:00:00Z", "drift_flag": false, "drift_weeks_count": 0, "drift_flagged_at": null, "created_at": "2026-01-14T08:01:00Z", "updated_at": "2026-01-14T08:01:00Z" },
  { "id": "lp8", "learner_id": "p8", "course_id": "course1", "primary_profile": "A", "secondary_profile": "T", "is_mixed_profile": false, "h_score": 8, "a_score": 14, "t_score": 10, "c_score": 9, "declared_preferences": ["peer collaboration","group discussion","forum engagement","social accountability"], "lms_flags": {"open_navigation":false,"structured_pathway":false,"reflection_journals":false,"peer_review":true,"cohort_grouping":true,"self_assessment":false,"deadline_reminders":true}, "pulse_consent": true, "pulse_consent_at": "2026-01-22T09:00:00Z", "drift_flag": true, "drift_weeks_count": 3, "drift_flagged_at": "2026-02-09T01:07:00Z", "created_at": "2026-01-22T09:01:00Z", "updated_at": "2026-03-02T01:07:00Z" }
]
```

### Behavioral Signals Seed (L1 — `behavioral_signals`, key rows)

```json
[
  { "id": "bs_p4_w3", "learner_id": "p4", "course_id": "course1", "week_number": 3, "login_frequency": 5, "time_on_task_hours": 5.8, "content_completion_rate": 0.88, "quiz_attempt_count": 5, "quiz_available_count": 5, "submission_timing": "on_time", "forum_post_count": 2, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.00,"time_on_task_hours":0.00,"content_completion_rate":0.00,"quiz_attempt_rate":0.00,"submission_timing":0.00,"forum_post_rate":0.00}, "colour_flags": {"login_frequency":"green","time_on_task_hours":"green","content_completion_rate":"green","quiz_attempt_rate":"green","submission_timing":"green","forum_post_rate":"green"}, "raw_data": {"resources_accessed":12,"optional_resources_accessed":3,"optional_resources_available":6}, "computed_at": "2026-02-08T01:05:00Z" },
  { "id": "bs_p4_w5", "learner_id": "p4", "course_id": "course1", "week_number": 5, "login_frequency": 2, "time_on_task_hours": 1.80, "content_completion_rate": 0.55, "quiz_attempt_count": 2, "quiz_available_count": 5, "submission_timing": "late_3_5", "forum_post_count": 0, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.667,"time_on_task_hours":0.500,"content_completion_rate":0.556,"quiz_attempt_rate":0.800,"submission_timing":0.700,"forum_post_rate":1.000}, "colour_flags": {"login_frequency":"amber","time_on_task_hours":"orange","content_completion_rate":"amber","quiz_attempt_rate":"amber","submission_timing":"orange","forum_post_rate":"red"}, "raw_data": {"resources_accessed":5,"optional_resources_accessed":0,"optional_resources_available":6}, "computed_at": "2026-02-22T01:05:00Z" },
  { "id": "bs_p4_w7", "learner_id": "p4", "course_id": "course1", "week_number": 7, "login_frequency": 2, "time_on_task_hours": 1.80, "content_completion_rate": 0.55, "quiz_attempt_count": 2, "quiz_available_count": 5, "submission_timing": "late_3_5", "forum_post_count": 0, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.667,"time_on_task_hours":0.500,"content_completion_rate":0.556,"quiz_attempt_rate":0.800,"submission_timing":0.700,"forum_post_rate":1.000}, "colour_flags": {"login_frequency":"amber","time_on_task_hours":"orange","content_completion_rate":"amber","quiz_attempt_rate":"amber","submission_timing":"orange","forum_post_rate":"red"}, "raw_data": {"resources_accessed":4,"optional_resources_accessed":0,"optional_resources_available":6}, "computed_at": "2026-03-08T01:05:00Z" },
  { "id": "bs_p8_w3", "learner_id": "p8", "course_id": "course1", "week_number": 3, "login_frequency": 4, "time_on_task_hours": 4.5, "content_completion_rate": 0.72, "quiz_attempt_count": 4, "quiz_available_count": 5, "submission_timing": "on_time", "forum_post_count": 2, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.00,"time_on_task_hours":0.00,"content_completion_rate":0.00,"quiz_attempt_rate":0.00,"submission_timing":0.00,"forum_post_rate":0.00}, "colour_flags": {"login_frequency":"green","time_on_task_hours":"green","content_completion_rate":"green","quiz_attempt_rate":"green","submission_timing":"green","forum_post_rate":"green"}, "raw_data": {"resources_accessed":10,"optional_resources_accessed":2,"optional_resources_available":5}, "computed_at": "2026-02-08T01:05:00Z" },
  { "id": "bs_p8_w7", "learner_id": "p8", "course_id": "course1", "week_number": 7, "login_frequency": 1, "time_on_task_hours": 0.80, "content_completion_rate": 0.38, "quiz_attempt_count": 1, "quiz_available_count": 5, "submission_timing": "late_3_5", "forum_post_count": 0, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.833,"time_on_task_hours":0.833,"content_completion_rate":0.733,"quiz_attempt_rate":0.933,"submission_timing":0.700,"forum_post_rate":1.000}, "colour_flags": {"login_frequency":"red","time_on_task_hours":"red","content_completion_rate":"red","quiz_attempt_rate":"red","submission_timing":"orange","forum_post_rate":"red"}, "raw_data": {"resources_accessed":2,"optional_resources_accessed":0,"optional_resources_available":5}, "computed_at": "2026-03-08T01:05:00Z" },
  { "id": "bs_p6_w5", "learner_id": "p6", "course_id": "course1", "week_number": 5, "login_frequency": 3, "time_on_task_hours": 3.20, "content_completion_rate": 0.71, "quiz_attempt_count": 3, "quiz_available_count": 5, "submission_timing": "late_1_2", "forum_post_count": 0, "forum_posts_required": 2, "navigation_pattern": "linear", "normalised_values": {"login_frequency":0.333,"time_on_task_hours":0.00,"content_completion_rate":0.00,"quiz_attempt_rate":0.571,"submission_timing":0.400,"forum_post_rate":1.000}, "colour_flags": {"login_frequency":"amber","time_on_task_hours":"green","content_completion_rate":"green","quiz_attempt_rate":"amber","submission_timing":"amber","forum_post_rate":"amber"}, "raw_data": {"resources_accessed":7,"optional_resources_accessed":1,"optional_resources_available":6}, "computed_at": "2026-02-22T01:05:00Z" },
  { "id": "bs_p2_w5", "learner_id": "p2", "course_id": "course1", "week_number": 5, "login_frequency": 4, "time_on_task_hours": 4.50, "content_completion_rate": 0.78, "quiz_attempt_count": 4, "quiz_available_count": 5, "submission_timing": "on_time", "forum_post_count": 1, "forum_posts_required": 2, "navigation_pattern": "nonlinear", "normalised_values": {"login_frequency":0.00,"time_on_task_hours":0.00,"content_completion_rate":0.00,"quiz_attempt_rate":0.00,"submission_timing":0.00,"forum_post_rate":0.50}, "colour_flags": {"login_frequency":"green","time_on_task_hours":"green","content_completion_rate":"green","quiz_attempt_rate":"green","submission_timing":"green","forum_post_rate":"amber"}, "raw_data": {"resources_accessed":9,"optional_resources_accessed":3,"optional_resources_available":7}, "computed_at": "2026-02-22T01:05:00Z" }
]
```

### Cognitive Signals Seed (L2 — `cognitive_signals`, key rows)

```json
[
  { "id": "cs_p4_w3", "learner_id": "p4", "course_id": "course1", "week_number": 3, "content_revisit_rate": 1.10, "revisit_flag": false, "quiz_first_attempt_score": 75.0, "quiz_final_attempt_score": 82.0, "quiz_learning_delta": 0.4600, "discussion_depth_score": 71.0, "avg_post_word_count": 185, "question_count": 1, "assertion_count": 5, "peer_response_rate": 0.6000, "optional_resource_access_rate": 0.5000, "feedback_uptake_lag_hours": 12, "colour_flags": {"content_revisit_rate":"green","quiz_learning_delta":"green","discussion_depth_score":"green","optional_resource_access_rate":"green","peer_response_rate":"green","feedback_uptake_lag_hours":"green"}, "computed_at": "2026-02-08T01:05:45Z" },
  { "id": "cs_p4_w5", "learner_id": "p4", "course_id": "course1", "week_number": 5, "content_revisit_rate": 2.90, "revisit_flag": false, "quiz_first_attempt_score": 50.0, "quiz_final_attempt_score": 60.0, "quiz_learning_delta": 0.2000, "discussion_depth_score": null, "avg_post_word_count": 0, "question_count": 0, "assertion_count": 0, "peer_response_rate": 0.0000, "optional_resource_access_rate": 0.0000, "feedback_uptake_lag_hours": 96, "colour_flags": {"content_revisit_rate":"amber","quiz_learning_delta":"amber","optional_resource_access_rate":"red","feedback_uptake_lag_hours":"orange"}, "computed_at": "2026-02-22T01:05:45Z" },
  { "id": "cs_p4_w7", "learner_id": "p4", "course_id": "course1", "week_number": 7, "content_revisit_rate": 3.60, "revisit_flag": true, "quiz_first_attempt_score": 44.0, "quiz_final_attempt_score": 57.0, "quiz_learning_delta": 0.2321, "discussion_depth_score": null, "avg_post_word_count": 0, "question_count": 0, "assertion_count": 0, "peer_response_rate": 0.0000, "optional_resource_access_rate": 0.0000, "feedback_uptake_lag_hours": 144, "colour_flags": {"content_revisit_rate":"red","quiz_learning_delta":"amber","optional_resource_access_rate":"red","feedback_uptake_lag_hours":"orange"}, "computed_at": "2026-03-08T01:05:45Z" },
  { "id": "cs_p8_w3", "learner_id": "p8", "course_id": "course1", "week_number": 3, "content_revisit_rate": 1.05, "revisit_flag": false, "quiz_first_attempt_score": 68.0, "quiz_final_attempt_score": 81.0, "quiz_learning_delta": 0.4063, "discussion_depth_score": 74.0, "avg_post_word_count": 198, "question_count": 2, "assertion_count": 6, "peer_response_rate": 0.8000, "optional_resource_access_rate": 0.4000, "feedback_uptake_lag_hours": 10, "colour_flags": {"content_revisit_rate":"green","quiz_learning_delta":"green","discussion_depth_score":"green","optional_resource_access_rate":"green","peer_response_rate":"green","feedback_uptake_lag_hours":"green"}, "computed_at": "2026-02-08T01:05:45Z" },
  { "id": "cs_p8_w7", "learner_id": "p8", "course_id": "course1", "week_number": 7, "content_revisit_rate": 4.20, "revisit_flag": true, "quiz_first_attempt_score": 38.0, "quiz_final_attempt_score": 47.0, "quiz_learning_delta": 0.1452, "discussion_depth_score": null, "avg_post_word_count": 0, "question_count": 0, "assertion_count": 0, "peer_response_rate": 0.0000, "optional_resource_access_rate": 0.0000, "feedback_uptake_lag_hours": 168, "colour_flags": {"content_revisit_rate":"red","quiz_learning_delta":"red","optional_resource_access_rate":"red","peer_response_rate":"red","feedback_uptake_lag_hours":"red"}, "computed_at": "2026-03-08T01:05:45Z" },
  { "id": "cs_p6_w5", "learner_id": "p6", "course_id": "course1", "week_number": 5, "content_revisit_rate": 2.80, "revisit_flag": false, "quiz_first_attempt_score": 48.0, "quiz_final_attempt_score": 61.0, "quiz_learning_delta": 0.2500, "discussion_depth_score": null, "avg_post_word_count": 0, "question_count": 0, "assertion_count": 0, "peer_response_rate": 0.0000, "optional_resource_access_rate": 0.1667, "feedback_uptake_lag_hours": 72, "colour_flags": {"content_revisit_rate":"amber","quiz_learning_delta":"amber","optional_resource_access_rate":"orange","feedback_uptake_lag_hours":"amber"}, "computed_at": "2026-02-22T01:05:45Z" }
]
```

### Emotional Signals Seed (L3 — `emotional_signals`, key rows)

```json
[
  { "id": "es_p4_w3", "learner_id": "p4", "course_id": "course1", "week_number": 3, "pulse_confidence": 4, "pulse_energy": 4, "pulse_composite": 4.00, "pulse_submitted": true, "pulse_submitted_at": "2026-02-02T08:50:00Z", "mood_drift_score": null, "mood_drift_flag": false, "help_seeking_rate": 0.30, "messages_to_facilitator": 1, "forum_questions_asked": 1, "feedback_response_lag_hours": 12, "voluntary_engagement_rate": 0.5000, "voluntary_engagement_delta": null, "badge_earned_this_week": false, "badge_response_delta": null, "colour_flags": {"pulse_composite":"green","help_seeking_rate":"green","voluntary_engagement_rate":"green"}, "computed_at": "2026-02-08T01:06:00Z" },
  { "id": "es_p4_w7", "learner_id": "p4", "course_id": "course1", "week_number": 7, "pulse_confidence": 1, "pulse_energy": 2, "pulse_composite": 1.50, "pulse_submitted": true, "pulse_submitted_at": "2026-03-02T09:05:00Z", "mood_drift_score": 2.65, "mood_drift_flag": true, "help_seeking_rate": 0.05, "messages_to_facilitator": 0, "forum_questions_asked": 0, "feedback_response_lag_hours": 144, "voluntary_engagement_rate": 0.0000, "voluntary_engagement_delta": -0.1667, "badge_earned_this_week": false, "badge_response_delta": null, "colour_flags": {"pulse_composite":"red","mood_drift_score":"red","help_seeking_rate":"orange","voluntary_engagement_rate":"red"}, "computed_at": "2026-03-08T01:06:00Z" },
  { "id": "es_p8_w3", "learner_id": "p8", "course_id": "course1", "week_number": 3, "pulse_confidence": 4, "pulse_energy": 3, "pulse_composite": 3.50, "pulse_submitted": true, "pulse_submitted_at": "2026-02-02T08:45:00Z", "mood_drift_score": null, "mood_drift_flag": false, "help_seeking_rate": 0.40, "messages_to_facilitator": 1, "forum_questions_asked": 2, "feedback_response_lag_hours": 10, "voluntary_engagement_rate": 0.4000, "voluntary_engagement_delta": null, "badge_earned_this_week": true, "badge_response_delta": 0.1500, "colour_flags": {"pulse_composite":"green","help_seeking_rate":"green","voluntary_engagement_rate":"green"}, "computed_at": "2026-02-08T01:06:00Z" },
  { "id": "es_p8_w7", "learner_id": "p8", "course_id": "course1", "week_number": 7, "pulse_confidence": 1, "pulse_energy": 1, "pulse_composite": 1.00, "pulse_submitted": true, "pulse_submitted_at": "2026-03-02T09:10:00Z", "mood_drift_score": 1.85, "mood_drift_flag": true, "help_seeking_rate": 0.03, "messages_to_facilitator": 0, "forum_questions_asked": 0, "feedback_response_lag_hours": 168, "voluntary_engagement_rate": 0.0000, "voluntary_engagement_delta": -0.1667, "badge_earned_this_week": false, "badge_response_delta": null, "colour_flags": {"pulse_composite":"red","mood_drift_score":"red","help_seeking_rate":"red","voluntary_engagement_rate":"red"}, "computed_at": "2026-03-08T01:06:00Z" },
  { "id": "es_p6_w5", "learner_id": "p6", "course_id": "course1", "week_number": 5, "pulse_confidence": 2, "pulse_energy": 3, "pulse_composite": 2.50, "pulse_submitted": true, "pulse_submitted_at": "2026-02-16T09:12:00Z", "mood_drift_score": 3.20, "mood_drift_flag": true, "help_seeking_rate": 0.12, "messages_to_facilitator": 0, "forum_questions_asked": 0, "feedback_response_lag_hours": 72, "voluntary_engagement_rate": 0.1667, "voluntary_engagement_delta": -0.4583, "badge_earned_this_week": false, "badge_response_delta": null, "colour_flags": {"pulse_composite":"orange","mood_drift_score":"amber","help_seeking_rate":"amber","voluntary_engagement_delta":"orange"}, "computed_at": "2026-02-22T01:06:00Z" },
  { "id": "es_p2_w5", "learner_id": "p2", "course_id": "course1", "week_number": 5, "pulse_confidence": 3, "pulse_energy": 4, "pulse_composite": 3.50, "pulse_submitted": true, "pulse_submitted_at": "2026-02-16T08:58:00Z", "mood_drift_score": 3.60, "mood_drift_flag": false, "help_seeking_rate": 0.28, "messages_to_facilitator": 0, "forum_questions_asked": 1, "feedback_response_lag_hours": 24, "voluntary_engagement_rate": 0.4286, "voluntary_engagement_delta": -0.1200, "badge_earned_this_week": false, "badge_response_delta": null, "colour_flags": {"pulse_composite":"green","mood_drift_score":"green","help_seeking_rate":"green","voluntary_engagement_delta":"green"}, "computed_at": "2026-02-22T01:06:00Z" }
]
```

### Risk Scores Seed (RE — `risk_scores`, key rows)

```json
[
  { "id": "rs_p4_w3", "learner_id": "p4", "course_id": "course1", "week_number": 3, "profile_type": "C", "l1_contribution": 0.0, "l2_contribution": 0.0, "l3_contribution": 0.0, "final_score": 5.2, "previous_week_score": null, "score_delta": null, "tier": 0, "anomaly_flag": false, "signal_breakdown": {}, "facilitator_notes_prompt": "David (C-profile) well engaged in W3. Baseline established.", "computed_at": "2026-02-08T01:06:30Z" },
  { "id": "rs_p4_w5", "learner_id": "p4", "course_id": "course1", "week_number": 5, "profile_type": "C", "l1_contribution": 8.2, "l2_contribution": 27.5, "l3_contribution": 26.9, "final_score": 42.6, "previous_week_score": 5.2, "score_delta": 37.4, "tier": 1, "anomaly_flag": true, "signal_breakdown": {"optional_resource_access_rate":{"raw":0.000,"normalised":1.000,"weight":0.20,"contribution":20.00,"colour":"red"},"quiz_learning_delta":{"raw":0.200,"normalised":0.538,"weight":0.15,"contribution":8.08,"colour":"amber"},"mood_drift_score":{"raw":3.20,"normalised":0.360,"weight":0.20,"contribution":7.20,"colour":"amber"},"voluntary_engagement_delta":{"raw":-0.500,"normalised":1.000,"weight":0.15,"contribution":15.00,"colour":"red"}}, "facilitator_notes_prompt": "ANOMALY: David +37.4pts. T1 auto-nudge queued.", "computed_at": "2026-02-22T01:06:30Z" },
  { "id": "rs_p4_w7", "learner_id": "p4", "course_id": "course1", "week_number": 7, "profile_type": "C", "l1_contribution": 9.8, "l2_contribution": 28.9, "l3_contribution": 25.4, "final_score": 64.1, "previous_week_score": 42.6, "score_delta": 21.5, "tier": 2, "anomaly_flag": true, "signal_breakdown": {"optional_resource_access_rate":{"raw":0.000,"normalised":1.000,"weight":0.20,"contribution":20.00,"colour":"red"},"mood_drift_score":{"raw":2.65,"normalised":0.633,"weight":0.20,"contribution":12.67,"colour":"red"},"login_frequency":{"raw":2,"normalised":0.667,"weight":0.10,"contribution":6.67,"colour":"amber"},"feedback_uptake_lag_hours":{"raw":144,"normalised":0.667,"weight":0.10,"contribution":6.67,"colour":"orange"}}, "facilitator_notes_prompt": "TIER 2 — Outreach within 48hrs. David (C-profile) mood drift 29%, optional 0%.", "computed_at": "2026-03-08T01:06:30Z" },
  { "id": "rs_p8_w7", "learner_id": "p8", "course_id": "course1", "week_number": 7, "profile_type": "A", "l1_contribution": 14.2, "l2_contribution": 32.1, "l3_contribution": 32.2, "final_score": 78.5, "previous_week_score": 64.1, "score_delta": 14.4, "tier": 3, "anomaly_flag": true, "signal_breakdown": {"forum_post_rate":{"raw":0.000,"normalised":1.000,"weight":0.20,"contribution":20.00,"colour":"red","note":"A-profile highest weight"},"peer_response_rate":{"raw":0.000,"normalised":1.000,"weight":0.15,"contribution":15.00,"colour":"red"},"mood_drift_score":{"raw":1.85,"normalised":0.630,"weight":0.20,"contribution":12.60,"colour":"red"}}, "facilitator_notes_prompt": "TIER 3 CRITICAL — Henry (A-profile). Immediate pastoral escalation.", "computed_at": "2026-03-08T01:06:30Z" },
  { "id": "rs_p6_w5", "learner_id": "p6", "course_id": "course1", "week_number": 5, "profile_type": "T", "l1_contribution": 4.9, "l2_contribution": 19.2, "l3_contribution": 18.5, "final_score": 42.6, "previous_week_score": 18.2, "score_delta": 24.4, "tier": 1, "anomaly_flag": true, "signal_breakdown": {"optional_resource_access_rate":{"raw":0.167,"normalised":0.820,"weight":0.20,"contribution":16.39,"colour":"orange"},"voluntary_engagement_delta":{"raw":-0.458,"normalised":0.917,"weight":0.15,"contribution":13.75,"colour":"orange"},"quiz_learning_delta":{"raw":0.250,"normalised":0.462,"weight":0.15,"contribution":6.92,"colour":"amber"}}, "facilitator_notes_prompt": "ANOMALY: Frank (T-profile) +24.4pts. T1 auto-nudge queued.", "computed_at": "2026-02-22T01:06:30Z" },
  { "id": "rs_p2_w7", "learner_id": "p2", "course_id": "course1", "week_number": 7, "profile_type": "H", "l1_contribution": 3.3, "l2_contribution": 7.8, "l3_contribution": 7.1, "final_score": 18.2, "previous_week_score": 12.4, "score_delta": 5.8, "tier": 0, "anomaly_flag": false, "signal_breakdown": {"quiz_learning_delta":{"raw":0.371,"normalised":0.077,"weight":0.15,"contribution":1.15,"colour":"amber"},"optional_resource_access_rate":{"raw":0.4286,"normalised":0.2296,"weight":0.20,"contribution":4.59,"colour":"amber"}}, "facilitator_notes_prompt": "Bob (H-profile) monitoring. No intervention required. Review W8.", "computed_at": "2026-03-08T01:06:30Z" }
]
```

### Interventions Seed (IE — `interventions`)

```json
[
  { "id": "iv_p6_w5", "learner_id": "p6", "course_id": "course1", "facilitator_id": null, "week_number": 5, "tier": 1, "trigger_score": 42.6, "profile_type": "T", "channel": "lms_message", "template_id": "T_T1_nudge", "message_body": "Hi Frank — we noticed this week has been a bit quieter than usual. Topic 5 is challenging, and we can see you've worked through the quiz multiple times — that persistence is great. Review the structured example problems in Week 5 resources before your next attempt. Let us know if we can clarify anything.", "sent_at": "2026-02-17T08:00:00Z", "score_at_t7": 38.2, "score_at_t14": 35.1, "outcome": "partial_recovery", "cooldown_expires_at": "2026-02-24T08:00:00Z", "notes": "Auto T1 T-profile nudge. Partial recovery at T+7 and T+14.", "created_at": "2026-02-17T01:07:00Z", "updated_at": "2026-03-03T01:07:00Z" },
  { "id": "iv_p4_w5", "learner_id": "p4", "course_id": "course1", "facilitator_id": null, "week_number": 5, "tier": 1, "trigger_score": 42.6, "profile_type": "C", "channel": "lms_message", "template_id": "C_T1_structured_nudge", "message_body": "Hi David — we want to make sure you're on track. Week 5 OOP content is genuinely complex. We've added a structured revision checklist to your dashboard. Your weekly milestone targets are visible in your learning plan. Office hours Thursday 2–4pm.", "sent_at": "2026-02-17T08:05:00Z", "score_at_t7": 48.1, "score_at_t14": 52.8, "outcome": "no_change", "cooldown_expires_at": "2026-02-24T08:05:00Z", "notes": "Auto T1 C-profile. Score rose — escalated to T2 at W7.", "created_at": "2026-02-17T01:07:00Z", "updated_at": "2026-03-08T01:07:00Z" },
  { "id": "iv_p4_w7", "learner_id": "p4", "course_id": "course1", "facilitator_id": "user1", "week_number": 7, "tier": 2, "trigger_score": 64.1, "profile_type": "C", "channel": "lms_message", "template_id": "C_T2_pedagogical", "message_body": "Hi David — I wanted to check in personally. Topics 5 and 6 have been tough, and I can see you've been revisiting the material. I've extended your Week 6 assignment deadline by 5 days — visible in your dashboard. Can we arrange a 15-minute call this week?", "sent_at": "2026-03-02T10:30:00Z", "score_at_t7": null, "score_at_t14": null, "outcome": null, "cooldown_expires_at": null, "notes": "T2 facilitator outreach. Deadline extension offered. Awaiting response.", "created_at": "2026-03-08T01:07:00Z", "updated_at": "2026-03-08T10:30:45Z" },
  { "id": "iv_p8_w5", "learner_id": "p8", "course_id": "course1", "facilitator_id": null, "week_number": 5, "tier": 2, "trigger_score": 64.1, "profile_type": "A", "channel": "lms_message", "template_id": "A_T2_social_outreach", "message_body": "Hi Henry — just checking in. The group discussion for Topic 5 is still open — your perspective would be really valuable to your peers. If things have been difficult lately, just let me know — no pressure.", "sent_at": "2026-02-17T08:10:00Z", "score_at_t7": 68.2, "score_at_t14": 72.1, "outcome": "no_change", "cooldown_expires_at": "2026-02-24T08:10:00Z", "notes": "Auto T2 A-profile. No forum response. Score worsened. Escalated to T3.", "created_at": "2026-02-17T01:07:00Z", "updated_at": "2026-03-08T01:07:00Z" },
  { "id": "iv_p8_w7", "learner_id": "p8", "course_id": "course1", "facilitator_id": "user1", "week_number": 7, "tier": 3, "trigger_score": 78.5, "profile_type": "A", "channel": "video_call", "template_id": "A_T3_pastoral", "message_body": "Hi Henry — I'm reaching out because I'm genuinely concerned and want to understand how you're doing. This isn't about grades. I've arranged a video call Thursday 11am. You're not in trouble — I just want to make sure you're okay.", "sent_at": "2026-03-02T10:00:00Z", "score_at_t7": null, "score_at_t14": null, "outcome": null, "cooldown_expires_at": null, "notes": "T3 pastoral escalation. Video call scheduled. Student support notified.", "created_at": "2026-03-08T01:07:00Z", "updated_at": "2026-03-08T10:00:30Z" }
]
```

### Feedback Evaluations Seed (FL — `feedback_evaluations`)

```json
[
  { "id": "fe1", "intervention_id": "iv_p6_w5", "learner_id": "p6", "course_id": "course1", "evaluated_at_week": 7, "score_before": 42.6, "score_at_t7": 38.2, "score_at_t14": 35.1, "score_delta_t7": -4.4, "score_delta_t14": -7.5, "recovery_threshold_met": true, "outcome_label": "partial_recovery", "re_threshold_adjustment": null, "model_notes": "T-profile T1 nudge partially effective. Optional access up to 33%, quiz attempts increased. T1 template appropriate for this risk band." },
  { "id": "fe2", "intervention_id": "iv_p4_w5", "learner_id": "p4", "course_id": "course1", "evaluated_at_week": 7, "score_before": 42.6, "score_at_t7": 48.1, "score_at_t14": 52.8, "score_delta_t7": 5.5, "score_delta_t14": 10.2, "recovery_threshold_met": false, "outcome_label": "worsened", "re_threshold_adjustment": {"profile":"C","recommendation":"Reduce T1 trigger from 40 to 35 for C-profile","rationale":"C-profile learners deteriorate faster — earlier trigger improves intervention window"}, "model_notes": "C-profile T1 message insufficient. +10.2pts over 14 days. RE calibration: C-profile T1 at 35, T2 human outreach mandatory at 50." },
  { "id": "fe3", "intervention_id": "iv_p8_w5", "learner_id": "p8", "course_id": "course1", "evaluated_at_week": 7, "score_before": 64.1, "score_at_t7": 68.2, "score_at_t14": 72.1, "score_delta_t7": 4.1, "score_delta_t14": 8.0, "recovery_threshold_met": false, "outcome_label": "escalated", "re_threshold_adjustment": {"profile":"A","recommendation":"Add pastoral_referral to A-profile T2 channel","rationale":"A-profile learners with zero forum 2+ weeks need voice contact, not LMS message"}, "model_notes": "A-profile T2 message ignored. RE: A-profile + zero_forum_2wks = automatic T3 escalation flag." }
]
```

### Profile Drift Logs Seed (FL — `profile_drift_logs`)

```json
[
  { "id": "pd1", "learner_id": "p4", "course_id": "course1", "detected_at_week": 5, "declared_profile": "C", "observed_pattern": "C-profile fully withdrawn from structured pathways — no weekly roadmap access, all deadlines missed, zero cohort interaction.", "drift_direction": "C → disengaged (no profile match)", "drift_severity": "moderate", "drift_confirmed_at": "2026-02-22T01:07:00Z", "facilitator_alerted": true, "resolution": null, "notes": "Drift flag set. RE applies disengaged weights. Profile re-declaration offered at W9." },
  { "id": "pd2", "learner_id": "p8", "course_id": "course1", "detected_at_week": 5, "declared_profile": "A", "observed_pattern": "A-profile completely withdrawn from all social signals — 0 forum posts, 0 peer responses, 0 facilitator messages for 2 consecutive weeks.", "drift_direction": "A → isolated (inverse of declared profile)", "drift_severity": "severe", "drift_confirmed_at": "2026-02-22T01:07:00Z", "facilitator_alerted": true, "resolution": null, "notes": "Severe A-profile drift + pulse < 2.0 = automatic T3 in RE. Student support notified." },
  { "id": "pd3", "learner_id": "p6", "course_id": "course1", "detected_at_week": 5, "declared_profile": "T", "observed_pattern": "T-profile showing reduced task completion and submission delays. Still partially matching declared profile (time-on-task adequate).", "drift_direction": "T → partially disengaged (mild degradation)", "drift_severity": "mild", "drift_confirmed_at": "2026-02-22T01:07:00Z", "facilitator_alerted": true, "resolution": "partial_recovery", "notes": "Mild drift. T1 nudge effective. Flag removed at W7. Re-declaration not required." }
]
```

---

## Full Pipeline Entity Relationship

```
users ──────────────────────────────────────────────────────────────────────┐
  │                                                                          │
  ├─< enrollments >── courses ──< sections ──< activities                   │
  │                      │                                                   │
  │                      ├──< grade_items ──< student_grades                │
  │                      ├──< dashboard_engagement                          │
  │                      ├──< activity_performance                          │
  │                      ├──< ai_performance_snapshots                      │
  │                      ├──< ai_skill_metrics                              │
  │                      └──< ai_generated_questions                        │
  │                                                                          │
  ├──< notifications                                                         │
  ├──< user_preferences                                                      │
  ├──< conversations >── users ──< messages                                │
  │                                                                          │
  │  ── L0 → L1 → L2 → L3 → RE → IE → FL ──────────────────────────────── │
  │                                                                          │
  ├──< learner_profiles (L0) >──── courses                                 │
  │         └── drift_flag → profile_drift_logs (FL)                        │
  │                                                                          │
  ├──< behavioral_signals (L1) >── courses  (weekly per learner)           │
  ├──< cognitive_signals  (L2) >── courses  (weekly per learner)           │
  ├──< emotional_signals  (L3) >── courses  (weekly per learner)           │
  │                                                                          │
  ├──< risk_scores (RE) >───────── courses                                 │
  │   weights ← learner_profiles (profile_type)                             │
  │   inputs  ← behavioral_signals + cognitive_signals + emotional_signals  │
  │   outputs → tier → interventions (IE)                                   │
  │                                                                          │
  ├──< interventions (IE) >────── courses                                  │
  │   trigger ← risk_scores.tier                                            │
  │   template ← profile_type + tier                                        │
  │   outcome → feedback_evaluations (FL)                                   │
  │                                                                          │
  ├──< feedback_evaluations (FL) >── interventions                         │
  │   calibrates → risk_scores thresholds per profile_type                  │
  │                                                                          │
  └──< profile_drift_logs (FL) >── learner_profiles                        │
      triggers → RE weight recalibration                                     │
                                                                             │
categories ────────────────────────────────────── courses ──────────────────┘
  └─< categories (self-referential parent_id)
```

<?php

return [
    // Comma-separated e-mails that act as admins (approve registrations, edit exercises, remove members).
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

    // Where the static site lives; login links in e-mails point here.
    'frontend_url' => env('FRONTEND_URL', 'https://sunnaa-ai2.github.io'),

    // Minutes a password-reset link stays valid.
    'reset_ttl' => (int) env('RESET_LINK_TTL', 60),

    // Days an API token stays valid.
    'token_days' => (int) env('API_TOKEN_DAYS', 30),

    // Disk for uploads: "local" for a persistent server, "s3" for Supabase Storage / any S3 bucket.
    'disk' => env('FILES_DISK', 'local'),

    // Max upload size in kilobytes (default 50 MB): per file, and for all files of one submission together.
    'max_upload_kb' => (int) env('MAX_UPLOAD_KB', 51200),

    // Claude features (submission review, course assistant). Off while ANTHROPIC_API_KEY is empty.
    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-haiku-5-5'),
        'reviews_per_day' => (int) env('AI_REVIEWS_PER_DAY', 3),
        'questions_per_day' => (int) env('AI_QUESTIONS_PER_DAY', 30),
    ],

    // Files one exercise submission can hold.
    'max_files' => (int) env('MAX_FILES_PER_WORK', 8),
];

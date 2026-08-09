<?php

return [
    'daily_submission_limit' => (int) env('PUBLIC_CATALOG_DAILY_SUBMISSION_LIMIT', 10),
    'daily_report_limit' => (int) env('PUBLIC_CATALOG_DAILY_REPORT_LIMIT', 20),
    'max_pending_submissions' => (int) env('PUBLIC_CATALOG_MAX_PENDING_SUBMISSIONS', 5),
    'terms_version' => env('PUBLIC_CATALOG_TERMS_VERSION', '2026-08-v1'),
    'reputation_rule_version' => env('PUBLIC_CATALOG_REPUTATION_RULE_VERSION', 'v1'),
    'reputation_points' => [
        'submission_approved' => 5,
        'submission_rejected' => -1,
        'report_upheld' => 2,
        'content_removed' => -5,
        'report_dismissed' => -1,
    ],
];

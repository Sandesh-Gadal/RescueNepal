<?php
return [
    'app_name' => 'Rescue Nepal - Missing Persons & Rescue Registry',
    'base_url' => 'https://rescuenepal.info',
    'timezone' => 'Asia/Kathmandu',
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DATABASE',
        'user' => 'YOUR_DATABASE_USER',
        'pass' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'rescuenepal_registry',
        'max_public_submissions_per_hour' => 20,
    ],
    'sms' => [
        // Optional. Configure a webhook accepting JSON: {phone,message,code}.
        // Leave blank to use staff/manual phone verification for family match requests.
        'otp_webhook_url' => '',
        'bearer_token' => '',
    ],
    'uploads' => [
        'max_photo_bytes' => 8 * 1024 * 1024,
        'photo_dir' => __DIR__ . '/uploads/photos',
        'thumb_dir' => __DIR__ . '/uploads/thumbs',
        'import_dir' => __DIR__ . '/uploads/imports',
        'evidence_dir' => __DIR__ . '/uploads/evidence',
        'family_dir' => __DIR__ . '/uploads/family',
    ],
];

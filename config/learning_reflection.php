<?php

return [
    'max_attachment_kib' => 51200, // ~50 MB per file
    'max_attachments_per_reflection' => 3,
    'max_body_characters' => (int) config('course_assistant.maximum_saved_response_characters', 6000),
    'disk' => 'learner_media_private',
    'quarantine_prefix' => 'reflections/quarantine',
    'blob_prefix' => 'reflections/blobs',
    'allowed' => [
        'webm' => ['audio/webm', 'video/webm'],
        'ogg' => ['audio/ogg', 'application/ogg', 'audio/x-ogg', 'video/ogg'],
        'oga' => ['audio/ogg', 'application/ogg', 'audio/x-ogg'],
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'mp4a' => ['audio/mp4'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'mp4' => ['video/mp4'],
    ],
];

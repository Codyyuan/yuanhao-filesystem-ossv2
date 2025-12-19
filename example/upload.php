<?php

return [
    // 默认存储驱动
    'default' => 'oss',

    // 存储驱动配置
    'drivers' => [
        'local' => [
            'type' => 'local',
            'root' => dirname(__DIR__) . '/uploads',
            'url' => '/uploads',
        ],
        'oss' => [
            'type' => 'oss',
            'accessKeyId' => 'OSS_ACCESS_KEY_ID',
            'accessKeySecret' => 'OSS_ACCESS_KEY_SECRET',
            'endpoint' => 'OSS_ENDPOINT',
            'bucket' => 'OSS_BUCKET',
            'region' => 'OSS_REGION',
            'url' => '',
        ],
        'cos' => [
            'type' => 'cos',
            'region' => '',
            'appId' => '',
            'secretId' => '',
            'secretKey' => '',
            'bucket' => '',
            'url' => '',
        ],
        'aws' => [
            'type' => 'aws',
            'key' => '',
            'secret' => '',
            'region' => '',
            'bucket' => '',
            'url' => '',
        ],
    ],

    // 文件类型配置
    'file_types' => [
        'image' => [
            'path' => 'images',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'thumbnails' => [
                'small' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 300, 'height' => 300],
            ],
        ],
        'video' => [
            'path' => 'videos',
            'allowed_extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
            'max_size' => 100 * 1024 * 1024, // 100MB
        ],
        'audio' => [
            'path' => 'audios',
            'allowed_extensions' => ['mp3', 'wav', 'flac', 'm4a', 'ogg'],
            'max_size' => 50 * 1024 * 1024, // 50MB
        ],
        'document' => [
            'path' => 'documents',
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md'],
            'max_size' => 20 * 1024 * 1024, // 20MB
        ],
        'attachment' => [
            'path' => 'attachments',
            'allowed_extensions' => ['zip', 'rar', '7z', 'tar', 'gz', 'exe', 'apk', 'ipa'],
            'max_size' => 100 * 1024 * 1024, // 100MB
        ],
    ],
];
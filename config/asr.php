<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 默认驱动
    |--------------------------------------------------------------------------
    |
    | 可选：tencent / xunfei / aliyun
    |
    */
    'default' => env('ASR_DRIVER', 'tencent'),

    'timeout' => (int) env('ASR_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | 语音识别驱动
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'tencent' => [
            'secret_id' => env('ASR_TENCENT_SECRET_ID'),
            'secret_key' => env('ASR_TENCENT_SECRET_KEY'),
            'region' => env('ASR_TENCENT_REGION', ''),
            'engine' => env('ASR_TENCENT_ENGINE', '16k_zh'),
        ],

        'xunfei' => [
            'app_id' => env('ASR_XUNFEI_APP_ID'),
            'api_key' => env('ASR_XUNFEI_API_KEY'),
            'api_secret' => env('ASR_XUNFEI_API_SECRET'),
            'language' => env('ASR_XUNFEI_LANGUAGE', 'zh_cn'),
            'domain' => env('ASR_XUNFEI_DOMAIN', 'iat'),
            'accent' => env('ASR_XUNFEI_ACCENT', 'mandarin'),
            'host' => env('ASR_XUNFEI_HOST', 'iat-api.xfyun.cn'),
        ],

        'aliyun' => [
            'access_key_id' => env('ASR_ALIYUN_ACCESS_KEY_ID'),
            'access_key_secret' => env('ASR_ALIYUN_ACCESS_KEY_SECRET'),
            'app_key' => env('ASR_ALIYUN_APP_KEY'),
            'region' => env('ASR_ALIYUN_REGION', 'cn-shanghai'),
            'token' => env('ASR_ALIYUN_TOKEN'),
        ],
    ],
];

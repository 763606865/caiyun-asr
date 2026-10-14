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

    /*
    |--------------------------------------------------------------------------
    | 请求超时（秒）
    |--------------------------------------------------------------------------
    |
    | 讯飞按帧上传，整段识别耗时接近音频时长，超时需要留出余量。
    |
    */
    'timeout' => (int) env('ASR_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | 语音识别驱动
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        /*
        | 腾讯云一句话识别。16k 音频用 16k_zh，8k 音频用 8k_zh。
        */
        'tencent' => [
            'secret_id' => env('ASR_TENCENT_SECRET_ID'),
            'secret_key' => env('ASR_TENCENT_SECRET_KEY'),
            'region' => env('ASR_TENCENT_REGION', ''),
            'engine' => env('ASR_TENCENT_ENGINE', '16k_zh'),
        ],

        /*
        | 讯飞语音听写（流式版）。小语种把 host 改为 iat-niche-api.xfyun.cn。
        */
        'xunfei' => [
            'app_id' => env('ASR_XUNFEI_APP_ID'),
            'api_key' => env('ASR_XUNFEI_API_KEY'),
            'api_secret' => env('ASR_XUNFEI_API_SECRET'),
            'language' => env('ASR_XUNFEI_LANGUAGE', 'zh_cn'),
            'domain' => env('ASR_XUNFEI_DOMAIN', 'iat'),
            'accent' => env('ASR_XUNFEI_ACCENT', 'mandarin'),
            'host' => env('ASR_XUNFEI_HOST', 'iat-api.xfyun.cn'),
        ],

        /*
        | 阿里云智能语音交互。region 可选 cn-shanghai、cn-beijing、cn-shenzhen。
        | 已经拿到 NLS Token 时填写 token，不必再填 AccessKey。
        */
        'aliyun' => [
            'access_key_id' => env('ASR_ALIYUN_ACCESS_KEY_ID'),
            'access_key_secret' => env('ASR_ALIYUN_ACCESS_KEY_SECRET'),
            'app_key' => env('ASR_ALIYUN_APP_KEY'),
            'region' => env('ASR_ALIYUN_REGION', 'cn-shanghai'),
            'token' => env('ASR_ALIYUN_TOKEN'),
        ],
    ],
];

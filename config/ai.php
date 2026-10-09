<?php

declare(strict_types=1);

/*
 * Настройки laravel/ai. Остальные ключи (default_for_images, caching и т.д.) пакет
 * подмешивает из своего конфига: mergeConfigFrom сливает только верхний уровень,
 * поэтому providers здесь — полный список, а не дополнение к пакетному.
 */
return [

    'default' => env('AI_PROVIDER', 'llama'),

    'providers' => [

        // llama.cpp из docker-compose: OpenAI-совместимый API, ключ не нужен.
        // Модель — имя GGUF-файла из dockerConfigs/models без .gguf.
        'llama' => [
            'driver' => 'openai-compatible',
            'url' => env('LLAMA_URL', 'http://llama:8080/v1'),
            'key' => env('LLAMA_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('LLAMA_MODEL', 'FineQwen3.5-2B_Q4'),
                ],
            ],
        ],

    ],

];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ollama LLM Configuration
    |--------------------------------------------------------------------------
    | base_url : URL where ollama is running  (default: http://localhost:11434)
    | model    : Model to use for generation  (e.g. llama3.2, mistral, gemma2)
    | timeout  : HTTP timeout in seconds
    */
    'base_url'     => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'model'        => env('OLLAMA_MODEL', 'llama3.2'),
    'vision_model' => env('OLLAMA_VISION_MODEL', 'llava'),
    'timeout'      => (int) env('OLLAMA_TIMEOUT', 120),
];

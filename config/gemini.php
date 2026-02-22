<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API Configuration
    |--------------------------------------------------------------------------
    | api_key      : Your Gemini API key (get from https://aistudio.google.com/apikey)
    | base_url     : Gemini REST API base URL
    | vision_model : Model for image analysis (OCR)
    | text_model   : Model for text analysis
    | timeout      : HTTP timeout in seconds
    */
    'api_key'      => env('GEMINI_API_KEY', ''),
    'base_url'     => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
    'text_model'   => env('GEMINI_TEXT_MODEL', 'gemini-2.5-flash'),
    'timeout'      => (int) env('GEMINI_TIMEOUT', 120),
];

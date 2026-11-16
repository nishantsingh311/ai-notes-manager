<?php

return [
    'api' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
    'semantic_search' => (int) env('SEMANTIC_SEARCH_RATE_LIMIT_PER_MINUTE', 10),
    'ai_summary' => (int) env('AI_SUMMARY_RATE_LIMIT_PER_MINUTE', 5),
];

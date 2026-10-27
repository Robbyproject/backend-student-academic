<?php

return [
    'campus_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('CAMPUS_EMAIL_DOMAINS', 'kampus.ac.id'))
    ))),
];

<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CampusEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr((string) $value, '@') ?: '', 1));
        $allowedDomains = array_map('strtolower', config('academic.campus_email_domains', []));

        if (! in_array($domain, $allowedDomains, true)) {
            $fail('Email harus menggunakan domain kampus yang terdaftar.');
        }
    }
}

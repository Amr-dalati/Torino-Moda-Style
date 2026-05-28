<?php

namespace App\Support;

final class SensitiveDataRedactor
{
    /**
     * @var list<string>
     */
    protected array $sensitiveKeyFragments = [
        'authorization',
        'bearer',
        'token',
        'secret',
        'password',
        'password_confirmation',
        'api_key',
        'apikey',
        'key',
        'signature',
        'card',
        'pan',
        'cvv',
        'cvc',
        'expiry',
        'exp_month',
        'exp_year',
        'email',
        'phone',
        'address',
    ];

    public static function redact(mixed $value): mixed
    {
        return (new self())->redactValue($value);
    }

    protected function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && $this->isSensitiveKey($k)) {
                    $out[$k] = '[REDACTED]';
                    continue;
                }
                $out[$k] = $this->redactValue($v);
            }
            return $out;
        }

        if (is_object($value)) {
            // Avoid trying to serialize complex objects. Keep minimal info.
            return '[REDACTED_OBJECT]';
        }

        return $value;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $k = strtolower($key);

        foreach ($this->sensitiveKeyFragments as $frag) {
            if (str_contains($k, $frag)) {
                return true;
            }
        }

        return false;
    }
}


<?php

namespace App\Services;

use Illuminate\Support\Str;

class PayloadRedactor
{
    /**
     * @param  array<string|int, mixed>  $payload
     * @return array<string|int, mixed>
     */
    public function redact(array $payload): array
    {
        $sensitiveKeys = array_map(
            fn (string $key): string => $this->normalizeKey($key),
            config('syncops.display.redacted_payload_keys', []),
        );

        return $this->redactArray($payload, $sensitiveKeys);
    }

    /**
     * @param  array<string|int, mixed>  $payload
     */
    public function toPrettyJson(array $payload): string
    {
        return json_encode(
            $this->redact($payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @param  list<string>  $sensitiveKeys
     * @return array<string|int, mixed>
     */
    private function redactArray(array $values, array $sensitiveKeys): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array($this->normalizeKey($key), $sensitiveKeys, true)) {
                $values[$key] = '[masqué]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redactArray($value, $sensitiveKeys);
            }
        }

        return $values;
    }

    private function normalizeKey(string $key): string
    {
        $normalized = str_replace(['-', ' ', '.'], '_', trim($key));
        $normalized = preg_replace(
            '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/',
            '_',
            $normalized,
        ) ?? $normalized;

        return Str::lower($normalized);
    }
}

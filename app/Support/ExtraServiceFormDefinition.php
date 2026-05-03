<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * JSON "application_form" on extra_services: a list of field objects shown in the mobile app when requesting a service.
 *
 * Example:
 * [{"key":"contact_name","type":"text","label":"Contact name","required":true,"max":120},
 *  {"key":"details","type":"textarea","label":"What do you need?","required":true,"max":2000}]
 */
final class ExtraServiceFormDefinition
{
    public const TYPES = ['text', 'textarea', 'email', 'tel', 'number'];

    public static function hasFields(?array $definition): bool
    {
        return is_array($definition) && $definition !== [] && array_is_list($definition);
    }

    /**
     * @param  array<int, mixed>|null  $definition
     */
    public static function assertValidDefinition(?array $definition): void
    {
        if ($definition === null || $definition === []) {
            return;
        }

        if (! array_is_list($definition)) {
            throw ValidationException::withMessages([
                'application_form_json' => ['Application form must be a JSON array of field objects.'],
            ]);
        }

        $seenKeys = [];

        foreach ($definition as $i => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Field at index {$i} must be an object."],
                ]);
            }

            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '' || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Field at index {$i}: \"key\" must be snake_case (letters, digits, underscore)."],
                ]);
            }

            if (isset($seenKeys[$key])) {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Duplicate field key: {$key}"],
                ]);
            }
            $seenKeys[$key] = true;

            $label = $field['label'] ?? null;
            if (! is_string($label) || $label === '') {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Field \"{$key}\": \"label\" is required."],
                ]);
            }

            $type = $field['type'] ?? 'text';
            if (! in_array($type, self::TYPES, true)) {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Field \"{$key}\": type must be one of: ".implode(', ', self::TYPES).'.'],
                ]);
            }

            if (isset($field['required']) && ! is_bool($field['required'])) {
                throw ValidationException::withMessages([
                    'application_form_json' => ["Field \"{$key}\": \"required\" must be boolean."],
                ]);
            }

            if (isset($field['max'])) {
                if (! is_int($field['max']) && ! (is_string($field['max']) && ctype_digit((string) $field['max']))) {
                    throw ValidationException::withMessages([
                        'application_form_json' => ["Field \"{$key}\": \"max\" must be a positive integer."],
                    ]);
                }
                $max = (int) $field['max'];
                if ($max < 1 || $max > 20000) {
                    throw ValidationException::withMessages([
                        'application_form_json' => ["Field \"{$key}\": \"max\" must be between 1 and 20000."],
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $definition
     * @param  array<string, mixed>  $payload
     */
    public static function validatePayload(array $definition, array $payload): array
    {
        if ($definition === []) {
            return [];
        }

        $out = [];

        foreach ($definition as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $required = (bool) ($field['required'] ?? false);
            $type = (string) ($field['type'] ?? 'text');
            $max = isset($field['max']) ? (int) $field['max'] : ($type === 'textarea' ? 5000 : 500);

            $raw = $payload[$key] ?? null;
            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                if ($required) {
                    throw ValidationException::withMessages([
                        "applicant_payload.{$key}" => ["{$label} is required."],
                    ]);
                }

                continue;
            }

            if (is_array($raw) || is_object($raw)) {
                throw ValidationException::withMessages([
                    "applicant_payload.{$key}" => ["{$label} must be a scalar value."],
                ]);
            }

            $str = trim((string) $raw);

            if (mb_strlen($str) > $max) {
                throw ValidationException::withMessages([
                    "applicant_payload.{$key}" => ["{$label} may not be greater than {$max} characters."],
                ]);
            }

            if ($type === 'email' && $str !== '' && ! filter_var($str, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    "applicant_payload.{$key}" => ["{$label} must be a valid email address."],
                ]);
            }

            if ($type === 'number' && $str !== '' && ! is_numeric($str)) {
                throw ValidationException::withMessages([
                    "applicant_payload.{$key}" => ["{$label} must be a number."],
                ]);
            }

            $out[$key] = $str;
        }

        return $out;
    }
}

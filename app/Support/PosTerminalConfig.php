<?php

namespace App\Support;

final class PosTerminalConfig
{
    /** @var list<string> */
    public const ADMIN_FIELDS = [
        'pos_enabled',
        'merchant_no',
        'terminal_no',
        'merchant_name',
        'device_sn',
        'host_ip',
        'host_port',
        'ssl',
        'comp_key1',
        'comp_key2',
        'base_url',
        'logo_url',
        'account_type',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromBusinessSettings(?array $settings): array
    {
        $raw = is_array($settings) ? ($settings['pos_terminal'] ?? []) : [];
        if (! is_array($raw)) {
            $raw = [];
        }

        return self::normalize($raw);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $enabled = filter_var($input['pos_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'pos_enabled' => $enabled,
            'merchant_no' => self::str($input['merchant_no'] ?? null),
            'terminal_no' => self::str($input['terminal_no'] ?? null),
            'merchant_name' => self::str($input['merchant_name'] ?? null),
            'device_sn' => self::str($input['device_sn'] ?? null),
            'host_ip' => self::str($input['host_ip'] ?? null),
            'host_port' => self::str($input['host_port'] ?? null),
            'ssl' => self::boolString($input['ssl'] ?? 'true'),
            'comp_key1' => self::str($input['comp_key1'] ?? null),
            'comp_key2' => self::str($input['comp_key2'] ?? null),
            'base_url' => self::normalizeBaseUrl(self::str($input['base_url'] ?? null)),
            'logo_url' => self::str($input['logo_url'] ?? null),
            'account_type' => self::accountType($input['account_type'] ?? '00'),
        ];
    }

    /**
     * Mobile/API payload: terminal_info + tid_config (ENKWAVE plugin shape).
     *
     * @return array<string, mixed>|null
     */
    public static function toMobilePayload(?array $settings): ?array
    {
        $cfg = self::fromBusinessSettings($settings);
        if (! ($cfg['pos_enabled'] ?? false)) {
            return null;
        }

        if (! self::isComplete($cfg)) {
            return [
                'pos_enabled' => false,
                'configured' => false,
                'message' => 'POS terminal settings are incomplete. Ask a manager to finish setup in admin.',
            ];
        }

        return [
            'pos_enabled' => true,
            'configured' => true,
            'terminal_info' => [
                'merchantNo' => $cfg['merchant_no'],
                'terminalNo' => $cfg['terminal_no'],
                'merchantName' => $cfg['merchant_name'],
                'deviceSN' => $cfg['device_sn'],
            ],
            'tid_config' => [
                'ip' => $cfg['host_ip'],
                'port' => $cfg['host_port'],
                'ssl' => $cfg['ssl'],
                'compKey1' => $cfg['comp_key1'],
                'compKey2' => $cfg['comp_key2'],
                'baseUrl' => $cfg['base_url'],
                'logoUrl' => $cfg['logo_url'],
            ],
            'account_type' => $cfg['account_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function isComplete(array $cfg): bool
    {
        foreach (['terminal_no', 'host_ip', 'host_port', 'ssl', 'comp_key1', 'comp_key2', 'base_url'] as $key) {
            if (($cfg[$key] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    private static function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private static function boolString(mixed $value): string
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return 'true';
        }

        return 'false';
    }

    private static function accountType(mixed $value): string
    {
        $v = trim((string) $value);
        if (in_array($v, ['00', '10', '20', '30'], true)) {
            return $v;
        }

        return '00';
    }

    private static function normalizeBaseUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $url = rtrim($url, '/');
        if (! str_ends_with(strtolower($url), '/api')) {
            $url .= '/api';
        }

        return $url.'/';
    }
}

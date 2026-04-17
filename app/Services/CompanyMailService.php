<?php

namespace Crater\Services;

use Crater\Models\CompanySetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company SMTP configuration service.
 *
 * Settings stored in company_settings with keys:
 *   company_mail_driver      — 'smtp' | '' (empty = use global .env config)
 *   company_mail_host
 *   company_mail_port
 *   company_mail_encryption  — 'tls' | 'ssl' | 'none'
 *   company_mail_username
 *   company_mail_password    — Crypt::encrypt()-ed
 *   company_mail_from_address
 *   company_mail_from_name
 */
class CompanyMailService
{
    protected const KEYS = [
        'company_mail_driver',
        'company_mail_host',
        'company_mail_port',
        'company_mail_encryption',
        'company_mail_username',
        'company_mail_password',
        'company_mail_from_address',
        'company_mail_from_name',
    ];

    /**
     * Return the company mail settings as an array.
     * Password is returned masked for the frontend (not the raw value).
     */
    public function getSettings(int $companyId): array
    {
        $raw = [];
        foreach (self::KEYS as $key) {
            $raw[$key] = CompanySetting::getSetting($key, $companyId) ?: '';
        }

        return [
            'driver'       => $raw['company_mail_driver'],
            'host'         => $raw['company_mail_host'],
            'port'         => $raw['company_mail_port'],
            'encryption'   => $raw['company_mail_encryption'],
            'username'     => $raw['company_mail_username'],
            'password'     => $raw['company_mail_password'] ? '••••••••' : '',
            'from_address' => $raw['company_mail_from_address'],
            'from_name'    => $raw['company_mail_from_name'],
            'configured'   => ! empty($raw['company_mail_driver']),
        ];
    }

    /**
     * Persist company mail settings. Encrypts the password if provided;
     * keeps the existing password if the submitted value is masked.
     */
    public function saveSettings(array $data, int $companyId): void
    {
        $password = $data['password'] ?? '';

        // Do not overwrite an existing encrypted password with the masked placeholder
        if ($password === '••••••••' || $password === '') {
            $existing = CompanySetting::getSetting('company_mail_password', $companyId);
            $password = $existing ?: '';
        } else {
            $password = Crypt::encrypt($password);
        }

        $settings = [
            'company_mail_driver'       => $data['driver'] ?? '',
            'company_mail_host'         => $data['host'] ?? '',
            'company_mail_port'         => $data['port'] ?? '',
            'company_mail_encryption'   => $data['encryption'] ?? 'tls',
            'company_mail_username'     => $data['username'] ?? '',
            'company_mail_password'     => $password,
            'company_mail_from_address' => $data['from_address'] ?? '',
            'company_mail_from_name'    => $data['from_name'] ?? '',
        ];

        CompanySetting::setSettings($settings, $companyId);
    }

    /**
     * Clear all company mail settings, reverting to global config.
     */
    public function clearSettings(int $companyId): void
    {
        $empty = array_fill_keys(self::KEYS, '');
        CompanySetting::setSettings($empty, $companyId);
    }

    /**
     * Resolve the mailer name to use for this company.
     *
     * If the company has a driver configured, registers a dynamic named mailer
     * ('company_{id}') in Laravel's config and returns its name.
     * Otherwise returns 'default' (falls back to global .env config).
     */
    public function resolveMailerName(int $companyId): string
    {
        $driver = CompanySetting::getSetting('company_mail_driver', $companyId);

        if (empty($driver)) {
            return 'default';
        }

        $host       = CompanySetting::getSetting('company_mail_host', $companyId);
        $port       = (int) (CompanySetting::getSetting('company_mail_port', $companyId) ?: 587);
        $encryption = CompanySetting::getSetting('company_mail_encryption', $companyId) ?: 'tls';
        $username   = CompanySetting::getSetting('company_mail_username', $companyId);
        $encrypted  = CompanySetting::getSetting('company_mail_password', $companyId);
        $password   = '';

        if ($encrypted) {
            try {
                $password = Crypt::decrypt($encrypted);
            } catch (\Exception $e) {
                $password = $encrypted; // fallback if not encrypted (legacy)
            }
        }

        $mailerKey = "company_{$companyId}";

        config(["mail.mailers.{$mailerKey}" => [
            'transport'  => 'smtp',
            'host'       => $host,
            'port'       => $port,
            'encryption' => $encryption === 'none' ? null : $encryption,
            'username'   => $username,
            'password'   => $password,
            'timeout'    => null,
        ]]);

        return $mailerKey;
    }

    /**
     * Return the configured from address for a company,
     * falling back to the global config.
     */
    public function getFromAddress(int $companyId): string
    {
        return CompanySetting::getSetting('company_mail_from_address', $companyId)
            ?: config('mail.from.address', '');
    }

    /**
     * Return the configured from name for a company,
     * falling back to the global config.
     */
    public function getFromName(int $companyId): string
    {
        return CompanySetting::getSetting('company_mail_from_name', $companyId)
            ?: config('mail.from.name', '');
    }
}

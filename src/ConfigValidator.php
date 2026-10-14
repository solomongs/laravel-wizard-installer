<?php

namespace SolomonGbeta\LaravelWizard;

final class ConfigValidator
{
    public static function validate(array $input, bool $requireHttps = true): array
    {
        if (isset($input['db_port']) && is_int($input['db_port'])) $input['db_port'] = (string) $input['db_port'];
        $fields = ['name','url','timezone','db_host','db_port','db_database','db_username','db_password'];
        $values = [];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || strlen($input[$field]) > 1024 || strpbrk($input[$field], "\r\n\0") !== false) {
                throw new InstallerException('Complete all website and database fields using single-line values.', 'validation');
            }
            $values[$field] = $field === 'db_password' ? $input[$field] : trim($input[$field]);
        }
        if ($values['name'] === '' || strlen($values['name']) > 100) throw new InstallerException('Enter a website name under 100 characters.', 'validation');
        $url = parse_url($values['url']);
        if (!$url || !isset($url['host'], $url['scheme']) || !in_array($url['scheme'], $requireHttps ? ['https'] : ['https','http'], true) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !filter_var($values['url'], FILTER_VALIDATE_URL)) {
            throw new InstallerException('Enter your full website address starting with '.($requireHttps ? 'https://' : 'http:// or https://').', without a query or password.', 'validation');
        }
        if (!in_array($values['timezone'], timezone_identifiers_list(), true)) throw new InstallerException('Choose a valid timezone, for example Africa/Lagos.', 'validation');
        if (!preg_match('/^[a-zA-Z0-9._:-]+$/D', $values['db_host']) || !ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535) throw new InstallerException('Enter the database hostname and numeric port from your hosting panel. Do not use a URL.', 'validation');
        if ($values['db_database'] === '' || $values['db_username'] === '') throw new InstallerException('Enter the full database name and assigned database username.', 'validation');
        if (($input['confirm_empty'] ?? false) !== true) throw new InstallerException('Confirm that you created a new empty database and have a backup of any existing site.', 'confirmation');
        $values['url'] = rtrim($values['url'], '/');
        return $values;
    }
}

<?php

namespace SolomonGbeta\LaravelWizard;

use PDO;

class DatabaseProbe
{
    public static function signature(array $values, string $salt): string
    {
        $tuple = [strtolower($values['db_host']), (string) $values['db_port'], $values['db_database'], $values['db_username'], $values['db_password']];
        return hash_hmac('sha256', json_encode($tuple, JSON_THROW_ON_ERROR), $salt);
    }

    public function connect(array $values): PDO
    {
        if (!extension_loaded('pdo_mysql')) throw new InstallerException('Enable the pdo_mysql PHP extension in your hosting account.', 'database_driver');
        // Host/name are DSN components: separators are forbidden, never interpolated SQL.
        foreach (['db_host','db_database'] as $key) {
            if (strpbrk($values[$key], ";\r\n\0") !== false) throw new InstallerException('The database host or name contains an unsupported character.', 'database_fields');
        }
        try {
            return new PDO('mysql:host='.$values['db_host'].';port='.$values['db_port'].';dbname='.$values['db_database'].';charset=utf8mb4', $values['db_username'], $values['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false]);
        } catch (\PDOException) {
            throw new InstallerException('Could not connect to MySQL. Copy the database hostname, full name, username and password from your hosting panel. Assign the user to that database with the application’s required privileges.', 'database_connection');
        }
    }

    public function verifyEmpty(array $values): void
    {
        $pdo = $this->connect($values);
        $query = $pdo->query('SHOW TABLES');
        if ($query->fetchColumn() !== false) {
            throw new InstallerException('This database already contains tables. Create a separate empty database. The installer will not erase an existing website.', 'database_not_empty');
        }
    }
}

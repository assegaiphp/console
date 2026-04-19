<?php

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Inspector;

const PACKAGE_NAME_CORE = 'assegaiphp/core';
const PACKAGE_NAME_CLI = 'assegaiphp/console';
const PACKAGE_NAME_ORM = 'assegaiphp/orm';
const PACKAGE_NAME_EVENTS = 'assegaiphp/events';
const RECOMMENDED_FRAMEWORK_RELEASE_LINE_FALLBACK = '^0.8.0';

if (!function_exists('recommended_framework_release_line_for_version')) {
    function recommended_framework_release_line_for_version(string $version): string
    {
        if (preg_match('/(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return RECOMMENDED_FRAMEWORK_RELEASE_LINE_FALLBACK;
        }

        return sprintf('^%s.%s.0', $matches[1], $matches[2]);
    }
}

if (!function_exists('recommended_framework_release_line')) {
    function recommended_framework_release_line(?string $version = null): string
    {
        $version = $version ?? Inspector::getRunningCLIVersion();

        if (!is_string($version) || trim($version) === '') {
            return RECOMMENDED_FRAMEWORK_RELEASE_LINE_FALLBACK;
        }

        return recommended_framework_release_line_for_version($version);
    }
}

define('RECOMMENDED_FRAMEWORK_RELEASE_LINE', recommended_framework_release_line());
define('RECOMMENDED_CORE_VERSION_CONSTRAINT', RECOMMENDED_FRAMEWORK_RELEASE_LINE);
define('RECOMMENDED_ORM_VERSION_CONSTRAINT', RECOMMENDED_FRAMEWORK_RELEASE_LINE);
define('RECOMMENDED_EVENTS_VERSION_CONSTRAINT', '*');
const DEFAULT_PROJECT_NAME = 'assegai-app';
const DEFAULT_PROJECT_VERSION = '0.0.1';
const DEFAULT_PROJECT_TYPE = 'project';
const DEFAULT_DEV_SERVER_PORT = 5000;
const DEFAULT_DEV_SERVER_HOST = 'localhost';
const MIN_PHP_VERSION = '8.3';
const DONATION_LINK = 'https://opencollective.com/assegai';
/* Database */
const DEFAULT_MYSQL_HOST = '127.0.0.1';
const DEFAULT_MYSQL_USER = 'root';
const DEFAULT_MYSQL_PORT = 3306;
const DEFAULT_MARIADB_HOST = '127.0.0.1';
const DEFAULT_MARIADB_USER = 'root';
const DEFAULT_MARIADB_PORT = 3306;
const DEFAULT_POSTGRES_HOST = '127.0.0.1';
const DEFAULT_POSTGRES_USER = 'postgres';
const DEFAULT_POSTGRES_PORT = 5432;
const DEFAULT_SQLITE_PATH = 'database.sqlite';
const DEFAULT_MSSQL_HOST = '127.0.0.1';
const DEFAULT_MSSQL_USER = 'sa';
const DEFAULT_MSSQL_PORT = 1433;
const BOOTSTRAP_FILE = 'bootstrap.php';
const DEFAULT_DATABASE_TYPE = null;
const DEFAULT_NAMESPACE = 'Assegai\App';

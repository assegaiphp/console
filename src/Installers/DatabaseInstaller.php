<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Core\Modules\ModuleDataSourceConfigurator;
use Assegai\Console\Util\ComposerManifest;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;

/**
 * Class DatabaseInstaller. Installs the database.
 *
 * @package
 */
class DatabaseInstaller extends AbstractInstaller
{
    /**
     * @var array<string, string[]> $requiredExtensions The required extensions for each database.
     */
    protected array $requiredExtensions = [
        'mysql' => ['intl', 'pdo_mysql', 'mysqli'],
        'postgresql' => ['intl', 'pdo_pgsql'],
        'sqlite' => ['intl', 'pdo_sqlite']
    ];

    /**
     * @var array<string> $supportedDatabase The supported databases.
     */
    protected array $supportedDatabase = [
        'mysql',
        'postgresql',
        'sqlite'
    ];

    /**
     * @inheritDoc
     */
    public function install(): int
    {
        if (!$this->shouldConfigureDatabases()) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Skipping database configuration...</comment>');
            $this->output->writeln('');
            return Command::SUCCESS;
        }

        $this->output->writeln('');
        $this->output->writeln(
            $this->formatter->formatBlock("Database Setup", 'question', true)
        );
        $this->output->writeln('');

        $databaseChoices = $this->selectDatabases();
        $configuredDatabaseNames = [];

        foreach ($databaseChoices as $database) {
            if ($missingExtensions = $this->checkForMissingExtensions($this->requiredExtensions[$database])) {
                $this->output->writeln($this->formatter->formatBlock('The following extensions are missing: ' . implode(', ', $missingExtensions), 'error', true));
                return Command::FAILURE;
            }

            $dbInstaller = $this->makeDatabaseInstaller($database);

            if (($statusCode = $dbInstaller->install()) > 0) {
                $this->output->writeln($this->formatter->formatBlock("Failed to install $database", 'error', true));
                return $statusCode;
            }

            if ($configuredDatabaseName = $dbInstaller->getConfiguredDatabaseName()) {
                $configuredDatabaseNames[] = $configuredDatabaseName;
            }
        }


        if (Command::SUCCESS !== $this->ensureDefaultUserResource()) {
            return Command::FAILURE;
        }

        if (Command::SUCCESS !== $this->installOrmPackage()) {
            return Command::FAILURE;
        }

        if (Command::SUCCESS !== $this->configureModuleDataSources($configuredDatabaseNames)) {
            return Command::FAILURE;
        }

        $this->output->writeln([
            '',
            "✔️  Database installation complete\n",
            ''
        ]);

        return Command::SUCCESS;
    }

    protected function shouldConfigureDatabases(): bool
    {
        return $this->prompts->confirm(
            'Would you like to add a database configuration?',
            true
        );
    }

    /**
     * @return string[]
     */
    protected function selectDatabases(): array
    {
        return $this->prompts->multiselect(
            'Which databases do you want to configure?',
            array_combine($this->supportedDatabase, $this->supportedDatabase) ?: [],
            [$this->supportedDatabase[0]]
        );
    }

    /**
     * Check for missing extensions.
     *
     * @param string[] $extensions The extensions to check for.
     *
     * @return string[] The missing extensions.
     */
    protected function checkForMissingExtensions(array $extensions): array
    {
        $missingExtensions = [];

        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        return $missingExtensions;
    }

    protected function makeDatabaseInstaller(string $database): AbstractInstaller
    {
        return match ($database) {
            'mysql' => new MySQLInstaller(
                $this->input,
                $this->output,
                $this->formatter,
                $this->questionHelper,
                $this->projectPath
            ),
            'postgresql' => new PostgreSQLInstaller(
                $this->input,
                $this->output,
                $this->formatter,
                $this->questionHelper,
                $this->projectPath
            ),
            default => new SQLiteInstaller(
                $this->input,
                $this->output,
                $this->formatter,
                $this->questionHelper,
                $this->projectPath
            ),
        };
    }

    protected function ensureDefaultUserResource(): int
    {
        if (file_exists(Path::join($this->projectPath, 'src', 'Users'))) {
            return Command::SUCCESS;
        }

        $userServiceName = $this->prompts->text(
            "What is the name of the users' resource?",
            'Users'
        );
        $command = $this->buildGenerateResourceCommand((string)$userServiceName);
        $statusCode = $this->runCommand($command);

        if ($statusCode === Command::SUCCESS) {
            return Command::SUCCESS;
        }

        $this->output->writeln([
            '',
            "<error>Failed to create resource, $userServiceName</error>",
            ''
        ]);

        return Command::FAILURE;
    }

    /**
     * Build the command used to generate the default users resource.
     */
    protected function buildGenerateResourceCommand(string $resourceName): string
    {
        $consoleBinary = $this->resolveConsoleBinaryCommand();

        return sprintf(
            'cd %s && %s --ansi generate resource %s',
            escapeshellarg($this->projectPath),
            $consoleBinary,
            escapeshellarg($resourceName)
        );
    }

    /**
     * Resolve the currently installed console binary so subprocesses use the same code path.
     */
    protected function resolveConsoleBinaryCommand(): string
    {
        $consoleBinaryPath = realpath(Path::join(dirname(__DIR__, 2), 'bin', 'assegai'));

        if (false === $consoleBinaryPath) {
            return 'assegai';
        }

        return sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($consoleBinaryPath));
    }

    /**
     * Run a shell command and return its exit status.
     */
    protected function runCommand(string $command): int
    {
        passthru($command, $statusCode);

        return $statusCode;
    }

    protected function installOrmPackage(): int
    {
        try {
            $composerConfig = ComposerManifest::load($this->projectPath);
            $composerConfig = ComposerManifest::ensureRequirement(
                $composerConfig,
                PACKAGE_NAME_ORM,
                RECOMMENDED_ORM_VERSION_CONSTRAINT
            );

            if (!ComposerManifest::save($this->projectPath, $composerConfig)) {
                throw new \RuntimeException('Failed to save composer.json');
            }
        } catch (\RuntimeException) {
            $this->output->writeln([
                '',
                '<error>Failed to add ORM to composer.json</error>',
                ''
            ]);
            return Command::FAILURE;
        }

        $this->output->writeln('<fg=bright-blue>UPDATE</> composer.json');

        return Command::SUCCESS;
    }

    /**
     * @param string[] $configuredDatabaseNames
     */
    protected function configureModuleDataSources(array $configuredDatabaseNames): int
    {
        $configuredDatabaseNames = array_values(array_unique(array_filter($configuredDatabaseNames)));

        if (empty($configuredDatabaseNames)) {
            return Command::SUCCESS;
        }

        $configurator = new ModuleDataSourceConfigurator(
            $this->input,
            $this->output,
            $this->questionHelper,
            $this->projectPath
        );

        foreach ($configuredDatabaseNames as $databaseName) {
            if (Command::SUCCESS !== $configurator->promptAndConfigure($databaseName)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}

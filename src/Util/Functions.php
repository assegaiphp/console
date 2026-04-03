<?php

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\AppConfig;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\select;

/**
 * Copies a directory recursively.
 *
 * @param string $source The source directory.
 * @param string $destination The destination directory.
 * @return bool True if the directory was copied successfully, false otherwise.
 */
function copy_directory(string $source, string $destination): bool
{
    $directory = dir($source);

    if (false === $directory) {
        return false;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    while (false !== ($entry = $directory->read())) {
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        if (is_dir($source . '/' . $entry)) {
            copy_directory($source . '/' . $entry, $destination . '/' . $entry);
            continue;
        }
        if (false === copy($source . '/' . $entry, $destination . '/' . $entry)) {
            return false;
        }
    }
    $directory->close();

    return true;
}

/**
 * Converts an array to a string.
 *
 * @param array<string, mixed> $array The array to convert.
 * @return false|string The string representation of the array.
 */
function array_to_string(array $array): false|string
{
    $output = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (false === $output) {
        return false;
    }

    $output = str_replace('{', '[', $output);
    $output = str_replace('}', ']', $output);
    return str_replace('":', '" =>', $output);
}

/**
 * Checks if a program is installed.
 *
 * @param string $programName The name of the program.
 * @return bool True if the program is installed, false otherwise.
 */
function is_installed(string $programName): bool
{
    $command = (PHP_OS_FAMILY === 'Windows') ? 'where' : 'which';
    $command = $command . ' ' . escapeshellarg($programName);
    return !empty(shell_exec($command));
}

if (!function_exists('format_bytes')) {
    /**
     * Formats bytes into a human-readable string.
     *
     * @param int $bytes The number of bytes.
     * @return string The formatted string.
     */
    function format_bytes(int $bytes): string
    {
        $units = ['bytes', 'kb', 'mb', 'gb', 'tb'];
        $unit = ' bytes';

        for ($exponent = 0; $exponent < count($units); $exponent++) {
            if ($bytes < 1024) {
                $unit = $units[$exponent];
                break;
            }
            $bytes /= 1024;
        }

        return round($bytes, 2) . " $unit";
    }
}

if (!function_exists('env')) {
    /**
     * Retrieve an environment value with an optional default.
     */
    function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}

if (!function_exists('update_module_file')) {
    /**
     * Updates the module file.
     *
     * @param array{use?: ?string[], declarations?: ?string[], imports?: ?string[], controllers?: ?string[], providers?: ?string[], exports?: ?string[], config?: ?string[]} $data The data to update the module file with.
     * @param string $filename The name of the module file to update. Defaults to 'AppModule'.
     * @param OutputInterface|null $output The output instance to write status messages to.
     * @return int Returns a status code.
     */
    function update_module_file(array $data, string $filename = 'AppModule', ?OutputInterface $output = null): int
    {
        $output ??= new ConsoleOutput(formatter: new OutputFormatter());
        $filename = preg_replace('/.php$/', '', $filename) ?? throw new RuntimeException("Failed to remove .php from $filename.");
        $filename = Path::join(getcwd() ?: '', 'src', $filename) . '.php';

        if (!file_exists($filename)) {
            $output->writeln("<error>File $filename does not exist.</error>");
            return Command::FAILURE;
        }

        # Read the file
        $contents = file_get_contents($filename) ?: throw new RuntimeException("Failed to read file $filename.");
        $originalBytes = strlen($contents);

        $contents = append_module_use_statements($contents, $data['use'] ?? []);

        $moduleAttributePattern = '/#\[Module\((?<body>[\s\S]*?)\)\]/';
        $moduleMatches = [];

        if (preg_match($moduleAttributePattern, $contents, $moduleMatches, PREG_OFFSET_CAPTURE) !== 1) {
            $output->writeln("<error>Failed to parse the Module attribute in $filename.</error>");
            return Command::FAILURE;
        }

        $moduleBody = $moduleMatches['body'][0];

        $modulePropertyNames = ['declarations', 'imports', 'controllers', 'providers', 'exports', 'config'];

        foreach ($modulePropertyNames as $propertyName) {
            if (!array_key_exists($propertyName, $data) || empty($data[$propertyName])) {
                continue;
            }

            $moduleBody = update_module_attribute_property(
                $moduleBody,
                $propertyName,
                $data[$propertyName] ?? [],
                $contents
            );
        }

        $contents = substr_replace(
            $contents,
            $moduleBody,
            $moduleMatches['body'][1],
            strlen($moduleMatches['body'][0])
        );

        # Write the file
        $bytes = file_put_contents($filename, $contents) ?: throw new RuntimeException("Failed to write file $filename.");

        $bytes = format_bytes(abs($bytes - $originalBytes));
        $relativeFilename = str_replace(Path::join((getcwd() ?: ''), 'src') . DIRECTORY_SEPARATOR, '', $filename);

        if ((int)$bytes > 0) {
            $output->writeln("<fg=blue>UPDATE</> $relativeFilename ($bytes)");
        }
        return Command::SUCCESS;
    }
}

if (!function_exists('append_module_use_statements')) {
    /**
     * Appends missing use statements while preserving the file's newline style.
     *
     * @param string[] $imports
     */
    function append_module_use_statements(string $contents, array $imports): string
    {
        $imports = array_values(array_filter($imports, fn(string $import): bool => $import !== ''));

        if (empty($imports)) {
            return $contents;
        }

        $missingImports = [];

        foreach ($imports as $import) {
            if (!str_contains($contents, "use $import;")) {
                $missingImports[] = $import;
            }
        }

        if (empty($missingImports)) {
            return $contents;
        }

        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $renderedImports = implode($newline, array_map(
            fn(string $import): string => "use $import;",
            $missingImports
        ));

        $useMatches = [];
        if (false !== preg_match_all('/^use\s+[^;]+;$/m', $contents, $useMatches, PREG_OFFSET_CAPTURE) && !empty($useMatches[0])) {
            $lastUse = end($useMatches[0]);

            if ($lastUse !== false) {
                return substr_replace(
                    $contents,
                    $newline . $renderedImports,
                    $lastUse[1] + strlen($lastUse[0]),
                    0
                );
            }
        }

        $namespaceMatch = [];
        if (preg_match('/^namespace\s+[^;]+;$/m', $contents, $namespaceMatch, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace(
                $contents,
                $newline . $renderedImports,
                $namespaceMatch[0][1] + strlen($namespaceMatch[0][0]),
                0
            );
        }

        return $renderedImports . $newline . $contents;
    }
}

if (!function_exists('update_module_attribute_property')) {
    /**
     * Updates or inserts a Module attribute property while preserving its formatting.
     *
     * @param string[] $newEntries
     */
    function update_module_attribute_property(
        string $moduleBody,
        string $propertyName,
        array  $newEntries,
        string $context
    ): string
    {
        $newEntries = array_values(array_filter($newEntries, fn(string $entry): bool => $entry !== ''));

        if (empty($newEntries)) {
            return $moduleBody;
        }

        $pattern = '/(?P<indent>^[ \t]*)' . preg_quote($propertyName, '/') . '(?P<afterName>\s*:\s*)\[(?P<body>.*?)\](?P<comma>,?)/ms';
        $matches = [];

        if (preg_match($pattern, $moduleBody, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $existingEntries = parse_module_attribute_entries($matches['body'][0]);
            $updatedEntries = $existingEntries;

            foreach ($newEntries as $entry) {
                if (!in_array($entry, $updatedEntries, true)) {
                    $updatedEntries[] = $entry;
                }
            }

            if ($updatedEntries === $existingEntries) {
                return $moduleBody;
            }

            $replacement = render_module_attribute_property(
                $propertyName,
                $matches['indent'][0],
                $matches['afterName'][0],
                $updatedEntries,
                $matches['comma'][0],
                $matches['body'][0],
                $context
            );

            return substr_replace(
                $moduleBody,
                $replacement,
                $matches[0][1],
                strlen($matches[0][0])
            );
        }

        return insert_module_attribute_property($moduleBody, $propertyName, $newEntries, $context);
    }
}

if (!function_exists('insert_module_attribute_property')) {
    /**
     * Inserts a missing Module attribute property using the module's existing style.
     *
     * @param string[] $entries
     */
    function insert_module_attribute_property(
        string $moduleBody,
        string $propertyName,
        array  $entries,
        string $context
    ): string
    {
        $entries = array_values(array_filter($entries, fn(string $entry): bool => $entry !== ''));

        if (empty($entries)) {
            return $moduleBody;
        }

        $newline = str_contains($context, "\r\n") ? "\r\n" : "\n";
        $format = detect_module_property_format($moduleBody, $context);
        $property = render_module_attribute_property(
            $propertyName,
            $format['indent'],
            $format['after_name'],
            $entries,
            $format['trailing_comma'],
            $format['sample_body'],
            $context
        );

        $trimmedBody = rtrim($moduleBody);
        $trailingWhitespace = substr($moduleBody, strlen($trimmedBody));

        if ($trimmedBody === '') {
            $leadingBreak = '';
            if (preg_match('/^\R/', $moduleBody, $leadingBreakMatch) === 1) {
                $leadingBreak = $leadingBreakMatch[0];
                $trailingWhitespace = substr($moduleBody, strlen($leadingBreak));
            }

            return $leadingBreak . $property . $trailingWhitespace;
        }

        if (!str_ends_with($trimmedBody, ',')) {
            $trimmedBody .= ',';
        }

        return $trimmedBody . $newline . $property . $trailingWhitespace;
    }
}

if (!function_exists('render_module_attribute_property')) {
    /**
     * Renders a Module attribute property using an existing property's layout as the sample.
     *
     * @param string[] $entries
     */
    function render_module_attribute_property(
        string $propertyName,
        string $indent,
        string $afterName,
        array  $entries,
        string $trailingComma,
        string $sampleBody,
        string $context
    ): string
    {
        $newline = str_contains($context, "\r\n") ? "\r\n" : "\n";

        if (str_contains($sampleBody, "\n") || str_contains($sampleBody, "\r")) {
            $entryIndent = detect_module_array_entry_indent($sampleBody, $indent, $context);
            $renderedEntries = implode($newline, array_map(
                fn(string $entry): string => $entryIndent . $entry . ',',
                $entries
            ));
            $body = $newline . $renderedEntries . $newline . $indent;

            return $indent . $propertyName . $afterName . '[' . $body . ']' . $trailingComma;
        }

        $leadingWhitespace = '';
        if (preg_match('/^\s*/', $sampleBody, $leadingWhitespaceMatch) === 1) {
            $leadingWhitespace = $leadingWhitespaceMatch[0];
        }

        $trailingWhitespace = '';
        if (preg_match('/\s*$/', $sampleBody, $trailingWhitespaceMatch) === 1) {
            $trailingWhitespace = $trailingWhitespaceMatch[0];
        }

        $separator = ', ';
        if (preg_match('/,(?<spacing>\s*)/', $sampleBody, $separatorMatch) === 1) {
            $separator = ',' . $separatorMatch['spacing'];
        } elseif (str_contains($sampleBody, ',')) {
            $separator = ',';
        }

        $body = $leadingWhitespace . implode($separator, $entries) . $trailingWhitespace;

        return $indent . $propertyName . $afterName . '[' . $body . ']' . $trailingComma;
    }
}

if (!function_exists('parse_module_attribute_entries')) {
    /**
     * @return string[]
     */
    function parse_module_attribute_entries(string $body): array
    {
        $trimmedBody = trim($body);

        if ($trimmedBody === '') {
            return [];
        }

        $entries = array_map('trim', explode(',', $trimmedBody));

        return array_values(array_filter($entries, fn(string $entry): bool => $entry !== ''));
    }
}

if (!function_exists('detect_module_property_format')) {
    /**
     * @return array{indent: string, after_name: string, sample_body: string, trailing_comma: string}
     */
    function detect_module_property_format(string $moduleBody, string $context): array
    {
        $matches = [];
        $pattern = '/(?P<indent>^[ \t]*)(?:declarations|imports|controllers|providers|exports|config)(?P<after_name>\s*:\s*)\[(?P<body>.*?)\](?P<comma>,?)/ms';

        if (preg_match($pattern, $moduleBody, $matches) === 1) {
            $trimmedModuleBody = rtrim($moduleBody);

            return [
                'indent' => $matches['indent'],
                'after_name' => $matches['after_name'],
                'sample_body' => $matches['body'],
                'trailing_comma' => str_ends_with($trimmedModuleBody, ',') ? ',' : '',
            ];
        }

        return [
            'indent' => detect_indent_unit($context),
            'after_name' => ': ',
            'sample_body' => '',
            'trailing_comma' => '',
        ];
    }
}

if (!function_exists('detect_module_array_entry_indent')) {
    /**
     * Detect the indentation used for array entries inside a Module property.
     */
    function detect_module_array_entry_indent(string $sampleBody, string $propertyIndent, string $context): string
    {
        $matches = [];

        if (preg_match('/\R([ \t]*)\S/', $sampleBody, $matches) === 1) {
            return $matches[1];
        }

        return $propertyIndent . detect_indent_unit($context, $propertyIndent);
    }
}

if (!function_exists('detect_indent_unit')) {
    /**
     * Detect the prevailing indentation unit in a document.
     */
    function detect_indent_unit(string $context, string $referenceIndent = ''): string
    {
        if ($referenceIndent !== '') {
            if (str_starts_with($referenceIndent, "\t")) {
                return "\t";
            }

            if (str_starts_with($referenceIndent, ' ')) {
                return str_repeat(' ', max(1, greatest_common_divisor(strlen($referenceIndent), 8)));
            }
        }

        $matches = [];

        if (false === preg_match_all('/^(?<indent>[ \t]+)\S/m', $context, $matches) || empty($matches['indent'])) {
            return '  ';
        }

        foreach ($matches['indent'] as $indent) {
            if (str_starts_with($indent, "\t")) {
                return "\t";
            }
        }

        $lengths = array_values(array_unique(array_map('strlen', $matches['indent'])));

        $unitLength = (int) array_shift($lengths);

        foreach ($lengths as $length) {
            $unitLength = greatest_common_divisor($unitLength, $length);
        }

        return str_repeat(' ', max(1, $unitLength));
    }
}

if (!function_exists('greatest_common_divisor')) {
    /**
     * Calculates the greatest common divisor between two integers.
     */
    function greatest_common_divisor(int $left, int $right): int
    {
        while ($right !== 0) {
            $remainder = $left % $right;
            $left = $right;
            $right = $remainder;
        }

        return abs($left);
    }
}

if (!function_exists('get_datasource_type')) {
    function get_datasource_type(InputInterface $input, OutputInterface $output, string $optionName = 'database_type'): string|false
    {
        return match (true) {
            $input->hasOption(DatabaseType::MYSQL->value) && $input->getOption(DatabaseType::MYSQL->value) => DatabaseType::MYSQL->value,
            $input->hasOption(DatabaseType::POSTGRESQL->value) && $input->getOption(DatabaseType::POSTGRESQL->value) => DatabaseType::POSTGRESQL->value,
            $input->hasOption(DatabaseType::SQLITE->value) && $input->getOption(DatabaseType::SQLITE->value) => DatabaseType::SQLITE->value,
            default => $input->getOption($optionName) ?? select("Which type of data source do you want to use?", DatabaseType::toArray())
        };
    }
}

if (!function_exists('get_datasource_name')) {
    function get_datasource_name(InputInterface $input, OutputInterface $output, string $datasourceType, string $optionName = ParameterKey::DB_NAME->value): string|false
    {
        $appConfig = new AppConfig($input, $output);

        if ($appConfig->load() !== Command::SUCCESS) {
            $output->writeln('<error>Failed to load the configuration file</error>');
            return false;
        }

        /** @var array<string, array<string, mixed>> $dataSources */
        $dataSources = $appConfig->get("databases.$datasourceType", []);
        $dataSourceChoices = array_keys($dataSources);

        if (!$dataSourceChoices) {
            $output->writeln("<error>No $datasourceType databases found</error>");
            return false;
        }

        $dataSourceName = '';

        if ($input->hasArgument($optionName)) {
            $dataSourceName = $input->getArgument($optionName);
        };

        if ($input->hasOption($optionName)) {
            $dataSourceName = $input->getOption($optionName);
        }

        if (!$dataSourceName) {
            $dataSourceName = select("Which $datasourceType data source do you want to use? ", $dataSourceChoices);
        }

        if (!$dataSourceName) {
            $output->writeln("<error>Invalid database name</error>\n");
            return false;
        }

        return $dataSourceName;
    }
}

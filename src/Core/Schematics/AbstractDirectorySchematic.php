<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Traits\NamespaceReflectivityTrait;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractDirectorySchematic. This class is a base class for all directory schematics.
 *
 * @package Assegai\Console\Core\Schematics
 */
abstract class AbstractDirectorySchematic implements SchematicInterface
{
    use NamespaceReflectivityTrait;

    /**
     * The namespace of the class
     *
     * @var string
     */
    protected string $namespace = 'Assegai\\App';
    /**
     * The namespace suffix of the class
     *
     * @var string
     */
    protected string $namespaceSuffix = '';
    /**
     * The name of the directory
     *
     * @var string
     */
    protected string $directoryName = '';
    /**
     * The structure of the directory
     *
     * @var array<string, string|array<string, mixed>> $structure
     */
    protected array $structure = [];
    /**
     * The output of the directory
     *
     * @var array<string, string> $outputDirectory
     */
    protected array $outputDirectory = [];
    /**
     * The name text
     *
     * @var Text $nameText
     */
    protected Text $nameText;
    /**
     * The singular text
     *
     * @var Text $singularName
     */
    protected Text $singularName;
    /**
     * The plural text
     *
     * @var Text $pluralName
     */
    protected Text $pluralName;
    /**
     * @var int The total number of writes
     */
    protected int $totalWrites = 0;
    /**
     * @var Inspector The workspace inspector
     */
    protected Inspector $inspector;

    /**
     * AbstractDirectorySchematic constructor.
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     * @param string $name The name of the schematic
     * @param string $path The path to the directory
     */
    public final function __construct(
        protected InputInterface  $input,
        protected OutputInterface $output,
        protected string          $name,
        protected string          $path,
        protected string          $subdirectory = '',
        protected bool            $isFlat = false,
        protected string          $prefix = '',
        protected string          $suffix = '',
    )
    {
        $this->nameText = new Text($this->name);
        $this->singularName = new Text($this->nameText->getSingularForm());
        $this->pluralName = new Text($this->singularName->getPluralForm());
        $this->directoryName = $this->nameText->pascalCase();
        $this->inspector = new Inspector($this->input, $this->output);
        $this->configure();
    }

    /**
     * @inheritDoc
     */
    public function configure(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public function prepareBuild(): int
    {
        // Override this method to perform any necessary operations before the build
        return Command::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public function build(): int
    {
        $this->loadNamespaceFromConfig();

        $outputStructure = $this->scaffold($this->structure);
        $outputStructure = $this->resolvePathNames($outputStructure);
        $outputStructure = $this->resolveContent($outputStructure);

        $this->totalWrites = 0;
        if (!$this->writeFiles($this->getRootDirectoryPath(), $outputStructure)) {
            $this->output->writeln(sprintf('<error>Failed to write output for %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        if ($this->totalWrites === 0) {
            $this->output->writeln('<comment>Nothing to do!</comment>');
        }

        return Command::SUCCESS;
    }

    /**
     * Scaffold the directory. This method should create the directory if it does not exist as well as any
     * subdirectories and files.
     *
     * @param array<string, array<string, mixed>|string> $structure The structure of the directory
     * @return array<string, array<string, mixed>|string> Returns the structure of the directory if it was scaffolded successfully, false otherwise
     */
    private function scaffold(array $structure): array
    {
        $output = [];

        foreach ($structure as $name => $value) {
            $output[$name] = $value;
        }

        return $output;
    }

    /**
     * Resolves all the directory and file names in the path
     *
     * @param array<string, array<string, mixed>|string> $structure The structure of the directory
     * @return array<string, array<string, mixed>|string> Returns true if the path names were resolved successfully, false otherwise
     */
    private function resolvePathNames(array $structure): array
    {
        $output = [];

        foreach ($structure as $name => $value) {
            $path = str_replace('__NAME__', $this->nameText->pascalCase(), $name);
            $path = str_replace('__SINGULAR_LC__', strtolower($this->singularName->pascalCase()), $path);
            $path = str_replace('__SINGULAR_CAMEL__', $this->singularName->camelCase(), $path);
            $path = str_replace('__SINGULAR__', $this->singularName->pascalCase(), $path);
            $path = str_replace('__PLURAL_LC__', strtolower($this->pluralName->pascalCase()), $path);
            $path = str_replace('__PLURAL__', $this->pluralName->pascalCase(), $path);

            $output[$path] = is_array($value) ? $this->resolvePathNames($value) : $value;
        }

        return $output;
    }

    /**
     * Resolves the content of the directory. This method performs any necessary operations to generate the content of
     * the directory.
     *
     * @param array<string, array<string, mixed>|string> $structure The structure of the directory
     * @return array<string, array<string, mixed>|string> Returns true if the content was resolved successfully, false otherwise
     */
    private function resolveContent(array $structure): array
    {
        $output = [];

        foreach ($structure as $name => $value) {
            $content = $value;
            if (is_string($content)) {
                $content = str_replace(DEFAULT_NAMESPACE, $this->namespace, $content);
                $content = str_replace('__NAME__', $this->nameText->pascalCase(), $content);
                $content = str_replace('__KEBAB__', $this->nameText->kebabCase(), $content);
                $content = str_replace('__CAMEL__', $this->nameText->camelCase(), $content);
                $content = str_replace('__SINGULAR_LC__', strtolower($this->singularName->pascalCase()), $content);
                $content = str_replace('__SINGULAR_CAMEL__', $this->singularName->camelCase(), $content);
                $content = str_replace('__SINGULAR__', $this->singularName->pascalCase(), $content);
                $content = str_replace('__PLURAL_KEBAB__', $this->pluralName->kebabCase(), $content);
                $content = str_replace('__PLURAL_LC__', strtolower($this->pluralName->pascalCase()), $content);
                $content = str_replace('__PLURAL__', $this->pluralName->pascalCase(), $content);
            }

            $output[$name] = is_array($content) ? $this->resolveContent($content) : $content;
        }

        return $output;
    }

    /**
     * Write the output of the directory
     *
     * @param string $workingDirectory The working directory
     * @param array<string, array<string, mixed>|string> $directoryStructure The directory structure
     * @return bool Returns true if the output was written successfully, false otherwise
     */
    private function writeFiles(string $workingDirectory, array $directoryStructure): bool
    {
        if (!file_exists($workingDirectory)) {
            if (false === mkdir($workingDirectory, 0755, true)) {
                $this->output->writeln("<error>Failed creating directory $workingDirectory</error>");
                return false;
            }
        }

        foreach ($directoryStructure as $name => $content) {
            $path = Path::join($workingDirectory, $name);
            if (is_array($content)) {
                if (!$this->writeFiles($path, $content)) {
                    $this->output->writeln("<error>Failed creating directory $path</error>");
                    return false;
                }
            }

            if (is_string($content) && !file_exists($path)) {
                $bytes = file_put_contents($path, $content);
                if (false === $bytes) {
                    $this->output->writeln("<error>Failed creating file $path</error>");
                    return false;
                }
                $this->totalWrites++;

                $bytes = format_bytes($bytes);

                $filename = str_replace(Path::join($this->path, 'src') . DIRECTORY_SEPARATOR, '', $path);
                $this->output->writeln("<info>CREATE</info> $filename ($bytes)");
            }
        }

        return true;
    }

    /**
     * Get the root directory path
     *
     * @return string Returns the root directory path
     */
    protected function getRootDirectoryPath(): string
    {
        $segments = array_merge([$this->path, 'src'], $this->getSubdirectorySegments());

        if ($this->shouldNestGeneratedDirectory()) {
            $segments[] = $this->directoryName;
        }

        return Path::join(...$segments);
    }

    /**
     * @return string[]
     */
    private function getSubdirectorySegments(): array
    {
        if (!$this->subdirectory) {
            return [];
        }

        $segments = array_filter(explode('/', $this->subdirectory));

        return array_map(
            fn(string $segment): string => (new Text($segment))->pascalCase(),
            $segments
        );
    }

    protected function shouldNestGeneratedDirectory(): bool
    {
        return ! $this->isFlat;
    }

    protected function getGeneratedNamespace(?string $suffix = null): string
    {
        $parts = [trim($this->namespace, '\\')];

        if ($this->shouldNestGeneratedDirectory()) {
            $parts[] = $this->nameText->pascalCase();
        }

        if (is_string($suffix) && trim($suffix, '\\') !== '') {
            $parts[] = trim($suffix, '\\');
        }

        return implode('\\', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }
    /**
     * @inheritDoc
     */
    public function finalizeBuild(): int
    {
        if (
            !$this->inspector->isValidWorkspace(getcwd() ?: '') ||
            !$this->hasModuleUpdates($this->getModuleUpdates())
        ) {
            return Command::SUCCESS;
        }

        $moduleFilename = $this->getParentModuleFilename() ?: 'AppModule';

        return update_module_file($this->getModuleUpdates(), $moduleFilename, $this->output);
    }

    /**
     * Determine whether there is anything to write back to a module file.
     *
     * @param array<string, string[]> $updates
     */
    private function hasModuleUpdates(array $updates): bool
    {
        foreach ($updates as $entries) {
            if (!empty($entries)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the module updates for the generated directory schematic.
     *
     * @return array{use: string[], declarations: string[], providers: string[], controllers: string[], imports: string[], exports: string[], config: string[]}
     */
    protected function getModuleUpdates(): array
    {
        return [
            'use' => [],
            'declarations' => [],
            'providers' => [],
            'controllers' => [],
            'imports' => [],
            'exports' => [],
            'config' => [],
        ];
    }

    /**
     * Get the parent module filename relative to src/.
     */
    private function getParentModuleFilename(): false|string
    {
        $segments = $this->getSubdirectorySegments();

        if (empty($segments)) {
            return false;
        }

        $workingDirectory = Path::join(...array_merge([$this->path, 'src'], $segments));
        if (!is_dir($workingDirectory)) {
            return false;
        }

        $localFiles = scandir($workingDirectory);

        if (false === $localFiles) {
            $this->output->writeln("<error>Failed to scan the directory: $workingDirectory</error>");
            return false;
        }

        $generatedModuleFilename = $this->nameText->pascalCase() . 'Module.php';
        $candidateModules = array_values(array_filter(
            $localFiles,
            static fn(string $file): bool => $file !== $generatedModuleFilename && str_ends_with($file, 'Module.php')
        ));

        if (empty($candidateModules)) {
            return false;
        }

        $preferredParentModuleFilename = $segments[array_key_last($segments)] . 'Module.php';

        if (in_array($preferredParentModuleFilename, $candidateModules, true)) {
            return Path::join(...array_merge($segments, [$preferredParentModuleFilename]));
        }

        return Path::join(...array_merge($segments, [$candidateModules[0]]));
    }

    /**
     * @inheritDoc
     */
    public function prepareTearDown(): int
    {
        // Override this method to perform any necessary operations before the teardown
        return Command::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): int
    {
        if (false === unlink($this->path)) {
            $this->output->writeln(sprintf('<error>Failed to delete %s</error>', $this->path));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public function finalizeTearDown(): int
    {
        // Override this method to perform any necessary operations after the teardown
        return Command::SUCCESS;
    }

    /**
     * Resolve the namespace suffix for the generated directory.
     *
     * Directory schematics only append parent segments here because their templates
     * already include the generated directory name in the namespace declaration.
     */
    protected function getResolvedNamespaceSuffix(): string
    {
        $namespaceSuffix = '';

        foreach ($this->getSubdirectorySegments() as $segment) {
            $namespaceSuffix .= '\\' . $segment;
        }

        return $namespaceSuffix;
    }
}

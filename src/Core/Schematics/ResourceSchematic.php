<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Path;
use Override;
use Symfony\Component\Console\Command\Command;

/**
 * A resource schematic is a directory schematic that contains
 * resources for the application.
 */
class ResourceSchematic extends AbstractDirectorySchematic
{
  public function configure(): void
  {
    $this->structure = [
      'DTOs' => [
        'Create__SINGULAR__DTO.php' => $this->getTemplateContent('dto', 'Create'),
        'Update__SINGULAR__DTO.php' => $this->getTemplateContent('dto', 'Update'),
      ],
      'Entities' => [
        '__SINGULAR__Entity.php' => $this->getTemplateContent('entity', '__SINGULAR__'),
      ],
      '__NAME__Controller.php' => $this->getTemplateContent('controller', '__NAME__'),
      '__NAME__Module.php' => $this->getTemplateContent('module', '__NAME__'),
      '__NAME__Service.php' => $this->getTemplateContent('service', '__NAME__'),
    ];
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function finalizeBuild(): int
  {
    $moduleFilename = 'AppModule.php';
    $absolutePath = Path::join($this->path, 'src', $moduleFilename);

    # Get the AppModule.php file
    if (! file_exists($absolutePath) ) {
      $this->output->writeln("<error>$moduleFilename not found.</error>");
      return Command::FAILURE;
    }
    $appModuleFileContent = file_get_contents($absolutePath);

    if (false === $appModuleFileContent) {
      $this->output->writeln("<error>Failed to read $moduleFilename.</error>");
      return Command::FAILURE;
    }

    # Update the use statements
    $useStatement = "use $this->namespace\\{$this->nameText->pascalCase()}\\{$this->nameText->pascalCase()}Module;";

    if (! str_contains($appModuleFileContent, $useStatement)) {
      $appModuleFileContent = preg_replace(
        '/(use .+;\n)(\n+#\[)/',
        "$1$useStatement\n$2",
        $appModuleFileContent
      ) ?? '';
    }

    if (! is_string($appModuleFileContent) ) {
      $appModuleFileContent = '';
    }

    # Update the module imports list
    $className = "{$this->nameText->pascalCase()}Module::class";

    if (! str_contains($appModuleFileContent, $className) ) {
      # Get imports list
      if (str_contains($appModuleFileContent, 'imports: []')) {
        $appModuleFileContent = str_replace(
          'imports: []',
          "imports: [$className]",
          $appModuleFileContent
        );
      } else {
        $matches = [];
        $importPattern = '/imports: \[([\w:\s,]*)]/';
        $totalMatches = preg_match_all($importPattern, $appModuleFileContent, $matches);

        if ($totalMatches !== false && count($matches) > 1) {
          $imports = $matches[1][0] ?? '';
          $multiline = str_contains($imports, "\n");
          $separator = $multiline ? "\n    " : " ";
          $imports = trim($imports, $separator);
          $imports = preg_split('/,\s*/', $imports) ?: [];
          foreach ($imports as $index => $import) {
            $imports[$index] = trim($import, " \n\r\t\v\0,");
          }

          $imports[] = "{$this->nameText->pascalCase()}Module::class";
          $imports = array_unique($imports);
          $imports = array_filter($imports, fn($import) => !empty($import));
          sort($imports);

          $separator = $multiline ? ",\n    " : ", ";
          $imports = implode($separator, $imports);
          if ($multiline) {
            if (! str_ends_with($imports, ',')) {
              $imports .= ',';
            }
          } else {
            if (str_ends_with($imports, ',')) {
              $imports = substr($imports, 0, -1);
            }
          }

          $replacement = $multiline ? "imports: [\n    $imports\n  ]" : "imports: [$imports]";
          $appModuleFileContent = preg_replace(
            $importPattern,
            $replacement,
            $appModuleFileContent
          );
        }
      }

      $bytes = file_put_contents($absolutePath, $appModuleFileContent);

      if (false === $bytes) {
        $this->output->writeln("<error>Failed to update $moduleFilename.</error>");
        return Command::FAILURE;
      }

      $bytes = format_bytes($bytes);
      $this->output->writeln("<fg=blue>UPDATE</> $moduleFilename ($bytes)");
    }

    return Command::SUCCESS;
  }

  /**
   * Gets the content of a template.
   *
   * @param string $templateType The type of the template.
   * @param string $prefix The prefix to use.
   * @return string The content of the template.
   */
  private function getTemplateContent(string $templateType, string $prefix = ''): string
  {
    $namespace = match ($templateType) {
      'dto' => "$this->namespace\__NAME__\DTOs",
      'entity' => "$this->namespace\__NAME__\Entities",
      default => "$this->namespace\__NAME__"
    };

    $output = <<<PHP
<?php

namespace $namespace;

PHP;

    $output .= match ($templateType) {
      'dto' => <<<PHP

use Assegai\Core\Attributes\Injectable;

#[Injectable]
class {$prefix}__SINGULAR__DTO
{
}
PHP,
      'entity' => <<<PHP

use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Traits\ChangeRecorderTrait;

#[Entity(table: '__KEBAB__')]
class __SINGULAR__Entity
{
  use ChangeRecorderTrait;
  
  #[PrimaryGeneratedColumn]
  public int \$id = 0;
}
PHP,
      'controller' => <<<PHP

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Param;
use $this->namespace\\$prefix\DTOs\Create__SINGULAR__DTO;
use $this->namespace\\$prefix\DTOs\Update__SINGULAR__DTO;

#[Controller('__KEBAB__')]
readonly class {$prefix}Controller
{
  /**
   * {$prefix}Controller constructor.
   * 
   * @param {$prefix}Service \$__CAMEL__Service
   */
  public function __construct(private {$prefix}Service \$__CAMEL__Service)
  {
  }
  
  /**
   * Finds all __NAME__.
   * 
   * @return string
   */
  #[Get]
  public function findAll(): string
  {
    return \$this->__CAMEL__Service->findAll();  
  }
  
  /**
   * Finds __NAME__ by ID. 
   * 
   * @param int \$id
   * @return string
   */
  #[Get(':id')]
  public function findById(#[Param('id')] int \$id): string
  {
    return \$this->__CAMEL__Service->findById(\$id);
  }
  
  /**
   * Creates a new __SINGULAR__.
   * 
   * @param Create__SINGULAR__Dto \$create__SINGULAR__Dto 
   * @return string
   */
  #[Post]
  public function create(#[Body] Create__SINGULAR__Dto \$create__SINGULAR__Dto): string
  {
    return \$this->__CAMEL__Service->create(\$create__SINGULAR__Dto);
  }
  
  /**
   * Updates __SINGULAR__ by ID.
   * 
   * @param int \$id
   * @param Update__SINGULAR__Dto \$update__SINGULAR__Dto
   * @return string
   */
  #[Put(':id')]
  public function updateById(
    #[Param('id')] int \$id,
    #[Body] Update__SINGULAR__Dto \$update__SINGULAR__Dto
  ): string
  {
    return \$this->__CAMEL__Service->updateById(\$id, \$update__SINGULAR__Dto);
  }
  
  /**
   * Deletes __SINGULAR__ by ID.
   * 
   * @param int \$id
   * @return string
   */
  #[Delete(':id')]
  public function deleteById(#[Param('id')] int \$id): string
  {
    return \$this->__CAMEL__Service->deleteById(\$id);
  }
}
PHP,
      'service' => <<<PHP

use Assegai\Core\Attributes\Injectable;
use $this->namespace\__NAME__\DTOs\Create__SINGULAR__Dto;
use $this->namespace\__NAME__\DTOs\Update__SINGULAR__Dto;

#[Injectable]
class __NAME__Service
{
  /**
   * Finds all __NAME__.
   * 
   * @return string
   */
  public function findAll(): string
  {
    return 'This action returns all __NAME__!';
  }
  
  /**
   * Finds __NAME__ by ID. 
   * 
   * @param int \$id
   * @return string
   */
  public function findById(int \$id): string
  {
    return "This action returns #\$id __SINGULAR__!";
  }
  
  /**
   * Creates a new __SINGULAR__.
   * 
   * @param Create__SINGULAR__Dto \$create__SINGULAR__Dto
   * @return string
   */
  public function create(Create__SINGULAR__Dto \$create__SINGULAR__Dto): string
  {
    return 'This action creates a new __SINGULAR__!';
  }

  /**
   * Updates a __SINGULAR__.
   * 
   * @parm int \$id
   * @param Update__SINGULAR__Dto \$update__SINGULAR__Dto
   * @return string
   */
  public function updateById(int \$id, Update__SINGULAR__Dto \$update__SINGULAR__Dto): string
  {
    return "This action updates #\$id __SINGULAR__!";
  }
  
  /**
   * Removes a(n) __SINGULAR__.
   * 
   * @return string
   */
  public function deleteById(int \$id): string
  {
    return "This action deletes #\$id __SINGULAR__!";
  }
}
PHP,
      'module' => <<<PHP

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [__NAME__Service::class],
  controllers: [__NAME__Controller::class]
)]
class __NAME__Module
{
}
PHP,
      default => ''
    };

    return $output;
  }
}
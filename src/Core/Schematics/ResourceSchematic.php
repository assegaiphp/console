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
    $moduleName = '\\' . $this->nameText->pascalCase() . '\\' . $this->nameText->pascalCase() . 'Module';
    return update_module_file([
      'use' => [$this->namespace . $moduleName],
      'imports' => ["{$this->nameText->pascalCase()}Module::class"],
    ]);
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
    return 'This action returns all __KEBAB__!';
  }
  
  /**
   * Finds __NAME__ by ID. 
   * 
   * @param int \$id
   * @return string
   */
  public function findById(int \$id): string
  {
    return "This action returns the #\$id __SINGULAR_LC__!";
  }
  
  /**
   * Creates a new __SINGULAR__.
   * 
   * @param Create__SINGULAR__Dto \$create__SINGULAR__Dto
   * @return string
   */
  public function create(Create__SINGULAR__Dto \$create__SINGULAR__Dto): string
  {
    return 'This action creates a new __SINGULAR_LC__!';
  }

  /**
   * Updates a __SINGULAR__.
   * 
   * @param int \$id
   * @param Update__SINGULAR__Dto \$update__SINGULAR__Dto
   * @return string
   */
  public function updateById(int \$id, Update__SINGULAR__Dto \$update__SINGULAR__Dto): string
  {
    return "This action updates the #\$id __SINGULAR_LC__!";
  }
  
  /**
   * Removes a(n) __SINGULAR__.
   * 
   * @param int \$id
   * @return string
   */
  public function deleteById(int \$id): string
  {
    return "This action deletes #\$id __SINGULAR_LC__!";
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
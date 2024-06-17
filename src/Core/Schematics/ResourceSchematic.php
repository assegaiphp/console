<?php

namespace Assegai\Console\Core\Schematics;

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
        'Create__SINGULAR__DTO.php' => $this->getTemplateContent('dto', '__SINGULAR__'),
        'Update__SINGULAR__DTO.php' => $this->getTemplateContent('dto', '__SINGULAR__'),
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
   * Gets the content of a template.
   *
   * @param string $templateType The type of the template.
   * @param string $prefix The prefix to use.
   * @return string The content of the template.
   */
  private function getTemplateContent(string $templateType, string $prefix = ''): string
  {
    $output = <<<PHP
<?php

namespace $this->namespace;
PHP;

    $output .= match ($templateType) {
      'controller' => <<<PHP

use Assegai\Core\Controller;

#[Controller('__NAME__')]
class __NAME__Controller
{
  public function __construct(private __NAME__Service \$__NAME__service)
  {
  }
  
  #[Get]
  public function findAll(): string
  {
    return $this->__NAME__Service->findAll();  
  }
}
PHP,
      default => ''
    };

    return $output;
  }
}
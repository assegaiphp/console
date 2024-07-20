<?php

namespace Assegai\Console\Core\Schematics;

use Symfony\Component\Console\Command\Command;

/**
 * Class PageSchematic. This class is used to create a page schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
class PageSchematic extends AbstractDirectorySchematic
{
  /**
   * @var string The prefix of the class
   */
  protected string $prefix = '';

  public function configure(): void
  {
    $this->structure = [
      '__NAME__Component.css' => "/* __NAME__Component.css */\n",
      '__NAME__Component.php' => $this->getComponentTemplateContent(),
      '__NAME__Component.twig' => "<p>{{ name }} works!</p>",
      '__NAME__Controller.php' => $this->getControllerTemplateContent(),
      '__NAME__Module.php' => $this->getModuleTemplateContent(),
      '__NAME__Service.php' => $this->getServiceTemplateContent(),
    ];
  }

  /**
   * Returns the content of the component template
   *
   * @return string The content of the component template
   */
  public function getComponentTemplateContent(): string
  {
    return <<<PHP
<?php

namespace $this->namespace\__NAME__;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\AssegaiComponent;

#[Component(
  selector: '__KEBAB__',
  templateUrl: './__NAME__Component.twig',
  styleUrls: ['./__NAME__Component.css']
)]
class __NAME__Component extends AssegaiComponent
{
  public string \$name = '__KEBAB__';
}
PHP;
  }

  /**
   * Returns the content of the controller template
   *
   * @return string The content of the controller template
   */
  public function getControllerTemplateContent(): string
  {
    return <<<PHP
<?php

namespace $this->namespace\__NAME__;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Exceptions\Container\ContainerException;
use ReflectionException;

#[Controller('__KEBAB__')]
readonly class __NAME__Controller
{
  public function __construct(private __NAME__Service \$__CAMEL__Service)
  {
  }
  
  /**
   * @throws ReflectionException
   * @throws ContainerException
   */
  #[Get]
  public function get__NAME__Page(): ComponentInterface
  {
    return \$this->__CAMEL__Service->get__NAME__Page();
  }
}
PHP;
  }

  /**
   * Returns the content of the module template
   *
   * @return string The content of the module template
   */
  public function getModuleTemplateContent(): string
  {
    return <<<PHP
<?php

namespace $this->namespace\__NAME__;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  declarations: [__NAME__Component::class],
  providers: [__NAME__Service::class],
  controllers: [__NAME__Controller::class],
)]
readonly class __NAME__Module
{}
PHP;
  }

  /**
   * Returns the content of the service template
   *
   * @return string The content of the service template
   */
  public function getServiceTemplateContent(): string
  {
    return <<<PHP
<?php

namespace $this->namespace\__NAME__;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Exceptions\Container\ContainerException;
use ReflectionException;

#[Injectable]
class __NAME__Service
{
  /**
   * @throws ReflectionException
   * @throws ContainerException
   */
  public function get__NAME__Page(): ComponentInterface
  {
    return render(__NAME__Component::class);
  }
}
PHP;
  }

  /**
   * @inheritDoc
   */
  public function finalizeBuild(): int
  {
    $moduleName = '\\' . $this->nameText->pascalCase() . '\\' . $this->nameText->pascalCase() . 'Module';
    return update_module_file([
      'use' => [$this->namespace . $moduleName],
      'imports' => ["{$this->nameText->pascalCase()}Module::class"],
    ]);
  }
}
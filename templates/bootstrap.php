<?php

use Assegai\Core\AssegaiFactory;
use Assegai\App\AppModule;

require __DIR__ . '/vendor/autoload.php';

/**
 * Bootstraps the application.
 *
 * @return void
 */
function bootstrap(): void
{
  $workingDirectory = getenv('ASSEGAI_WORKING_DIR');

  if (!is_string($workingDirectory) || trim($workingDirectory) === '') {
    $workingDirectory = __DIR__;
  }

  $app = AssegaiFactory::createFromProject(AppModule::class, $workingDirectory);
  $app->run();
}

bootstrap();

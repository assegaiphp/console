<?php

namespace Assegai\App;

use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [
    ProjectConfig::class, // This should always be imported before AppService::class
    AppService::class
  ],
  controllers: [AppController::class],
  imports: []
)]
class AppModule
{
}
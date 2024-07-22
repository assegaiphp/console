<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;

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
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [AppService::class],
  controllers: [AppController::class],
  imports: []
)]
class AppModule
{
}
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Rendering\View;

#[Controller(path: '')]
class AppController
{
  public function __construct(protected AppService $appService)
  {
  }

  public function home()
  {
    return $this->appService->home();
  }
}
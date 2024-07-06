<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Rendering\View;

/**
 * The controller for the app.
 *
 * @package Assegai\App
 */
#[Controller(path: '')]
class AppController
{
  public function __construct(protected AppService $appService)
  {
  }

  /**
   * The home page.
   *
   * @return View The home page view.
   */
  #[Get]
  public function home(): View
  {
    return $this->appService->home();
  }
}
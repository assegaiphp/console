<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\View;

#[Injectable]
class AppService
{
  /**
   * The constructor.
   *
   * @param ProjectConfig $config The project configuration.
   */
  public function __construct(protected ProjectConfig $config)
  {
  }

  /**
   * The home page.
   *
   * @return View The home page view.
   * @throws RenderingException
   */
  public function home(): View
  {
    $name = $this->config->get('name') ?? 'Your app';

    return view('index', [
      'title' => 'Muli Bwanji',
      'titleNote' => 'Hello in Chichewa',
      'projectName' => $name,
      'status' => "$name is running.",
      'summary' => 'Your AssegaiPHP project is ready for modules, controllers, services, DTOs, and everything else you want to wire together next.',
      'websiteLink' => Config::get('contact')['links']['assegai_website'],
      'guideLink' => Config::get('contact')['links']['guide_link'],
      'supportLink' => Config::get('contact')['links']['support_link'],
    ]);
  }
}

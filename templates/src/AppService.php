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
      'subtitle' => "Congratulations! $name is running. 🥳🎉🥳",
      'welcomeLink' => Config::get('contact')['links']['assegai_website'],
      'getStartedLink' => Config::get('contact')['links']['guide_link'],
      'documentationLink' => Config::get('contact')['links']['documentation_link'],
      'donateLink' => Config::get('contact')['links']['support_link'],
    ]);
  }
}
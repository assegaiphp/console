<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Rendering\View;

#[Injectable]
class AppService
{
  public function home(): View
  {
    return view('index', [
      'title' => 'Muli Bwanji',
      'subtitle' => '&ndash;&ndash; AssegaiPHP &ndash;&ndash;>',
      'welcomeLink' => Config::get('contact')['links']['assegai_website'],
      'getStartedLink' => Config::get('contact')['links']['guide_link'],
      'documentationLink' => Config::get('contact')['links']['documentation_link'],
      'donateLink' => Config::get('contact')['links']['support_link'],
    ]);
  }
}
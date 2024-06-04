<?php

use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

describe("ProjectConfig", function() {
  it("can create an instance", function(InputInterface $input, OutputInterface $output) {
    expect(new ProjectConfig($input, $output))->toBeInstanceOf(ProjectConfig::class);
  })->with([
    [
      "input" => new MockInput(),
      "output" => new MockOutput(),
    ]
  ]);

  it("can load the project config file", function(InputInterface $input, OutputInterface $output) {
    $projectConfig = new ProjectConfig($input, $output);
    expect($projectConfig->load())->toBe(Command::SUCCESS);
  })->with([
    [
      "input" => new MockInput(),
      "output" => new MockOutput(),
    ]
  ])->skip();
});

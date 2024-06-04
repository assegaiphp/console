<?php

use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\ComposerConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

describe("ComposerConfig", function() {
  $input = new MockInput();
  $output = new MockOutput();

  it("can create an instance", function(InputInterface $input, OutputInterface $output) {
    expect(new ComposerConfig($input, $output))->toBeInstanceOf(ComposerConfig::class);
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("can load the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    expect($composerConfig->load())->toBe(Command::SUCCESS);
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("can get a value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("name"))->toBe("assegaiphp/console");
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("can get a nested value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("config.allow-plugins.pestphp/pest-plugin"))->toBeTrue();
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("returns null if the key does not exist", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("config.allow-plugins.pestphp/pest-plugin2"))->toBeNull();
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("can get a value from the composer.json file with a different working directory", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output, __DIR__ . '/../Mocks');
    $composerConfig->load();
    expect($composerConfig->get("name"))->toBe("assegaiphp/tests");
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);

  it("can update a value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    $composerConfig->set('name', "assegaiphp/console");
    $composerConfig->commit();
    expect($composerConfig->get("name"))->toBe("assegaiphp/console");
  })->with([
    [
      "input" => $input,
      "output" => $output,
    ]
  ]);
});
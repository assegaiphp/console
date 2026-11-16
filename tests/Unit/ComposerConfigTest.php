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
  $directory = $previousDirectory = $originalManifest = null;
  $sourceManifest = dirname(__DIR__, 2) . '/composer.json';

  beforeEach(function () use (&$directory, &$previousDirectory, &$originalManifest, $sourceManifest): void {
    $originalManifest = file_get_contents($sourceManifest);
    $previousDirectory = getcwd();
    $directory = sys_get_temp_dir() . '/assegai-composer-config-' . bin2hex(random_bytes(8));
    if ($originalManifest === false || $previousDirectory === false || !mkdir($directory, 0700)
      || !copy($sourceManifest, $directory . '/composer.json') || !chdir($directory)) {
      throw new RuntimeException('Cannot prepare the owned Composer configuration fixture.');
    }
  });

  afterEach(function () use (&$directory, &$previousDirectory, &$originalManifest, $sourceManifest): void {
    if (is_string($previousDirectory)) { chdir($previousDirectory); }
    if (is_string($directory) && is_dir($directory)) {
      unlink($directory . '/composer.json');
      rmdir($directory);
    }
    expect(file_get_contents($sourceManifest))->toBe($originalManifest);
  });

  it("can create an instance", function(InputInterface $input, OutputInterface $output) {
    expect(new ComposerConfig($input, $output))->toBeInstanceOf(ComposerConfig::class);
  })->with([[$input, $output]]);

  it("can load the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    expect($composerConfig->load())->toBe(Command::SUCCESS);
  })->with([[$input, $output]]);

  it("can get a value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("name"))->toBe("assegaiphp/console");
  })->with([[$input, $output]]);

  it("can get a nested value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("config.allow-plugins.pestphp/pest-plugin"))->toBeTrue();
  })->with([[$input, $output]]);

  it("returns null if the key does not exist", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    expect($composerConfig->get("config.allow-plugins.pestphp/pest-plugin2"))->toBeNull();
  })->with([[$input, $output]]);

  it("can get a value from the composer.json file with a different working directory", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output, __DIR__ . '/../Mocks');
    $composerConfig->load();
    expect($composerConfig->get("name"))->toBe("assegaiphp/tests");
  })->with([[$input, $output]]);

  it("can update a value from the composer.json file", function(InputInterface $input, OutputInterface $output) {
    $composerConfig = new ComposerConfig($input, $output);
    $composerConfig->load();
    $composerConfig->set('name', "assegaiphp/updated-fixture");
    expect($composerConfig->commit())->toBe(Command::SUCCESS);
    $reloaded = new ComposerConfig($input, $output);
    expect($reloaded->load())->toBe(Command::SUCCESS)
      ->and($reloaded->get("name"))->toBe("assegaiphp/updated-fixture");
  })->with([[$input, $output]]);
});

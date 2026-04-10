<?php

use Assegai\Console\Core\ProjectTemplateDefaults;
use Assegai\Console\Core\WorkspaceManager;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;

describe('Workspace manager', function () {
  it('derives modern package and namespace defaults for new projects', function () {
    $manager = new class(new MockInput(), new MockOutput(), new FormatterHelper(), new QuestionHelper()) extends WorkspaceManager {
      public function exposeDefaultPackageName(Text $projectName): string
      {
        return $this->buildDefaultPackageName($projectName);
      }

      public function exposeDefaultNamespace(string $packageName): string
      {
        return $this->buildDefaultNamespace($packageName);
      }
    };

    expect($manager->exposeDefaultPackageName(new Text('Blog API')))->toBe('assegaiphp/blog-api');
    expect($manager->exposeDefaultNamespace('assegaiphp/blog-api'))->toBe('Assegaiphp\\BlogApi\\');
  });

  it('validates package and namespace prompt inputs', function () {
    $manager = new class(new MockInput(), new MockOutput(), new FormatterHelper(), new QuestionHelper()) extends WorkspaceManager {
      public function exposePackageValidation(string $value): ?string
      {
        return $this->validatePackageName($value);
      }

      public function exposeNamespaceValidation(string $value): ?string
      {
        return $this->validateNamespace($value);
      }
    };

    expect($manager->exposePackageValidation('assegaiphp/blog-api'))->toBeNull();
    expect($manager->exposePackageValidation('BlogApi'))->toBe('Use vendor/package-name format in lowercase.');
    expect($manager->exposeNamespaceValidation('Acme\\BlogApi\\'))->toBeNull();
    expect($manager->exposeNamespaceValidation('acme/blog-api'))->toBe('Use a PSR-4 namespace such as Acme\\BlogApi\\.');
  });

  it('hydrates the generated assegai.json with the full supported default shape', function () {
    $workspace = sys_get_temp_dir() . '/' . uniqid('workspace-manager-', true);

    if (! mkdir($workspace, 0755, true) && ! is_dir($workspace)) {
      throw new RuntimeException("Failed to create test workspace: $workspace");
    }

    CliPrompt::fake([
      'text' => [
        'My Blog API',
        'A sample app',
        '0.1.0',
        'acme/blog-api',
        'Acme\\BlogApi\\',
      ],
      'confirm' => [
        false,
      ],
    ]);

    try {
      $manager = new WorkspaceManager(
        new MockInput([], ['skip-git' => true], true),
        new MockOutput(),
        new FormatterHelper(),
        new QuestionHelper()
      );

      $projectName = null;

      expect($manager->init($projectName, $workspace))->toBe(0);

      $configFilename = $workspace . '/my-blog-api/assegai.json';
      $config = json_decode(file_get_contents($configFilename) ?: '', true);
      $expectedDefaults = ProjectTemplateDefaults::loadAssegaiConfig();

      expect($config)->toBeArray();
      expect($config['name'])->toBe('My Blog API');
      expect($config['description'])->toBe('A sample app');
      expect($config['version'])->toBe('0.1.0');
      expect($config['projectType'])->toBe('project');
      expect($config['development'])->toBe($expectedDefaults['development']);
      expect($config['apiDocs'])->toBe($expectedDefaults['apiDocs']);
      expect($config['webComponents'])->toBe($expectedDefaults['webComponents']);

      $composerFilename = $workspace . '/my-blog-api/composer.json';
      $composer = json_decode(file_get_contents($composerFilename) ?: '', true);
      $readmeFilename = $workspace . '/my-blog-api/README.md';
      $readme = file_get_contents($readmeFilename) ?: '';

      expect($composer)->toBeArray();
      expect($composer['require']['php'])->toBe('^8.3');
      expect($composer['autoload']['psr-4'])->toBe(['Acme\\BlogApi\\' => 'src/']);
      expect(array_key_exists('Assegai\\App\\', $composer['autoload']['psr-4']))->toBeFalse();
      expect($readme)->toContain('This project was scaffolded with `assegai new`.');
      expect($readme)->toContain('assegai serve');
      expect($readme)->toContain('assegai add orm');
      expect($readme)->toContain('assegai database:configure cinema_db');
    } finally {
      CliPrompt::flushFake();

      if (is_dir($workspace)) {
        $items = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
          RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
          if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
          }

          unlink($item->getPathname());
        }

        rmdir($workspace);
      }
    }
  });
});

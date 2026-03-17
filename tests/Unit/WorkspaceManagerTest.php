<?php

use Assegai\Console\Core\WorkspaceManager;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
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
});

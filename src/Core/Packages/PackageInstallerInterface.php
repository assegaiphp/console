<?php

namespace Assegai\Console\Core\Packages;

interface PackageInstallerInterface
{
  public function install(PackageInstallContext $context): int;
}

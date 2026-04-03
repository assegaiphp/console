<?php

namespace Assegai\Console\Core\Packages;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class PackageInstallContext
{
  public function __construct(
    public InputInterface $input,
    public OutputInterface $output,
    public string $workspace,
    public string $packageName,
  )
  {
  }
}

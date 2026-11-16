<?php

namespace Assegai\Console\Util;

final readonly class ProcessResult
{
  public bool $successful;

  public function __construct(
    public int $status,
    public string $stdout,
    public string $stderr,
  ) {
    $this->successful = $status === 0;
  }
}

<?php

namespace Assegai\Console\Prompts;

use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CliPrompt
{
  /**
   * @var array<string, list<mixed>>
   */
  protected static array $fakeResponses = [];

  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
  )
  {
  }

  /**
   * @param array<string, list<mixed>> $responses
   */
  public static function fake(array $responses): void
  {
    self::$fakeResponses = $responses;
  }

  public static function flushFake(): void
  {
    self::$fakeResponses = [];
  }

  public function confirm(
    string $label,
    bool $default = true,
    string $yes = 'Yes',
    string $no = 'No',
    string $hint = '',
  ): bool
  {
    $fake = $this->consumeFakeResponse(__FUNCTION__);

    if ($fake['used']) {
      return (bool) $fake['value'];
    }

    $this->bootstrap();

    return confirm($label, $default, $yes, $no, false, null, $hint);
  }

  public function text(
    string $label,
    string $default = '',
    string $hint = '',
    string $placeholder = '',
    bool|string $required = false,
    mixed $validate = null,
  ): string
  {
    $fake = $this->consumeFakeResponse(__FUNCTION__);

    if ($fake['used']) {
      return (string) $fake['value'];
    }

    $this->bootstrap();

    if ($placeholder === '' && $default !== '') {
      $placeholder = $default;
    }

    return text(
      $label,
      placeholder: $placeholder,
      default: $default,
      required: $required,
      validate: $validate,
      hint: $hint
    );
  }

  public function password(
    string $label,
    string $hint = '',
  ): string
  {
    $fake = $this->consumeFakeResponse(__FUNCTION__);

    if ($fake['used']) {
      return (string) $fake['value'];
    }

    $this->bootstrap();

    return password($label, hint: $hint);
  }

  /**
   * @param array<int|string, string> $options
   */
  public function select(
    string $label,
    array $options,
    int|string|null $default = null,
    string $hint = '',
  ): int|string|null
  {
    $fake = $this->consumeFakeResponse(__FUNCTION__);

    if ($fake['used']) {
      return $fake['value'];
    }

    $this->bootstrap();

    if ($options === []) {
      return $default;
    }

    return select($label, $options, $default, hint: $hint);
  }

  /**
   * @param array<int|string, string> $options
   * @param array<int|string> $default
   * @return array<int|string>
   */
  public function multiselect(
    string $label,
    array $options,
    array $default = [],
    string $hint = 'Use the space bar to select options.',
  ): array
  {
    $fake = $this->consumeFakeResponse(__FUNCTION__);

    if ($fake['used']) {
      return array_values((array) $fake['value']);
    }

    $this->bootstrap();

    if ($options === []) {
      return $default;
    }

    return multiselect($label, $options, $default, hint: $hint);
  }

  protected function bootstrap(): void
  {
    Prompt::setOutput($this->output);
    Prompt::interactive($this->input->isInteractive());
  }

  /**
   * @return array{used: bool, value: mixed}
   */
  protected function consumeFakeResponse(string $method): array
  {
    if (! array_key_exists($method, self::$fakeResponses) || self::$fakeResponses[$method] === []) {
      return ['used' => false, 'value' => null];
    }

    return ['used' => true, 'value' => array_shift(self::$fakeResponses[$method])];
  }
}

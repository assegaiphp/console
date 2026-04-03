<?php

namespace Assegai\Console\WebComponents;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class WebComponentScaffolder
{
  private const string RUNTIME_DIRECTORY = 'src/WebComponents/runtime';

  private final function __construct()
  {
  }

  public static function ensureRuntime(string $workspace, OutputInterface $output): int
  {
    WebComponentConfig::ensureDefaults($workspace);

    $runtimeDirectory = self::getRuntimeDirectory($workspace);

    if (!is_dir($runtimeDirectory) && false === mkdir($runtimeDirectory, 0755, true)) {
      $output->writeln("<error>Failed to create $runtimeDirectory.</error>");
      return Command::FAILURE;
    }

    foreach (self::getRuntimeFiles() as $relativePath => $contents) {
      $filename = Path::join($runtimeDirectory, $relativePath);
      $directory = dirname($filename);

      if (!is_dir($directory) && false === mkdir($directory, 0755, true)) {
        $output->writeln("<error>Failed to create $directory.</error>");
        return Command::FAILURE;
      }

      if (is_file($filename)) {
        continue;
      }

      $bytes = file_put_contents($filename, $contents);

      if (false === $bytes) {
        $output->writeln("<error>Failed to write to $filename.</error>");
        return Command::FAILURE;
      }

      $relativeFilename = ltrim(str_replace($workspace, '', $filename), DIRECTORY_SEPARATOR);
      $output->writeln("<info>CREATE</info> $relativeFilename ($bytes bytes)");
    }

    return Command::SUCCESS;
  }

  public static function createComponentFile(
    string $workspace,
    string $filename,
    string $componentName,
    string $displayName,
    string $tagName,
    OutputInterface $output
  ): int {
    if (Command::SUCCESS !== self::ensureRuntime($workspace, $output)) {
      return Command::FAILURE;
    }

    $directory = dirname($filename);

    if (!is_dir($directory) && false === mkdir($directory, 0755, true)) {
      $output->writeln("<error>Failed to create $directory.</error>");
      return Command::FAILURE;
    }

    if (is_file($filename)) {
      return Command::SUCCESS;
    }

    $runtimeImport = self::getRuntimeImportPath($workspace, $filename);
    $contents = self::renderComponentTemplate($componentName, $displayName, $tagName, $runtimeImport);
    $bytes = file_put_contents($filename, $contents);

    if (false === $bytes) {
      $output->writeln("<error>Failed to write to $filename.</error>");
      return Command::FAILURE;
    }

    $relativeFilename = ltrim(str_replace($workspace, '', $filename), DIRECTORY_SEPARATOR);
    $output->writeln("<info>CREATE</info> $relativeFilename ($bytes bytes)");

    return Command::SUCCESS;
  }

  public static function getRuntimeDirectory(string $workspace): string
  {
    return Path::join($workspace, self::RUNTIME_DIRECTORY);
  }

  public static function getRuntimeImportPath(string $workspace, string $componentFilename): string
  {
    return self::relativeImportPath(dirname($componentFilename), self::getRuntimeDirectory($workspace));
  }

  public static function renderComponentTemplate(
    string $componentName,
    string $displayName,
    string $tagName,
    string $runtimeImport
  ): string
  {
    $elementName = (new Text($componentName))->pascalCase();
    $escapedDisplayName = addslashes($displayName);

    return <<<TS
import {
  AssegaiElement,
  defineElement,
  parseAttributeProps,
  parseJsonProps,
} from '$runtimeImport';

export class {$elementName}Element extends AssegaiElement<Record<string, unknown>> {
  static get observedAttributes(): string[] {
    return [];
  }

  protected resolveProps(): Record<string, unknown> {
    return {
      ...parseAttributeProps(this),
      ...parseJsonProps<Record<string, unknown>>(this),
    };
  }

  protected render(): void {
    const name: string = this.getAttribute('name') || '$escapedDisplayName';

    this.shadow.innerHTML = `
        <style></style>
        <p>\${name} works!</p>`;
  }
}

defineElement('$tagName', {$elementName}Element);
TS;
  }

  private static function relativeImportPath(string $fromDirectory, string $toDirectory): string
  {
    $from = array_values(array_filter(
      explode('/', trim(Path::normalize($fromDirectory), '/')),
      static fn(string $segment): bool => $segment !== '',
    ));
    $to = array_values(array_filter(
      explode('/', trim(Path::normalize($toDirectory), '/')),
      static fn(string $segment): bool => $segment !== '',
    ));

    while (!empty($from) && !empty($to) && $from[0] === $to[0]) {
      array_shift($from);
      array_shift($to);
    }

    $relativePath = implode('/', array_merge(array_fill(0, count($from), '..'), $to));

    if ($relativePath === '') {
      return './index';
    }

    if (!str_starts_with($relativePath, '.')) {
      $relativePath = './' . $relativePath;
    }

    return $relativePath;
  }

  /**
   * @return array<string, string>
   */
  private static function getRuntimeFiles(): array
  {
    return [
      'index.ts' => <<<TS
export { AssegaiElement } from './AssegaiElement';
export { parseJsonProps, parseAttributeProps } from './props';
export { emit } from './events';
export { qs, qsa } from './dom';
export { defineElement } from './registry';
export type { AssegaiProps } from './types';
TS,
      'AssegaiElement.ts' => <<<TS
export abstract class AssegaiElement<TProps extends Record<string, unknown> = Record<string, unknown>>
  extends HTMLElement {
  protected props: TProps = {} as TProps;
  private mounted = false;

  constructor() {
    super();

    if (!this.shadowRoot) {
      this.attachShadow({ mode: 'open' });
    }
  }

  connectedCallback(): void {
    this.props = this.resolveProps();

    if (!this.mounted) {
      this.onInit();
      this.mounted = true;
    }

    this.onMount();
    this.render();
  }

  disconnectedCallback(): void {
    this.onUnmount();
  }

  attributeChangedCallback(name: string, oldValue: string | null, newValue: string | null): void {
    if (oldValue === newValue) {
      return;
    }

    this.props = this.resolveProps();
    this.onPropsChanged(name, oldValue, newValue);
    this.render();
  }

  protected onInit(): void {
  }

  protected onMount(): void {
  }

  protected onUnmount(): void {
  }

  protected onPropsChanged(name: string, oldValue: string | null, newValue: string | null): void {
  }

  protected resolveProps(): TProps {
    return {} as TProps;
  }

  protected emit<TDetail = unknown>(name: string, detail?: TDetail): void {
    this.dispatchEvent(new CustomEvent(name, {
      detail,
      bubbles: true,
      composed: true,
    }));
  }

  protected get shadow(): ShadowRoot {
    if (!this.shadowRoot) {
      this.attachShadow({ mode: 'open' });
    }

    return this.shadowRoot;
  }

  protected abstract render(): void;
}
TS,
      'props.ts' => <<<TS
export function parseJsonProps<T = Record<string, unknown>>(element: HTMLElement): Partial<T> {
  const raw = element.dataset.props;

  if (!raw) {
    return {};
  }

  try {
    return JSON.parse(raw) as Partial<T>;
  } catch {
    console.warn('[AssegaiElement] Invalid data-props JSON:', raw);
    return {};
  }
}

export function parseAttributeProps(element: HTMLElement): Record<string, unknown> {
  const props: Record<string, unknown> = {};

  for (const attr of element.attributes) {
    if (attr.name === 'data-props') {
      continue;
    }

    props[attr.name] = attr.value;
  }

  return props;
}
TS,
      'events.ts' => <<<TS
export function emit<TDetail = unknown>(
  element: HTMLElement,
  name: string,
  detail?: TDetail,
): void {
  element.dispatchEvent(new CustomEvent(name, {
    detail,
    bubbles: true,
    composed: true,
  }));
}
TS,
      'dom.ts' => <<<TS
export function qs(root: ParentNode, selector: string): Element | null {
  return root.querySelector(selector);
}

export function qsa(root: ParentNode, selector: string): Element[] {
  return Array.from(root.querySelectorAll(selector));
}
TS,
      'registry.ts' => <<<TS
export function defineElement(tag: string, ctor: CustomElementConstructor): void {
  if (!customElements.get(tag)) {
    customElements.define(tag, ctor);
  }
}
TS,
      'types.ts' => <<<TS
export type AssegaiProps = Record<string, unknown>;
TS,
    ];
  }
}

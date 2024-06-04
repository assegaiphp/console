<?php

namespace Assegai\Console\Core\Schematics\Enumerations;

/**
 * Enumerates the different types of class templates.
 *
 * @package Assegai\Console\Core\Schematics\Enumerations
 */
enum ClassTemplate: string
{
  case DEFAULT = 'class';
  case INTERFACE = 'interface';
  case TRAIT = 'trait';
  case ABSTRACT_CLASS = 'abstract class';
  case ENUM = 'enum';
}

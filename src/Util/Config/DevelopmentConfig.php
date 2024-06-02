<?php

namespace Assegai\Console\Util\Config;

class DevelopmentConfig
{
  /**
   * The DevelopmentConfig constructor
   *
   * @param array{host: string, port: int, openBrowser: boolean} $server
   */
  public function __construct(
    protected array $server = []
  )
  {
  }

  /**
   * @param object $object
   * @return $this
   */
  public function loadFromObject(object $object): self
  {
    foreach ($object as $property => $value)
    {
      if (property_exists($this, $property))
      {
        if (gettype($this->$property) === 'array' && gettype($value) === 'object')
        {
          $value = json_decode(json_encode($value), true);
        }
        $this->$property = $value;
      }
    }

    return $this;
  }

  /**
   * Returns the value of the property of given name if it exists.
   *
   * @param string $propertyName The property name
   * @return mixed The value of the given property if it exists, otherwise null.
   */
  public function get(string $propertyName): mixed
  {
    if (property_exists($this, $propertyName))
    {
      return $this->$propertyName;
    }

    return null;
  }
}
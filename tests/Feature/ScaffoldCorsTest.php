<?php

describe('project scaffold CORS ownership', function () {
  it('leaves CORS and preflight handling to the application', function () {
    $index = file_get_contents(__DIR__ . '/../../templates/index.php');
    $htaccess = file_get_contents(__DIR__ . '/../../templates/.htaccess.example');

    if ($index === false || $htaccess === false) {
      throw new RuntimeException('Unable to read the project scaffold templates.');
    }

    expect(str_contains($index, 'Access-Control-Allow-Origin'))->toBeFalse()
      ->and(str_contains($index, "REQUEST_METHOD'] === 'OPTIONS'"))->toBeFalse()
      ->and(str_contains($htaccess, 'Access-Control-Allow-Origin'))->toBeFalse()
      ->and(str_contains($htaccess, 'Access-Control-Allow-Credentials'))->toBeFalse();
  });
});

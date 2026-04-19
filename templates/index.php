<?php
/**
 * This file is part of the Assegai framework.
 *
 * (c) Assegai Team <https://assegaiphp.com>
 */

$publicDirectory = realpath(__DIR__ . '/public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($publicDirectory !== false && $requestPath !== '/' && $requestPath !== '') {
  $assetRelativePath = trim(str_replace('\\', '/', ltrim($requestPath, '/')), '/');
  $segments = $assetRelativePath === '' ? [] : array_values(array_filter(explode('/', $assetRelativePath), static fn(string $segment): bool => $segment !== ''));
  $hasHiddenSegment = false;

  foreach ($segments as $segment) {
    if (str_starts_with($segment, '.')) {
      $hasHiddenSegment = true;
      break;
    }
  }

  if (!$hasHiddenSegment && $assetRelativePath !== '') {
    $assetCandidate = $publicDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath);
    $assetPath = realpath($assetCandidate);
    $extension = strtolower(pathinfo($assetPath ?: '', PATHINFO_EXTENSION));

    if (
      $assetPath !== false
      && is_file($assetPath)
      && str_starts_with($assetPath, $publicDirectory . DIRECTORY_SEPARATOR)
      && !in_array($extension, ['php', 'phtml', 'phar', 'inc'], true)
    ) {
      $mimeType = mime_content_type($assetPath) ?: 'application/octet-stream';
      $contentLength = filesize($assetPath);

      header("Content-Type: $mimeType");
      header('X-Content-Type-Options: nosniff');

      if (is_int($contentLength) && $contentLength >= 0) {
        header('Content-Length: ' . $contentLength);
      }

      readfile($assetPath);
      exit();
    }
  }
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,X-Access-Token,Authorization,x-api-key");
header("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,PUT,PATCH,POST,DELETE");
header("Access-Control-Allow-Origin: *");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if (!isset($_GET['path']) || $_GET['path'] === '') {
  $_GET['path'] = trim($requestPath, '/');
}

require_once './bootstrap.php';
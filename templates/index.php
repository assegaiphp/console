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

  foreach ($segments as $index => $segment) {
    if (str_starts_with($segment, '.') && !($index === 0 && $segment === '.well-known')) {
      $hasHiddenSegment = true;
      break;
    }
  }

  if (!$hasHiddenSegment && $assetRelativePath !== '') {
    $assetCandidate = $publicDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath);
    $assetPath = realpath($assetCandidate);
    $extension = strtolower(pathinfo($assetPath ?: '', PATHINFO_EXTENSION));
    $normalizedRelativePath = trim(str_replace('\\', '/', $assetRelativePath), '/');
    $shouldBypassStreaming = false;

    if (in_array($extension, ['php', 'phtml', 'phar', 'inc'], true)) {
      $shouldBypassStreaming = true;
    } elseif ($extension === '') {
      $shouldBypassStreaming = !str_starts_with($normalizedRelativePath, '.well-known/');
    } else {
      $allowedExtensions = [
        '7z',
        'atom',
        'avif',
        'bmp',
        'bz2',
        'css',
        'csv',
        'eot',
        'gif',
        'gz',
        'htm',
        'html',
        'ico',
        'jpeg',
        'jpg',
        'js',
        'json',
        'map',
        'md',
        'mjs',
        'mp3',
        'mp4',
        'mpeg',
        'oga',
        'ogv',
        'ogx',
        'otf',
        'pdf',
        'png',
        'rss',
        'rtf',
        'svg',
        'svgz',
        'tgz',
        'ts',
        'ttf',
        'txt',
        'wasm',
        'wav',
        'weba',
        'webm',
        'webmanifest',
        'webp',
        'woff',
        'woff2',
        'xhtml',
        'xls',
        'xlsx',
        'xml',
        'zip',
      ];

      $shouldBypassStreaming = !in_array($extension, $allowedExtensions, true)
        || in_array(
          strtolower(pathinfo($assetPath ?: '', PATHINFO_BASENAME)),
          ['index.htm', 'index.html', 'index.php', 'index.xhtml'],
          true,
        );
    }

    if (
      $assetPath !== false
      && is_file($assetPath)
      && str_starts_with($assetPath, $publicDirectory . DIRECTORY_SEPARATOR)
      && !$shouldBypassStreaming
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
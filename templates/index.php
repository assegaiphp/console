<?php
/**
 * This file is part of the Assegai framework.
 *
 * (c) Assegai Team <https://assegaiphp.com>
 */

$publicDirectory = realpath(__DIR__ . '/public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($publicDirectory !== false && $requestPath !== '/' && $requestPath !== '') {
  $assetPath = assegai_resolve_public_asset_path($publicDirectory, $requestPath);
  $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

  if (is_string($assetPath)) {
    if (PHP_SAPI === 'cli-server' && $documentRoot === $publicDirectory) {
      return false;
    }

    assegai_stream_public_asset($assetPath);
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

function assegai_resolve_public_asset_path(string $publicDirectory, string $requestPath): string|false
{
  $assetRelativePath = trim(str_replace('\\', '/', ltrim($requestPath, '/')), '/');

  if ($assetRelativePath === '' || assegai_contains_hidden_path_segment($assetRelativePath)) {
    return false;
  }

  $assetCandidate = $publicDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetRelativePath);
  $assetPath = realpath($assetCandidate);

  if ($assetPath === false || !is_file($assetPath)) {
    return false;
  }

  $publicDirectoryPrefix = rtrim($publicDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

  if (!str_starts_with($assetPath, $publicDirectoryPrefix)) {
    return false;
  }

  return assegai_should_serve_public_asset($assetPath, $assetRelativePath) ? $assetPath : false;
}

function assegai_contains_hidden_path_segment(string $relativePath): bool
{
  $segments = array_values(array_filter(explode('/', $relativePath), static fn(string $segment): bool => $segment !== ''));

  foreach ($segments as $index => $segment) {
    if (str_starts_with($segment, '.') && !($index === 0 && $segment === '.well-known')) {
      return true;
    }
  }

  return false;
}

function assegai_should_serve_public_asset(string $assetPath, string $relativePath): bool
{
  $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
  $normalizedRelativePath = trim(str_replace('\\', '/', $relativePath), '/');

  if (in_array($extension, ['php', 'phtml', 'phar', 'inc'], true)) {
    return false;
  }

  if ($extension === '') {
    return str_starts_with($normalizedRelativePath, '.well-known/');
  }

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

  if (!in_array($extension, $allowedExtensions, true)) {
    return false;
  }

  return !in_array(
    strtolower(pathinfo($assetPath, PATHINFO_BASENAME)),
    ['index.htm', 'index.html', 'index.php', 'index.xhtml'],
    true,
  );
}

function assegai_public_asset_mime_type(string $assetPath): string
{
  $mimeType = mime_content_type($assetPath);

  return match (strtolower(pathinfo($assetPath, PATHINFO_EXTENSION))) {
    'css' => 'text/css',
    'js', 'mjs' => 'text/javascript',
    'json', 'map', 'webmanifest' => 'application/json',
    'svg', 'svgz' => 'image/svg+xml',
    'wasm' => 'application/wasm',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    default => is_string($mimeType) ? $mimeType : 'application/octet-stream',
  };
}

function assegai_stream_public_asset(string $assetPath): never
{
  $contentLength = filesize($assetPath);

  header('Content-Type: ' . assegai_public_asset_mime_type($assetPath));
  header('X-Content-Type-Options: nosniff');

  if (is_int($contentLength) && $contentLength >= 0) {
    header('Content-Length: ' . $contentLength);
  }

  readfile($assetPath);
  exit();
}

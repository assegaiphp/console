<?php

return [
  'authentication' => [
    'loginRedirect' => [
      'url' => '/auth/login',
      'statusCode' => 302,
      'preserveTarget' => true,
      'targetSessionKey' => 'auth.intended_url',
      'excludedPaths' => [],
    ],
  ],
  'session' => [
    'name' => 'assegai_session',
    'cookieLifetime' => 0,
    'cookiePath' => '/',
    'cookieDomain' => '',
    'cookieSecure' => null,
    'cookieHttpOnly' => true,
    'cookieSameSite' => 'Lax',
  ],
];

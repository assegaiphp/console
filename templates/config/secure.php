<?php

return [
  'databases' => [],
  'authentication' => [
    'secret' => env('APP_SECRET_KEY', 'your-secret-key'),
    'strategies' => [],
    'jwt' => [
      'audience' => 'https://yourdomain.com',
      'issuer' => 'assegai',
      'lifespan' => '1 hour',
      'entityName' => 'user',
      'entityClassName' => Assegai\App\Users\Entities\UserEntity::class,
      'entityIdFieldName' => 'email',
      'entityPasswordFieldName' => 'password',
    ],
  ],
];

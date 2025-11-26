<?php

return [
  'company_name' => 'My Company',
  'default_password_hash_algo' => '2y',
  'databases' => [
    'mysql' => [
      'db_name' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
    'pgsql' => [
      'db_name' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 5432,
      ],
    ],
    'sqlite' => [
      'db_name' => [
        'path' => '.data/db_name.sq3',
      ],
    ],
    'mongodb' => [],
  ],
  'request' => [
    'DEFAULT_LIMIT' => 10,
    'DEFAULT_SKIP' => 0,
  ],
  'authentication' => [
    'secret' => env("APP_SECRET_KEY", "your-secret-key"),
    'strategies' => [
      'local' => 'Assegai\\Core\\Authentication\\Strategies\\LocalStrategy',
      'jwt' => 'Assegai\\Core\\Authentication\\Strategies\\JWTStrategy',
      'oauth' => 'Assegai\\Core\\Authentication\\Strategies\\OAuthStrategy',
    ],
    'default_strategy' => 'local',
    'jwt' => [
      'audience' => 'https://yourdomain.com',
      'issuer' => 'assegai',
      'lifespan' => '1 hour',
      'entityName' => 'user',
      'entityClassName' => 'Assegai\\App\\Users\\Entities\\UserEntity',
      'entityIdFieldName' => 'email',
      'entityPasswordFieldName' => 'password',
    ],
  ],
  'contact' => [
    'links' => [
      'assegai_website' => 'https://assegaiphp.com',
      'guide_link' => 'https://assegaiphp.com/guide',
      'documentation_link' => 'https://docs.assegaiphp.com',
      'support_link' => 'https://assegaiphp.com/support',
      'blog_link' => 'https://blog.assegaiphp.com',
    ]
  ]
];
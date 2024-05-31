<?php

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,X-Access-Token,Authorization,x-api-key");
header("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,PUT,PATCH,POST,DELETE");
header("Access-Control-Allow-Origin: *");

$_GET['path'] = trim($_SERVER['REQUEST_URI'], '/');

require_once('index.php');

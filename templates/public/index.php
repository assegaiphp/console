<?php
/**
 * This file is part of the Assegai framework.
 *
 * (c) Assegai Team <https://assegaiphp.com>
 */


/*
 * This is a simple router that routes all requests to the app.php file.
 * It is a simple way to get started with the Assegai framework.
 *
 * You can replace this file with your own router if you want to.
 */
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,X-Access-Token,Authorization,x-api-key");
header("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,PUT,PATCH,POST,DELETE");
header("Access-Control-Allow-Origin: *");

/*
 * Set the path to the request URI.
 */
$_GET['path'] = trim($_SERVER['REQUEST_URI'], '/');

/*
 * This is the entry point of the application.
 */
require_once(__DIR__ . '/../app.php');

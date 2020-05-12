<?php

use Github\Client;
use Github\ResultPager;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/globals.php';

if (!file_exists('token.txt')) {
    exit("Token file not found." . PHP_EOL);
}

$client = new Client();
$token = file_get_contents('token.txt');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new ResultPager($client);

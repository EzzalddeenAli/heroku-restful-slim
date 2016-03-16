<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;

$app->get('/', function(Request $req, Response $res){
	echo 'hello heroku-restful-slim';
});

$app->run();

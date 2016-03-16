<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$dbopts = parse_url(getenv('DATABASE_URL'));
$config = array(
	'displayErrorDetails' => true,
	'db' => array(
		'host' => $dbopts['host'],
		'dbname' => ltrim($dbopts['path'], '/'),
		'user' => $dbopts['user'],
		'pass' => $dbopts['pass'],
		'port' => $dbopts['port']
	)
);
$container = new \Slim\Container(array(
	'settings' => $config
));
$container['db'] = function($c){
	$db = $c['settings']['db'];
	return new medoo(array(
		'database_type' => 'pgsql',
		'database_name' => $db['dbname'],
		'server' => $db['host'],
		'username' => $db['user'],
		'password' => $db['pass'],
		'port' => $db['port'],
		'charset' => 'utf8'
	));
};
$app = new \Slim\App($container);

$app->get('/', function(Request $req, Response $res){
	echo 'hello heroku-restful-slim';
});

$app->get('/db', function(Request $req, Response $res){
	$db = $this->get('db');
	$data = $db->select('users', '*');
	echo '<pre>';
	var_dump($data);
	echo '</pre>';
});

$app->run();

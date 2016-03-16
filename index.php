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

$app->get('/api/users', function(Request $req, Response $res){
	$db = $this->get('db');
	$data = $db->select('users', '*');
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->get('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$db = $this->get('db');
	$data = $db->select('users', '*', array('id' => $id));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data[0]);
});

$app->post('/api/users', function(Request $req, Response $res){
	$user = $req->getParsedBody();
	$db = $this->get('db');
	$db->insert('users', array(
		'name' => $user['name'],
		'age' => $user['age'],
		'address' => $user['address']
	));
	$lastId = $db->pdo->lastInsertId('users_id_seq');
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('lastId' => $lastId));
});

$app->put('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$user = $req->getParsedBody();
	$db = $this->get('db');
	$data = $db->select('users', '*', array('id' => $id));
	if(isset($user['name'])) $data[0]['name'] = $user['name'];
	if(isset($user['age'])) $data[0]['age'] = $user['age'];
	if(isset($user['address'])) $data[0]['address'] = $user['address'];
	$ret = $db->update('users', array(
		'name' => $data[0]['name'],
		'age' => $data[0]['age'],
		'address' => $data[0]['address']
	), array('id' => $id));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data[0]);
});

$app->delete('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$db = $this->get('db');
	$ret = $db->delete('users', array('id' => $id));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo $ret;
});

$app->run();

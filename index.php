<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

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
$container['medoo'] = function($c){
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
$container['monolog'] = function($c){
	$logger = new Logger('monolog');
	$logger->pushProcessor(new Monolog\Processor\UidProcessor());
	$logger->pushHandler(new StreamHandler(__DIR__ . '/dev.log', Logger::DEBUG));
	$logger->pushHandler(new FirePHPHandler(Logger::DEBUG));
	return $logger;
};
$app = new \Slim\App($container);

$app->get('/', function(Request $req, Response $res){
	phpinfo();
});

$app->get('/api/users', function(Request $req, Response $res){
	$data = $this->medoo->select('users', '*');
	$this->monolog->addDebug('GET', $data);
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->get('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$data = $this->medoo->select('users', '*', array('id' => $id));
	$this->monolog->addDebug('GET', $data);
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data[0]);
});

$app->post('/api/users', function(Request $req, Response $res){
	$user = $req->getParsedBody();
	$this->medoo->insert('users', array(
		'name' => $user['name'],
		'age' => $user['age'],
		'address' => $user['address']
	));
	$insertId = $this->medoo->pdo->lastInsertId('users_id_seq');
	$user['id'] =  $insertId;
	$this->monolog->addDebug('POST', $user);
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($user);
});

$app->put('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$user = $req->getParsedBody();
	$set = array();
	foreach($user as $key => $value){
		$set[$key] = $value;
	}
	$affectedRows = $this->medoo->update('users', $set, array('id' => $id));
	$this->monolog->addDebug('PUT', $set);
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->delete('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$affectedRows = $this->medoo->delete('users', array('id' => $id));
	$this->monolog->addDebug('DELETE', array('affectedRows' => $affectedRows, 'id' => $id));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->run();

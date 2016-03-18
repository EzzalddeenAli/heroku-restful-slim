<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
	return new \Slim\PDO\Database(
		'pgsql:dbname='.$db['dbname'].';host='.$db['host'].';port='.$db['port'],
		$db['user'],
		$db['pass']
	);
};
$container['monolog'] = function($c){
	$monolog = new Logger('monolog');
	$monolog->pushProcessor(new Monolog\Processor\UidProcessor());
	$monolog->pushHandler(new StreamHandler(__DIR__ . '/dev.log', \Monolog\Logger::DEBUG));
	return $monolog;
};

$app = new \Slim\App($container);

$app->get('/', function(Request $req, Response $res){
	$log = $this->get('monolog');
	$log->addDebug('root handler', array('response' => $req));
	//echo 'hello heroku-restful-slim';
	phpinfo();
});

$app->get('/api/users', function(Request $req, Response $res){
	$db = $this->get('db');
	$stmt = $db->select()->from('users')->execute();
	$data = $stmt->fetchAll();
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->get('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$db = $this->get('db');
	$stmt = $db->select()->from('users')->where('id', '=', $id)->execute();
	$data = $stmt->fetch();
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->post('/api/users', function(Request $req, Response $res){
	$user = $req->getParsedBody();
	$db = $this->get('db');
	// PostgresqlではlastInsertId()でIDを取得できるようになってない
	$insertId = $db->insert(array('name', 'age', 'address'))
		->into('users')->values(array($user['name'], $user['age'], $user['address']))
		->execute();
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('insertId' => $insertId));
});

$app->put('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$user = $req->getParsedBody();
	$db = $this->get('db');
	$stmt = $db->select()->from('users')->where('id', '=', $id)->execute();
	$data = $stmt->fetch();
	if(isset($user['name'])) $data['name'] = $user['name'];
	if(isset($user['age'])) $data['age'] = $user['age'];
	if(isset($user['address'])) $data['address'] = $user['address'];
	$affectedRows = $db->update(array(
		'name' => $data['name'],
		'age' => $data['age'],
		'address' => $data['address']
	))->table('users')->where('id', '=', $id)
	->execute();
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->delete('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$db = $this->get('db');
	$affectedRows = $db->delete()->from('users')->where('id', '=', $id)->execute();
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->run();

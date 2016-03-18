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
$container['fpdo'] = function($c){
	$db = $c['settings']['db'];
	$pdo = new PDO('pgsql:dbname='.$db['dbname'].';host='.$db['host'].';port='.$db['port'], $db['user'], $db['pass']);
	return new FluentPDO($pdo);
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
	phpinfo();
});

$app->get('/api/users', function(Request $req, Response $res){
	$query = $this->fpdo->from('users')->orderBy('id');
	$data = $query->fetchAll();
	$this->monolog->addDebug('GET', $data);
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->get('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$query = $this->fpdo->from('users')->where('id', $id);
	$data = $query->fetch();
	$this->monolog->addDebug('GET', array('id' => $id, $data));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode($data);
});

$app->post('/api/users', function(Request $req, Response $res){
	$user = $req->getParsedBody();
	$insertId = $this->fpdo->insertInto('users')
		->values(array('name' => $user['name'], 'age' => $user['age'], 'address' => $user['address']))
		->execute('users_id_seq');
	$this->monolog->addDebug('POST', array('insertId' => $insertId, $user));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('insertId' => $insertId));
});

$app->put('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$user = $req->getParsedBody();
	$set = array();
	foreach($user as $key => $value){
		$set[$key] = $value;
	}
	$affectedRows = $this->fpdo->update('users')
		->set($set)->where('id', $id)->execute();
	$this->monolog->addDebug('PUT', array('id' => $id, $set));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->delete('/api/users/{id:[0-9]+}', function(Request $req, Response $res, $args){
	$id = (int)$args['id'];
	$affectedRows = $this->fpdo->deleteFrom('users')->where('id', $id)->execute();
	$this->monolog->addDebug('DELETE', array('id' => $id));
	$res->withStatus(200);
	$res->withHeader('Content-Type', 'application/json');
	echo json_encode(array('affectedRows' => $affectedRows));
});

$app->run();

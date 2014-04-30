<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$amqp = new AMQPConnection(array('host'=>'localhost','user'=>'guest', 'password'=>'guest'));
$amqp->connect();

$ch = new AMQPChannel($amqp);

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->declare();

function createqueue($ch, $name, $ex, $key) {
	$q = new AMQPQueue($ch);
	$q->setName($name);
	$q->setFlags(AMQP_DURABLE);
	$q->declare();
	$q->bind('org.blocked', $key);
}

$result = $conn->query("select lower(replace(name,' ','_')) as name from isps", array());
while ($isp = $result->fetch_assoc()) {
	createqueue($ch, 'url.'.$isp['name'].'.public', $ex, 'url.public');
	createqueue($ch, 'url.'.$isp['name'].'.org', $ex, 'url.*');
}

createqueue($ch, "results", $ex, "results.*");


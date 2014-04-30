<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$amqp = new AMQPConnection(array('host'=>'localhost','user'=>'guest', 'password'=>'guest'));
$amqp->connect();

$ch = new AMQPChannel($amqp);

$ex2 = new AMQPExchange($ch);
$ex2->setName('org.results');
$ex2->setType('topic');
$ex2->declare();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->declare();

function createqueue($ch, $name,  $key) {
	$q = new AMQPQueue($ch);
	$q->setName($name);
	$q->setFlags(AMQP_DURABLE);
	$q->declare();
	$q->bind('org.blocked', $key);
}

$result = $conn->query("select lower(replace(name,' ','_')) as name from isps", array());
while ($isp = $result->fetch_assoc()) {
	createqueue($ch, 'url.'.$isp['name'].'.public',  'url.public');
	createqueue($ch, 'url.'.$isp['name'].'.org',  'url.*');
}

createqueue($ch, "results",  "results.#");


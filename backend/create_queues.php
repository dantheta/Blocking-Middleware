<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
include "$dir/../api/1.2/libs/amqp.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$ch = amqp_connect();

$ex2 = new AMQPExchange($ch);
$ex2->setName('org.results');
$ex2->setType('topic');
$ex2->setFlags(AMQP_DURABLE);
$ex2->declare();

$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');
$ex->setType('topic');
$ex->setFlags(AMQP_DURABLE);
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
	if (strpos($isp['name'], ',') !== false) {
		continue;
	}
	createqueue($ch, 'url.'.$isp['name'].'.public',  'url.public');
	createqueue($ch, 'url.'.$isp['name'].'.org',  'url.*');
}

createqueue($ch, "results",  "results.#");


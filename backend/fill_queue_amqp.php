<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";

/*
This script will backfill the queue table with all submitted URLs.
Run from cron to feed the queue when:
 * New URLs have been added
 * New ISPs have been added
*/
$amqp = new AMQPConnection(array('host'=>'localhost','user'=>'guest', 'password'=>'guest'));
$amqp->connect();
$ch = new AMQPChannel($amqp);
$ex = new AMQPExchange($ch);
$ex->setName('org.blocked');

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$result = $conn->query("select id, name from isps", array());
while ($isp = $result->fetch_assoc()) {
	$c = 0;
	$res2 = $conn->query("select urlid, url from urls where source = 'alexa'", array());
	while ($url = $res2->fetch_assoc()) {
		$c += 1;
		$msgbody = json_encode($url);
		$ex->publish($msgbody, 'url.org', AMQP_NOPARAM, array('priority'=>2));
	}

	print "Added {$conn->affected_rows} rows for isp: {$isp['name']}\n";
}


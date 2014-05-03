<?php


$amqp = new AMQPConnection(array('host'=>'localhost','user'=>'guest', 'password'=>'guest'));
$amqp->connect();
$ch = new AMQPChannel($amqp);


print "Removing: " . $argv[1] . "\n";
try {
$q = new AMQPQueue($ch);
$q->setName($argv[1]);
$q->purge();
$q->delete();
} catch(Exception $e) {
	print "Error: " . $e->getMessage();
}

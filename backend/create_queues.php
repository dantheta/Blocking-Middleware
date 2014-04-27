
<?php

$dir = dirname(__FILE__);
include "$dir/../api/1.2/libs/DB.php";
$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

function runcmd($cmdline) {
	print "Running: " . $cmdline . "\n";
	system($cmdline);
}

runcmd("qpid-config add exchange topic org.blocked --durable");

$result = $conn->query("select lower(replace(name,' ','_')) as name from isps", array());
while ($isp = $result->fetch_assoc()) {
	foreach(array('public','org') as $type) {
		$name = $isp['name'] . '.' . $type;
		runcmd("qpid-config add queue url.{$name} --durable --argument x-qpid-priorities=10");
		if ($type == 'public') {
			runcmd("qpid-config bind org.blocked url.{$name} 'url.{$type}' ");
		} else {			
			runcmd("qpid-config bind org.blocked url.{$name} 'url.*' ");
		}
	}
}

runcmd("qpid-config add queue results --durable");
runcmd("qpid-config bind org.blocked results 'results.*'");

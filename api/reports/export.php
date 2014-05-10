<?php

include_once __DIR__ . "/../1.2/libs/DB.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

header("Content-type: text/csv");

$sql = "select url, network_name, created, config, http_status, status 
from urls inner join results using(urlid) order by url, network_name";

$result = $conn->query($sql, array());

$fp = fopen('php://output','w');
if (!$fp) {
	error_log("Unable to open output");
	die;
}

fputcsv($fp, array("URL","Network/ISP","Timestamp","Config version","HTTP Status","Status"));
while ($row = $result->fetch_row()) {
	fputcsv($fp, $row);
}
fclose($fp);




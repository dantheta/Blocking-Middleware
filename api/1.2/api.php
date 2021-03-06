<?php

require_once __DIR__."/../../backend/silex/vendor/autoload.php";

include_once "libs/DB.php";
include_once "libs/pki.php";
include_once "libs/password.php";
include_once "libs/exceptions.php";
include_once "libs/services.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

$app = new Silex\Application();
$app['debug'] = true;

$app['service.db'] = $app->share(function() {
	global $dbuser, $dbpass, $dbhost, $dbname;
	return new APIDB($dbhost, $dbuser, $dbpass, $dbname);
});

$app['db.user.load'] = function($app) {
	return new UserLoader($app['service.db']);
};
$app['db.probe.load'] = function($app) {
	return new ProbeLoader($app['service.db']);
};
$app['db.url.load'] = function($app) {
	return new UrlLoader($app['service.db']);
};

function checkParameters($req, $params) {
	# check that required GET/POST parameters are present
	foreach($params as $requiredParam) {
		if (!$req->get($requiredParam)) {
			# throw if any are missing
			throw new InputError();
		}
	}
}

function checkProbe($probe) {
	# Probe status check, throw if not enabled
	if ($probe['enabled'] != 1) {
		throw new ProbeStateError();
	}
}
function checkUser($user) {
	# User status check, throw if not "ok"
	if ($user['status'] != 'ok') {
		throw new UserStatusError($row['status']);
	}
}

function checkAdministrator($user) {
	# Administrator privilege check, throw if not an admin
	checkUser($user); # also check general user status
	if ($user['administrator'] == 0) {
		throw new UserPrivsError();
	}
}

$app->error(function(APIException $e, $code) {
	switch(get_class($e)) {
		case "ProbeLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "UserLookupError":
			$code = 404;
			$message = "No matches in DB, please contact ORG support";
			break;
		case "InputError":
			$code = 400;
			$message = "One or more required parameters missing or invalid";
			break;
		case "DatabaseError":
			$code = 500;
			$message = "A database error occurred: " . $e->getMessage();
			break;
		case "SignatureError":
			$code = 403;
			$message = "Signature verification failed.";
			break;
		case "TimestampError":
			$code = 400;
			$message = "Timestamp out of range (too old/new)";
			break;
		case "ConflictError":
			$code = 409;
			$message = $e->getMessage();
			break;
		case 'UserStatusError':
			$code = 403;
			$message = "Account is " . $e->getMessage();
			break;
		case 'UserPrivsError':
			$code = 403;
			$message = "User is not authorised to perform this action";
			break;
		case 'IpLookupError':
			$code = 500;
			$message = "An error occurred gathering IP information";
			break;
	};
	return new JsonResponse(
		array('success'=>false, 'error'=>$message), $code
		);
});

$app->after(function(Request $request, Response $response) {
	# Set API version header on all responses

	global $APIVersion; // from DB.php
	$response->headers->set('API-Version', $APIVersion);
});

/* URL Endpoints */

$app->post('/submit/url', function(Request $req) use ($app) {
	/* Add a URL for testing */
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature'));

	$row = $app['db.user.load']->load($req->get('email'));
	checkUser($row);

	Middleware::verifyUserMessage($req->get('url'), $row['secret'], 
		$req->get('signature')
	);

	# there is some badness here - URL is uniquely indexed to only the first 
	# 767 characters

	$conn->query(
		"insert ignore into tempURLs(URL, hash, lastPolled) values (?,?,now())",
		array($req->get('url'), md5($req->get('url')))
		);
	# Because of the unique index (and the insert ignore) we have to query
	# to get the ID, instead of just using insert_id
	$url = $app['db.url.load']->load($req->get('url'));

	$conn->query(
		"insert into requests(urlID, userID, submission_info, created)
			values (?,?,?,now())",
		array($url['tempID'], $row['id'], $req->get('additional_data'))
		);
	$request_id = $conn->insert_id;

	return $app->json(array('success' => true, 'uuid' => $request_id), 201);
});
	
$app->get('/status/user',function(Request $req) use ($app) {
	# Get the status of a user
	$conn = $app['service.db'];

	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$row = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage( $req->get('email') .':'. $req->get('date'), 
		$row['secret'], $req->get('signature'));


	return $app->json(array('success'=>'true', 'status'=> $row['status']));
	
});


$app->post('/register/user', function(Request $req) use ($app) {
	# Add a new user
	global $Salt;

	$conn = $app['service.db'];
	checkParameters($req, array('email','password'));

	$email = $req->get('email');
	$password = password_hash($req->get('password'), PASSWORD_DEFAULT);
	$probeHMAC = md5($Salt . rand() . $email);

	$secret = Middleware::generateSharedSecret(); 
	try {
		$result = $conn->query(
			"insert into users (email, password, probeHMAC, secret) VALUES (?,?,?,?)",
			array($email,$password,$probeHMAC,$secret)
			);
	}
	catch (DatabaseError $e) {
		
		if ($e->getCode() == 1062) {
			throw new ConflictError("A user account with this email address has already been registered");
		} else {
			throw $e;
		}
	}
	return $app->json(array(
		'success'=>true,
		'status'=>'pending',
		'secret'=>$secret
		),
		201
	);
});

$app->post('/prepare/probe', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$conn = $app['service.db'];

	$row = $app['db.user.load']->load($req->get('email'));
	checkUser($row);

	Middleware::verifyUserMessage($req->get('email') . ':' . $req->get('date'), $row['secret'], $req->get('signature'));

	$probeHMAC = Middleware::generateSharedSecret(32);

	$conn->query("update users set probeHMAC = ? where email = ?",
		array($probeHMAC, $req->get('email'))
		);

	return $app->json(array(
		'success' => true,
		'probe_hmac' => $probeHMAC
		));
});

$app->post('/register/probe', function(Request $req) use ($app) {
	checkParameters($req, array('email','signature'));

	$conn = $app['service.db'];
	$row = $app['db.user.load']->load($req->get('email'));
	checkUser($row);

	$check_uuid = md5($req->get('probe_seed') . '-' . $row['probeHMAC']);
	if ($check_uuid != $req->get('probe_uuid')) {
		return $app->json(array(
			'success' => false,
			'error' => 'Probe seed and HMAC verification failed'
			), 403);
	}

	$secret = Middleware::generateSharedSecret();

	try {
		$conn->query("insert into probes (uuid,userID,secret,countrycode,type) values (?,?,?,?,?)",
			array(
				$req->get('probe_uuid'), $row['id'], $secret, 
				$req->get('country_code'), $req->get('probe_type')
				)
			);
	}
	catch (DatabaseError $e) {
		if ($e->getCode() == 1062) {
			throw new ConflictError("A probe with this UUID already exists");
		} else {
			throw $e;
		}
	}

	return $app->json(array(
		'success' => true,
		'secret' => $secret), 201);
});

$app->get('/request/httpt', function(Request $req) use ($app) {
	checkParameters($req, array('probe_uuid','signature'));
	$conn = $app['service.db'];

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);
	Middleware::verifyUserMessage($req->get('probe_uuid'),  $probe['secret'], $req->get('signature'));

	$row = $app['db.url.load']->get_next();
	if ($row == null) {
		return $app->json(array(
			'success' => false,
			'error' => 'No queued URLs found'
			), 404);
	}
	error_log("Got URL: " . $row['URL']);
	$ret = array(
		'success' => true,
		'url' => $row['URL'],
		'hash' => $row['hash']
		);
	$conn->query(
		"update tempURLs set lastPolled = now(), polledAttempts = polledAttempts + 1 where tempID = ?",
		array($row['tempID'])
		);
	$app['db.probe.load']->updateReqSent($probe['uuid']);

	return $app->json($ret, 200);
});

$app->post('/response/httpt', function(Request $req) use ($app) {
	checkParameters($req, 
		array('probe_uuid','url','config','ip_network','status',
		'http_status','date','signature','network_name')
		);

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);
	$url = $app['db.url.load']->load($req->get('url'));

	Middleware::checkMessageTimestamp($req->get('date'));

	Middleware::verifyUserMessage(
		implode(":", array(
			$req->get('probe_uuid'),
			$req->get('url'),
			$req->get('status'),
			$req->get('date'),
			$req->get('config')
			)
		),
		$probe['secret'],
		$req->get('signature')
	);

	$conn = $app['service.db'];
	$conn->query(
		"insert into results(urlID,probeID,config,ip_network,status,http_status,network_name, created) values (?,?,?,?,?,?,?,now())",
		array(
			$url['tempID'],$probe['id'], $req->get('config'),$req->get('ip_network'),
			$req->get('status'),$req->get('http_status'), $req->get('network_name')
		)
	);

	$conn->query(
		"update tempURLs set polledSuccess = polledSuccess + 1 where tempID = ?",
		array($url['tempID'])
		);

	$app['db.probe.load']->updateRespRecv($probe['uuid']);

	return $app->json(array('success' => true, 'status' => 'ok'));

});

$app->get('/config/{version}', function (Request $req, $version) use ($app) {
	if (!$version) {
		throw new InputError();
	}
	if ($version != 'latest' || !is_numeric($version)) {
		throw new InputError();
	}
		
	// fetch and return config here
	
	return "";
});

$app->post('/update/gcm', function(Request $req) use ($app) {
	checkParameters($req, array('gcm_id','probe_uuid','signature'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));

	Middleware::verifyUserMessage($req->get('gcm_id'), $probe['secret'],  $req->get('signature'));

	$conn = $app['service.db'];
	$conn->query("update probes set gcmRegID=?, lastSeen=now(), gcmType=?, frequency=? where uuid=?",
		array(
			$req->get('gcm_id'),
			$req->get('gcm_type'),
			$req->get('frequency'),
			$req->get('probe_uuid'),
			));

	return $app->json(array('success'=>true,'status'=>'ok'));
});

$app->get('/status/ip/{client_ip}', function(Request $req, $client_ip) use ($app) {
	# Get information about an IP.  If {client_ip} is omitted, use request originating IP
	checkParameters($req, array('probe_uuid','signature','date'));

	$probe = $app['db.probe.load']->load($req->get('probe_uuid'));
	checkProbe($probe);

	Middleware::checkMessageTimestamp($req->get('date'));

	Middleware::verifyUserMessage($req->get('date'), $probe['secret'], $req->get('signature') );

	if ($client_ip) {
		$ip = $client_ip;
	} else { 
		$ip = $req->getClientIp();
	}

	# run a whois query for the IP address
	$cmd = "/usr/bin/whois '" . escapeshellarg($ip) . "'";

	$fp = popen($cmd, 'r');
	if (!$fp) {
		throw new IpLookupError();
	}
	$descr = '';
	while (!feof($fp)) {
		$line = fgets($fp);
		$parts = explode(":",chop($line));
		if ($parts[0] == "descr") {
			# save the value of the last descr tag, seems to work in most cases
			$descr = trim($parts[1]);
		}
	}
	fclose($fp);

	if (!$descr) {
		throw new IpLookupError();
	}

	return $app->json(array('success'=>true,'ip'=>$ip, 'isp'=>$descr));
})
->value('client_ip',''); # make client_ip arg optional


#--------- Administrator Functions

$app->get('/list/users/{status}', function (Request $req, $status) use ($app) {
	checkParameters($req, array('email','date','signature'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$user = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($req->get('date'), $user['secret'], $req->get('signature'));
	checkAdministrator($user);

	$conn = $app['service.db'];

	# if status has been supplied, only list users with that status
	if ($status) {
		$rs = $conn->query("select email, fullName, createdAt, status from users where status = ?",
			array($status));
	} else {
		$rs = $conn->query("select email, fullName, createdAt, status from users");
	}

	$out = array();
	while ($row = $rs->fetch_assoc()) {
		$out[] = $row;
	}

	return $app->json(array("success"=>true,"users"=>$out));
})
->value('status','');

$app->post('/status/user/{user}', function (Request $req, $user) use ($app) {
	# Set the status of a user
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));

	Middleware::verifyUserMessage($user . ":". $req->get('status'), $adminuser['secret'], $req->get('signature'));
	checkAdmin($adminuser);

	$conn = $app['service.db'];
	$conn->query("UPDATE users set status = ? where email = ?",
		array($req->get('status'), $user));

	if ($conn->affected_rows == 0) {
		throw new UserLookupError();
	}

	return $app->json(array('success' => true, "status" => $req->get('status'), "email" => $user));
});

$app->post('/status/probe/{uuid}', function (Request $req, $uuid) use ($app) {
	# Set the status of a probe
	checkParameters($req, array('email','signature','date'));

	Middleware::checkMessageTimestamp($req->get('date'));

	$adminuser = $app['db.user.load']->load($req->get('email'));
	Middleware::verifyUserMessage($uuid . ":". $req->get('status'), $adminuser['secret'], $req->get('signature'));
	checkAdmin($adminuser);

	if (!($req->get('status') == "enabled" || $req->get('status') == 'disabled')) {
		return $app->json(array(
			"success"=> false,
			"error"=> "Unknown status: " . $req->get('status')
		), 500);
	}

	$conn = $app['service.db'];
	$conn->query("UPDATE probes set enabled = ? where uuid = ?",
		array($req->get('status') == "enabled" ? 1 : 0, $uuid));

	if ($conn->affected_rows == 0) {
		throw new ProbeLookupError();
	}

	return $app->json(array('success'=> true, "status"=> $req->get('status'), "email"=> $user));
});


$app->run();

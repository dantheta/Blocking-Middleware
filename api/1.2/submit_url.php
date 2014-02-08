<?php
	include('libs/DB.php');
        include('libs/password.php');
        include('libs/compat.php');
        include('libs/pki.php');

        header('Content-type: application/json');
        header("API-Version: $APIVersion");


        $email = mysql_real_escape_string($_POST['email']);
	$signature = $_POST['signature'];
	$url = mysql_real_escape_string($_POST['url']);

        $result = array();
        $result['success'] = false;
	
	if(empty($email) || empty($signature))
        {
                $result['error'] = "Email address or signature were blank";
                $status = 400;
        }
        else
        {
                $Query = "select secret,status from users where email = \"$email\"";
		$mySQLresult = mysql_query($Query);

                if(mysql_errno() == 0)
                {
			if(mysql_num_rows($mySQLresult) == 1)
			{

				$row = mysql_fetch_assoc($mySQLresult);

				if(Middleware::verifyUserMessage($url, $row['secret'],$signature))
				{
					$Query = "insert into tempURLs (URL,hash,lastPolled) VALUES (\"$url\",\"$md5\",\"2013-12-01 00:00:01\")";
        	                        mysql_query($Query);
	
                	                if(mysql_errno() == 0)
                        	        {
                                	        $result['success'] = true;
                                        	$result['uuid'] = mysql_insert_id();
                                                $status = 201;
                                	}
                                	else
                                	{
                                        	$result['error'] = mysql_error();
                                                $status = 500;
                                	}

				}
				else
				{
					$result['error'] = "Signature verification failed";
                                        $status = 403;
				}
			}
			else
			{
				$result['error'] = "No matches in DB. Please contact ORG support";
                                $status = 404;
			}
                }
                else
                {
                        $result['error'] = mysql_error();
                        $status = 500;
                }
        }

        if ($status) {
                http_response_code($status);
        }
        print(json_encode($result));

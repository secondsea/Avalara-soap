<?php
/*
	KEEP RESTRICTED TO INDIVIDUAL ADMIN
*/

//doctrine mysql values
$doc_user = 'doc';
$doc_pass = 'd0ctr1n3';
$doc_host = '10.1.101.101;port=6603';
$doc_db	= 'doctrine';

// connect to mysql DB, select DB
function dbConnect()
{
	$host = "10.1.101.101:6603";
	$user = "web";
	$pass = "the1_web2_PASS";
	$db = "annieselke";
	
	try
	{
		$connect = mysql_connect($host,$user,$pass);
		if ($connect === false)
			throw new Exception('Web connect error: ' . mysql_error());
			
		if (mysql_select_db($db) === false)
			throw new Exception('select DB error: ' . mysql_error());
	}
	catch (Exception $e)
	{
		return $e->getMessage();
	}

	return $connect;
}

function webConnect ($db = 'annieselke')
{
	$host = "10.1.101.101;port=6603";
	$user = "web";
	$pass = "the1_web2_PASS";

	try
	{
		$connect = new PDO
		(
			'mysql:host=' . $host . ';dbname=' . $db
		,	$user
		,	$pass
		);
	}
	catch (PDOException $e )
	{
		die( 'Unable to connect to database.' );
	}
	//mssql_select_db($db) or die(mssql_get_last_message());

	//be sure to catch all exceptions if below is true...
	$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $connect;
}

// connect to resq
function resqConnect ($debug = false)
{
	$test_env = DEFINED('TEST') && TEST === true;

	$db = 'sandbox';
	$host = $test_env ? 'pchsqldev' : 'pchsql'; // matches freetds alias 
	$user = 'PCH4_w3b5-Usr6';
	$pass = 'p33c33aitch_w3b-p455';
	try
	{
		$string = "dblib:host=$host;dbname=$db";
		$connect = new PDO($string, $user, $pass);
	}
	catch (PDOException $e)
	{
		return $e->getMessage();
	}
	
	//be sure to catch all exceptions if below is true...
	if ($debug === false)
   		$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
	return $connect;
}

//non-pdo connect
function resqConnectOld()
{
	$test_env = DEFINED('TEST') && TEST === true;

	$db = 'sandbox';
	$host = $test_env ? 'pchsqldev' : 'pchsql'; // matches freetds alias 
	$user = 'PCH4_w3b5-Usr6';
	$pass = 'p33c33aitch_w3b-p455';
	
	try
	{
		$resq = mssql_connect($host, $user, $pass);
		if ($resq === false)
			throw new Exception('Resq connect error: ' . mssql_get_last_message());
			
		if (mssql_select_db($db, $resq) === false)
			throw new Exception('Resq db select error: ' . mssql_get_last_message());
	}
	catch (Exception $e)
	{
		return $e->getMessage();
	}
	
	return $resq;
}

//connect to active directory with pseudo domain admin
function adConnect()
{
	$ad = ldap_connect('colodc1.pineconehill.com') or die('unable to connect');
	ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);
	ldap_bind($ad, "pineconehill\PSEUDO", "@r0und_th3_CL0CK") or die('incorrect user/pass');
	return $ad;
}

// replace ' with '' to avoid malformed SQL queries
function sqlQuotes (&$str)
{
$str = str_replace("'","''",stripslashes($str));
}

// evaluate an SQL query, return first cell of first row
function dbValue ($query)
{
	if ($query) {
	$data = mysql_fetch_row(mysql_query($query));
	return $data[0];
		}
	else return 0;
}


//checks to see if variable is loaded into session, if not, loads variable from db into session
function loadVar ($var)
{
if (!isset($_SESSION[$var])) $_SESSION[$var] = loadUser($var);
}

//saves session variable into corresponding DB var
function saveVar ($var)
{
saveData($var, $_SESSION);
}

//delete session variable
function varDel ($var)
{
unset($_SESSION[$var]);
}


//stores a session variable from user array <loadUser>, or can be used as varReg("variable", "value") <note:in latter usage variable cant be multiword.>
//use for vars that wont be stored in the db.
function varReg ($var, $array)
{
if (is_array($array))
	{
	$vars = explode(" ",$var);
	for ($i=0; $i<count($vars); $i++) 
		{
		if (isset($array[$vars[$i]]))
			{
			$_SESSION[$vars[$i]] = $array[$vars[$i]];
			} else echo "VarReg: " . $vars[$i] . " not present.<br>";
		}
	} else {
			$_SESSION[$var] = $array;
	 		}
}

// Example: saveData("cash networth", $userarray), or saveData("cash",$cash)
function saveData ($values, $array)
{
$id = $_SESSION['id'];
global $userdb;
if (is_array($array))
	{

	$items = explode(" ",$values);
	$update = "";
	$i = 0;
	while ($tmp = $items[$i++])
		{
		$values = $array[$tmp];
		if (is_numeric($values)) $update .= $tmp . "=" . $values;
		else
			{
			sqlQuotes($values);
			$update .= $tmp . "='" . $values ."'";
			}
		if (isset($items[$i])) $update .= ",";
		}
	} else {
			if (is_numeric($array)) $update = $values . "=" . $array;
			else
				{
				sqlQuotes($array);
				$update = $values . "='" . $array ."'";
				}
			}
mysql_query("UPDATE $userdb SET $update WHERE id=$id") or die(mysql_error());
}

//update an entire column's worth of values <num = numeric or not>
function updateCol ($col, $value, $num = 1)
{
global $userdb;
if (!$num) $value = "'value'";
mysql_query("UPDATE $userdb SET $col=$value") or die("error updating column");
}

function sidVerify ()
{
global $game, $sessiondb;
$sid = $_COOKIE['sid'];
if (dbValue("select sid from $sessiondb where sid=$sid"))
	{
	session_id("$sid");
	session_start();
	} else reLogin();
} 
?>

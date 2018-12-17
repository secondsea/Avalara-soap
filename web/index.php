<?php


$src = realpath(dirname(__FILE__));

// include all our lib files
$includes = array
	(
		'daemon.class.php'
	,	'resq.class.php'
	,	'resqDb/Core.php'
	,	'resqDb.php'
	,	'pch_db.php'
	,	'pch.class.php'
	,	'pchArray.class.php'
	,	'pchImpex.php'
	);
foreach($includes as $inc)
{
	include_once('../lib/' . $inc);
}

ini_set('memory_limit', '256M');
set_time_limit(0);

date_default_timezone_set('America/New_York');



$host = $_SERVER['HTTP_HOST'];
$_uri = $_SERVER['REQUEST_URI'];

$uri_prefix = '';
$_uriparts = explode('?', $_uri);
$uri = $_uriparts[0];

$_uri_params = false;

$actual_link = "http://{$host}{$uri}";





?>

<html>
	<head>
		<title>Avalara Q </title>
		
	</head>
	
	<body>
		
	<?php include ('../pages/header.php'); ?>
		
	<?php
	try
	{

		switch($uri)
		{
			case '/':
				include('../pages/landing.php');
			break;
		}
	} 
	catch (Exception $e) 
	{
		var_dump($e->getMessage());
		die();
	}
	catch (SoapFault $e) 
	{
		var_dump($e->getMessage());
		die();
	}
	?>
	
	</body>
	
</html>
		
<?php

die();

?>



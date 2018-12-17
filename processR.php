<?php

/**
 * here we bootstrap our lib files, and initiate ccq processing
 */

/**
 * includes:
 * 
 * daemon
 * captureq  // may not need
 * pchAvalaraQ
 * pchauth
 * resqDb, resqDb/Core   
 * 
 */


// include all our lib files
$includes = array
	(
		'daemon.class.php'
	,	'pch_db.php'
	,	'pchAvalaraR.php'
	,	'resq.class.php'
	,	'resqDb/Core.php'
	,	'resqDb.php'
	);

foreach($includes as $inc)
{
	require('lib/' . $inc);
}

$project_path = realpath(dirname(__FILE__) . '/..');
		
// check daemon entry
$is_test = stripos($project_path, '/web/') === 0;
DEFINE('IS_TEST',$is_test);


set_time_limit(0);
date_default_timezone_set('America/New_York');


$dbtype = $is_test ? 'test' : 'live';
 

 



// check running processes for this so we don't run multiple concurrently
//$dupe_check_cmd = "ps aux | grep csccq | awk 'END { print NR}'";
$dupe_check_cmd = "ps aux | grep avalaraq | awk 'END { print NR}'";
 

 

//echo "running ccq for CS transactions\n\n\n";
echo "running avalaraq for tax transactions\n\n\n";


try
{
	$ava = new pchAvalara();

	$ava->processTax();

	$ava->processResults();
}
catch(Exception $e)
{
	echo "ERROR: " . $e->getMessage() . "\n\n";
}


//$daemon->end();

?>

<?php
$project_path = realpath(dirname(__FILE__));

var_dump($project_path);

echo "##########################begin #######################################################################################\n\nstarting\n\n";
$includes = array(
	'taxtmp.php'
,	'resqDb/Core.php'
,	'resq.class.php'
,	'reztmp.php'
,	'pchAvalaraAuth.php'
,	'pch_db.php'
,	'daemon.class.php'
,	'AvaTax/Utils.php'
,	'AvaTax/Address.php'
,	'AvaTax/Line.php'
,	'AvaTax/BaseResult.php'
,	'AvaTax/GetTaxResult.php'
,	'AvaTax/Message.php'
,	'AvaTax/TaxLine.php'
, 	'AvaTax/TaxDetail.php'
,	'AvaTax/GetTaxRequest.php'
,	'AvaTax/AvalaraSoapClient.php'
,	'AvaTax/DynamicSoapClient.php'

,	'AvaTax/Enum.php'
,	'AvaTax/SeverityLevel.php'
,	'AvaTax/DetailLevel.php'

,	'AvaTax/DocumentType.php'
,	'AvaTax/ServiceMode.php'
,	'AvaTax/TaxServiceSoap.php'
);

foreach($includes as $inc) {
//	echo "include file '" . $project_path . '/lib/' . $inc . "' \n";
	require($project_path . '/lib/' . $inc);
}


require('Credentials.php');
// matt damon


$is_test = stripos($project_path, '/web/') !== false; 

echo 'is_test';
var_dump($is_test);
die();

DEFINE('IS_TEST',$is_test);


$dbtype = $is_test ? 'test' : 'live';
$daemon = new daemon('avalaraq', $dbtype);

// and skip if it's already running
$run_process = $daemon->start();
if ( ! $run_process )
	return;


date_default_timezone_set('America/New_York');
$pch=new pchAvalaraAuth();
$rez=new REZTMP();
$rez->setvars();
//$pch=new pchAvalaraAuth();
$pch->taxService = new TaxServiceSoap('Development');
$pch->taxRequest = new GetTaxRequest();
$pch->setHeader();
$pch->resq=$rez;
$pch->processTax();
$pch->processResults();
$daemon->end();



?>
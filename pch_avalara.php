<?php
echo "starting";
$includes = array('taxtmp.php','resqDb/Core.php','resq.class.php', 'reztmp.php','pchAvalaraAuth.php', 'AvaTax/TaxServiceSoap.php','AvaTax/SeverityLevel.php','AvaTax/Utils.php', 'pch_db.php');
foreach($includes as $inc) {
	//echo " include file " . $inc, PHP_EOL;;
	include('./lib/' . $inc);
}
require('Credentials.php');

date_default_timezone_set('America/New_York');
$pch=new pchAvalaraAuth();
$pch->processq();	
$rez=new REZTMP();
$rez->setvars();


$pch=new pchAvalaraAuth();
use AvaTax\Address;
use AvaTax\DetailLevel;
use AvaTax\GetTaxRequest;
use AvaTax\Line;
use AvaTax\SeverityLevel;
//use AvaTax\TaxOverride;
//use AvaTax\TaxOverrideType;
use AvaTax\TaxServiceSoap;
// set origin address 

$pch->taxService = new TaxServiceSoap('Development');
$pch->taxRequest = new GetTaxRequest();
$pch->setHeader();
//$pch->getTaxRequest->setDetailLevel(DetailLevel::$Tax);
//$Origin = $pch->createOriginAddress();  
//$getTaxRequest->setOriginAddress($Origin);

$pch->resq=$rez;


$pch->processTax();






?>
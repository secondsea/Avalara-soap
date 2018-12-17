<?php
//$includes = array( 
//'AvaTax/Address.php'
//'taxtmp.php',	'resqDb/Core.php',	'resq.class.php', 'reztmp.php','pchAvalaraAuth.php' 'AvaTax/TaxServiceSoap.php','AvaTax/SeverityLevel.php','AvaTax/Utils.php'
//, 'AvaTax/AvalaraSoapClient.php',
// 'AvaTax/ATConfig.php'
//'taxtmp.php','line.php','GetTaxRequest.php',	'resqDb/Core.php',	'resq.class.php', 'reztmp.php' , 'Address.php', 'taxService.php'
//

//);





//foreach($includes as $inc) {
	//echo "  includes loop  ",PHP_EOL;;
	//echo " include file " . $inc, PHP_EOL;;
	//include('./lib/' . $inc);
	
//}

//Holding Array for Contract numbers, item numbers, tax amounts for inserting in table.


use AvaTax\Address;
use AvaTax\Line;
use AvaTax\GetTaxRequest;
use AvaTax\DetailLevel;
use AvaTax\SeverityLevel;
/**
 * Attempt capture on transactions listed in resq ccq view
 * Record response in corresponding resq cc_tran table
 * @todo if cc_response resq call fails, send an email with the transaction response information, and break the loop!
 * @package pchAuth
 * @subpackage cc
 */
class pchAvalaraAuth
{
	/*
	 * @var resqDb
	 */
	protected $_resq;

	/**
	 * did anything error, warranting an email send?
	 * @var boolean
	 */
	protected $_fail = false;

	/**
	 * what Exceptions have we collected to email out?
	 * @var array
	 */
	protected $_fail_messages = array();

	/**
	 * what server is this? (used for error messages)
	 * @var string
	 */
	protected $_server;

	/**
	 * CLI, or web?
	 * @var boolean
	 */
	protected $_cli;

	/**
	 * Newline?
	 * @var string
	 */
	protected $_nl;

	/**
	 * we have to run the pending procs 3 times, one for each db.
	 * @var array
	 */
	protected $_companies = array('dash', 'fresh', 'outlet', 'luxe','home', 'pch'  );
	//protected $_companies = array('dash', 'pch', 'outlet', 'fresh', 'luxe', 'home');
	//public $GetTaxRequest;
	public $Today;
	public $taxRequest;
	public $taxService;
	public $taxResult;
	public $taxRecord=array();
	public $contractNO;

	public function __construct(){
		ini_set('mssql.timeout', 1200);
		date_default_timezone_set('America/New_York');	
		$this->Today=$Today= date("Y-m-d");		
		$itemzTax = array();
	}  // end contruct
	
	public function setHeader() {
		echo "in header \n";
		//$this->getTaxRequest = new GetTaxRequest();
		$this->taxRequest->setCompanyCode("fatest");
		$this->taxRequest->setDocType("SalesInvoice");
		$this->taxRequest->setDocDate($this->Today);
		$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		$Origin = $this->createOriginAddress();  
		$this->taxRequest->setOriginAddress($Origin);
	}  // end set Header
	
	
	public function processTax() {
echo "in process tax \n\n";
		foreach ($this->_companies as $biz) {
echo $biz . "\n";
			$this->resq->getDatabase($biz);
//echo $this->resq->database . "\n";
			$contracts=$this->resq->getContracts();
var_dump($contracts);
			foreach ($contracts as $contract)  {
				echo "**********CONTRACT NUMBER: " . $contract["ITEMNO"] ."*************************************" , PHP_EOL;
				$this->contractno=$contract["ITEMNO"];
				$this->contractInfo=$this->resq->getContractInfo($this->contractno);
//var_dump($this->contractInfo);
				$this->taxRequest->setDocCode($this->contractno); // contract no
				$this->taxRequest->setCustomerCode($this->contractInfo['CustomerNo']);	
				$Destination=$this->createDestinationAddress();
				$this->taxRequest->setDestinationAddress($Destination);
				$this->taxRequest->setAddressCode($Destination);
//var_dump($Destination); 
				$items=$this->resq->getContractItems($this->contractno);
				$this->createItemRecords($items);
				$this->initTaxRecordArray();
				$theLinz=$this->createItemLines($items);
//$this->itemzTax[0]['type']='CONTRACT';
				$this->itemzTax[0]['itemno']=$this->contractno;
				$this->taxRequest->setLines($theLinz);
//var_dump($this->taxRequest);
				$this->getTax();
				echo 'GetTax is: ' . $this->taxResult->getResultCode() . "\n";
				if ($this->taxResult->getResultCode() == SeverityLevel::$Success) {
					$this->processTaxResult();
				} else {
					$this->processTaxError();
				} // end else condition sucess
			} // end for each contract
		} // end foreach biz
	}// end process tax
	
	public function initTaxRecordArray(){
		unset($this->taxRecord);	
		$this->taxRecord['type']= 'CONTRACT';
		$this->taxRecord['orderno']=$this->contractno;
		$this->taxRecord['itemno']=$this->contractno;
		$this->taxRecord['satus']='';
		$this->taxRecord['masterno']='';
		$this->taxRecord['message']='';
	} // end init Tax Record Array

	public function createItemRecords($items){
		unset($this->taxRecord);
		$this->taxRecord['type']= 'ITEM';
		$this->taxRecord['status']='PENDING';
		$this->taxRecord['message']='';
		$this->taxRecord['taxamount']=0.000;
		foreach ($items as $item) {
			$this->taxRecord['orderno']=$this->contractno;
			$this->taxRecord['itemno']=$item["CONITEMNO"];
			$this->taxRecord['masterno']=$item["MASTERNO"];
			$this->resq->insertTaxRecord($this->taxRecord);	
//	$this->taxRecord['masterno']='';
		} // end of foreach loop
	} // end create Item Records

	public function updateContractTaxRecord(){
		$this->taxRecord['type']= 'CONTRACT';
	//$this->taxRecord['orderno']=$contractno;
	//$this->taxRecord['orderno']=$this->itemzTax[0]['itemno'];
	//$this->taxRecord['itemno']=$this->itemzTax[0]['itemno'];
		$this->taxRecord['itemno']=$this->contractno;
	//$this->taxRecord['masterno']='';
	//$this->taxRecord['taxamount']=$tax;
	//$this->taxRecord['status']='SUCCESS';	
	//$this->taxRecord['message']='SUCCESS';
		$this->resq->updateTaxRecord($this->taxRecord);	
	} // end init Tax Record Array	
	
	public function updateItemTaxRecord($itemno,$tax){
	//$this->taxRecord['type']= 'ITEM';
		$this->taxRecord['itemno']=$itemno;
		$this->taxRecord['taxamount']=$tax;
		$this->resq->updateTaxRecord($this->taxRecord);	
	} // end create Item Records	
	
	
	public function getTax() {
		try{
	//$getTaxResult = $taxSvc->getTax($getTaxRequest);
			$this->taxResult = $this->taxService->getTax($this->taxRequest);
				//var_dump($this->taxResult);
		}
		catch(SoapFault $exception) {
			$message = "Exception: ";
			if ($exception) {
				$message .= $exception->faultstring;
			}
			echo $message . "\n";
			echo $this->taxService->__getLastRequest() . "\n\n\n\n";
			echo $this->taxService->__getLastResponse() . "\n \n\n\n  ";
		}
	}// end getTax


	public function processq() {
	//$this->initBatch();
	//if ( ! $this->_cli)
	//		ob_start();
		$this->_resq = new REZTMP('mssql');	
			
	}
	
	
	
	public function createOriginAddress (){
		$address = new Address();
		$address->setLine1("125 Pecks Rd");
		$address->setCity("Pittsfield");
		$address->setRegion("MA");
		$address->setPostalCode("01201");
		$address->setCountry("US");
		return $address;
	}  // end create Origin Address
	
	public function createDestinationAddress() {
		$destination=new Address();
		$destination->setLine1($this->contractInfo['Address1']);
		$destination->setLine2($this->contractInfo['Address2']);
		$destination->setLine3($this->contractInfo['Name']);
		$destination->setCity($this->contractInfo['City']);
		$destination->setRegion($this->contractInfo['State']);
		$destination->setPostalCode($this->contractInfo['Zip']);
		$destination->setCountry($this->contractInfo['Country']);
		return $destination;
	}  // end create Destination Address
	
	
	
	public function createItemLines($items) {
		$Linz = array();
		$idx = 0;
		$LN=1;
		unset($this->itemzTax);
		$this->itemzTax[$idx]=array();
		$this->itemzTax[$idx]['type']="CONTRACT";
//$this-itemzTax[$idx]
	 	foreach ($items as $item) {
			$Linz[$idx] = new Line();
			$Linz[$idx]->setNo($LN);
			$Linz[$idx]->setItemCode($item["MASTERNO"]);
			$Linz[$idx]->setDescription($item["DESCRIPTION"]);
			$Linz[$idx]->setQty($item["QTY"]);
			$Linz[$idx]->setAmount($item["PRICE"]);
			$LN++;
			$idx++;
			$this->itemzTax[$idx]=array();
			$this->itemzTax[$idx]['type']='ITEM';
			$this->itemzTax[$idx]['itemno']=$item["CONITEMNO"];
		} // end of foreach loop
		return $Linz;
	} // end createItemLines
	
	
	
	public function processTaxResult() {
//Success - Display GetTaxResults to console
//Document Level Results
//echo "DocCode: " . $taxMatrix->getDocCode() . "\n";
		echo "TotalAmount: " . $this->taxResult->getTotalAmount() . "\n";
		echo "TotalTax: " . $this->taxResult->getTotalTax() . "\n";
//$totalTax=$this->taxResult->getTotalTax();
		$this->taxRecord['taxamount']=$this->taxResult->getTotalTax();	
		$this->taxRecord['status']='SUCCESS';	
		$this->taxRecord['message']='SUCCESS';
		$this->updateContractTaxRecord();
//$this-> 	resq->updateTaxTable($this->itemzTax[0]['itemno'], $totalTax);		
//Line Level Results (from TaxLines array class)
		$this->taxRecord['type']='ITEM';
		foreach ($this->taxResult->getTaxLines() as $currentTaxLine) {
			echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $currentTaxLine->getTax() . " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
			$lnNo=$currentTaxLine->getNo();
//$tax=$currentTaxLine->getTax();
//$this->updateItemTaxRecord($this->itemzTax[$lnNo]['itemno'],$tax);
			$this->taxRecord['itemno']=$this->itemzTax[$lnNo]['itemno'];
	        $this->taxRecord['taxamount']=$currentTaxLine->getTax();
//var_dump($this->taxRecord);
			$this->resq->updateTaxRecord($this->taxRecord);	
//$this->resq->insertTaxRecord($this->itemzTax[$lnNo]['itemno'],$tax);
//$this->insertTaxRecord($this->itemzTax[$lnNo]['itemno'],$tax);
//Line Level Results
			foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails) {
				echo "          Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
			} // end foreach currentTaxLines
			echo"\n";
		} // for each get Tax Matrix 		
//var_dump($this->itemzTax);
	}
	

	public function processTaxError() {
		$this->taxRecord['status']='ERROR';	
		$this->taxRecord['taxamount']=0.00;
		foreach ($this->taxResult->getMessages() as $message) {
			echo $message->getName() . ": " . $message->getSummary() . "\n";
			$this->taxRecord['message']= $this->taxRecord['message'] . " " .  $message->getSummary();
			$this->updateContractTaxRecord();		
			$this->resq->updateTaxRecordBulk($this->taxRecord);		
		}  // end for each tax result  
	}  // end process tax error
	
	
	private function get_conn() {
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
		
		return $connect;
	}
	
	
	
//private function initBatch () {
	
//}	
	
	
	
	
	
	
	
	

}  // end class


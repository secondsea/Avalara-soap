<?php


$includes = array( 

//'AvaTax/Address.php'

//'taxtmp.php',	'resqDb/Core.php',	'resq.class.php', 'reztmp.php','pchAvalaraAuth.php' 'AvaTax/TaxServiceSoap.php','AvaTax/SeverityLevel.php','AvaTax/Utils.php'
//, 'AvaTax/AvalaraSoapClient.php',
// 'AvaTax/ATConfig.php'
//'taxtmp.php','line.php','GetTaxRequest.php',	'resqDb/Core.php',	'resq.class.php', 'reztmp.php' , 'Address.php', 'taxService.php'

//


);





foreach($includes as $inc) {
	//echo "  includes loop  ",PHP_EOL;;
	//echo " include file " . $inc, PHP_EOL;;
	include('./lib/' . $inc);
	
}

//Holding Array for Contract numbers, item numbers, tax amounts for inserting in table.
$itemzTax = array();

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
    public $taxRecord =array();
	public function __construct()
	{
		
		ini_set('mssql.timeout', 1200);
		date_default_timezone_set('America/New_York');	
		$this->Today=$Today= date("Y-m-d");		
 
		
		
	}
	
	
	public function setHeader() {
		$this->taxRequest = new GetTaxRequest();
		$this->taxRequest->setCompanyCode("fatest");
		$this->taxRequest->setDocType("SalesInvoice");
		$this->taxRequest->setDocDate($this->Today);
		$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		$Origin = $this->createOriginAddress();  
		$this->taxRequest->setOriginAddress($Origin);
		
	}
	
	
	public function processTax() {
		echo "in process tax \n\n";
	//	foreach ($this->_companies as $biz) {
		//echo $biz . "\n";
	//	$this->resq->getDatabase($biz);
		//echo $this->resq->database . "\n";
		$contracts=$this->resq->getContracts();
//var_dump($contracts);
		foreach ($contracts as $contract)  {
		// create a tax record with pending status
			unset($this->taxRecord);
	echo "**********CONTRACT NUMBER: " . $contract["ITEMNO"] ."*************************************" , PHP_EOL;
			$contractno=$contract["ITEMNO"];
			//$this->taxRecord['type']= 'CONTRACT';
			//$this->taxRecord['orderno']=$contractno;
			//$this->taxRecord['itemno']=$contractno;
			//$this->taxRecord['status']='PENDING';	
			$this->resq->insertTaxRecord($this->taxRecord);
			$contractInfo=$this->resq->getContractInfo($contractno);
		var_dump($contractInfo);
			$this->taxRequest->setDocCode($contractno); // contract no
			$this->taxRequest->setCustomerCode($contractInfo['CustomerNo']);	
			$Destination=$this->createDestinationAddress($contractInfo);
        var_dump($Destination); 
			$this->taxRequest->setDestinationAddress($Destination);
echo "after set Destination address \n";
			$this->taxRequest->setAddressCode($Destination);
echo "after set Address code \n";
			$taxRecord['type']='ITEM';
			$items=$this->resq->getContractItems($contractno);
echo "after get Contract items \n";
		    $theLinz=$this->createItemLines($items);
echo "after create line items \n";
		    //$this->itemzTax[0]['type']='CONTRACT';
			$this->itemzTax[0]['itemno']=$contractno;
			$this->taxRequest->setLines($theLinz);
echo "after set Lines \n";
			$this->getTax($this->taxRequest);
echo "after get tax \n";
		echo 'GetTax is: ' . $this->taxResult->getResultCode() . "\n";
		if ($this->taxResult->getResultCode() == SeverityLevel::$Success) {
				$this->processTaxResult();
		} else {
			foreach ($this->taxResult->getMessages() as $message) {
					echo $message->getName() . ": " . $message->getSummary() . "\n";
				}  // end foreach get tax result (not sucessful)
		} // end else condition sucess


		//$this->



		} // end for each contract
	//	$rez








		} // end foreach biz
		
		
		
	}// end process tax
	



	public function getTax($taxRequest) {
		try{
echo "taxRequest dump \n";
	 $getTaxResult = $taxSvc->getTax($taxRequest);
	
				//$this->taxResult = $this->taxService->getTax($this->taxRequest);
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
	}
	
	
	//public function setTaxHeading($contract, $taxDoc) {
			//	$taxDoc->setCompanyCode($contract['Name']);
				//$taxDoc->setDocCode($contractno);    // This is optional 
				//$taxDoc->setDocType("SalesInvoice");  //  This is required  what do I put in here????
			//	$taxDoc->setDocDate($Today);   
		//		$taxDoc->setCustomerCode($contractInfo['CustomerNo']);
		
	//}
		
	
	
	
	
	
	
	
	public function createDestinationAddress($contractInfo) {
		$destination=new Address();
		$destination->setLine1($contractInfo['Address1']);
		$destination->setLine2($contractInfo['Address2']);
		$destination->setLine3($contractInfo['Name']);
		$destination->setCity($contractInfo['City']);
		$destination->setRegion($contractInfo['State']);
		$destination->setPostalCode($contractInfo['Zip']);
		$destination->setCountry($contractInfo['Country']);
		return $destination;
	
	}
	
	
	
		public function createItemLines($items) {
			$Linz = array();
			$idx = 0;
			$LN=1;
			unset($this->itemzTax);
			$this->itemzTax[$idx]=array();
			$this->itemzTax[$idx]['type']="CONTRACT";
			//$this-itemzTax[$idx]
		 	foreach ($items as $item) {
				$this->createTaxRecord($item);
				$Linz[$idx] = $this->createNewLine($item,$LN);
				//$Linz[$idx] = new Line();
				//$Linz[$idx]->setNo($LN);
				//$Linz[$idx]->setItemCode($item["MASTERNO"]);
				//$Linz[$idx]->setDescription($item["DESCRIPTION"]);
				//$Linz[$idx]->setQty($item["QTY"]);
				//$Linz[$idx]->setAmount($item["PRICE"]);
				$LN++;
				$idx++;
				$this->itemzTax[$idx]=array();
			    $this->itemzTax[$idx]['type']='ITEM';
				$this->itemzTax[$idx]['itemno']=$item["CONITEMNO"];
				
			} // end of foreach loop
			return $Linz;
		} // end createItemLines
	
	
	public function createTaxRecord($item){
		$this->taxRecord['itemno']=$item["CONITEMNO"];
		$this->taxRecord['masterno']=$item['MASTERNO'];
        $this->resq->insertTaxRecord($this->taxRecord);
	}

	public function createNewLine($item,$LN) {
		$Line = new Line();
		$Line->setNo($LN);
		$Line->setItemCode($item["MASTERNO"]);
		$Line->setDescription($item["DESCRIPTION"]);
		$Line->setQty($item["QTY"]);
		$Line->setAmount($item["PRICE"]);

	}
	
public function processTaxResult() {
	//Success - Display GetTaxResults to console
	//Document Level Results
	//echo "DocCode: " . $taxMatrix->getDocCode() . "\n";
	echo "TotalAmount: " . $this->taxResult->getTotalAmount() . "\n";
	$this->taxRecord['taxAmount']=$this->taxResult->getTotalTax();
	echo "TotalTax: " . $this->taxResult->getTotalTax() . "\n";
	//$totalTax=$this->taxResult->getTotalTax();
	$this->taxRecord['itemno']=$this->itemzTax[0]['itemno'];
	$this->taxRecord['status']='SUCCESS';
	$this->taxRecord['type']='CONTRACT';
	$this->taxRecord['message']='';
    $this->resq->updateTaxTable($this->taxRecord);		
	$this->taxRecord['type']='ITEM';
		//Line Level Results (from TaxLines array class)
	foreach ($this->taxResult->getTaxLines() as $currentTaxLine) {
		$this->taxRecord['itemno']=$this->itemzTax[$lnNo]['itemno'];
		$this->taxRecord['taxamount']=$tax=$currentTaxLine->getTax();
		$this->resq->updateTaxRecord($this->titemzTax[$lnNo]['itemno'],$tax);
		echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $this->taxRecord['itemno'] . " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
					//$lnNo=$currentTaxLine->getNo();
					//$tax=$currentTaxLine->getTax();
//$this->resq->insertTaxRecord($this->itemzTax[$lnNo]['itemno'],$tax);
					//Line Level Results
		foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails) {
			echo "          Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
		} // end foreach currentTaxLines
		echo"\n";
	} // for each get Tax Matrix 		
		//var_dump($this->itemzTax);
		
}
	
	
	
	
	
	private function get_conn()
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
		
		return $connect;
	}
	
	
	
//private function initBatch () {
	
//}	
	
	
	
	
	
	
	
	

}


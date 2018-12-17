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
	protected $_companies = array( 'pch'  );
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
		//$this->getTaxRequest = new GetTaxRequest();
		$this->taxRequest->setCompanyCode("fatest");
		$this->taxRequest->setDocType("SalesInvoice");
		$this->taxRequest->setDocDate($this->Today);
		$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		$Origin = $this->createOriginAddress();  
		$this->taxRequest->setOriginAddress($Origin);
	}  // end set Header
	
	
	public function processTax() {
//echo "in process tax \n\n";
		foreach ($this->_companies as $biz) {
//echo $biz . "\n";
			$this->resq->getDatabase($biz);
//echo $this->resq->database . "\n";
		$contracts=$this->resq->getContracts();
//var_dump($contracts);
			foreach ($contracts as $contract)  {
				echo "**********CONTRACT NUMBER: " . $contract["ITEMNO"] ."*************************************" , PHP_EOL;
				$this->contractno=$contract["ITEMNO"];
				$items=$this->resq->getAvalaraItems($this->contractno);		
//var_dump($items);		
//var_dump($items[0]);		
				$this->contractInfo=$this->getContractInfo($items);



			//$this->contractInfo=$this->resq->getContractInfo($this->contractno);
var_dump($this->contractInfo);
echo "tag 1 \n";
				$this->taxRequest->setDocCode($this->contractno); // contract no
echo "tag 2 \n";
				$this->taxRequest->setCustomerCode($this->contractInfo['CustomerNo']);	
echo "tag 3 \n";
				$Destination=$this->createDestinationAddress();
echo "tag 4 \n";
				$this->taxRequest->setDestinationAddress($Destination);
echo "tag 5 \n";
				$this->taxRequest->setAddressCode($Destination);
var_dump($Destination); 
//				$items=$this->resq->getContractItems($this->contractno);
echo "tag 6 \n";	
			$this->createItemRecords($items);
echo "tag 7 \n";
				$this->initTaxRecordArray();
echo "tag 8 \n";
				$theLinz=$this->createItemLines($items);
//$this->itemzTax[0]['type']='CONTRACT';
echo "tag 9 \n";				$this->itemzTax[0]['itemno']=$this->contractno;
				$this->taxRequest->setLines($theLinz);
var_dump($this->taxRequest);
				$this->getTax();
echo "tag 10 \n";
				echo 'GetTax is: ' . $this->taxResult->getResultCode() . "\n";
echo "tag 11 \n";
				if ($this->taxResult->getResultCode() == SeverityLevel::$Success) {
					$this->processTaxResult();
				} else {
					$this->processTaxError();
				} // end else condition sucess
			} // end for each contract
		} // end foreach biz
	}// end process tax
	
	
	
	
	
	public function getContractInfo($firstItem){
		unset($contractInfo);
		$contractInfo['CustomerNo'] = $firstItem["CUSTOMERNO"];
		$contractInfo['Name'] =	$firstItem["COMPANY"];
		$contractInfo['Address1'] = $firstItem["SHIPADDRESS1"];
		//$contractInfo['Address2'] = $contract['SHIPADDRESS2'];
		$contractInfo['City']=$firstItem['SHIPCITY'];
		$contractInfo['State']=$firstItem['SHIPSTATE'];
		$contractInfo['Zip']=$firstItem['SHIPZIP'];
		//$contractInfo['Country']=$contract['SHIPCOUNTRY'];
		//contrractInfo['Phone']=contract['PHONE'];    
		return $contractInfo;
	}  // end get Contract Info
	
	
	
	
	
	
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
	//	$destination->setLine2($this->contractInfo['Address2']);
		$destination->setLine3($this->contractInfo['Name']);
		$destination->setCity($this->contractInfo['City']);
		$destination->setRegion($this->contractInfo['State']);
		$destination->setPostalCode($this->contractInfo['Zip']);
		//$destination->setCountry($this->contractInfo['Country']);
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
	
	
	
	
	
	/**
	 * catch doctrine_exceptions
	 * @param Exception $e - the exception being caught
	 * @param string|object $var - Doctrine variable to check for additional debug output (pass null/false to omit). Can also be a string message.
	 * @param string $error_title - header to show on error page
	 * @param array $options
	 *			string	message (vanity error message, defaults to @query-error)
	 *			string	route (route to redirect to, defaults to 'error')
	 *			boolean redirect (redirect with error message? default to true)
	 *			boolean log_only (only write a log, don't redirect, default to false)
	 *			boolean	send_email (send an email summary with dev error message, defaults to false)
	 *			string	mail_to (address to send email to, defaults to wd@)
	 *			string	details (extra info to include in dev message, defaults to empty)
	 *			boolean	backtrace (include backtrace in dev error message)
	 *			boolean	full_backtrace (if true, include symfony filters)
	 *			string	error_title (will override error_title param, for class specific defaults)
	 *
	 * @return void - (forwards to error page, or just returns if no redirect)
	 */
	public static function catcher($e, $var, $error_title, $_options = array())
	{
		
		var_dump($e->getMessage());
		return;
		
		
		$def_options = array(
			'message' => '@query-error'
		,	'route' => 'error'
		,	'redirect' => true
		,	'log_only' => false
		,	'send_email' => false
		,	'mail_to' => 'wd@pineconehill.com'
		,	'details' => false // so we can track extra info
		,	'backtrace' => true
		,	'full_backtrace' => false // if false, we remove all the filters from the backtrace (most of the info)
		,	'error_title' => false
		);

		// get exception class
		// even if we catch generic Exception, we can get the specific class that was thrown
		$exception_class = is_object($e) ? get_class($e) : false;

		// are there custom defaults for this exception class?
		// e.g. DupeOrderException just logs the error and goes on, set options.log_only => 1
		$all_exception_options = array(); // sfConfig::get('app_error_exception-options');
		$exception_options = ( ! empty($exception_class) && isset($all_exception_options[$exception_class]))
			? $all_exception_options[$exception_class]
			: false;
		
		if ( ! empty($exception_options))
			$def_options = array_merge($def_options, $exception_options);

		// set up options array
		$options = array_merge($def_options, $_options);

		// can we get additional debug info for this class?
		$this_method = false;
		if (is_object($var))
		{
			$debug_methods = array
				(
					'Doctrine_Query' => 'getQuery'
				,	'Doctrine_Record' => 'toArray'
				,	'Doctrine_Pager' => 'getQuery'
				,	'Doctrine_Collection' => 'toArray'
				);

			foreach ($debug_methods as $object => $method)
				if ($var instanceof $object)
					$this_method = $method;

			if ($this_method !== false)
			{
				try {$message = $var->$this_method();}
				catch (Doctrine_Exception $dupe) {$message = "[ debug error in $object" . "->$method() ]";}

				if (is_array($message)) // if array output of debug method, return string
					$message = '<pre>' . print_r($message, true) . '</pre>';
			}
			else $message = '';
		}
		// if no var was passed, omit this message
		else if (is_string($var))
			$message = "Debug Message: <pre>$var</pre>";
		else $message = ! empty($var) ? "[ '$var' either nonexistent object at exception time, or meant as a debug message ]<br />\n" : "<br />\n";




		// add exception message, get specific exception class
		if (is_object($e))
		{
			$dev_message = $e->getMessage();
			$message .= '<pre>-- DEV ERROR - ' . $exception_class . ' - "' . $dev_message . "\"\n</pre><br />\n";
		}


		// any extra info we're including?
		if ( ! empty($options['details']))
			$message .= $options['details'];


		// grab backtrace and attach pertinent info to dev message
		// this way we don't have to include __METHOD__ in catcher() call
		// do we want to include the entire stack? or just the most recent few? what about omitting all the sf filters?
		if ($options['backtrace'])
		{
			$backtrace_message = pch::get_backtrace($options['full_backtrace']);
			$message .= $backtrace_message;
		}


		// check for existing dev error in flash, if exists, tack onto end of current

		if($options['error_title'] !== false)
			$error_title = $options['error_title'];

		// log the dev error
		message::log_error($error_title . ': ' . $message);

		// are we sending an email?
		$send_email = ! $options['log_only'] && $options['send_email'];
		if ($send_email)
		{
			$mail_to = $options['mail_to'];
			$subject = $error_title;
			$email_message = str_replace(array('<br />', '<pre>', '</pre>'), array("\n", '', ''), $message);
			mail($mail_to, $subject, $email_message, 'From: dash_error@pineconehill.com');
		}
		
		// if prod env, only send out vanity error message
		$output_message = (SF_ENVIRONMENT == 'prod')
			? $options['message']
			: $message;


		// are we redirecting with an error message?
		$redirect = ! $options['log_only'] && $options['redirect'];
		if ($redirect === true)
		{
			// save this dev message in flash so we can use it elsewhere (error page email form, another catcher() call, etc)
			$action->setFlash('dev_error', $dev_message);
			message::error($error_title, $output_message, $options['route']);
		}
		else
			echo $output_message;

		return;
	}
	
	
	

}  // end class


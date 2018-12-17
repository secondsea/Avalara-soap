<?php

class pchAvalara
{
	/** @var resqDb  */
	protected $_resq;

	/** * did anything error, warranting an email send?   * @var boolean  	 */
	protected $_fail = false;

	/**  * what Exceptions have we collected to email out?	 * @var array	 */
	protected $_fail_messages = array();

	/**  * what server is this? (used for error messages)	 * @var string	 */
	protected $_server;

	/**	 * CLI, or web?  	 * @var boolean  	 */
	protected $_cli;

	/**	 * Newline?	 * @var string	 */
	protected $_nl;

	public $today;
	public $taxRequest;
	public $taxService;
	public $taxResult;
	public $taxRecord=array();
	public $taxNo;
	public $biz;
	public $status;
	public $type;
	public $attempts;
	public $recordCount;
	public $MSG;
	public $taxType;
	public $recordsWithNulls;
	public $nullFields;
	public $items=array();
	public $errMSG=array();
	public $errorMSG;
	public $contractInfo;
    //public $recordInfo();
	
	public function __construct()
	{
		ini_set('mssql.timeout', 1200);
		date_default_timezone_set('America/New_York');	
		
		$ava_includes = array(
			'reztmpR.php'
		,	'AvaTax/ATConfig.php'
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
		, 	'AvaTax/TaxRequest.php'		
		, 	'AvaTax/PostTaxRequest.php'
		,	'AvaTax/PostTaxResult.php'
		,	'AvaTax/DocumentType.php'
		,   'AvaTax/GetTaxHistoryRequest.php'
		,   'AvaTax/GetTaxHistoryResult.php'
		,   'AvaTax/AdjustTaxRequest.php'
		,   'AvaTax/AdjustTaxResult.php'
		,	'AvaTax/ServiceMode.php'
		,	'AvaTax/TaxServiceSoap.php'
		);
		
		foreach($ava_includes as $ava_inc)
		{
			require($ava_inc);
		}
		
		$rez=new REZTMP();
		$rez->setvars();
		
		try
		{
			echo "Initializing connection with Avalara \n";
			$this->taxService = new TaxServiceSoap('Development');
			$this->taxRequest = new GetTaxRequest();	
			$this->postTaxRequest = new PostTaxRequest();
			$this->taxHistory=new GetTaxHistoryRequest();
		}
		catch (Exception $e)
		{
			//return $e->getMessage();
			throw new Exception("Problem with Avalara Initialization: ". $e->getMessage() );
		}
		
		$this->resq=$rez;
		$this->today = date("Y-m-d");
//$project_path = realpath(dirname(__FILE__));		
//		$is_test = stripos($project_path, '/web/') !== false; // sfContext::getInstance()->getUser()->is_test();
		
		$is_test = DEFINED('IS_TEST') && IS_TEST;
		$dbtype = $is_test ? 'test' : 'live';
		
		$this->is_test = $is_test;
		$this->Env = $is_test ? 'test' : 'live';
		$this->setHeaders();
	}  // end contruct
	
	// set headers
	public function setHeaders() 
	{
		$this->setHeader("taxRequest");
		$this->setHeader("postTaxRequest");
	//	$this->setHeader("taxHistory");  
	}  // end set headers
	
	
	public function setHeader ($module)
	
	{
		echo "setting header for " . $module ."\n";
		$this->{$module}->setCompanyCode("fatest");
		//$this->{$module}->setDocType("SalesOrder");
		$this->{$module}->setDocType("SalesInvoice");
		//if ($module != 'taxHistory'){ 	
		$this->{$module}->setDocDate($this->today);
		//}
		if ($module == 'taxRequest') {
			$Origin = $this->createOriginAddress();  
			$this->taxRequest->setOriginAddress($Origin);
		}
		if ($module !='postTaxRequest' ){
			$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		}
		if ($module=='postTaxRequest') {
			$this->postTaxRequest->setCommit(true);
		}
	}  // end set Header
	
	
	// create origin address	
	
	public function createOriginAddress ()
	{
		$address = new Address();
		$address->setLine1("125 Pecks Rd");
		$address->setCity("Pittsfield");
		$address->setRegion("MA");
		$address->setPostalCode("01201");
		$address->setCountry("US");
		return $address;
	}  // end create Origin Address
	
		
	// The main method that drives the tax verification process	
	public function processTax()
	{
		echo "Checking for existing orders to process \n ";
		//  here get part of reversal contracts test for end of file 
		// loop through part of table and process piece meal$
		
		
		
		// sql server start at row  n  process  in loop update with new pointer 
		
		
		// since this is a one off  we can set absoluts for single and multiple views records in table
		
		$recCountMU=312;
		 	$recCountSI=1191;
		//$recCountMU=26;
		//$recCountSI=7;
		$recOffset=0;
		$blockLength=150;
		//dowhile  recoffset < reccount
		
		
		// $i = 0;
        // $num = 50;
         
       while( $recOffset <= $recCountSI) {
        $taxContracts=$this->resq->getTaxContractsSI($recOffset,$blockLength);  // use a different fetch that uses limit 100 or so
        
		//var_dump($taxContracts);
		  $this->processContractBlockSI($taxContracts);
		  $recOffset = $recOffset + $blockLength;
		  echo "echo record offset is " . $recOffset . ".\n";
		  
		  
		  
		  
         } // end while
		
		$recOffset=0;
		$blockLength=50;
 		while ($recOffset < $recCountMU) {
			$taxContracts=$this->resq->getTaxContractsMU($recOffset,$blockLength);   
			
		//var_dump($taxContracts);
			$this->processContractblockMU($taxContracts);
			
			  $recOffset = $recOffset + $blockLength;
			
		} // end while 
		
		
		
			
	
	}// end process tax
	
	
	
	
	
	
	
	
	public function processContractBlockSI($taxContracts)
	{
		foreach ($taxContracts as $this->contractInfo)  {
		echo "**********Record ITEM NUMBER: " . $this->contractInfo["contractno"] ."*************************************" , PHP_EOL;
		//$this->taxNo=$taxContract["ITEMNO"];
		//$this->taxType=$taxContract["ITEMTYPE"];
		//$this->postTaxRequest->setDocCode($this->taxNo);
		//$Destination=$this->createDestinationAddress();
		//$this->taxRequest->setDestinationAddress($Destination);
		//$this->taxRequest->setAddressCode($Destination);
     	//$this->itemzTax[0]['itemno']=$this->taxNo;
		//$this->taxRequest->setLines($theLine);
		$this->processTaxItem();
		} // end foreach
		
	}	//  end single item tax contracts 
	
		
		public function setSingleLineArray() {
			echo "setting up line \n";
		   // $OneLine = array();	
			$OneLine = new Line();
			// $LN=1;
			$OneLine->setNo(1);
			
			$OneLine->setItemCode($this->contractInfo["masterno"]);
			$OneLine->setDescription($this->contractInfo["DESCRIPTION"]);
			$OneLine->setQty($this->contractInfo["qty"]);
			$OneLine->setAmount($this->contractInfo["PRICEPER"]*-1);
			// if avalara doesnt take negative quantities may have to use rev price and set qty to one
			 
			 $this->itemzTax[01]['type']='ITEM';
			$this->itemzTax[01]['itemno']=$this->contractInfo["masterno"];
			 
			//$this->itemzTax[$idx]['type']='ITEM';
			//$this->itemzTax[$idx]['itemno']=$item["CONITEMNO"];
		$onelinenumber=$OneLine->getNo();
		var_dump($onelinenumber);
//var_dump ($OneLine);
		return $OneLine;
		 
			
			
			
			
			
			
		}  // end one line array set 
		
		public function processTaxItem() 
		
		
		{
			
				echo "preparing tax item for Avalara tranmission" . "\n";

		
			$this->taxRequest->setCustomerCode($this->contractInfo["Customerno"]);	
			$this->taxRequest->setDocCode($this->contractInfo["RevContract"]); // contract no
		
		
	
		$this->postTaxRequest->setDocCode($this->contractInfo["RevContract"]);
		

		$Destination=$this->createDestinationAddress();
		$this->taxRequest->setDestinationAddress($Destination);
		$this->taxRequest->setAddressCode($Destination);
	    $theLine=$this->setSingleLineArray();

	//	$this->itemzTax[0]['itemno']=$this->taxNo;
	//$getTaxRequest->setLines(array($line1, $line2, $line3));
		$this->taxRequest->setLines(array($theLine));
	// }
	 
	 
	 
 	//var_dump($this->taxRequest);
			$this->getTax();
		if (!is_object($this->taxResult))
		{
			throw new Exception ("taxResult Object not defined."); 
		}
		if ($this->taxResult->getResultCode() == SeverityLevel::$Success)
			{
				$this->processTaxResult("taxResult");
		} else {
				$this->processTaxError("taxResult");
		} // end else condition sucess
	}	// end  process tax items
	
	
	
		//public function setRequestParams($module) {
		//echo "Setting Document Level parameters for " .$module. ": " .$this->taxNo . "\n";
		//$this->{$module}->setDocCode($this->taxNo); // contract no		
	//	}
	
	
			
			
 
		
		
		
		
	public function processContractblockMU($taxContracts)
	{
		
		
		
				foreach ($taxContracts as $taxContract)  {
				// should I test for null or blank contract number?
				
				
				
				
				
				
			
				echo "**********Record ITEM NUMBER: " . $taxContract["contractno"] ."*************************************" , PHP_EOL;
				echo "Accessing items from tax number" . $taxContract["contractno"] ."\n";
		$this->items=$this->resq->getContractItems($taxContract["contractno"]);
		
	//	$this->setContractInfo($this->items[0])
	       $this->contractInfo = $this->items[0];
		//   var_dump ($this->items);
		$this->taxNo =$this->contractInfo["RevContract"];
	
	
	
	
	
	
	
//var_dump(	$this->items);
		$this->processTaxItems(); 
		
				} // end foreach
		
		
		
		
		
	}	 // end process contracts with multiple items 
	
	
	
	
	//public function setContractInfo($firstItem) {
		
		
		
		//	$this->taxRequest->setCustomerCode($this->contractInfo["Customerno"]);	
	//		$this->taxRequest->setDocCode($this->contractInfo["RevContract"]); // contract no
		
		
		
	//}
	
	
	
	
	
	
 
	
	
	
	
	
	
	
	
	
	
	
	
	

	
	 
	
	 
 

	
	
	
	// process tax items  set up the request to send to Avalara
	public function processTaxItems()
	{
		echo "preparing tax items for Avalara tranmission" . "\n";
 
		
		
		
		
	
		
		
		
		$this->taxRequest->setDocCode($this->taxNo); // contract no
		$this->postTaxRequest->setDocCode($this->taxNo);
		
		$this->taxRequest->setCustomerCode($this->contractInfo['Customerno']);	
		$Destination=$this->createDestinationAddress();
		$this->taxRequest->setDestinationAddress($Destination);
		$this->taxRequest->setAddressCode($Destination);

		$theLinz=$this->createItemLines();

	//	$this->itemzTax[0]['itemno']=$this->contractInfo["CUSTOMERNO"];
		$this->taxRequest->setLines($theLinz);
		//}
	//catch ($exception) {
	
	//throw new Exception("something went wrong in the heading");
	//}	
			$this->getTax();
		if (!is_object($this->taxResult))
		{
			throw new Exception ("taxResult Object not defined."); 
		}
		if ($this->taxResult->getResultCode() == SeverityLevel::$Success)
			{
				$this->processTaxResult("taxResult");
		} else {
				$this->processTaxError("taxResult");
		} // end else condition sucess
	}	// end  process tax items
	
	
	
		//public function setRequestParams($module) {
		//echo "Setting Document Level parameters for " .$module. ": " .$this->taxNo . "\n";
		//$this->{$module}->setDocCode($this->taxNo); // contract no		
	//	}
	
	// Create Destination address
	public function createDestinationAddress()
	{
		$destination=new Address();
		$destination->setLine1($this->contractInfo['SHIPADDRESS1']);
	 	$destination->setLine3($this->contractInfo['company']);
		$destination->setCity($this->contractInfo['SHIPCITY']);
		$destination->setRegion($this->contractInfo['SHIPSTATE']);
		$destination->setPostalCode($this->contractInfo['SHIPZIP']);
		$destination->setCountry('USA');
		return $destination;
	}  // end create Destination Address
	
	
	// create Line Items
	public function createItemLines()
	{
		$Linz = array();
		$idx = 0;
		$LN=1;
		//unset($this->itemzTax);
		$this->itemzTax[$idx]=array();
		//  contract or invoice
		//$this->itemzTax[$idx]['type']=$this->taxType;
		//$this->itemzTax[$idx]['type']="CONTRACT";

		foreach ($this->items as $item)
		{
			$Linz[$idx] = new Line();
			$Linz[$idx]->setNo($LN);
			$Linz[$idx]->setItemCode($item["masterno"]);
			$Linz[$idx]->setDescription($item["DESCRIPTION"]);
			$Linz[$idx]->setQty($item["qty"]);
			$Linz[$idx]->setAmount($item["PRICEPER"]*-1);
			
			
			
			
			
			
			$LN++;
			$idx++;
			$this->itemzTax[$idx]=array();
			$this->itemzTax[$idx]['type']='ITEM';
			$this->itemzTax[$idx]['itemno']=$item["masterno"];
		} // end of foreach loop

		return $Linz;
	} // end createItemLines
	

	// Process Tax Results  factore in tax per item !!!!!
	public function processTaxResult($module)
	{
		
		//var_dump($this->taxResult);
		//Success - Display GetTaxResults to console
		//Document Level Results
		$this->totalTax=$this->{$module}->getTotalTax() . "\n" ;
		$this->totalAmount=$this->{$module}->getTotalAmount(); 
		echo "TotalAmount: " . $this->totalAmount . "\n";
		echo "TotalTax: " . $this->totalTax . "\n";
	//	
		//$array = [
   // "foo" => "bar",
    //"bar" => "foo",
//];
		
		
		

		//$this->initTaxRecordSUCCESS($module);
		//$this->initTaxRecordSUCCESS();	
		
	//	echo "Transaction Type: ". $this->taxType .  "Environment: " . $this->Env . "\n";
		
//		if ($this->taxType=="INVOICE" and $this->Env == 'live')
	//	{
	//		echo " committing transaction \n";
			$this->postAndCommit();
				
	//	}  
	//	else 
	//	{
			
	//		echo "Transcation WILL NOT be committed \n";
	//	}
		
		
		
		
		//	if ($this->taxType=="INVOICE" and $this->Env == 'Test')
	//	{
	//		echo " pretending to  committing transaction   \n";
		 
				
	//	} 
		
		
		///Line Level Results (from TaxLines array class)
 		foreach ($this->{$module}->getTaxLines() as $currentTaxLine)
		{
			echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $currentTaxLine->getTax() . " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
			$lnNo=$currentTaxLine->getNo();
		//	$this->taxRecord['itemno']=$this->itemzTax[$lnNo]['itemno'];
		//	$this->taxRecord['taxamount']=$currentTaxLine->getTax();			
    	//	$this->resq->updateTaxRecord($this->taxRecord);	
		
				$this->resq->markProcessed($this->totalTax,$this->contractInfo['contractno'],$this->itemzTax[$lnNo]['itemno'] );
		
		
		
// maybe here factor in qty for more accurate output??
			//Line Level Results
			foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails)
			{
				echo "Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
			} // end foreach currentTaxLines
			echo"\n";
		} // for each tax line 		
	}  // end process Tax Results



// post tax and commit transaction
	public function postAndCommit()
	{
		echo "preparing tax for posting/commit  \n";
		//echo "This is the taxno it's supposed to go in doc code" . $this->taxNo ."\n";
		//$this->postTaxRequest->setDocCode($this->taxNo);
		$this->postTaxRequest->setTotalAmount($this->totalAmount);
		$this->postTaxRequest->setTotalTax($this->totalTax);
		$this->postTaxRequest->setCommit(true);
	//	var_dump($this->postTaxRequest);
	  // PostTax and Results
	  var_dump($this->postTaxRequest);
		try
		{
			$this->postTaxResult = $this->taxService->postTax($this->postTaxRequest);
			echo 'PostTax ResultCode is: ' . $this->postTaxResult->getResultCode() . "\n";
			// Success - Display GetTaxResults to console
			if ($this->postTaxResult->getResultCode() != SeverityLevel::$Success)
			{
				$this->processTaxError( "postTaxResult");
			}
		} catch (SoapFault $exception)
		{
			if ($exception)
			{
				$this->requestCatcher($exception);
			}
		}  // end catch 
	}
 
	//  request Catcher  
	
	public function requestCatcher($exception) {
		$this->mailCatcher($exception);
		
		///$message = "Exception: ";
		//$message .= $exception->faultstring;
		//echo $message . "\n";
		echo $this->taxService->__getLastRequest() . "\n";
		echo $this->taxService->__getLastResponse() . "\n   ";
	}
 
 
 
// mail catcher
	function mailCatcher($exception)   {
		$message = "Exception: ";
		$message .= $exception->faultstring;
		echo $message . "\n";
		$mailto = 'mcusack@pineconehill.com';
		$subject = 'Avalara Alert';
		//$message = 'Email Body';
		$headers[] = 'From: noreply@pineconehill.com';
		$headers[] = 'Reply-To: noreply@pineconehill.com';
		$headers[] = 'X-Mailer: PHP/' . phpversion();
		@mail($mailto, $subject, $message, implode("\r\n", $headers));
				
	}   // end mail catcher
	




	
	// get Tax 
	public function getTax()
	{
		try
		{
			$this->taxResult = $this->taxService->getTax($this->taxRequest);
		}
		catch(SoapFault $exception)
		{
		//	if ($exception)
		//	{
				echo "exception get tax";
			$this->requestCatcher($exception);
		 //   }
		}  // end catch
	}// end getTax


	
	// process tax error
	public function processTaxError($module)
	{
	
		$this->errMSG=$this->{$module}->getMessages();
		$this->errorMSG=$this->errMSG[0]->getName();
		echo "This is the Error Message:" .$this->errorMSG ."\n";
		if($this->errorMSG=='DocStatusError') {
			$this->docStatusError();
		}
         else 
		 {	 
			foreach ($this->errMSG as $message)
			{
				echo $message->getName() . ": " . $message->getSummary() .  $message->getSource(). $message->getRefersTo() ."\n";
				$this->taxRecord['message']= $module.":".$this->taxRecord['message'] . " " .  $message->getSummary();
		}  // end for each tax result  
		$this->errorRecordSetup();
		 }// else if error message is docstatus error	
	}  // end process tax error
	

	public function docStatusError() {
		echo "Checking the status of " . $this->taxNo ."\n";
		$this->checkTaxStatus();
		if ($this->docStatus=="Committed"){
			$this->adjustTax();
		}  // end if committed
	} // end docStatusError
	
	// Check Tax Status
	public function checkTaxStatus() {
		echo "check tax status  for " . $this->taxNo . "\n";
		//var_dump($this->taxHistory);
		// re-set the doc code for this run
		$this->taxHistory->setDocCode($this->taxNo); // contract no	\
		//$this->postTaxRequest->setDocCode($this->taxNo);
		//$this->setRequestParams("taxHistory");
		$this->taxHistory->setDetailLevel(DetailLevel::$Tax);
		try{
			$this->taxHistoryResult = $this->taxService->getTaxHistory($this->taxHistory);
			$historyResult=$this->taxHistoryResult->getResultCode();
			//echo 'GetTaxHistory ResultCode is: ' . $this->taxHistoryResult->getResultCode() . "\n";
		    echo 'GetTaxHistory ResultCode is: ' . $historyResult . "\n";
			//if ($this->taxHistoryResult->getResultCode() != SeverityLevel::$Success) {
			if ($historyResult != SeverityLevel::$Success) {
				foreach ($this->taxHistoryResult->getMessages() as $message)
				{
					echo $message->getName() . ": " . $message->getSummary() . "\n";
				}
			} else
			{
				echo "Document Type:  " . $this->taxHistoryResult->getGetTaxResult()->getDocType() . "\n";
				echo "Invoice Number: " . $this->taxHistoryResult->getGetTaxRequest()->getDocCode() . "\n";
				echo "Tax Date:  " . $this->taxHistoryResult->getGetTaxResult()->getTaxDate() . "\n";
				//echo "Last Timestamp:  " . $getTaxHistoryResult->getGetTaxResult()->getTimestamp() . "\n";
				// echo "Detail:  " . $getTaxHistoryResult->getGetTaxRequest()->getDetailLevel() . "\n";
			    $this->docStatus=$this->taxHistoryResult->getGetTaxResult()->getDocStatus();
				echo "Document Status:  " . $this->docStatus . "\n";
				echo "Total Amount:  " . $this->taxHistoryResult->getGetTaxResult()->getTotalAmount() . "\n";
				// echo "Total Taxable:  " . $getTaxHistoryResult->getGetTaxResult()->getTotalTaxable() . "\n";
				echo "Total Tax:  " . $this->taxHistoryResult->getGetTaxResult()->getTotalTax() . "\n";
				// echo "Total Discount:  " . $getTaxHistoryResult->getGetTaxResult()->getTotalDiscount() . "\n";
				foreach ($this->taxHistoryResult->getGetTaxResult()->getTaxLines() as $currentTaxLine) 
				{
				//	echo "######## dump current tax line ########## \n";
				//	VAR_DUMP($currentTaxLine)	;
					
					echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $currentTaxLine->getTax() . " TaxCode: " . $currentTaxLine->getTaxCode() .     "\n";
					$lnNo=$currentTaxLine->getNo();
			    //Line Level Results
					foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails)
					{
						echo " Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
					}   // end for each tax detail
				}  // end for each tax line				
			} // end if else unsuccessful tax history retrieval 	
		} // end try
		catch (SoapFault $exception)
		{
			if ($exception)
			{
			$this->requestCatcher($exception);
		    }
		} // end catch		
	} // end check tax status
	
	
	
	// adjust Tax
	public function adjustTax () {
		echo $this->taxNo . "is already committed now we are going to have to adjust it. \n";
		// 0 - not adjusted
		// 1 - sourcing issue
		// 2 - reconciled with general ledger
		// 3 - exemption cert applied.  if preovsly taxed item is supposed to be exempted.
		// 4 - price or quanity adustment
		// 5 - item returned
		// 6 - item replaced 
		// 7 - bad debt
		// 8 - Other 
		// return code set to 5  exchange set to 6  if a discount is being applied after the order had been placed 
		// changes you can check for before adjusting a first time.  State change - 1 sourcing issue.   number of tax lines different 4 - price or quanity adjudstment 
		$this->adjustTaxRequest = new AdjustTaxRequest();
		$this->adjustTaxRequest->setAdjustmentReason(8);
		$this->adjustTaxRequest->setAdjustmentDescription("Order Detail Adjustment: price, qty, shipping address, adding or deleting item.");
		$this->adjustTaxRequest->setGetTaxRequest($this->taxRequest);
		try{
			$this->adjustTaxResult = $this->taxService->AdjustTax($this->adjustTaxRequest);
		    echo 'GetTax is: ' . $this->adjustTaxResult->getResultCode() . "\n";
		    if ($this->adjustTaxResult->getResultCode() == SeverityLevel::$Success) {
				//Document Level Results
				$this->processTaxResult("adjustTaxResult");
				//  changes you can check for after 1 adjustment county changes - 1 sourcing issue 
				//  Total amount or total tax changes 4 
				// if state, country, amount and tax same and not a refund or exchange --- reset to zero.
			} else {
				
				
					$this->processTaxError("adjustTaxResult");
				
				
				
			//	foreach ($this->adjustTaxResult->getMessages() as $message) {
					
					
					
					
					
				//	echo $message->getName() . ": " . $message->getSummary() . "\n";
				//}  // end foreach
			}// end if not success			
		} // end try
		catch (SoapFault $exception) {
			if ($exception)
			{
			$this->requestCatcher($exception);
		    }
		} // end catch
	} // end adjust tax 
	
	
	
	// error record set up	
	public function errorRecordSetup()
	{
		echo "value of attempts in begining of error record set up <br>";
		//var_dump($this->attempts);
		$this->attempts=($this->attempts+1);
	//	echo "supposed to increment attempts by one <br>";
	//	var_dump($this->attempts);
		$this->taxRecord['status'] = 'ERROR';	
		$this->taxRecord['itemno'] = $this->taxRecord['orderno'];
		$this->taxRecord['taxamount'] = 0.00;
		$this->taxRecord['attempts']=$this->attempts;
	//	var_dump($this->taxRecord);
		$this->resq->updateTaxItemRecords($this->taxRecord);	
	} // end error record setup
	
	
	// maybe this should be in a separate file in a hidden directory.		
	private function get_conn()
	{
		$test_env = DEFINED('IS_TEST') && IS_TEST === true;
		$db = 'sandbox';
		$host = 'pchsql'; // SQL server configured in freetds.conf per web server (Alchemist: /etc/freetds/freetds.conf ; Coloweb2: /usr/local/etc/freetds.conf )
		$user = 'PCH4_w3b5-Usr6';
		$pass = 'p33c33aitch_w3b-p455';
		try
		{
			$string = "dblib:host=$host;dbname=$db";
			$connect = new PDO($string, $user, $pass);
		}
		catch (PDOException $e)
		{
		    //return $e->getMessage();
			throw new Exception ('Unable to Connect to Resq; ' . $e->getMessage());
		}
		
		//be sure to catch all exceptions if below is true...
		$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $connect;
	}  // end get conn
	
	// This calls the sql stored procedure that processes the tax results to the resq tables	
	public function processResults()
	{
			try
		{
			ini_set('mssql.timeout', 2400);
			
			echo "running resq proc";
			
			// connect to colosql1 using mssql, not PDO. PDO having issues with proc errors?
			$resq = $this->resq_connect();
			
			
			if (!$resq || !mssql_select_db('sandbox', $resq)) {
				throw new Excepton('Unable to connect to SQL server or select database.');
			}
			
			mssql_query("SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;", $resq);
			try{
			$proc = mssql_init('process_avatax_q', $resq);
			$exec = mssql_execute($proc);
			}
			catch (Exception $e)
		{
			//$message = $e->getMessage();
			
				if ($exception)
			{
				$this->mailCatcher($e);
		    }
			
		
		}
			// Iterate through returned records
			do {
				while ($row = mssql_fetch_array($exec, MSSQL_ASSOC)) {
				//	var_dump($row);
				}
			} while (mssql_next_result($exec));
			
			//$rs = $this->handle_proc_result($exec, $resq);
			
		}
		catch (Exception $e)
		{
				if ($exception)
			{
				$this->mailCatcher($e);
		    }
		
			
		//	pch::catcher($e, $proc, "error calling SQL sandbox..process_avatax_q");
		} 

	}  // end 
	
	
	
	
	

	
	
	
	
	
	
	
	
	
	function resq_connect()
	{
		$db = 'sandbox';
		$host = 'pchsql';
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
			$message = $e->getMessage();
			
				pch::catcher($e, $proc, $message);
			
			//return $e->getMessage();
		}

		return $resq;
	} 
	
	
}  // end class


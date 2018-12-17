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
	public $taxType;
	public $recordsWithNulls;
	public $nullFields;
	public $items=array();
	public $errMSG=array();
	public $errorMSG;
	public $contractInfo;
	public $docStatus;
	    public $totalAmount;
	public $totalTax;
    public $rmaNo;
	
	public function __construct()
	{
		ini_set('mssql.timeout', 1200);
		date_default_timezone_set('America/New_York');	
		
		$ava_includes = array(
			'reztmp.php'
			
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
	//	, 'Avatax/CommitTaxRequest.php'
	//	,	'AvaTax/CommitTaxResult.php'
		,	'AvaTax/DocumentType.php'
			, 'AvaTax/GetTaxHistoryRequest.php'
		, 'AvaTax/GetTaxHistoryResult.php'
		, 'AvaTax/AdjustTaxRequest.php'
		, 'AvaTax/AdjustTaxResult.php'
		,	'AvaTax/ServiceMode.php'
		,	'AvaTax/TaxServiceSoap.php'
		);
		
		foreach($ava_includes as $ava_inc)
		{
			require($ava_inc);
		}
		
		$rez=new REZTMP();
		$rez->setvars();
		
 	try{
			echo "Initializing connection with Avalara \n";
			$this->taxService = new TaxServiceSoap('Development');
			$this->taxRequest = new GetTaxRequest();	
			$this->postTaxRequest = new PostTaxRequest();
			$this->taxHistory=new GetTaxHistoryRequest();
		//	$this->committTax= new CommitTaxRequest();
	}
		catch (Exception $e)
		{
			//return $e->getMessage();
			throw new Exception("Problem with Avalara Initialization: ". $e->getMessage() );
		}
		
	
		$this->resq=$rez;
		
		$this->today = date("Y-m-d");	
		
		$this->setHeaders();
	}  // end contruct
	
	public function setHeaders() 
	{
		$this->setHeader("taxRequest");
		$this->setHeader("postTaxRequest");
		$this->setHeader("taxHistory");
	}
	public function setHeader($module) 
	{
		echo "setting header for " . $module ."\n";
		$this->{$module}->setCompanyCode("fatest");
		$this->{$module}->setDocType("SalesInvoice");
		// taxHistory doesn't have DocDate
		//if ($module != 'taxHistory' && $module !='committTax'){ 	
		if ($module != 'taxHistory'){ 	
			//	if ($module !='postTaxRequest' ){
		
		$this->{$module}->setDocDate($this->today);
		}
		if ($module == 'taxRequest') {
		//$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		$Origin = $this->createOriginAddress();  
			$this->taxRequest->setOriginAddress($Origin);
		}
		//if ($module !='postTaxRequest' && $module !='committTax'){
		if ($module !='postTaxRequest' ){
				
			$this->{$module}->setDetailLevel(DetailLevel::$Tax);
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
 		$taxContracts=$this->resq->getTaxContracts();
		$noContracts=count($taxContracts);
		echo "Found " . $noContracts . "Contracts/Orders \n";
		foreach ($taxContracts as $taxContract)  {
			// should I test for null or blank contract number?
			echo "**********Record ITEM NUMBER: " . $taxContract["ITEMNO"] ."*************************************" , PHP_EOL;
			$this->taxNo=$taxContract["ITEMNO"];
			//#######
			$this->taxType=$taxContract["ITEMTYPE"];
			$this->taxBiz=$this->resq->getDatabase($taxContract["biz"]);
			$this->checkTaxItems();
		} // end for each contract
	}// end process tax
	

	
// retrieves the items associated with the invoice or contract order.  Checks them for missing elements	
	public function checkTaxItems()
	{
		echo "in check tax items\n" ;
		$this->recordsWithNulls=0;
		echo "Accessing items from tax number" . $this->taxNo . "\n";
		$this->items=$this->resq->getContractItems($this->taxNo);

		// All records have contract level information but we only need one so just get info from top record item
		if( isset( $this->items[0] ) )
		{
			$this->prepTaxRecords();
		} 
		else 
		{
			$this->processNoItemsError();
		}  // end if - else if items array is set
	
		if ($this->recordsWithNulls==0)
		{
			try
			{
			$this->processTaxItems();
			}
		catch (Exception $e)
		{
			//return $e->getMessage();
				throw new Exception("Problem with Avalara Tax Lookup: ". $e->getMessage() );
		}
			
	 
		
		
		
		
		
		
		} //  end if records with nulls is zero
	}  // end check Tax Items
	
	// prepare tax records for table
	public function prepTaxRecords()
	{
		$this->contractInfo=$this->items[0];
		$this->biz=$this->contractInfo['biz'];
		$this->type=$this->contractInfo['ITEMTYPE'];
		$this->status=$this->contractInfo['STATUS'];
		$this->initTaxRecordArray();
		$this->createItemRecords();
	}  // end prep tax records
	
	
	// Set up tax record array to write an error if there are no items to this order  
	public function processNoItemsError()
	{
		$this->taxRecord['type']= $this->taxType;
		$this->taxRecord['orderno']=$this->taxNo;
		$this->taxRecord['itemno']='N/A';
		$this->taxRecord['masterno']='N/A';
		$this->taxRecord['status']='ERROR';
		$this->taxRecord['message']='No Tax Item records for tax record:' . $this->taxNo . ".  No lookup attempted"  ;
		echo $this->taxRecord['message'] . "\n";
		$this->taxRecord['taxamount']=0.00;
		$this->taxRecord['BIZ']=$this->taxBiz;
	    $this->resq->insertTaxRecord($this->taxRecord);	
		$this->recordsWithNulls=1;
	} // end process no Items error	  
	
		
	// create Item Records
	public function createItemRecords()
	{
		foreach ($this->items as $item)
		{
			
		//	echo $item["PRICE"] . "\n";
		//if ($item["PRICE"] < 0) {
		//	$this->rmaCheck($item["CONITEMNO"]);
		//	}
			
			$this->taxRecord["message"]="";
			$nullsInRecord=0;
			$nullsInRecord=$this->nullcheck($item);
			if($nullsInRecord>0)
			{
				$this->recordsWithNulls ++;
				$this->taxRecord["message"]= $this->nullFields. " Tax lookup not attempted.";
				
				echo $this->taxRecord['message'];
			}  // end if nulls in record is greater than zero 

			$this->taxRecord['itemno']=$item["CONITEMNO"];
			$this->taxRecord['masterno']=$item["MASTERNO"];

			$this->resq->insertTaxRecord($this->taxRecord);	
		} // end of foreach loop
	} // end create Item Records


	
	public function rmaCheck($conitemno) {
		
		 $this->rmaNo='';
		
		
		echo $conitemno . "\n";
		
		$this->rmaNo=$this->resq->getRMAnumber($conitemno, $this->taxBiz);
		echo $this->rmaNo . "\n";
		
		
	}
	
	
	
	
	


 
	// Check for fields that have empty values or are null
	public function nullCheck($fields)
	{
		$cNull=0;
		$iNull=0;
		$nullcheck=0;
		$cFields = array();
		$iFields=array();
		$this->nullFields="-";
 		foreach ($fields as $key=>$value)
		{
			if ($key !="TAX" && $key!="ERROR")
			{
				//echo "value is " . $value . "\n";
				$test_me = rtrim( $value );
				if ( empty( $test_me ) )
				{
					$nullcheck++;
					switch($key)
					{
						case  'COMPANY':
							array_push($cFields,"Contact");
							$cNull++;
						break;
						case 'CUSTOMERNO':
							array_push($cFields,"Customer Number");
							$cNull++;
						break;
						case 'MASTERNO':
							array_push($iFields,"Master Number");
							$iNull++;
						break;
						case 'CONITEMNO':
							array_push($iFields,"Con-item number");
							$iNull++;
						break;
						case 'DESCRIPTION':
							array_push($iFields,"Description");
							$iNull++;
						break;
						case 'QTY':
							array_push($iFields,"Quantity");
							$iNull++;
						break;
						case 'PRICE':
							array_push($iFields,"Price");
							$iNull++;
						break;
						case 'ITEMTOTAL':
							array_push($iFields, "Item Total");
							$iNull++;
						break;
						case 'SHIPADDRESS1':
							array_push($cFields, "Ship Address");
							$cNull++;
						break;
						case 'SHIPCITY':
							array_push($cFields, "Ship City");
							$cNull++;
						break;
						case 'SHIPSTATE':
							array_push($cFields, "Ship State");
							$cNull++;
						break;
						case 'SHIPZIP':
							array_push($cFields, "Ship Zip");
							$cNull++;
						break;
						default:
				
	
						break;
					}  //  end key switch test
				} // end if test me is empty
			} // end if key is not tax and if key not error--we skip these because they can be null or empty.
		}  // end for each field array
		if ($nullcheck>0)
		{
            if ($nullcheck == 12)
			{
				$this->nullFields ="All required fields missing.  Check for lost contract or invoice record ";
			} 		
		     else
			{
				if($cNull>0)
				{
					$this->nullFields=" " . $cNull ." fields missing on Contract:";
					$this->nullFields .= implode(",",$cFields);
				} // end cnull greater than zero
				if($iNull>0)
				{	
					$this->nullFields .= "-Item missing ".$iNull ." fields:"  ;
					$this->nullFields .= implode(",",$iFields) .".";
					//if (inNull==6)
					//	$this->nullFields .= "-Possible missing item record"  ;
					//} // end if inull 6
				} // end if inull greater than zero
			} // end if-else nullcheck is 12
		} // end if nullcheck over zero
//echo $this->nullFields;
		return $nullcheck;
	} // end null check
	
	
	
	
	// process tax items  set up the request to send to Avalara
	public function processTaxItems()
	{
		echo "preparing tax items for Avalara tranmission" . "\n";
		$this->setRequestParams("taxRequest");
		$this->setRequestParams("postTaxRequest");
	//	$this->taxRequest->setDocCode($this->taxNo); // contract no
		$this->taxRequest->setCustomerCode($this->contractInfo['CUSTOMERNO']);	
		$Destination=$this->createDestinationAddress();
		$this->taxRequest->setDestinationAddress($Destination);
		$this->taxRequest->setAddressCode($Destination);

		$theLinz=$this->createItemLines();

		$this->itemzTax[0]['itemno']=$this->taxNo;
		$this->taxRequest->setLines($theLinz);
		//$this->checkTaxStatus();
	
	
			// here check for old avalara committal
		
		//switch ($this->docStatus) {
    //case "Saved":
	 //   echo $this->taxNo . " is uncommitted tax look up as usual";
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
	
	
        ///code to be executed if n=label1;
     //   break;
   // case "Committed":
    //   	echo $this->docStatus . " we are going to adust tax here?? \n";
     //   break;
    //case "Void":
    //echo $this->taxNo . " is void output error message to check tax and order";
     //   break;
 
   // default:
    //    echo $this->taxNo . " is  ".  $this->docStatus    .   " uncommitted tax look up unneccary process as usual";
//}
	
	
	
	
	
	
	
	} // end process tax items
	
	
	
	 
	
	
		public function setRequestParams($module) {
		echo "Setting Document Level parameters for " .$module. ": " .$this->taxNo . "\n";
		$this->{$module}->setDocCode($this->taxNo); // contract no		
		
		
	}
	

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// Create Destination address
	public function createDestinationAddress()
	{
		$destination=new Address();
		$destination->setLine1($this->contractInfo['SHIPADDRESS1']);
		//$destination->setLine2($this->contractInfo['Address2']);
		$destination->setLine3($this->contractInfo['COMPANY']);
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
		unset($this->itemzTax);
		$this->itemzTax[$idx]=array();
		$this->itemzTax[$idx]['type']="CONTRACT";

		foreach ($this->items as $item)
		{
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
	

	// Process Tax Results  factore in tax per item !!!!!
	public function processTaxResult($module)
	{
		//Success - Display GetTaxResults to console
		//Document Level Results
	//	echo "TotalAmount: " . $this->{$module}->getTotalAmount() . "\n";
	//	echo "TotalTax: " . $this->{$module}->getTotalTax() . "\n";

		
		
		//$this->totalAmount=$this->taxResult->getTotalAmount();
		//$this->totalTax=$this->taxResult->getTotalTax();
		
		
		$this->totalAmount=$this->{$module}->getTotalAmount();
	$this->totalTax=$this->{$module}->getTotalTax();
		
		
		
		echo "TotalAmount: " . $this->totalAmount . "\n";
		echo "TotalTax: " . $this->totalTax . "\n";
		
		
		
		
		
		$this->initTaxRecordSUCCESS($module);
			$this->postAndCommit();
		
		//Line Level Results (from TaxLines array class)
 		foreach ($this->{$module}->getTaxLines() as $currentTaxLine)
		{
			echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $currentTaxLine->getTax() . " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
			$lnNo=$currentTaxLine->getNo();
			$this->taxRecord['itemno']=$this->itemzTax[$lnNo]['itemno'];
			$this->taxRecord['taxamount']=$currentTaxLine->getTax();			
    		$this->resq->updateTaxRecord($this->taxRecord);	
		//	if (!empty($this->rmaNo)) {
				
				
			//	echo "Now update RMA record so it won't be stuck  \n";
				//	$this->taxRecord['orderno']	=$this->rmaNo;
				//	$this->taxRecord['itemno']=$this->rmaNo;
	            //	$this->taxRecord['message']= "RMA Entry:" . 	$this->taxRecord['message'];    			
				//		$this->resq->insertTaxRecord($this->taxRecord);	
						
		//	}
			
			
			
			
			
			
			
			
// maybe here factor in qty for more accurate output??
			//Line Level Results
			foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails)
			{
				echo "Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
			} // end foreach currentTaxLines
			echo"\n";
		} // for each tax line 		

	}  // end process Tax Results
 
 
 
	public function postAndCommit()
	{
		echo "preparing tax for posting/commit  \n";
		$this->postTaxRequest->setTotalAmount($this->totalAmount);
		$this->postTaxRequest->setTotalTax($this->totalTax);
		$this->postTaxRequest->setCommit(true);
	  // PostTax and Results
	 // var_dump($this->postTaxRequest);
		try {
			$this->postTaxResult = $this->taxService->postTax($this->postTaxRequest);
			echo 'PostTax ResultCode is: ' . $this->postTaxResult->getResultCode() . "\n";
			// Success - Display GetTaxResults to console
			if ($this->postTaxResult->getResultCode() != SeverityLevel::$Success) {
				
					$this->processTaxResult("postTaxResult");
				
				
			//	foreach ($this->postTaxResult->getMessages() as $message) {
			//		echo $message->getName() . ": " . $message->getSummary() . "\n";
			//	}
			}
		} catch (SoapFault $exception)
		{
			$message = "Exception: ";
			if ($exception)
			{
				$message .= $exception->faultstring;
			}
			echo $message . "\n";
			echo $this->taxService->__getLastRequest() . "\n";
			echo $this->taxService->__getLastResponse() . "\n   ";
		}
	}
 
 
 
 
 
 
 
 
 
 
 
 
	
	// initialize tax record array	
	public function initTaxRecordArray()
	{
		unset($this->taxRecord);	
		$this->taxRecord['type']= $this->type;
		$this->taxRecord['orderno']=$this->taxNo;
		$this->taxRecord['itemno']='';
		$this->taxRecord['masterno']='';
		$this->taxRecord['status']='UNPROCESSED';
		$this->taxRecord['message']='';
		$this->taxRecord['taxamount']=0.00;
		$this->taxRecord['BIZ']=$this->biz;
	} // end init Tax Record Array

	// Initialize a successful tax record 
	public function initTaxRecordSUCCESS($module)
	{
	 	$this->taxRecord['status']='SUCCESS';	
		$this->taxRecord['taxamount']=$this->{$module}->getTotalTax();
		// maybee somewhere around here I may have to factor in qty  for a more accurate output
	 	$this->taxRecord['message']='SUCCESS Total tax for ORDER '.$this->taxRecord['orderno']. ' ' .$this->{$module}->getTotalTax();
	} // end init Tax Record Array	


	
	// get Tax 
	public function getTax()
	{
		try
		{
			$this->taxResult = $this->taxService->getTax($this->taxRequest);
		}
		catch(SoapFault $exception)
		{
			$message = "Exception: ";
			if ($exception)
			{
				$message .= $exception->faultstring;
			}
			
			$this->taxRecord["message"] = $message;
			$this->errorRecordSetup();
			
			echo $message . "\n";
			echo $this->taxService->__getLastRequest() . "\n\n\n\n";
			echo $this->taxService->__getLastResponse() . "\n \n\n\n  ";
		}
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
		
		echo "Hi, we are going to check the status of " . $this->taxNo ."\n";
		$this->checkTaxStatus();
		if ($this->docStatus=="Committed"){
			$this->adjustTax();

			
			
			
		}
	}
	
	
	
	
	
	
		
	public function checkTaxStatus() {
		echo "check tax status  for " . $this->taxNo . "\n";
		//var_dump($this->taxHistory);
		
		// re-set the doc code for this run
		$this->taxHistory->setDocCode($this->taxNo); // contract no	
		
		//$this->setRequestParams("taxHistory");
		$this->taxHistory->setDetailLevel(DetailLevel::$Tax);
		
		// try setting the following as well
		/*
$getTaxHistoryRequest->setCompanyCode("APITrialCompany");
$getTaxHistoryRequest->setDocType(DocumentType::$SalesInvoice);
		*/
		
		
		try{
			$this->taxHistoryResult = $this->taxService->getTaxHistory($this->taxHistory);
			
			echo 'GetTaxHistory ResultCode is: ' . $this->taxHistoryResult->getResultCode() . "\n";
		    if ($this->taxHistoryResult->getResultCode() != SeverityLevel::$Success) {
				foreach ($this->taxHistoryResult->getMessages() as $message)
				{
					echo $message->getName() . ": " . $message->getSummary() . "\n";
				}
			} else
			{
				
				
				
				
				
				
				
					echo "Document Type:  " . $this->taxHistoryResult->getGetTaxResult()->getDocType() . "\n";
					echo "Invoice Number: " . $this->taxHistoryResult->getGetTaxRequest()->getDocCode() . "\n";
					echo "Tax Date:  " . $this->taxHistoryResult->getGetTaxResult()->getTaxDate() . "\n";
					// echo "Last Timestamp:  " . $getTaxHistoryResult->getGetTaxResult()->getTimestamp() . "\n";
					// echo "Detail:  " . $getTaxHistoryResult->getGetTaxRequest()->getDetailLevel() . "\n";
				    $this->docStatus=$this->taxHistoryResult->getGetTaxResult()->getDocStatus();
					echo "Document Status:  " . $this->docStatus . "\n";
					echo "Total Amount:  " . $this->taxHistoryResult->getGetTaxResult()->getTotalAmount() . "\n";
					// echo "Total Taxable:  " . $getTaxHistoryResult->getGetTaxResult()->getTotalTaxable() . "\n";
					echo "Total Tax:  " . $this->taxHistoryResult->getGetTaxResult()->getTotalTax() . "\n";
					// echo "Total Discount:  " . $getTaxHistoryResult->getGetTaxResult()->getTotalDiscount() . "\n";
			} // end if else unsuccessful tax history retrieval 	
				
		} // end try
		catch (SoapFault $exception)
		{
			$message = "Exception: ";
			if ($exception)
			{
				$message .= $exception->faultstring;
			}
			echo $message . "\n";
			echo $this->taxService->__getLastRequest() . "\n";
			echo $taxService->__getLastResponse() . "\n   ";
		} // end catch		
				
	}
	
	
	
	
	public function adjustTax () {
		
		
		
		
		
					echo $this->taxNo . "is already committed now were going to have to adjust it. \n";
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



  //  echo "DocCode: " . $this->adjustTaxResult->getDocCode() . "\n";
  //  echo "DocStatus: " . $this->adjustTaxResult->getDocStatus() . "\n";
  //  echo "TotalAmount: " . $this->adjustTaxResult->getTotalAmount() . "\n";
   // echo "TotalTax: " . $this->adjustTaxResult->getTotalTax() . "\n";
//Line Level Results (from TaxLines array class)
   // foreach ($this->adjustTaxResult->getTaxLines() as $currentTaxLine) {
     // echo "     Line: " . $currentTaxLine->getNo() .     " Tax: " . $currentTaxLine->getTax() .      " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
//Line Level Results
     // foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetail) {
      //  echo "          Juris Type: " . $currentTaxDetail->getJurisType() . "; Juris Name: " . $currentTaxDetail->getJurisName() .   "  Rate: " . $currentTaxDetail->getRate() . " Amt: " . $currentTaxDetail->getTax() . "\n";
      //}
     // echo"\n";
 //   }
// If NOT success - display error messages to console     
  } else {
    foreach ($this->adjustTaxResult->getMessages() as $message) {
      echo $message->getName() . ": " . $message->getSummary() . "\n";
    }
  }
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
		} // end try
		catch (SoapFault $exception) {
			$message = "Exception: ";
			if ($exception) {
				$message .= $exception->faultstring;
			}
			echo $message . "\n";
			echo $this->taxService->__getLastRequest() . "\n";
			echo $this->taxService->__getLastResponse() . "\n   ";
		} // end catch
	}
	
	
	
	
	// error record set up	
	public function errorRecordSetup()
	{
		$this->taxRecord['status'] = 'ERROR';	
		$this->taxRecord['itemno'] = $this->taxRecord['orderno'];
		$this->taxRecord['taxamount'] = 0.00;
		
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
		
		echo "calling carrie's proc that is supposed to export the results \n";
		try
		{
			$sql = "exec sandbox..process_avatax_q";
			
			// echo $sql . "\n";
			
			
			$resq = $this->get_conn();
			
			
			$pdo_query = $resq->prepare($sql);
			
			$pdo_query->execute();
			
			//$rs = $pdo_query->fetchAll();
			//$rs = $pdo_query->fetchAll($sql);
		}
		catch(PDOException $e)
		{
			echo "ERROR: " . $e->getMessage();
			return false;
		}
	}  // end 
	
}  // end class


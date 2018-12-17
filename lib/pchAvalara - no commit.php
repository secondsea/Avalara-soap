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
	public $contractInfo;

	
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

		,	'AvaTax/DocumentType.php'
		,	'AvaTax/ServiceMode.php'
		,	'AvaTax/TaxServiceSoap.php'
		);
		
		foreach($ava_includes as $ava_inc)
		{
			require($ava_inc);
		}
		
		$rez=new REZTMP();
		$rez->setvars();
		
		//$pch=new pchAvalaraAuth();
		try{
			echo "Initializing connection with Avalara \n";
			$this->taxService = new TaxServiceSoap('Development');
			$this->taxRequest = new GetTaxRequest();	

	}
		catch (Exception $e)
		{
			//return $e->getMessage();
			throw new Exception("Problem with Avalara Initialization: ". $e->getMessage() );
		}
		
	
		$this->resq=$rez;
		
		$this->today = date("Y-m-d");	
		
		$this->setHeader();
	}  // end contruct
	
	public function setHeader() 
	{
		$this->taxRequest->setCompanyCode("fatest");
		$this->taxRequest->setDocType("SalesInvoice");
		$this->taxRequest->setDocDate($this->today);
		$this->taxRequest->setDetailLevel(DetailLevel::$Tax);
		$Origin = $this->createOriginAddress();  
		$this->taxRequest->setOriginAddress($Origin);
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
			
			try{
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
		// We don't want to add records to a previously processed tax error so we delete old records
		$noRecords=$this->resq->checkForOldRecords($this->taxNo);

		if($noRecords>0 )
		{
			$this->resq->destroyItemRecords($this->taxNo);
		} // end if no records greater than zero	
		
		foreach ($this->items as $item)
		{
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
		$this->taxRequest->setDocCode($this->taxNo); // contract no
		$this->taxRequest->setCustomerCode($this->contractInfo['CUSTOMERNO']);	
		$Destination=$this->createDestinationAddress();
		$this->taxRequest->setDestinationAddress($Destination);
		$this->taxRequest->setAddressCode($Destination);

		$theLinz=$this->createItemLines();

		$this->itemzTax[0]['itemno']=$this->taxNo;
		$this->taxRequest->setLines($theLinz);

		$this->getTax();
		if (!is_object($this->taxResult))
		{
			throw new Exception ("taxResult Object not defined."); 
		}
		if ($this->taxResult->getResultCode() == SeverityLevel::$Success)
			{
				$this->processTaxResult();
		} else {
			$this->processTaxError();
		} // end else condition sucess
	}	// end  process tax items
	
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
	public function processTaxResult()
	{
		//Success - Display GetTaxResults to console
		//Document Level Results
		echo "TotalAmount: " . $this->taxResult->getTotalAmount() . "\n";
		echo "TotalTax: " . $this->taxResult->getTotalTax() . "\n";

		$this->initTaxRecordSUCCESS();
		
		//Line Level Results (from TaxLines array class)
 		foreach ($this->taxResult->getTaxLines() as $currentTaxLine)
		{
			echo "     Line: " . $currentTaxLine->getNo() . " Tax: " . $currentTaxLine->getTax() . " TaxCode: " . $currentTaxLine->getTaxCode() . "\n";
			$lnNo=$currentTaxLine->getNo();
			$this->taxRecord['itemno']=$this->itemzTax[$lnNo]['itemno'];
			$this->taxRecord['taxamount']=$currentTaxLine->getTax();			
    		$this->resq->updateTaxRecord($this->taxRecord);	
// maybe here factor in qty for more accurate output??
			//Line Level Results
			foreach ($currentTaxLine->getTaxDetails() as $currentTaxDetails)
			{
				echo "Juris Type: " . $currentTaxDetails->getJurisType() . "; Juris Name: " . $currentTaxDetails->getJurisName() . "; Rate: " . $currentTaxDetails->getRate() . "; Amt: " . $currentTaxDetails->getTax() . "\n";
			} // end foreach currentTaxLines
			echo"\n";
		} // for each tax line 		

	}  // end process Tax Results
 
 
	
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
	public function initTaxRecordSUCCESS()
	{
	 	$this->taxRecord['status']='SUCCESS';	
		$this->taxRecord['taxamount']=$this->taxResult->getTotalTax();
		// maybee somewhere around here I may have to factor in qty  for a more accurate output
	 	$this->taxRecord['message']='SUCCESS Total tax for ORDER '.$this->taxRecord['orderno']. ' ' .$this->taxResult->getTotalTax();
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
	public function processTaxError()
	{
		foreach ($this->taxResult->getMessages() as $message)
		{
			echo $message->getName() . ": " . $message->getSummary() . "\n";
			$this->taxRecord['message']= $this->taxRecord['message'] . " " .  $message->getSummary();
		}  // end for each tax result  
		$this->errorRecordSetup();
	}  // end process tax error
	

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
	}  // end get conn
	
	// This calls the sql stored procedure that processes the tax results to the resq tables	
	public function processResults()
	{
		try
		{
		$sql= "exec sandbox.dbo.process_avatax_q";
		$resq=$this->get_conn();
		$pdo_query = $resq->prepare($sql);
		$pdo_query->execute();
		}
		catch(PDOException $e)
		{
			echo "ERROR: " . $e->getMessage();
			return false;
		}
	}  // end 
	
}  // end class


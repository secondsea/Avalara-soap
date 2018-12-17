<?php
Class REZTMP extends resqDb_Core {
	public $database;
	public $contractInfo=array();
	function setvars(){
//	$this->database ='PINECONEHILL';
	$this->resq=$this->connecttmp(); 
} // init

	public function getContracts () {
		$sql="SELECT  [ITEMNO]  FROM [" .  $this->database . "].[dbo].[avatax] WHERE [STATUS] = 'UNPROCESSED'";
//where [TYPE] ='CONTRACT'   AND [STATUS] ='PENDING'    ";
//  or ITEMTYPE ='INVOICE' 
//var_dump($sql);
		$resq=$this->connecttmp(); 
		$pdo_query = $resq->prepare($sql);
		$pdo_query->execute();
		$contracts =$pdo_query->fetchAll(PDO::FETCH_ASSOC);
//var_dump($contracts);
		return $contracts;
	} // end get contracts
	
	public function getContractInfo($contractno){
//$sql="SELECT  SHIPCOMPANY as name from [" .  $database . "].[dbo]. [CONTRACT] where [CONTRACTNO] ='" . $contractno . "'";
		$sql="SELECT  [CUSTOMERNO],[CONTRACTDATE],[SHIPCOMPANY], [SHIPADDRESS1], [SHIPADDRESS2], [SHIPCITY],[SHIPSTATE],[SHIPCOUNTRY],[SHIPZIP],[PHONE] FROM [" .  $this->database . "].[dbo]. [CONTRACT] where [CONTRACTNO] ='" . $contractno . "'";
//	var_dump($sql);
		$pdo_query=$this->resq->prepare($sql);
		$pdo_query->execute();
		$contract =$pdo_query->fetch(PDO::FETCH_ASSOC);
//var_dump($contract);
		unset($contractInfo);
		$contractInfo['CustomerNo'] = $contract["CUSTOMERNO"];
		$contractInfo['Name'] =	$contract["SHIPCOMPANY"];
		$contractInfo['Address1'] = $contract["SHIPADDRESS1"];
		$contractInfo['Address2'] = $contract['SHIPADDRESS2'];
		$contractInfo['City']=$contract['SHIPCITY'];
		$contractInfo['State']=$contract['SHIPSTATE'];
		$contractInfo['Zip']=$contract['SHIPZIP'];
		$contractInfo['Country']=$contract['SHIPCOUNTRY'];
//contrractInfo['Phone']=contract['PHONE'];    
		return $contractInfo;
	}  // end get Contract Info
	
	
	
	
	public function getAvalaraItems ($contractno)   {
		echo "in get avalara items blah blah";
		//$sp = 'sandbox.dbo.avatax_export';  
		try{
			   $sql = 'EXEC [SANDBOX].[DBO].avatax_export :contractno';
				$pdo_query=$this->resq->prepare($sql);
				$pdo_query->bindParam(':contractno',$contractno);
		$pdo_query->execute();
		$items =$pdo_query->fetch(PDO::FETCH_ASSOC);
//var_dump($items);		
 		
				
		}  // end try
		
		catch (Exception $e){
			
			
				return $e->getMessage();
			
		//	pch::catcher($e, $proc, "CC $type Q");
		}  // end catch
			
		return $items;
	} // end getAvalaraQ
	
	
	
	
	
	
	
	
	
	
	
	
	public function getContractItems ($contractno) {
//$resq=$this->connecttmp(); 
		$sql="SELECT  [CONITEMNO],[MASTERNO],[DESCRIPTION], [QTY], [PRICE] FROM [" .  $this->database . "].[dbo]. [CONTRACTITEM] where [CONTRACTNO] ='" . $contractno . "'";
		$theLinz = array();
		$idx = 0;
		$LN=1;
		$pdo_query = $this->resq->prepare($sql);
		$pdo_query->execute();
		$contractItems=$pdo_query->fetchAll(PDO::FETCH_ASSOC);
//var_dump($sql);
		return $contractItems;
	}	// end get contract items
	
//	public function updateTaxTablebk($itemno,$tax) {
//		$sql="UPDATE [" .  $this->database  . "].[dbo].[_000tempAvalara] set [TAXAMOUNT] = ". $tax . "Where [ITEMNO] = '" . $itemno . "' and [TYPE]='CONTRACT'" ;		 
//var_dump($sql);		
//$pdo_query = $this->resq->prepare($sql);
//	    $pdo_query->execute();
//	 }
	
	
	
	public function updateTaxRecord($taxRecord) {
//var_dump($taxRecord);	
		echo $taxRecord['type'] . "wtf \n /n";
		if ($taxRecord['type']=="CONTRACT" ) {
			$table="_000tempAvalara";
		} else {
			$table="_000tempAvalaraResults";
		} // end if type contract
		$sql="UPDATE [".$this->database."].[dbo].[".$table."] set [TAXAMOUNT] = '". $taxRecord['taxamount'] ."',[STATUS] = '" . $taxRecord['status'] . "',[MESSAGE]= '". $taxRecord['message'] . "' WHERE [ITEMNO] = '" . $taxRecord['itemno']. "' and [TYPE]='" . $taxRecord['type'] . "'" ;		 
//var_dump($sql);		
		$pdo_query = $this->resq->prepare($sql);
		$pdo_query->execute();
	} // end update tax record

	public function updateTaxRecordBulk($taxRecord) {
//if ($taxRecord['type']="CONTRACT" ) {
//$table="_000tempAvalara";
//	} else {
		$table="_000tempAvalaraResults";
//} // end if type contract
		$sql="UPDATE [".$this->database."].[dbo].[".$table."] set [TAXAMOUNT] = '". $taxRecord['taxamount'] ."',[STATUS] = '" . $taxRecord['status'] . "',[MESSAGE]= '". $taxRecord['message'] . "' WHERE [ORDERNO] = '" . $taxRecord['orderno']. "'";		 
//var_dump($sql);		
		$pdo_query = $this->resq->prepare($sql);
		$pdo_query->execute();
	} // update tax record bulk
	
	
//	public function insertTaxRecordbk($itemno,$tax) {
//		$sql= "INSERT INTO [" .  $this->database  . "].[dbo].[_000tempAvalara]    ([TYPE]  ,[ITEMNO]  ,[TAXAMOUNT])  VALUES ('ITEM', " .  $itemno . ", " . $tax . ")";
//var_dump($sql); 		
//		$pdo_query = $this->resq->prepare($sql);
//		$pdo_query->execute();
//	}
	
	public function insertTaxRecord($taxRecord) {
		$sql= "INSERT INTO [" .  $this->database  . "].[dbo].[_000tempAvalaraResults]    ([TYPE],[ORDERNO],[ITEMNO],[MASTERNO],[TAXAMOUNT],[STATUS],[MESSAGE])  VALUES ('".$taxRecord['type']."','".$taxRecord['orderno']."','".$taxRecord['itemno']."','".$taxRecord['masterno']."',".$taxRecord['taxamount'].",'".$taxRecord['status']."','".$taxRecord['message']."')";
		$pdo_query = $this->resq->prepare($sql);
		$pdo_query->execute();
	}  // end insert tax record
	
	
	
	
	// **************************************************tmp until I can use the one already here
	public function connecttmp($debug = false) 
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
			return $e->getMessage();
		}
		//be sure to catch all exceptions if below is true...
		if ($debug === false){
			$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $connect;
		}
	//	echo "hello";
	}  // end connectmp
	
	
	 
	
	
	
	
	/**
	 * return the appropriate resq db, given the biz
	 * @param string $biz (optional)
	 * if is_test(), use dev db.
	 * @see resq::check_biz()
	 * @see myUser::is_test()
	 * @throws Exception (if unhandled DB passed)
	 * @return string (the mssql database name)
	 */
	public function getDatabase($biz = null)
	{
		//$test = false; //  self::test_mode();
		//$daily = false; //  self::daily_mode();
//echo "biz biz biz biz biz " . $biz;
		switch($biz)
		{
			case 'dash':
				$db = 'dash_albert';
			break;

			case 'pch':
				$db = 'pineconehill';
			break;

			case 'outlet':
				$db = 'outlet';
			break;

			case 'potluck':
				$db = 'potluck';
			break;

			case 'fresh':
				$db = 'fresh'; // TESTMOD: resq isn't set up yet
			break;
		
			case 'luxe':
				$db = 'luxe';
//				$db = 'colosql2.luxe';
			break;
		case 'home':
				$db = 'HOME';
//				$db = 'colosql2.luxe';
			break;
			default:
				// throw new Exception("Couldn't find db for app.yml biz '$biz'");
				$db = 'pch';
			break;
		}

	//	if ($daily === true)
		//	$db .= '_daily';
	//	else if ($test === true)
		//	$db .= '_dev';
		
		$this->database=$db;
        //return $db;
	}
	
	
	
	
} // end class





?>

<?php
Class REZTMP extends resqDb_Core {
	public $database;
	public $taxContractInfo=array();
	function setvars(){
	$this->resq=$this->connecttmp(); 
} // init


// Get tax contracts or invoices from avatax export procedure
	public function getTaxContracts () {
		$sql="SELECT  distinct top 100 a1.[ITEMNO],[biz], [ITEMTYPE] FROM [SANDBOX].[dbo].[AVATAX_Q] as a1 inner join ( select  [ITEMNO]   FROM [SANDBOX].[dbo].[AVATAX_Q]   WHERE [STATUS] !='EXPORTED' AND [STATUS] !='SUCCESS'   ) as a2 on a1.itemno = a2.itemno ";
//var_dump($sql);
		$resq=$this->connecttmp(); 
		try
		{
			$pdo_query = $resq->prepare($sql);
			$pdo_query->execute();
			$taxContracts =$pdo_query->fetchAll(PDO::FETCH_ASSOC);   
//var_dump($taxContracts);
        }
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
		return $taxContracts;
	} // end get contracts
	

	
	// retrieves item records from the avatax export stored procedure.
	public function getContractItems ($contractno) {
//$sp = 'sandbox.dbo.avatax_export';  
		$sql = 'EXEC [SANDBOX].[DBO].avatax_export :contractno';
		// might need to pass biz
		try{
			$pdo_query=$this->resq->prepare($sql);
			$pdo_query->bindParam(':contractno',$contractno);
			$pdo_query->execute();
			$taxContractItems =$pdo_query->fetchAll(PDO::FETCH_ASSOC);
		
		}
		catch (PDOException $e)
		
		 {
			return $e->getMessage();
		}
		//var_dump($contractItems);	
//die();	
		return $taxContractItems;
	}	//  end get Contract Items
	


// Update tax record	
	public function updateTaxRecord($taxRecord) {
//		$table="AvalaraResults";
//		$taxTitle="TAXAMOUNT";
//		$msgTitle="MESSAGE";
//	} // end if type contract
		$sql="UPDATE [SANDBOX].[dbo].[AvalaraResults] set [TAXAMOUNT] = '". $taxRecord['taxamount'] ."',[STATUS] = '" . $taxRecord['status'] . "',[MESSAGE]= '". $taxRecord['message'] . "', [PROCESSDATE] = GETDATE()   WHERE [ITEMNO] = '" . $taxRecord['itemno']. "' AND [TYPE] ='". $taxRecord['type']   ."'AND [STATUS] !='ERROR' ";
//var_dump($sql);		
		try{
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->execute();
		}
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
	} // end update tax record
	
	
// Update tax item records - bulk update of all item records of the tax request.
	public function updateTaxItemRecords($taxRecord) {
		$sql="UPDATE [SANDBOX].[dbo].[AvalaraResults] set [TAXAMOUNT] = '". $taxRecord['taxamount'] ."',[MESSAGE]= '". $taxRecord['message'] ."',[ATTEMPTS]=". $taxRecord['attempts'] . "   WHERE [ORDERNO] = '" . $taxRecord['orderno']. "'";		 
//var_dump($sql);		
		try{
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->execute();
		}
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
	} // update tax record bulk
	
	
	public function checkForOldRecords($contractno) {

		echo "In Check records <br>";
		//echo $contractno ."br" ;
	//	var_dump ($contractno);
		$sql="SELECT COUNT(*) AS [RECORDCOUNT], (Select top 1 [attempts] from [SANDBOX].[DBO].[AvalaraResults] WHERE [ORDERNO] = '" . $contractno . "' )as [ATTEMPTS],	  (Select top 1 [MESSAGE] from [SANDBOX].[DBO].[AvalaraResults] WHERE [ORDERNO] = '" . $contractno . "' )as [MSG]      FROM [SANDBOX].[DBO].[AvalaraResults] WHERE [ORDERNO] = '" . $contractno . "'";
	
	//    $sql="SELECT COUNT(*) AS [RECORDCOUNT]	FROM [SANDBOX].[DBO].[AvalaraResults] WHERE [ORDERNO] = '" . $contractno . "'";
	//var_dump($sql);

	try{
			$pdo_query = $this->resq->prepare($sql);
		  $pdo_query->execute();
			$result =$pdo_query->fetchAll(PDO::FETCH_ASSOC);
		//	var_dump($result);
		$rowCount= (int)$result[0]['RECORDCOUNT'];
		$attempts= (int)$result[0]['ATTEMPTS'];
		$msg=$result[0]['MSG'];
	   	//var_dump ($rowCount);
		//var_dump($attempts);
			}	
			
		//$recCounteiSomeVar = (int) $sSomeOtherVar;
			catch (PDOException $e)
		{
			return $e->getMessage();
		}
		
		 return array ($rowCount,$attempts,$msg);
//		return $result;/
	}  // end count Item Records
	
	
	public function destroyItemRecords($contractno) {
		try{
			$sql="DELETE FROM [SANDBOX].[DBO].[AvalaraResults] WHERE [ORDERNO] = '" . $contractno . "'";
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->execute();
		}
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
	}  // end count Item Records
	
	// insert tax record 
	public function insertTaxRecord($taxRecord) {
		$sql= "INSERT INTO [SANDBOX].[dbo].[AvalaraResults]    ([TYPE],[ORDERNO],[ITEMNO],[MASTERNO],[TAXAMOUNT],[STATUS],[MESSAGE],[CONITEMNO],[biz], [PROCESSDATE])  VALUES ('".$taxRecord['type']."','".$taxRecord['orderno']."','".$taxRecord['itemno']."','".$taxRecord['masterno']."',".$taxRecord['taxamount'].",'".$taxRecord['status']."','".$taxRecord['message']."','".$taxRecord['itemno'] ."','".    $taxRecord['BIZ']."', GETDATE())";
//	var_dump($sql);
//	die();
		
		try{
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->execute();
		}
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
	}  // end insert tax record
	
	
	
	
	
	public function checkLockedTax($itemno) {
		echo "in check locked tax \n";
		var_dump($itemno);
		//$sql="Select tax3amount from contractitem where conitemno ='".$itemno."'";
		
		
		//	try{
		//	$pdo_query = $this->resq->prepare($sql);
		//	$pdo_query->execute();
		
		
		
		
		//	$result =$pdo_query->fetchAll(PDO::FETCH_ASSOC);
		//	var_dump($result);
		//$rowCount= (int)$result[0]['RECORDCOUNT'];
		//$attempts= (int)$result[0]['ATTEMPTS'];
		//$msg=$result[0]['MSG'];
	   	//var_dump ($rowCount);
		//var_dump($attempts);
		
		
		
		
		
		
	//	}
	//	catch (PDOException $e)
	//	{
	//		return $e->getMessage();
	//	}
		
		
		
	} // end checkLockedTax
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
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
		    //return $e->getMessage();
			throw new Exception ('Unable to Connect to Resq; ' . $e->getMessage());
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
		
		
		
			if ($biz =="dashandalbert") {
				$db="dash_albert";
			}
		else {
			
			$db=$biz;
			
		}
		
		
	// if more than one busines is not the same as db then go into switch logic	
		
//echo "biz biz biz biz biz " . $biz;
//***		switch($biz)
//***		{
	//		case 'dash':
	//			$db = 'dash_albert';
	//		break;
//
//			case 'pch':
//				$db = 'pineconehill';
//			break;/

//			case 'outlet':
//				$db = 'outlet';
//			break;/
//
//			case 'potluck':
//				$db = 'potluck';
//			break;

//			case 'fresh':
//				$db = 'fresh'; // TESTMOD: resq isn't set up yet
//			break;
		
//			case 'luxe':
//				$db = 'luxe';
//				$db = 'colosql2.luxe';
//			break;
//		case 'home':
//				$db = 'HOME';
//				$db = 'colosql2.luxe';
//			break;
//			default:
				// throw new Exception("Couldn't find db for app.yml biz '$biz'");
	//			$db = 'pch';
	//		break;
//*****		}    // end return db switch

	//	if ($daily === true)
		//	$db .= '_daily';
	//	else if ($test === true)
		//	$db .= '_dev';
		
	//	$this->database=$db;
       return $db;
	} // end getdb
	
	
	
	
} // end class





?>

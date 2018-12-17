<?php
Class REZTMP extends resqDb_Core {
	public $database;
	public $taxContractInfo=array();
	function setvars(){
	$this->resq=$this->connecttmp(); 
} // init










public function getTaxContractsSI($recOffset, $blockLength) 
{
	$sql="SELECT  [contractno],[RevContract],[Customerno],[company],[masterno],[qty],[RevQty],[DESCRIPTION],[PRICEPER],[PRICE],[RevPrice],[SHIPADDRESS1],[SHIPCITY],[SHIPSTATE],[SHIPZIP] FROM [SANDBOX].[dbo].[AVATAXRS] ORDER by [contractno] OFFSET " .  $recOffset  .  " ROWS  FETCH NEXT ". $blockLength .  " ROWS ONLY"; 
var_dump($sql);
  
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
		
		
	
	
	
}


public function getTaxContractsMU($recOffset,$blockLength)  {
	
	
		$sql="SELECT  distinct   [contractno] FROM [SANDBOX].[dbo].[AVATAXRM] ORDER by [contractno] OFFSET " .  $recOffset  .  " ROWS  FETCH NEXT ". $blockLength . " ROWS ONLY";
var_dump($sql);
	
	
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
	
} 
















 
	
	
	
	// retrieves item records from the avatax export stored procedure.
	public function getContractItems ($contractno) {
		$sql="	SELECT  [contractno],[RevContract] ,[Customerno] ,[company]  ,[masterno] ,[qty]  ,[RevQty] ,[DESCRIPTION] ,[PRICEPER]  ,[PRICE] ,[RevPrice],[SHIPADDRESS1],[SHIPCITY],[SHIPSTATE] ,[SHIPZIP]    FROM [SANDBOX].[dbo].[AVATAXRM] where contractno =  '"  . $contractno . "'" ;
		$resq=$this->connecttmp(); 
		try
		{
			$pdo_query = $resq->prepare($sql);
			$pdo_query->execute();
			$taxContractItems =$pdo_query->fetchAll(PDO::FETCH_ASSOC);   
//var_dump($taxContracts);
        }
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
		
		
		
		
		
 
		return $taxContractItems;
	}	//  end get Contract Items
	


// Update tax record	
	public function markProcessed($tax,$contract,$master) {
//		$table="AvalaraResults";
//		$taxTitle="TAXAMOUNT";
//		$msgTitle="MESSAGE";
//	} // end if type contract
		$sql="UPDATE [SANDBOX].[dbo].[AVATAX-R] set [ITEMTYPE] = 'RMA Processed. TAX: ". $tax ."' WHERE [contractno] = '" . $contract. "' AND [masterno] ='". $master ."' ";
           var_dump($sql);		
		try{
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->execute();
		}
		catch (PDOException $e)
		{
			return $e->getMessage();
		}
	} // end update tax record
	
	
 
	
 
	
	
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
	
	
	 
	
	
	

	
	
	
	
} // end class





?>

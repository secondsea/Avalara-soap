<?php
/**
 * BatchSvc.class.php
 */

/**
 * 
 *
 * @author    Avalara
 * @copyright © 2004 - 2011 Avalara, Inc.  All rights reserved.
 * @package   Batch
 */

namespace AvaTax\Batch;
use AvaTax\ATConfig;
use AvaTax\DynamicSoapClient;
class BatchSvc extends \SoapClient {

  	private $client;
	private static $classmap = array(
                                    'BatchFetch' => 'AvaTax\Batch\BatchFetch',
                                    'FetchRequest' => 'AvaTax\Batch\FetchRequest',
                                    'BatchFetchResponse' => 'AvaTax\Batch\BatchFetchResponse',
                                    'BatchFetchResult' => 'AvaTax\Batch\BatchFetchResult',
                                    'BaseResult' => 'AvaTax\Batch\BaseResult',
                                    'SeverityLevel' => 'AvaTax\Batch\SeverityLevel',
                                    'Message' => 'AvaTax\Batch\Message',
                                    'Batch' => 'AvaTax\Batch\Batch',
                                    'BatchFile' => 'AvaTax\Batch\BatchFile',
                                    'Profile' => 'AvaTax\Batch\Profile',
                                    'BatchSave' => 'AvaTax\Batch\BatchSave',
                                    'BatchSaveResponse' => 'AvaTax\Batch\BatchSaveResponse',
                                    'BatchSaveResult' => 'AvaTax\Batch\BatchSaveResult',
                                    'AuditMessage' => 'AvaTax\Batch\AuditMessage',
                                    'BatchDelete' => 'AvaTax\Batch\BatchDelete',
                                    'DeleteRequest' => 'AvaTax\Batch\DeleteRequest',
                                    'FilterRequest' => 'AvaTax\Batch\FilterRequest',
                                    'BatchDeleteResponse' => 'AvaTax\Batch\BatchDeleteResponse',
                                    'DeleteResult' => 'AvaTax\Batch\DeleteResult',
                                    'FilterResult' => 'AvaTax\Batch\FilterResult',
                                    'BatchProcess' => 'AvaTax\Batch\BatchProcess',
                                    'BatchProcessRequest' => 'AvaTax\Batch\BatchProcessRequest',
                                    'BatchProcessResponse' => 'AvaTax\Batch\BatchProcessResponse',
                                    'BatchProcessResult' => 'AvaTax\Batch\BatchProcessResult',
                                    'BatchFileFetch' => 'AvaTax\Batch\BatchFileFetch',
                                    'BatchFileFetchResponse' => 'AvaTax\Batch\BatchFileFetchResponse',
                                    'BatchFileFetchResult' => 'AvaTax\Batch\BatchFileFetchResult',
                                    'BatchFileSave' => 'AvaTax\Batch\BatchFileSave',
                                    'BatchFileSaveResponse' => 'AvaTax\Batch\BatchFileSaveResponse',
                                    'BatchFileSaveResult' => 'AvaTax\Batch\BatchFileSaveResult',
                                    'BatchFileDelete' => 'AvaTax\Batch\BatchFileDelete',
                                    'BatchFileDeleteResponse' => 'AvaTax\Batch\BatchFileDeleteResponse',
                                    'Ping' => 'AvaTax\Batch\Ping',
                                    'PingResponse' => 'AvaTax\Batch\PingResponse',
                                    'PingResult' => 'AvaTax\Batch\PingResult',
                                    'IsAuthorized' => 'AvaTax\Batch\IsAuthorized',
                                    'IsAuthorizedResponse' => 'AvaTax\Batch\IsAuthorizedResponse',
                                    'IsAuthorizedResult' => 'AvaTax\Batch\IsAuthorizedResult',
                                   );

	public function __construct($configurationName = 'Default')
    {
        $config = new ATConfig($configurationName);
        
        $this->client = new DynamicSoapClient   (
            $config->batchWSDL,
            array
            (
                'location' => $config->url.$config->batchService, 
                'trace' => $config->trace,
                'classmap' => BatchSvc::$classmap
            ), 
            $config
        );
    }    

  /**
   * Fetches one or more Batch 
   *
   * @param BatchFetch $parameters
   * @return BatchFetchResponse
   */  
    public function BatchFetch(&$fetchRequest) {    
      
      return $this->client->BatchFetch(array('FetchRequest' => $fetchRequest))->getBatchFetchResult();
  }

  /**
   * Saves a Batch entry 
   *
   * @param BatchSave $parameters
   * @return BatchSaveResponse
   */
  public function BatchSave(&$batch) {
   	
  	return $this->client->BatchSave(array('Batch' => $batch))->getBatchSaveResult();
  	
  }

  /**
   * Deletes one or more Batches 
   *
   * @param BatchDelete $parameters
   * @return BatchDeleteResponse
   */
  public function BatchDelete(&$deleteRequest) {
     	
  	return $this->client->BatchDelete(array('DeleteRequest' => $deleteRequest))->getBatchDeleteResult();
  	
  }

  /**
   * Processes one or more Batches 
   *
   * @param BatchProcess $parameters
   * @return BatchProcessResponse
   */
  public function BatchProcess(&$batchProcessRequest) {
    
  	return $this->client->BatchProcess(array('BatchProcessRequest' => $batchProcessRequest))->getBatchProcessResult();
  	
  }

  /**
   * Fetches one or more BatchFiles 
   *
   * @param BatchFileFetch $parameters
   * @return BatchFileFetchResponse
   */
  public function BatchFileFetch(&$fetchRequest) {
  	
  	return $this->client->BatchFileFetch(array('FetchRequest' => $fetchRequest))->getBatchFileFetchResult();
    
  }

  /**
   * Saves a Batch File 
   *
   * @param BatchFileSave $parameters
   * @return BatchFileSaveResponse
   */
  public function BatchFileSave(&$batchFile) {   
  	
  	return $this->client->BatchFileSave(array('BatchFile' => $batchFile))->getBatchFileSaveResult();
  	
  }

  /**
   * Deletes one or more BatchFiles 
   *
   * @param BatchFileDelete $parameters
   * @return BatchFileDeleteResponse
   */
  public function BatchFileDelete(&$deleteRequest) {
    
  	return $this->client->BatchFileDelete(array('DeleteRequest' => $deleteRequest))->getBatchFileDeleteResult();
  	
  }

  /**
   * Tests connectivity and version of the service 
   *
   * @param Ping $message
   * @return PingResponse
   */
  public function Ping($message = '') {    
      return $this->client->Ping(array('Message' => $message))->getPingResult();
  }

  /**
   * Checks authentication and authorization to one or more operations on the service. 
   *
   * @param IsAuthorized $operations
   * @return IsAuthorizedResponse
   */
public function IsAuthorized($operations)
    {
        return $this->client->IsAuthorized(array('Operations' => $operations))->getIsAuthorizedResult();
    }

}

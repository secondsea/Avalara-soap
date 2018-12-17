<?php
/**
 * CancelTaxResult.class.php
 */

/**
 * Result data returned from {@link TaxSvcSoap#cancelTax}
 * @see CancelTaxRequest
 *  
 * @author    Avalara
 * @copyright © 2004 - 2011 Avalara, Inc.  All rights reserved.
 * @package   Tax
 * 
 */
// namespace AvaTax;
class CancelTaxResult // extends BaseResult
{
    

// BaseResult innards - workaround a bug in SoapClient

/**
 * @var string
 */
    private $TransactionId;
/**
 * @var string must be one of the values defined in {@link SeverityLevel}.
 */
    private $ResultCode = 'Success';
/**
 * @var array of Message.
 */
    private $Messages = array();
	
	private $DocId;

/**
 * Accessor
 * @return string
 */
    public function getTransactionId() { return $this->TransactionId; }
	
	public function getDocId() { return $this->DocId; }
	
/**
 * Accessor
 * @return string
 */
    public function getResultCode() { return $this->ResultCode; }
/**
 * Accessor
 * @return array
 */
    public function getMessages() { return Utils::EnsureIsArray($this->Messages->Message); }



}

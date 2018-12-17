<?php
/**
 * add this PostTaxResult.class.php
 */

/**
 * Result data returned from {@link TaxServiceSoap#postTax}.
 *
 * @see PostTaxRequest
 * 
 * @author    Avalara
 * @copyright © 2004 - 2011 Avalara, Inc.  All rights reserved.
 * @package   Tax
 */
// namespace AvaTax;
class PostTaxResult //extends BaseResult
{
  
		
// BaseResult innards - work around a bug in SoapClient

/**
 * @var string
 */
    private $TransactionId;
	
	private $DocId;
/**
 * @var string must be one of the values defined in {@link SeverityLevel}.
 */
    private $ResultCode = 'Success';
/**
 * @var array of Message.
 */
    private $Messages = array();

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

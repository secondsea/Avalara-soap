<?php
/**
 * ATConfig.class.php
 */
 
/**
 * Contains various service configuration parameters as class static variables.
 *
 * {@link AddressServiceSoap} and {@link TaxServiceSoap} read this file during initialization.
 *
 * @author    Avalara
 * @copyright © 2004 - 2011 Avalara, Inc.  All rights reserved.
 * @package   Base
 */
// namespace AvaTax;
class ATConfig
{
    private static $Configurations = array();
    private $_ivars;
    
    public function __construct($name, $values = null)
    {
        if($values)
        {
            ATConfig::$Configurations[$name] = $values;
        }
        $this->_ivars = ATConfig::$Configurations[$name];
    }
    
    public function __get($n) 
    {
        if($n == '_ivars') { return parent::__get($n); }
        if(isset($this->_ivars[$n])) 
        {
            return $this->_ivars[$n]; 
        }
        else if(isset(ATConfig::$Configurations['Default'][$n])) // read missing values from default
        {
            return ATConfig::$Configurations['Default'][$n]; 
        }
        else
        {
            return null;
        }
    }
}
/* Specify configurations by name here.  You can specify as many as you like */


$__wsdldir = dirname(__FILE__)."/wsdl";

/* This is the default configuration - it is used if no other configuration is specified */
new ATConfig('Default', array(
    'url'       => 'no url specified',
    'addressService' => '/Address/AddressSvc.asmx',
    'taxService' => '/Tax/TaxSvc.asmx',
	'batchService'=> '/Batch/BatchSvc.asmx',
    'addressWSDL' => 'file://'.$__wsdldir.'/Address.wsdl',
    'taxWSDL'  => 'file://'.$__wsdldir.'/Tax.wsdl',
	'batchWSDL'  => 'file://'.$__wsdldir.'/BatchSvc.wsdl',
	'avacert2WSDL'  => 'file://'.$__wsdldir.'/AvaCert2Svc.wsdl',
    'account'   => '',
    'license'   => '',
    'adapter'   => 'avatax4php,14.2.0.0',
    'client'    => 'AvalaraPHPInterface,1.0',
	'name'    => '13.7.0.0',
    'trace'     => true) // change to false for production
);
/* Authentication Credentials
 * 
 * Development Account
 * TODO: Modify the account and license key 
 *       values below with your own.
 * 
 * Note: The ATConfig object is how Authentication credentials are set. 
 */
new ATConfig('Development', array(
//'url'=>'https://avatax.avalara.net/1.0/tax/get',
 //   'url'       => 'https://development.avalara.net',
    'url'       => 'https://avatax.avalara.net',
    'account'   => '1100013556',
    'license'   => '218025EBF2B8A610',
    'trace'     => true, // change to false for production
    'client' => 'AvaTaxSample',
	'name' => '14.4')
);

/* Production Account
 * TODO: Modify the account and license key 
 *       values below with your own.
 */
new ATConfig('Production', array(
 //      'url'=>'https://avatax.avalara.net/1.0/tax/get',
    'url'       => 'https://avatax.avalara.net',
    'account'   => '1100013556',
    'license'   => '218025EBF2B8A610',
    'trace'     => false, // change to false for development
	'client' => 'AvaTaxSample',
	'name' => '14.4')
);


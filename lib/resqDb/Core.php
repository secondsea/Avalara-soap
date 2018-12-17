<?php

/**
 * @author eric
 * @package ResqDb
 * @subpackage Core
 * Core functionality for hooking into exposed web logic in tsql from php
 */

class resqDb_Core
{
	/**
	 * @var mixed $resq		(Resq PDO Connection, or mssql/MS connection resource)
	 */
	public $resq;

	/**
	 * The fully-qualified db (e.g., dash_albert_dev).
	 * @see get_object()
	 * @var string
	 */
	public $biz = null;

	/**
	 * @param string $connect (pdo|mssql) - the type of sql connection to make (default: pdo)
	 * @param string $db (optional) - if omitted, defaults to biz in app.yml
	 */
	public function __construct($connect = 'pdo', $biz = null)
	{
		// determine connection-type
		switch ($connect)
		{
			case 'mssql':
				$method = 'connect2';
			break;

			/*
			case 'ms':
				$method = 'connect3';
			break;
			*/

			default:
				$method = 'connect';
			break;
		}

		$this->resq = resq::$method();

		// in case of heterogenous queries, avoid any issues by setting what the defaults should already be
		if ($connect == 'mssql')
		{
			mssql_query("SET ansi_warnings ON", $this->resq);
			mssql_query("SET ansi_nulls ON", $this->resq);
		}

		$this->biz = $biz;
	}

	/**
	 * returns the connect handler
	 * @return mixed (PDO object, or connection resource)
	 */
	public function get_handle()
	{
		return $this->resq;
	}

	/**
	 * return the proper resq view, or stored proc, with biz support.
	 *
	 * @param string $name - the base name of the view
	 * @see $biz
	 * @return string (the fully-qualified view, or proc, name)
	 */
	 public function get_object($name)
	 {
		 return resq::get_view($name, $this->biz);
	 }

	 /**
	  * return the db, based on the current resqDb biz.
	  * @return string (the database name)
	  */
	 public function get_db()
	 {
		 return resq::get_db($this->biz);
	 }


	/**
	 * get the function name for the correct db
	 * e.g., pineconehill_dev.dbo.fx_date_precision
	 * @param string $name - the name of the string (WITHOUT THE FX_)
	 * @see $biz
	 * @return string fully-qualified name of the function
	 */
	public function get_function($name)
	{
		return resq::get_function($name, $this->biz);
	}

	/**
	 * is the current connection type mssql (old)?
	 * @return mixed pdo|mssql|unknown, or null if no handle
	 */
	public function link_type()
	{
		$type = null; // no resource is default

		if (is_resource($this->resq))
		{
			$resource = get_resource_type($this->resq);
			switch ($resource)
			{
				case 'mssql link':
					$type = 'mssql';
				break;

				default:
					$type = 'unknown'; // unknown resource type
				break;
			}
		}
		else if ($this->resq instanceof PDO)
			$type = 'pdo';

		return $type;
	}

	/**
	 * if connection handle doesn't match, throw an Exception.
	 * @param string $handle - the handle
	 * @see function link_type()
	 * @return bool
	 */
	public function require_handle($handle)
	{
		$link = $this->link_type();
		if (strcasecmp($link, $handle) !== 0)
				throw new Exception("Wrong connection type. Expected: '{$handle}', Actual: '{$link}'");
		else return true;
	}

	/**
	 * Convenience method for initializing mssql proc
	 * @see resq::get_view()
	 * @param string $name
	 * @param bool $append_db - (default true) - are we appending _biz(_dev) on the end?
	 * @return resource (mssql proc instance)
	 */
	public function init_proc($name, $append_db = true)
	{
		if ($append_db === true)
			$name = $this->get_object($name);

		return mssql_init($name, $this->resq);
	}

	/**
	 * Process the confuckulation of mssql proc errors
	 * Check for standard resq errors
	 * If output variable pass, check for 'failed' value.
	 * @param resource $exec - returned from mssql_execute
	 * @param mixed $output (output) - the output variable that was supposed to be set
	 * @param string $output_field (output) - the output field name
	 * @throws Exception (if error detected)
	 * @return bool|array (resultset, if no error and present - otherwise, false)
	 */
	public function handle_proc_result($exec, $output = false, $output_field = null)
	{
		$this->require_handle('mssql');

		// we had an error, let's handle it, ghetto-ass php mssql style.
		if ($exec === false)
		{
			// we know we have a good handle (courtesy require_handle), so let's get more info.
			$error = mssql_get_last_message();
			$code_q = mssql_query('select @@ERROR as code', $this->resq);
			$rs = mssql_fetch_array($code_q, MSSQL_NUM);
			$code = ! empty($rs) ? $rs[0] : null;

			throw new Exception("(mssql_execute) Error #$code: $error");
		}

		//suppress no resultset error, only exists if proc dies
		$rs = @mssql_fetch_assoc($exec);

		$error_message = $this->get_rs_error($rs);

		// if error column exists, exception was thrown, capture and log.
		// we should have 2 keys, an error key and a message key. we want the message.
		if ($error_message !== false)
			throw new Exception('(mssql_execute) ' . $error_message);

		/*
		 * ok, now we know that it's a legit resultset.
		 * since we're using bullshit mssql here, we have to loop through append any additional rows we find.
		 */

		$rows = array();
		if ( ! empty($rs))
			$rows[] = $rs;

		while ($row = @mssql_fetch_assoc($exec))
			$rows[] = $row;

		// output not passed, no output checking...
		if ($output !== false)
		{
			// if output_field is passed, do last check in RS for this value
			if ($output_field !== null && $output !== null && isset($rs[$output_field]))
				$output = $rs[$output_field];

			if ($output == 'failed')
				throw new Exception("(mssql_execute) Output variable '$output_field' returned as 'failed', uncaught error on sql side.");

			if ($output === null)
				throw new Exception("(mssql_execute) Output variable '$output_field' was never set.");
		}

		// resultset didnt error, so return.
		return $rows;
	}

	/**
	 *  MSSQL only. does the RS have an error present? if so, return the message.
	 * @see pchArray::key_exists
	 * @param array $rs - PDO resultset
	 * @return string|bool - false if no message, otherwise, the string
	 */
	public function get_rs_error($rs)
	{
		$this->require_handle('mssql');

		// if we have a message, grab it.
		if (pchArray::key_exists('nerror', $rs) || pchArray::key_exists('error', $rs))
		{
			// should be the last key: errormessage, or error.
			$message = end($rs);
		}
		else
			$message = false;

		return $message;
	}

	/**
	 * catch mssql proc errors
	 * @param Exception $e
	 * @param string $proc - SP name
	 * @param string $title - production error title
	 * @param string $message - production error message
	 * @param string $page - production error page (default: error)
	 */
	public function catcher(Exception $e, $info, $error_title, $pretty_error = '@pdo-error', $page = 'error')
	{
		
		$debug_message = "$info\n" . $e->getMessage();

		$action = sfContext::getInstance()->getActionStack()->getLastEntry()->getActionInstance();
		$action->setFlash('dev_error', $debug_message);

		$is_cli = ( ! isset($_SERVER['HTTP_HOST']));
		
		message::log_error($debug_message);

		if (SF_ENVIRONMENT == 'prod' && ! $is_cli)
		{
			$title = $error_title;
			$message = $pretty_error;
		}
		else
		{
			$title = 'ResqDB Error:';
			$message = $debug_message;
			$page = 'error';
		}
		
		return message::error($title, $message, $page);
	}

}
<?php

class resq
{
	
	
	function ms_escape_string($data) {
        if ( !isset($data) or empty($data) ) return '';
        if ( is_numeric($data) ) return $data;

        $non_displayables = array(
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        );
        foreach ( $non_displayables as $regex )
            $data = preg_replace( $regex, '', $data );
        $data = str_replace("'", "''", $data );
		
        return $data;
    }

	function resq_connect()
	{
		$db = 'sandbox';
		$host = 'pchsql';
		$user = 'PCH4_w3b5-Usr6';
		$pass = 'p33c33aitch_w3b-p455';

		try
		{
			$resq = mssql_connect($host, $user, $pass);
			if ($resq === false)
				throw new Exception('Resq connect error: ' . mssql_get_last_message());

			if (mssql_select_db($db, $resq) === false)
				throw new Exception('Resq db select error: ' . mssql_get_last_message());
		}
		catch (Exception $e)
		{
			return $e->getMessage();
		}

		return $resq;
	}
	
	/**
	 * Currently in test resq mode, or live resq mode? Combination of is_test and our force live constants
	 * @see myUser::is_test()
	 * @see PCH\FORCE_ALL, PCH\FORCE_RESQ
	 * @return bool
	 */
	 public static function test_mode()
	 {
		if ( (defined('PCH\LIVE_ALL') and PCH\LIVE_ALL === true) || (defined('PCH\LIVE_RESQ') and PCH\LIVE_RESQ === true) )
			$test = false;
		else
			$test = false;//sfContext::getInstance()->getUser()->is_test();

		return $test;
	 }
	 
	/**
	 * Currently in daily resq mode? Combination of is_test and our force live constants
	 * @see PCH\FORCE_ALL, PCH\FORCE_RESQ
	 * @return bool
	 */
	 public static function daily_mode()
	 {
		$daily = false;
		 
		if ( (defined('PCH\DAILY_ALL') and PCH\DAILY_ALL === true) || (defined('PCH\DAILY_RESQ') and PCH\DAILY_RESQ === true) )
			$daily = true;

		return $daily;
	 }

	/**
	 * return the proper resq view
	 *
	 * @param string $name - the base name of the view
	 * @param string $biz (optional)
	 * @see resq::check_biz()
	 * @return string (the fully-qualified view name)
	 */
	public static function get_view($name, $biz = null)
	{
		$biz = resq::check_biz($biz);
		$test = self::test_mode();

		$view = "sandbox.dbo.{$name}_" . $biz;

		if ($test)
			$view .= '_dev';

		return $view;
	}

	/**
	 * return the appropriate resq db, given the biz
	 * @param string $biz (optional)
	 * if is_test(), use dev db.
	 * @see resq::check_biz()
	 * @see myUser::is_test()
	 * @throws Exception (if unhandled DB passed)
	 * @return string (the mssql database name)
	 */
	public static function get_db($biz = null)
	{
		$biz = resq::check_biz($biz);
		$test = self::test_mode();
		$daily = self::daily_mode();

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

			default:
				throw new Exception("Couldn't find db for app.yml biz '$biz'");
		}

		if ($daily === true)
			$db .= '_daily';
		else if ($test === true)
			$db .= '_dev';
		
		return $db;
	}

	/**
	 * return proper biz based on app.yml and pchUser::is_outlet()
	 * if biz is null, get biz value from app.yml
	 * if is pch and is outlet, set to outlet for proper resq/proc usage
	 * @param string $biz (optional), if null, use app.yml value
	 * @return string (biz)
	 */
	public static function check_biz($biz = null)
	{
		if ($biz === null)
			$biz = sfConfig::get('app_biz');

		if ($biz == 'pch' && sfContext::getInstance()->getUser()->is_outlet())
			$biz = 'outlet';

		return $biz;
	}

	/**
	 * return the appropriate resq db, given the biz
	 * @see self::get_db
	 * @param string $biz (optional) - if omitted, use biz in app.yml
	 * @return string (the mssql database name)
	 */
	public static function get_function($name, $biz = null)
	{
		$db = self::get_db($biz);

		return $db . '.dbo.fx_' . $name;
	}

	public static function connect()
	{
		$connect = resqConnect();
		if (is_string($connect))
			throw new Exception('resqConnect error: ' . $connect);
		
		return $connect;
	}
	
	//connect with no PDO
	public static function connect2()
	{
		$connect = resqConnectOld();
		if (is_string($connect))
			throw new Exception('resqConnect error: ' . $connect);

		return $connect;
	}
	
	//new-fangled ms drive connect
	public static function connect3()
	{
		return resqConnectNew();
	}
}

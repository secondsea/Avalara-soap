<?php

/**
 * @author eric
 * @package ResqDb
 * Site-specific resq selects/writebacks from mysql -> tsql
 * @todo move cc logic to a resqDb_CC class.
 */

class resqDb extends resqDb_Core
{
	/**
	 * get shipping addresses for this customer from resq
	 * @param string $customerno
	 * @param bool|int $location_id - if false, return all addresses formatted for confirm shipping page, if int, return single address)
	 * @return array (all of customer's addresses, or single address)
	 */
	public function get_order_addresses($customerno, $location_id = false)
	{
		$shipping_view = $this->get_object('shipping');
		
		$sql = "select * from $shipping_view where customerno = :me";
		$sql .= ($location_id === false) ? " order by main_flag" : " and id = :id";
		
		try
		{
			$this->require_handle('pdo');
			$pdo_query = $this->resq->prepare($sql);
			$pdo_query->bindParam(':me', $customerno);
			
			if ($location_id !== false)
			{
				$pdo_query->bindParam(':id', $location_id);
				$pdo_query->execute();
				return $pdo_query->fetch(PDO::FETCH_ASSOC);
			}
			else
			{
				$pdo_query->execute();
			
				$db_locations = $pdo_query->fetchAll(PDO::FETCH_ASSOC);
			
				$locations = array();
				foreach ($db_locations as $location)
				{
					unset($address);
					$address['id'] = $location['id'];
					$address['siteno'] = $location['siteno'];
					$address['sitename'] = $location['sitename'];
					$address['address1'] = $location['address1'];
					$address['address2'] = $location['address2'];
				
					//remove blanks - country not needed.
					$stateline = array_filter(array($location['city'], $location['state'], $location['zip']));
					$address['address3'] = implode(', ', $stateline);
					$address['radio_name'] = 'location_' . $location['siteno'];
				
					$locations[] = $address;
					
				}
				
				return $locations;
			}
		} 
		catch (PDOException $e)
		{
			pch::catcher($e, $pdo_query, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
		
	}
	
	/**
	 * proxy method for get_order_addresses, gets single address
	 * @param string $customerno
	 * @param int $my_location integer (location id for address)
	 * @return array (shipping address for this customer)
	 */
	public function get_order_address($customerno, $my_location)
	{
		return $this->get_order_addresses($customerno, $my_location);
	}

	/**
	 * get all credit cards stored for this customer
	 * @param string $customerno
	 * @param bool|int $cc - if false, return all cards, if int, return single card info)
	 * @return array (all of customer's cards, or single card)
	 */
	public function get_cards($customerno, $cc = false)
	{
        
		$cc_view = $this->get_object('cc');
		
		$sql = "select * from {$cc_view} where customerno = :customerno";
		if ($cc !== false)
			$sql .= " and ccardno = :cc";
		
		try
		{
			$this->require_handle('pdo');
			$resq_sql = $this->resq->prepare($sql);
			$resq_sql->bindParam(':customerno', $customerno);
			
			if ($cc !== false)
				$resq_sql->bindParam(':cc', $cc);
				
			$resq_sql->execute();
			
			if ($cc !== false)
				return $resq_sql->fetch(PDO::FETCH_ASSOC);
			else return $resq_sql->fetchAll(PDO::FETCH_ASSOC);
		} 
		catch (PDOException $e) 
		{
			pch::catcher($e, $resq_sql, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
	}

	/**
	 * create a record of the current card in the creditcards table
	 * this number is passed to cc_auth and stored in cc_tran, for reference
	 * no sensitive card data (number/ccv/expiry) is saved.
	 * TODO: store CIM here, also
	 * @param string $cardname - Cardholder's name
	 * @param string $cardtype - amex|discover|mastercard|visa
	 * @param int $last4
	 * @param string $customerno
	 * @param string $retail_id
	 * @return string (the ccardno of the inserted, or existing, creditcard table entry)
	 */
	public function add_retail_cc($cardname, $cardtype, $last4, $customerno, $retail_id, $cc = null, $retain = 0)
	{
		try
		{
			$this->require_handle('mssql');
			$proc = $this->init_proc('add_retail_cc');
			
			if (empty($cc))
			{
				$cc = array('cc' => null, 'ccv' => null);
				$expiry = null;
			}
			else $expiry = $cc['expiryYear'] . '-' . $cc['expiryMonth'];

			mssql_bind($proc, '@cardname', $cardname, SQLVARCHAR, false, false, 100);
			mssql_bind($proc, '@cardtype', $cardtype, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@last4digits', $last4, SQLVARCHAR, false, false, 4);
			mssql_bind($proc, '@customerno', $customerno, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@retail_id', $retail_id, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@cardno', $cc['cc'], SQLVARCHAR, false, false, 50);
			mssql_bind($proc, '@pin', $cc['ccv'], SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@expiry', $expiry, SQLVARCHAR, false, false, 7);
			mssql_bind($proc, '@retain', $retain, SQLINT1);
			mssql_bind($proc, '@first_name', $cc['firstName'], SQLVARCHAR, false, false, 100);
			mssql_bind($proc, '@last_name', $cc['lastName'], SQLVARCHAR, false, false, 100);

			mssql_bind($proc, '@ccardno', $ccardno, SQLVARCHAR, true);

			$exec = mssql_execute($proc);
			$this->handle_proc_result($exec, $ccardno, 'ccardno');
			return rtrim($ccardno);
		}
		catch(Exception $e)
		{
			pch::catcher($e, $proc, 'Order Creation', array('message' => '@cc-error'));
		}
	}

	/**
	 * get credit card info stored for this customer and card id
	 * @param string $customerno
	 * @param int $cc
	 * @return array (card info for this customer and cardno)
	 */
	public function get_card($customerno, $cc)
	{
		return $this->get_cards($customerno, $cc);
	}

	/**
	 * create/update this customer's retail profile
	 * @param string $retail_id
	 * @param array $billing - doctrine retailAddress array
	 * @param string $email
	 * @return mixed (false if fail, else string id)
	 */
	public function retail_profile($retail_id, array $billing, $email)
	{
		try
		{
			// TESTMOD: to reproduce blank retail_id string
			//$proc = 'omgwtfbbq';
			// TESTMOD: useful for rapid-fire debugging, to avoid unique constraint.
			//$email = pch::generate_pass() . '@foo.com';

			$rid = null;
			$this->require_handle('mssql');
			$profile = $this->init_proc('retail_profile');
			$outlet = false; // (bool) sfContext::getInstance()->getUser()->is_outlet();
			$guest = sfContext::getInstance()->getUser()->is_guest(true);
			// prevent multiple spaces in name
			$name = trim(preg_replace('# +#', ' ', $billing['contact']));

			if (strcasecmp($billing['country'], 'can') == 0)
				$billing['country'] = 'CANADA';

			mssql_bind($profile, '@retail_id', $retail_id, SQLVARCHAR, false, false, 10);
			mssql_bind($profile, '@name', $name, SQLVARCHAR, false, false, 100);
			mssql_bind($profile, '@email', $email, SQLVARCHAR, false, false, 200);
			mssql_bind($profile, '@phone', $billing['phone'], SQLVARCHAR, false, false, 30);
			mssql_bind($profile, '@address1', $billing['address1'], SQLVARCHAR, false, false, 100);
			mssql_bind($profile, '@address2', $billing['address2'], SQLVARCHAR, false, false, 100);
			mssql_bind($profile, '@city', $billing['city'], SQLVARCHAR, false, false, 100);
			mssql_bind($profile, '@state', $billing['state'], SQLVARCHAR, false, false, 10);
			mssql_bind($profile, '@zip', $billing['zip'], SQLVARCHAR, false, false, 10);
			mssql_bind($profile, '@country', $billing['country'], SQLVARCHAR, false, false, 100);
			mssql_bind($profile, '@outlet', $outlet, SQLINT1);
			mssql_bind($profile, '@guest', $guest, SQLINT1);
			
			mssql_bind($profile, '@id', $rid, SQLVARCHAR, true);
			$exec = mssql_execute($profile);
			$this->handle_proc_result($exec, $rid, 'id');

			return $rid;
		}
		catch(Exception $e)
		{
			pch::catcher($e, $profile, 'Retail Account Creation', array('message' => '@retail-account-fail'));
		}

	}

	/**
	 * get a contractno to be used to create an order
	 * @return string
	 */
	public function gen_contractno()
	{
		try
		{
			$contractno = null;
			$this->require_handle('mssql');
			$gen_contractno = $this->init_proc('gen_contractno');
			mssql_bind($gen_contractno, '@contractno', $contractno, SQLVARCHAR, true);
			
			$exec = mssql_execute($gen_contractno);
			$this->handle_proc_result($exec, $contractno, 'contractno');

			return $contractno;
		}
		catch(Exception $e)
		{
			pch::catcher($e, $gen_contractno, 'Order Creation', array('message' => '@pdo-error'));
		}
	}

	/**
	 * create a resq order
	 * @see CreateOrderAction::execute()
	 * @param string $contractno
	 * @param array $order_data
	 * @param array $ship_address
	 * @param array $ship_options
	 * @param array $text (note and comment assembly
	 * @param boolean $is_gma - GMA promo?
	 * @return boolean
	 */
	public function create_order($contractno, array $order_data, array $ship_address, array $ship_options, array $text, $is_gma)
	{
		try
		{
			$this->require_handle('mssql');
			$create_order = $this->init_proc('create_order');

			$user = sfContext::getInstance()->getUser();
			$spoof = $user->is_spoofed();
			$rep = $user->has_customer_mounted() && $user->is_salesrep();
			$outlet = $user->is_outlet();
			
			$_showcode = $user->get_show_code();
			$is_wholesaleorder = $user->is_wholesaleorder();
			
			// set showcode if set in session and is show ordering account
			$showcode = $_showcode === false || ! $is_wholesaleorder ? null : $_showcode;

			$pickup = strcasecmp($ship_options['method'], 'outlet_pickup') == 0;

			if (strcasecmp($ship_address['country'], 'can') == 0)
				$ship_address['country'] = 'CANADA';

			mssql_bind($create_order, '@customerno', $order_data['customerno'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@location_id', $order_data['location'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@ponum', $order_data['ponum'], SQLVARCHAR, false, false, 50);
			mssql_bind($create_order, '@dlvdate', $ship_options['future'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@canceldate', $ship_options['cancel_date'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@shipcompleteflag', $ship_options['complete'], SQLINT1);
			mssql_bind($create_order, '@min_price', $ship_options['min_cost'], SQLFLT8);
			mssql_bind($create_order, '@sidemark', $order_data['sidemark'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@shipcompany', $ship_address['sitename'], SQLVARCHAR, false, false, 200);
			mssql_bind($create_order, '@shipcomment', $ship_options['comment'], SQLVARCHAR, false, false, 500);
			mssql_bind($create_order, '@shipmethod', $ship_options['method'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@shipaddress1', $ship_address['address1'], SQLVARCHAR, false, false, 200);
			mssql_bind($create_order, '@shipaddress2', $ship_address['address2'], SQLVARCHAR, false, false, 200);
			mssql_bind($create_order, '@shipcity', $ship_address['city'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@shipstate', $ship_address['state'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@shipzip', $ship_address['zip'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@shipcountry', $ship_address['country'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@shipcontact', $ship_address['contact'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@shipphone', $order_data['phone'], SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@login_id', $order_data['login_id'], SQLFLT8);
			mssql_bind($create_order, '@login_name', $order_data['login_name'], SQLVARCHAR, false, false, 100);
			mssql_bind($create_order, '@cc', $order_data['cc'], SQLVARCHAR, false, false, 10);
			mssql_bind($create_order, '@retail_id', $order_data['retail_id'], SQLVARCHAR, false, false, 10);
			mssql_bind($create_order, '@existing_contractno', $contractno, SQLVARCHAR, false, false, 10);
			mssql_bind($create_order, '@spoof', $spoof, SQLINT1);
			mssql_bind($create_order, '@rep', $rep, SQLINT1);
			mssql_bind($create_order, '@comment', $text['comment'], SQLVARCHAR, false, false, 500);
			mssql_bind($create_order, '@note', $text['note'], SQLVARCHAR, false, false, 500);
			mssql_bind($create_order, '@req_sig', $ship_options['req_signature'], SQLINT1);
			mssql_bind($create_order, '@outlet', $outlet, SQLINT1);
			mssql_bind($create_order, '@pickup', $pickup, SQLINT1);
			mssql_bind($create_order, '@is_gma', $is_gma, SQLINT1);
			mssql_bind($create_order, '@showcode', $showcode, SQLVARCHAR, false, false, 20);
			mssql_bind($create_order, '@liftgate', $ship_options['lift_gate'], SQLINT1);
			mssql_bind($create_order, '@whiteglove', $ship_options['inside_delivery'], SQLINT1);

			$new_contractno = null;
			mssql_bind($create_order, '@contractno', $new_contractno, SQLVARCHAR, true);

			$exec_create = mssql_execute($create_order);
			$this->handle_proc_result($exec_create, $new_contractno, 'contractno');
		}
		catch (Exception $e)
		{
			pch::catcher($e, $create_order, 'Order Creation', array('message' => '@order-creation'));
		}
	}

	/**
	 * Insert resq order lineitems
	 * @param string $contractno
	 * @param array $orderitems
	 * @return void
	 */
	public function insert_orderitems($contractno, array $orderitems)
	{
		// insert lineitems
		try
		{
			// need to pass if this is retail so the correct account gets hit with the money on the resq end
			$user = sfContext::getInstance()->getUser();
			$retail = $user->is_retail();
			
			$insert_proc = $this->get_object('insert_orderitems');
			mssql_query('begin tran', $this->resq);

			foreach ($orderitems as $line)
			{
				$insert_lineitem = @mssql_init($insert_proc, $this->resq);
				if ($insert_lineitem === false)
				{
					$message = 'failed to initialize lineitem insertion proc';
					$lineitem_error = true;
				}

				if (SF_ENVIRONMENT == 'dev')
				{
// 					pch::dump($line, 'line');
					
				}

				$_masterno = $line['masterno'];

				$promo_id = isset($line['promo_id']) ? $line['promo_id'] : null;
				$promo_code = isset($line['promo_code']) ? $line['promo_code'] : null;
				mssql_bind($insert_lineitem, '@contractno', $contractno, SQLVARCHAR, false, false, 20);
				mssql_bind($insert_lineitem, '@masterno', $line['masterno'], SQLVARCHAR, false, false, 20);
				mssql_bind($insert_lineitem, '@qty', $line['qty'], SQLINT4);
				mssql_bind($insert_lineitem, '@price', $line['price'], SQLFLT8);
				mssql_bind($insert_lineitem, '@promo_id', $promo_id, SQLINT4);
				mssql_bind($insert_lineitem, '@promo_code', $promo_code, SQLVARCHAR, false, false, 20);
				mssql_bind($insert_lineitem, '@retail', $retail, SQLINT1);

				if ($user->is_pch() && ! $user->is_outlet())
					mssql_bind($insert_lineitem, '@userbed_id', $line['userbed_id'], SQLINT4);

				$insert = mssql_execute($insert_lineitem);
				$this->handle_proc_result($insert);

				mssql_free_statement($insert_lineitem);
				unset($insert_lineitem);
			}
			mssql_query('commit tran', $this->resq);
		}
		catch (Exception $e)
		{
			if (SF_ENVIRONMENT == 'dev')
			{

				pch::dump($e->getMessage(), 'e->message');
// 				pch::dump($insert, 'insert rs');
// 				pch::dump($orderitems, 'orderitems');
				pch::dump($contractno, 'contractno');
				pch::dump($insert_proc, 'insert_proc');
				pch::dump($_masterno, '_masterno');
				die();
			}
			pch::catcher($e, $insert_proc, 'Order Creation', array('message' => '@order-creation', 'route' => 'last'));
		}
	}

	/**
	 * approve resq order
	 * @param string $contractno
	 * @return string|bool - false on success, or a warning message string
	 */
	public function approve_order($contractno)
	{
		$warning = false;
		try
		{
			// tran handling occurs in-proc: also, no need to rollback if error here, order is saved.
			$approve_order = $this->init_proc('approve_order');
			mssql_bind($approve_order, '@contractno', $contractno, SQLVARCHAR, false, false, 20);
			$approve_exec = mssql_execute($approve_order);
			
			$rs = @mssql_fetch_assoc($approve_exec);

			$error_message = $this->get_rs_error($rs);
			if ($error_message !== false)
			{
				if (SF_ENVIRONMENT == 'prod')
				{
					message::log_error("Query: {$approve_proc}\n" . $error_message);

					// cover creditlimit exceeded case
					if ($error_message == 'Warning: Customer has exceeded credit limit.')
						$warning = '@credit-limit';
					else
						$warning = '@approve-order';
				}
				else
					$warning = "Error in approve_order: $error_message";
			}
		}
		catch (Exception $e)
		{
			pch::catcher($e, $approve_order, 'Order Approval', array('message' => '@approve-order'));
		}
		return $warning;
	}

	/**
	 * check resq for order using contractno
	 *
	 * @param contractno
	 * @return bool true for success, false for fail
	 * @throws resq exception
	 */
	public function is_order_valid($contractno)
	{
		try
		{
			if ( ! isset($contractno) )
				throw new ResqException('Invalid argument passed. Missing required parameter.');
		
			$resq_obj = new resqDb('pdo');

			$biz_table = resq::get_db();

			$table = "{$biz_table}.dbo.contractitem";
			$query = $resq_obj->resq->prepare
			(
				"select case when exists (
					select *
					from {$table}
					where contractno = :contractno
					AND statuscode != 'cancelled'
					) then 1 else 0 end;
				"
			);

			$query->bindParam(':contractno', $contractno);
			$query->execute();

			$count = $query->fetch(PDO::FETCH_NUM);
			return reset($count) ? true : false;

		}
		catch(Exception $e)
		{
			pch::catcher($e, $query, 'Order Approval', array('message' => '@approve-order'));
		}
	}

	/**
	 * insert a cc_tran entry into resq for the authorized card
	 * @param float $trans_id
	 * @param float $amount
	 * @param float $tax
	 * @param float $freight
	 * @param string $reason
	 * @param string $contractno
	 * @param string $customerno
	 * @param string $retail_id
	 * @param string $type - type of transaction (e.g., AUTH_ONLY)
	 * @param string $status - status to set the cc_tran entry to (e.g., pending)
	 * @param string $error - error string, if present
	 * @param string $avs - AVS message (e.g., address didnt match)
	 * @param string $ccv - CCV message (e.g., (M) Match)
	 * @param string $cavv - CAVV message
	 * @param bool $test - testmode?
	 * @param string $ccardno (optional)
	 * @param int $cc_tran_id (optional) - if passed, this row is updated, rather than inserting a new one. only passed from resq via captureQAction.
	 * @return void
	 */
	public function cc_auth
	(
		$trans_id, $amount, $tax, $freight, $reason
	,	$contractno, $customerno, $retail_id
	,	$type, $status, $error, $avs, $ccv, $cavv
	,	$test, $ccardno, $cc_tran_id = null
	)
	{
		try
		{
			$this->require_handle('mssql');
			$proc = $this->init_proc('cc_auth');

			$ip = pch::user_ip();

			mssql_bind($proc, '@trans_id', $trans_id, SQLFLT8);
			mssql_bind($proc, '@amount', $amount, SQLFLT8);
			mssql_bind($proc, '@tax', $tax, SQLFLT8);
			mssql_bind($proc, '@freight', $freight, SQLFLT8);
			mssql_bind($proc, '@reason', $reason, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@contractno', $contractno, SQLVARCHAR, false, false, 20);
			mssql_bind($proc, '@customerno', $customerno, SQLVARCHAR, false, false, 20);
			mssql_bind($proc, '@retail_id', $retail_id, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@ccardno', $ccardno, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@type', $type, SQLVARCHAR, false, false, 20);
			mssql_bind($proc, '@status', $status, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@error', $error, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@avs', $avs, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@ccv', $ccv, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@cavv', $cavv, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@test', $test, SQLINT1);
			mssql_bind($proc, '@ip', $ip, SQLVARCHAR, false, false, 50);
			mssql_bind($proc, '@cc_tran_id', $cc_tran_id, SQLINT4);

			$exec = mssql_execute($proc);
			$this->handle_proc_result($exec);
		}
		catch(Exception $e)
		{
			pch::catcher($e, $proc, 'Order Creation', array('message' => '@cc-error'));
		}
	}

	/**
	 * insert a cc_tran entry into resq for the credit attempt
	 * @param int $id - cc tran id
	 * @param float $trans_id
	 * @param string $status
	 * @param string $reason
	 * @param string $error
	 * @return void
	 */
	public function cc_credit($id, $trans_id, $amount, $approved, $errored, $reason, $error)
	{
		try
		{
			$this->require_handle('mssql');
			$proc = $this->init_proc('cc_credit');

			mssql_bind($proc, '@cc_tran_id', $id, SQLINT4);
			mssql_bind($proc, '@trans_id', $trans_id, SQLFLT8);
			mssql_bind($proc, '@amount', $amount, SQLFLT8);
			mssql_bind($proc, '@approved', $approved, SQLINT1);
			mssql_bind($proc, '@errored', $errored, SQLINT1);
			mssql_bind($proc, '@reason', $reason, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@error', $error, SQLVARCHAR, false, false, 200);

			$exec = mssql_execute($proc);
			$this->handle_proc_result($exec);
		}
		catch(Exception $e)
		{
			pch::catcher($e, $proc, 'CC Credit Response', array('message' => '@cc-error'));
		}
	}

	/**
	 * insert a cc_tran entry into resq for the void attempt
	 * @param int $id - cc tran id
	 * @param float $trans_id
	 * @param string $status
	 * @param string $reason
	 * @param string $error
	 * @return void
	 */
	public function cc_void($id, $trans_id, $status, $reason, $error)
	{
		try
		{
			$this->require_handle('mssql');
			$proc = $this->init_proc('cc_void');

			mssql_bind($proc, '@cc_tran_id', $id, SQLINT4);
			mssql_bind($proc, '@trans_id', $trans_id, SQLFLT8);
			mssql_bind($proc, '@status', $status, SQLVARCHAR, false, false, 10);
			mssql_bind($proc, '@reason', $reason, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@error', $error, SQLVARCHAR, false, false, 200);

			$exec = mssql_execute($proc);
			$this->handle_proc_result($exec);
		}
		catch(Exception $e)
		{
			pch::catcher($e, $proc, 'CC Void Response', array('message' => '@cc-error'));
		}
	}

	/**
	 * update the appropriate cc_tran table with the results from the capture attempt.
	 *
	 * @param int $id - The cc_tran row id.
	 * @param float $trans_id
	 * @param float $amount
	 * @param bool $approved
	 * @param bool $errored
	 * @param string $error
	 * @param bool $test
	 * @throws Exception (if proc error detected)
	 * @return void
	 */
	public function cc_response($id, $trans_id, $amount, $approved, $errored, $reason, $error, $test)
	{
		try
		{
			$this->require_handle('mssql');
			$proc = $this->init_proc('cc_response');

			mssql_bind($proc, '@id', $id, SQLINT4);
			mssql_bind($proc, '@trans_id', $trans_id, SQLFLT8);
			mssql_bind($proc, '@amount', $amount, SQLFLT8);
			mssql_bind($proc, '@approved', $approved, SQLINT1);
			mssql_bind($proc, '@errored', $errored, SQLINT1);
			mssql_bind($proc, '@reason', $reason, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@error', $error, SQLVARCHAR, false, false, 200);
			mssql_bind($proc, '@test', $test, SQLINT1);

			$exec = mssql_execute($proc);
			$rs = $this->handle_proc_result($exec); //will check for errors, and return assembled list of all rows
		}
		catch (Exception $e)
		{
			pch::catcher($e, $proc, 'Credit Capture', array('message' => '@pdo-error'));
		}

		return $rs;
	}

	/**
	 * get resultset of specified ccq entries for the current db.
	 * @param string $type - get capture|auth|credit|void entries (default: capture)
	 * @param bool $test - test project?
	 * @return array
	 */
	public function get_ccq($type = 'capture', $test = true)
	{
		// base proc is capture (legacy code, only capture originally existed)
		$sp = 'sandbox.dbo.get_ccq';
		if (strcasecmp($type, 'capture') !== 0)
			$sp .= "_{$type}";

		try
		{
			$this->require_handle('mssql');
			$proc = mssql_init($sp, $this->resq);
			$db = $this->get_db();

			mssql_bind($proc, '@db', $db, SQLVARCHAR, false, false, 50);
			mssql_bind($proc, '@test', $test, SQLINT1);

			$exec = mssql_execute($proc);
			$rs = $this->handle_proc_result($exec); //will check for errors, and return assembled list of all rows
		}
		catch (Exception $e)
		{
			pch::catcher($e, $proc, "CC $type Q");
		}

		return $rs;
	}
	
	/**
	 * return # orders in resq, for web comparison
	 * @param $customerno mixed (string|int - customerno for this customer or login_id for retail customer)
	 * @param $is_retail bool (is this a retail customer?)
	 * @return int (number orders in resq)
	 */
	public function get_num_orders($customerno, $is_retail)
	{
		$order_view = $this->get_object('order');
		
		$sql = "select count(*) from $order_view where ";
		
		$sql .= $is_retail
			? "customerno = 'RETAIL' AND login_id = ?"
			: "customerno = ?";
		
		try
		{
			$this->require_handle('pdo');
			$resq_sql = $this->resq->prepare($sql);
			$resq_sql->bindParam(1, $customerno);
			$resq_sql->execute();
			
			$rs = $resq_sql->fetch(PDO::FETCH_NUM);
			$resq_order_count = (int) $rs[0];
			
			return $resq_order_count;
		}
		catch (PDOException $e) 
		{
			pch::catcher($e, $resq_sql, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
	}
	

	/**
	 * get a customer's orders from resq
	 * @param $customerno mixed (string|int - customerno for this customer or login_id for retail customer)
	 * @param $is_retail bool (is this a retail customer?)
	 * @param $contractno mixed (false|string - contactno for specific order, if this isn't false return just this order)
	 * @param $is_search
	 * @return array (array of orders, or single order)
	 */
	public function get_orders($customerno, $is_retail, $contractno = false, $is_search = false)
	{
		$order_view = $this->get_object('order');
		$sql = "select * from $order_view where ";
		$where = array();

		// if customerno is false, we retrieve the order anyway, since order perms check comes later.
		if ($customerno !== false)
		{
			if ($is_retail)
			{
				$where[] = "customerno = 'RETAIL'";
				$where[] = "login_id = :cust";
			}
			else
				$where[] = "customerno = :cust";
		}
			
		// if a search, match contractno with a like instead of =
		if ($contractno !== false)
			$where[] = $is_search 
				? "contractno like :contractno" 
				: "contractno = :contractno";

		$where[] = "statuscode != 'cancelled'";
		$sql .= implode(' AND ', $where);
		$sql .= " order by startdate desc";
		
		try
		{
			$this->require_handle('pdo');
			$resq_sql = $this->resq->prepare($sql);
			if ($customerno !== false)
				$resq_sql->bindParam('cust', $customerno);
			
			if ($contractno !== false)
			{
				if ($is_search)
					$contractno = "%{$contractno}%";
					
				$resq_sql->bindParam('contractno', $contractno);
			}
				
			$resq_sql->execute();
			
			$rs = ($contractno === false || $is_search)
				? $resq_sql->fetchAll(PDO::FETCH_ASSOC) 
				: $resq_sql->fetch(PDO::FETCH_ASSOC);
			
			return $rs;
		}
		catch (PDOException $e)
		{
			pch::catcher($e, $resq_sql, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
	}
	
	/**
	 * get a customer's order from resq
	 * @param $customerno mixed (string|int - customerno for this customer or login_id for retail customer)
	 * @param $contractno string ( contactno for specific order)
	 * @param $is_retail bool (is a retail customer?)
	 * @return array (order info)
	 */
	public function get_order($customerno, $contractno, $is_retail)
	{
		$rs = $this->get_orders($customerno, $is_retail, $contractno);
		
		// do we have comments on a single order? if so, hydrate into array.
		if (isset($rs['comment']))
			$rs['comment'] = array_filter(explode("\n", $rs['comment']));
		
		return $rs;
	}


	/**
	 * determine if the passed ident (customerno or retail id) matches the contractno.
	 * This indicates whether or not the customer has permission to view the order (it's from an externally encrypted key).
	 * @param string $contractno
	 * @param string $ident
	 * @return bool
	 */
	public function order_perm($contractno, $ident)
	{	
		$order_view = $this->get_object('order');
		$sql = "select count(*) as num from $order_view where contractno = ? and ((customerno != 'RETAIL' and customerno = ?) or retail_id = ?)";
		
		try
		{
			if ( ! ctype_alnum($contractno) ||  ! ctype_alnum($ident))
			{
				throw new PDOException('Your order was not detected in our system, sorry!');
			}
		
			$this->require_handle('pdo');
			$resq_sql = $this->resq->prepare($sql);
			$resq_sql->bindParam(1, $contractno);
			$resq_sql->bindParam(2, $ident);
			$resq_sql->bindParam(3, $ident);
	
			$resq_sql->execute();
			$rs = $resq_sql->fetch(PDO::FETCH_NUM);

			return (bool) $rs[0];
		}
		catch (PDOException $e)
		{
			pch::catcher($e, $resq_sql, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
	}


	/**
	 * Grab orderitems from the sandbox orderitem view in resq.
	 * @param string $contractno
	 * @return array
	 */
	public function get_orderitems($contractno)
	{
		$orderitem_view = $this->get_object('orderitem');
		
		try
		{
			$this->require_handle('pdo');
			$item_sql = $this->resq->prepare
			(
				"select *
				from $orderitem_view
				where contractno = :contractno
				order by line_no"
			);
			$item_sql->bindParam(':contractno', $contractno);
			$item_sql->execute();
			
			$rs = $item_sql->fetchAll(PDO::FETCH_ASSOC);
			return $rs;
		}
		catch (PDOException $e)
		{
			pch::catcher($e, $item_sql, 'Internal Ordering System');
		}
	}
	
	public function get_dropship($contractno)
	{
		$dropship_view = $this->get_object('dropship');
		
		try
		{
			$this->require_handle('pdo');
			$dropship_sql = $this->resq->prepare
			(
				"select *
				from $dropship_view
				where contractno = :contractno"
			);
			$dropship_sql->bindParam(':contractno', $contractno);
			$dropship_sql->execute();
			
			return $dropship_sql->fetch(PDO::FETCH_ASSOC);
		}
		catch (PDOException $e)
		{
			pch::catcher($e, $dropship_sql, 'Internal Ordering System', array('message' => '@pdo-error'));
		}
	}

	/**
	 * check how many times a customer has ordered a particular item
	 * hardcoded logic for kit customer_limits, eventually promo will support this...
	 * @param string $customerno
	 * @param string $masterno
	 * @return int (the number of times they've ordered the product
	 */
	 public function customer_sku_count($customerno, $masterno)
	 {
		 $orderitems = $this->get_object('orderitem');
		 $orders = $this->get_object('order');

		 try
		 {
			 $this->require_handle('pdo');
			 $sql_string = "select sum(qty) as qty
				from $orderitems oi
				join $orders o on (o.contractno = oi.contractno)
				where o.customerno = :customerno and oi.masterno = :masterno";

			 $sql = $this->resq->prepare($sql_string);
			 $sql->bindParam(':customerno', $customerno);
			 $sql->bindParam(':masterno', $masterno);
			 $sql->execute();
			 $rs = $sql->fetch(PDO::FETCH_NUM);
			 
			 $sku_count = (int) $rs[0];

			return $sku_count;
		 }
		 catch (PDOException $e)
		 {
			 pch::catcher($e, $sql, 'Internal Ordering System');
		 }
	 }

}

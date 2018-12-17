<?php

class pch
{
	
	/**
	 * are our servers under duress? return the flags set if so
	 * @param string $flag (optional) - if not passed, return if any of our duress constants are set
	 * @return param boolean - under duress?
	 */
	public static function under_load($flag = null)
	{
		if ($flag !== null)
			$flags = array('PCH\LOAD_' . strtoupper($flag));
		else
			$flags = array('PCH\LOAD_BANDWIDTH', 'PCH\LOAD_SERVER');
	
		$duress = false;
		// as soon as we find a duress flag, return true
		foreach ($flags as $constant)
		{
			if (defined($constant) && constant($constant) === true)
			{
				$duress = true;
				break;
			}
		}
			
		return $duress;
		
	}
	
	/*
	 * "how you gonna output this size, julian? prettily?"
	 * Add to examples if you modify. (really should be a unit test...)
	 * @example Test array: array('42" x 108"', '14"x20"', '10x14', 'LARGE/X LARGE', 'EXTRA SMALL', 'DOUBLE BOUDOIR', 'FULL/QUEEN');
	 * @example expected (long): array('42" x 108"', '14" x 20"', "10' x 14'", 'Large/X Large', 'Extra Small', 'Double Boudoir', 'Full/Queen');
	 * @example expected (short): array('42x108', '14x20', "10'x14'", 'Large/X Large', 'Extra Small', 'Dbl Boudoir', 'Full/Queen');
	 */
	public static function prettify_size($_size, $short = false)
	{
		$size = $_size; // remember old version for comparison
		$size = ucwords(strtolower($size));
		
		$patterns = $replacements = array();

		// replace raw numbers with feet. always formatted in the size as, e.g., 6x9
		$patterns[] = '(\d)x(\d+)'; // first number
		$replacements[] = "$1'x$2'";
		
		// any standalone X's? these should be lowercase.
		$patterns[] = ' X ';
		$replacements[] = ' x ';

		if ($short !== false)
		{
			$patterns[] = 'double';
			$replacements[] = 'Dbl';
			
			// remove double quotes if we have an X between them (and its spaces if found)
			$patterns[] = '"? x "?';
			$replacements[] = 'x';
		}
		else // non-short replacements
		{
			// if an X is found that's not part of a word, it's a separator, space it out.
			$patterns[] = '([^A-Z /])x([^A-Z /])';
			$replacements[] = "$1 x $2";
		}
			
		// capitalize after any of these characters
		$separators = array('/', '\\', '-');
		foreach ($separators as $divider)
		{
			// if present, turn into array, ucfirst, turn back into string
			if (strpos($size, $divider) !== false)
			{
				$size_pieces = array_map('ucfirst', explode($divider, $size));
				$size = implode($divider, $size_pieces);
			}
		}
		
		// patternify - case-insensitive due to ucwords
		$patterns = array_map(function($val) {return '#' . $val . '#i';}, $patterns);
		$size = preg_replace($patterns, $replacements, $size);
		
		return $size;
	}

	public static function generate_pass()
	{
		return substr(md5(rand()), 0, 6);
	}

	/**
	 * get user's IP address
	 * if a secure connection, grab ip address from session, otherwise it will return load balancer IP
	 *
	 * @return string (IP address)
	 */
	public static function user_ip()
	{
		// if going through load balancer, grab forwarded ip
		$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
			? $_SERVER['HTTP_X_FORWARDED_FOR']
			: $_SERVER['REMOTE_ADDR'];

		$context = sfContext::getInstance();
		$user = $context->getUser();
		$is_secure = $context->getRequest()->isSecure();

		if ($is_secure)
			$ip = $user->getAttribute('ip_address', $ip);
		else $user->setAttribute('ip_address', $ip);
		
		return $ip;
	}

	/**
	 * determine if a user is internal or external
	 */
	public static function user_ip_internal()
	{
		$ip = self::user_ip();
		
		return substr($ip, 0, 7) == '192.168';
	}

	/**
	 * determine if a user is from our IT dept
	 */
	public static function user_ip_it()
	{
		$ip = self::user_ip();

		if (strpos($ip, '192.168.0.') === false)
			return false;

		// ip range for IT is .0.2 through .0.29
		$ip_seg = (int) str_replace('192.168.0.', '', $ip);
		$is_it = $ip_seg >= 2 && $ip_seg <= 29;

		return $is_it;
	}

	public static function prettify_text($text)
	{
		//capitalize first letter after / character--left as match, assuming that more chars will be used
		//	return preg_replace('#(\/)(\w)#e', "'$1' . strtoupper('$2')", ucwords(strtolower(htmlspecialchars_decode($text))));

		$text = pchString::knit_format_text($text);

		//more performant (and better practice probably)
		return preg_replace_callback
		(
			'#(\/)(\w)#'
			,	create_function('$matches', 'return $matches[1] . strtoupper($matches[2]);')
			,	ucwords(strtolower(htmlspecialchars_decode($text)))
		);
	}

	/**
	 * properly pluralize a word, instead of just adding 's' to the end
	 *
	 * @param string $text
	 * @return string
	 */
	public static function prettify_plural($text, $lowercase = false)
	{
		$formatted_text = strtolower($text);

		$odd_products = sfConfig::get('app_oddball-products_product');

		if (isset($odd_products[$formatted_text]))
			$formatted_text = $odd_products[$formatted_text];

		if (substr($formatted_text, -1) !== 's')
			$formatted_text .= 's';
		else if (substr($formatted_text, -2) == 'ss')
			$formatted_text .= 'es';

		if ($lowercase === false)
			$formatted_text = ucwords($formatted_text);

		return $formatted_text;
	}

	/**
	 * display a shortened version of product name for brevity (i.e. button text)
	 *
	 * @param string $full_product (full product name)
	 * @return string $product(shortened product name)
	 */
	public static function product_abbr($full_product, $plural = false)
	{
		$full_product = strtolower($full_product);

		$abbrs = sfConfig::get('search_abbrev_product');
		$product = (isset($abbrs[$full_product])) ? $abbrs[$full_product] : $full_product;

		if ($plural !== false)
			$product = pch::prettify_plural($product, true);

		return $product;
	}
	
	/**
	 * convert string before saving to mysql varchar, gettting received with bogus characters
	 * 
	 * mysql is converting é and others to invalid chars, and displays scrambled chars
	 */
	public static function db_escape($_string)
	{
		// we know these chars tend to break iconv
		// here are actual characters with their html entity equivalents
		$problem_chars = array
			(
				'rsquo' => '’'
			,	'mdash' => '—'
			,	'ndash' => '—'
			,	'trade' => '™'
			,	'#8220' => '“'
			,	'#8221' => '”'
			);
		
		// create some arrays for str_replace, quicker and simpler than a preg_replace or something
		$bad_chars = $placeholder_chars = $actual_chars = array();
		foreach($problem_chars as $html => $bad)
		{
			$bad_chars[] = $bad;
			$placeholder_chars[] = "_{$html}_";
			$actual_chars[] = "&{$html};";
		}
		
		
		// first escape problem characters so we don't lose all text after that character
		$string = str_replace($bad_chars, $placeholder_chars, $_string);
		
		// then run iconv and get html entities where we can
		$string = iconv("UTF-8", "ISO-8859-1", $string);
		$string = htmlentities($string);
		
		// then change the problem chars to their proper html entities
		$string = str_replace($placeholder_chars, $actual_chars, $string);
		
		return $string;
	}

	/**
	 * generate image src string for thumbshots.com thumbnail service
	 *
	 * @param string $url (website to get thumbnail of)
	 * @param integer $width (width in pixels)
	 * @param integer $height (height in pixels)
	 * @return string (image src) 
	 */
	public static function thumbshots_image_url($url, $width = false, $height = false)
	{
		// default to the smallest size for now?
		if ($width === false || $height === false)
		{
			$width = 60;
			$height = 45;
		}

		// format url string
		$url = urlencode($url);

		$url_format = "http://simple.thumbshots.com/image.aspx?cid=1457&v=1&w=%d&h=%d&url=%s";
		$image_url = sprintf($url_format, $width, $height, $url);

		return $image_url;
	}
	
	/**
	 * display a prettified name for subcats
	 * 
	 * @param string $subcat
	 * @param string $val (if string, it's the value, if null, it's the header)
	 * @return string
	 */
	public static function display_subcat($subcat, $val = null)
	{
		$vanity = sfConfig::get('vanity_site_Vanity');

		if ($subcat == 'size')
		{
			return $val === null  // if val is null, it's a header
				? ucwords($subcat) 
				: pch::prettify_size($val);
		}
		else
		{
			if ($val != null)
			{
				if ( ! empty($vanity['rug'][$subcat][$val]) ) $val = $vanity['rug'][$subcat][$val];
			}
			else
			{
				if ( ! empty($vanity['rug'][$subcat]['display']) ) $val = $vanity['rug'][$subcat]['display'];
			}
			
			if ($subcat == 'product' && $val == null) $val = 'Product';

			return empty($val) ? ucwords($subcat) : $val ;
		}
	}

	/**
	 * date
	 *
	 * formats any string date value as mm/dd/yyyy
	 *
	 * @param string $date_string
	 *
	 * @return string
	 */
	public static function date($date_string, $_format = null)
	{
		$default_format = 'n/j/o';
		
		$format = empty($_format) ? $default_format : $_format;
		
		return is_null($date_string) ? null : date($format, strtotime($date_string));
	}

	public static function root_url()
	{
		$url_new = url_for('@homepage', true);
		$url_new = preg_replace('/(http.+\/\/.+\/).+/', '$1', $url_new);

		return $url_new;
	}

	/**
	 * original version from the Zend Framework
	 * modified to handle xdebug output, and common doctrine objects
	 * 
	 * @see http://framework.zend.com/developer/browser/trunk/library/Zend.php
	 * @param mixed $var - the variable to output
	 * @param string $label (optional, default blank) - label to print for said variable
	 * @param bool $echo (optional, default true) - if false, return output as a variable.
	 *
	 */
	public static function dump($var, $label = null, $echo = true, $method = null, $file = null)
	{
		$has_reflection = ! pchArray::all_empty($method, $file);

		// format the label
		if ($has_reflection)
		{
			$label_out = '[in ';

			// does the method have class embedded?
			$has_class = (strpos($method, '::') !== false);
			
			if ( ! $has_class)
			{
				// no class, can we use file, e.g., for helper classes?
				if ( ! empty($file))
					$label_out .= basename($file, '.php') . '::';
			}

			if ( ! empty($method))
				$label_out .= $method . '()';

				$label_out .= '] ';
		}
		else $label_out = '';

		$label_out .= ($label === null) ? '' : '$' . rtrim($label) . ' ';

		// Save old value of html_errors
		$oldIni = ini_get('html_errors');

		// Set value to no html if needed
		if ($oldIni != 0)
			ini_set('html_errors', 0);

		// var_dump the variable into a buffer and keep the output
		ob_start();

		// handle common object output
		if (is_object($var))
		{	
			// can we get additional debug info for this class?
			$this_method = false;
			$debug_methods = array
			(
				'Doctrine_Query' => 'getQuery'
			,	'Doctrine_Record' => 'toArray'
			,	'Doctrine_Pager' => 'getQuery'
			,	'Doctrine_Collection' => 'toArray'
			,	'Doctrine_Table' => 'getOptions'
			,	'sfOutputEscaper' => 'getRawValue'
			,	'simpleXMLElement' => 'asXML'
			,	'soapFault' => '__toString'
			,	'soapClient' => '__toString'
			,	'stdClass' => 'toArray'
			);

			foreach ($debug_methods as $object => $method)
				if ($var instanceof $object)
				{
					$this_method = $method;
					$this_object = $object;
				}

			// use the classname, if we don't have this object specifically handled above.
			if ( ! isset($this_object))
				$this_object = get_class($var);

			echo '(instance of "' . $this_object . '"):' . "\n";

			// if it's a doctrine class, give connection info
			if (strpos($this_object, 'Doctrine_') === 0)
			{
				$manager = Doctrine_Manager::getInstance();
				echo '[Doctrine Object, current connection: "', $manager->getConnectionName($manager->getCurrentConnection()), '"]', "\n";
			}

			if ($this_method !== false)
			{
				try
				{
					$message = $var->$this_method();
				}
				catch (Exception $dupe)
				{
					$message = "[ debug error in $this_object" . "->$method() ]";
				}

				if (is_array($message)) // if array output of debug method, return string
					$message = print_r($message, true);

				echo $message;
			}
			else
			{
				echo __METHOD__, " doesn't know how to output an object of class $this_object.";
			}
		}
		else
			var_dump($var);
		
		$output = ob_get_clean();

		// Restore html_errors value
		if ($oldIni != 0)
			ini_set('html_errors', $oldIni);

		// neaten the newlines and indents
		$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
		if (PHP_SAPI == 'cli')
			$output = PHP_EOL . $label_out . PHP_EOL . $output . PHP_EOL;
		else
			$output = '<pre>' . $label_out . htmlentities($output, ENT_QUOTES) . '</pre>';

		if ($echo)
			echo($output);

		return $output;
	}
	
	/**
	 * does the store have an image to display?
	 * if not, use default
	 * @return string - the file path of the image to use
	 */
	public static function get_store_image($customerno = false, $zip = false)
	{
		// default image name
		$default_image = 'default_store';
		$ds = DIRECTORY_SEPARATOR;
		$image_string = 'stores' . $ds . '%s.jpg';

		if ($customerno === false || $zip === false)
			$image = $default_image;
		else
		{
			$zip_lower = strtolower(str_replace(' ', '', $zip));
			// check for customerno_zip.jpg, use it if it exists
			$image = strtolower($customerno) . '_' . $zip_lower;
			$filecheck = sprintf(sfConfig::get('sf_web_dir') . $ds . 'images' . $ds . $image_string, $image);

			if ( ! file_exists($filecheck))
				$image = $default_image;
		}

		return sprintf($image_string, $image);
	}

	/**
	 * delete a directory, and all the files/directories in it recursively
	 *
	 * @param string $dir (directory to delete)
	 * @return boolean (pass/fail)
	 */
	public static function rmdirr($dir)
	{
		// don't let anyone try to delete root...
		if ($dir == '/')
			return false;

		$files = glob($dir . '*', GLOB_MARK );
		foreach($files as $file)
		{
			if( substr($file, -1) == '/' )
				$success = self::rmdirr($file);
			else $success = unlink($file);

			if ($success === false)
				return false;
		}

		if (is_dir($dir))
			$success = rmdir($dir);

		return $success;
	}
	/**
	 * test a url to verify that it is well-formed
	 *
	 * @param string $url (string you would like to check)
	 * @return boolean (true/false)
	 */
	 public static function wf_url($url)
	 {
		 $pattern = "@^\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))$@";
		 $win = preg_match($pattern, $url);

		 return $win;
	 }

	 /**
	  * is this an external beta site? if so we send emails, disable ssl, amongst other things
	  * @return bool
	  */
	 public static function is_beta()
	 {
		 return (defined('BETA_SITE') && BETA_SITE);
	 }

	 /**
	  * if SSL enabled, set url to https and return
	  * used for login etc links, where https is required even if action doesn't require it (and current page isnt ssl)
	  * @param string $route - symfony route (e.g., @login_page)
	  * @see self::ssl_disabled
	  * @return string
	  */
	 public static function secure_url_for($route)
	 {
		 sfLoader::loadHelpers(array('Url'));
		 $url = url_for($route, true);

		 if ( ! self::ssl_disabled())
			 $url = str_replace('http:', 'https:', $url);

		 return $url;
	 }

	 /**
	  * is ssl enabled on this copy currently?
	  * @see is_beta()
	  * @return bool
	  */
	public static function ssl_disabled()
	{
		$config_disabled = sfConfig::get('app_disable_sslfilter', false);
		$beta_site = self::is_beta();

		$disable_safari = pch::disable_safari();

		return ($config_disabled || $beta_site || $disable_safari);
	}

	public static function disable_safari()
	{
		if ( ! isset($_SERVER['HTTP_USER_AGENT']))
			return false;
		
		// TESTMOD: having ssl issues with safari, just disable ssl for now if useragent == safari until we resolve the issue
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$is_safari = strpos($useragent, 'Safari/') !== false;
		if ($is_safari)
		{
			$safari_version = (int) substr($useragent, strpos($useragent, 'Safari/')+7, 4);
			$disable_safari = sfConfig::get('app_disable-ssl-safari') && $safari_version < 529;
			if ($disable_safari)
				return true;
		}

		return false;
	}

	public static function show_upgrade_dialog()
	{
		// TESTMOD: get this working
		return true;

		$useragent = $_SERVER['HTTP_USER_AGENT'];

		$disable_safari = pch::disable_safari();
		$disable_ie = stripos($useragent, 'msie') !== false;

		if ($disable_safari || $disable_ie)
			return true;

		return false;
	}

	public static function has_http_proto_url($url)
	{
		 //first find if the submitted link is a proper url
		$win = pch::wf_url($url);

		//does it have a protocol specified?
		$has_proto = false;
		if( strtolower(substr($url, 0, 7)) === 'http://' || $win === true )
			$has_proto = true;

		return $has_proto;
	 }
	 
	/**
	 * grab the proper domain based on biz
	 * @param string $biz - use outlet for biz if outlet.
	 * @return string
	 */
	public static function biz_url($biz)
	{
		switch ($biz)
		{
			case 'pch':
				$domain = 'pineconehill.com';
			break;
		
			case 'dash':
				$domain = 'dashandalbert.com';
			break;
		
			case 'outlet':
				$domain = 'pineconehilloutlet.com';
			break;
		
			case 'fresh':
				$domain = 'freshamerican.com/store';
			break;
		
			default:
				$domain = false;
		}
		
		if ($domain !== false)
			$domain = 'www.' . $domain;
		
		return $domain;
	}

	public static function catch_error($e, $prod_message = false)
	{

		$dev_message = $e->getMessage();
		// save dev error in flash to be retrieved by error page email form
		$action = sfContext::getInstance()->getActionStack()->getLastEntry()->getActionInstance();
		$action->setFlash('dev_error', $dev_message);

		if (SF_ENVIRONMENT == 'prod')
		{
			$message = $prod_message !== false ? $prod_message : '@query-error';

			message::log_error($dev_message);
			message::error('Error', $message, 'error');
		}
		else message::error('Error', $dev_message, 'error');
	}

	/**
	 * catch doctrine_exceptions
	 * @param Exception $e - the exception being caught
	 * @param string|object $var - Doctrine variable to check for additional debug output (pass null/false to omit). Can also be a string message.
	 * @param string $error_title - header to show on error page
	 * @param array $options
	 *			string	message (vanity error message, defaults to @query-error)
	 *			string	route (route to redirect to, defaults to 'error')
	 *			boolean redirect (redirect with error message? default to true)
	 *			boolean log_only (only write a log, don't redirect, default to false)
	 *			boolean	send_email (send an email summary with dev error message, defaults to false)
	 *			string	mail_to (address to send email to, defaults to wd@)
	 *			string	details (extra info to include in dev message, defaults to empty)
	 *			boolean	backtrace (include backtrace in dev error message)
	 *			boolean	full_backtrace (if true, include symfony filters)
	 *			string	error_title (will override error_title param, for class specific defaults)
	 *
	 * @return void - (forwards to error page, or just returns if no redirect)
	 */
	public static function catcher($e, $var, $error_title, $_options = array())
	{
		$def_options = array(
			'message' => '@query-error'
		,	'route' => 'error'
		,	'redirect' => true
		,	'log_only' => false
		,	'send_email' => false
		,	'mail_to' => 'wd@pineconehill.com'
		,	'details' => false // so we can track extra info
		,	'backtrace' => true
		,	'full_backtrace' => false // if false, we remove all the filters from the backtrace (most of the info)
		,	'error_title' => false
		);

		// get exception class
		// even if we catch generic Exception, we can get the specific class that was thrown
		$exception_class = is_object($e) ? get_class($e) : false;

		// are there custom defaults for this exception class?
		// e.g. DupeOrderException just logs the error and goes on, set options.log_only => 1
		$all_exception_options = sfConfig::get('app_error_exception-options');
		$exception_options = ( ! empty($exception_class) && isset($all_exception_options[$exception_class]))
			? $all_exception_options[$exception_class]
			: false;
		
		if ( ! empty($exception_options))
			$def_options = array_merge($def_options, $exception_options);

		// set up options array
		$options = array_merge($def_options, $_options);

		// can we get additional debug info for this class?
		$this_method = false;
		if (is_object($var))
		{
			$debug_methods = array
				(
					'Doctrine_Query' => 'getQuery'
				,	'Doctrine_Record' => 'toArray'
				,	'Doctrine_Pager' => 'getQuery'
				,	'Doctrine_Collection' => 'toArray'
				);

			foreach ($debug_methods as $object => $method)
				if ($var instanceof $object)
					$this_method = $method;

			if ($this_method !== false)
			{
				try {$message = $var->$this_method();}
				catch (Doctrine_Exception $dupe) {$message = "[ debug error in $object" . "->$method() ]";}

				if (is_array($message)) // if array output of debug method, return string
					$message = '<pre>' . print_r($message, true) . '</pre>';
			}
			else $message = '';
		}
		// if no var was passed, omit this message
		else if (is_string($var))
			$message = "Debug Message: <pre>$var</pre>";
		else $message = ! empty($var) ? "[ '$var' either nonexistent object at exception time, or meant as a debug message ]<br />\n" : "<br />\n";


		// tack on current page to dev message
		$message .= '<pre>-- from: ' . sfRequestHistory::getCurrentUri() . "\n</pre><br />\n";


		// add exception message, get specific exception class
		if (is_object($e))
		{
			$dev_message = $e->getMessage();
			$message .= '<pre>-- DEV ERROR - ' . $exception_class . ' - "' . $dev_message . "\"\n</pre><br />\n";
		}


		// any extra info we're including?
		if ( ! empty($options['details']))
			$message .= $options['details'];


		// grab backtrace and attach pertinent info to dev message
		// this way we don't have to include __METHOD__ in catcher() call
		// do we want to include the entire stack? or just the most recent few? what about omitting all the sf filters?
		if ($options['backtrace'])
		{
			$backtrace_message = pch::get_backtrace($options['full_backtrace']);
			$message .= $backtrace_message;
		}


		// check for existing dev error in flash, if exists, tack onto end of current
		$action = sfContext::getInstance()->getActionStack()->getLastEntry()->getActionInstance();
		$existing_dev_error = $action->getFlash('dev_error', '');
		if ( ! empty($existing_dev_error))
			$message = $existing_dev_error . "\n\n" . $dev_message;

		if($options['error_title'] !== false)
			$error_title = $options['error_title'];

		// log the dev error
		message::log_error($error_title . ': ' . $message);

		// are we sending an email?
		$send_email = ! $options['log_only'] && $options['send_email'];
		if ($send_email)
		{
			$mail_to = $options['mail_to'];
			$subject = $error_title;
			$email_message = str_replace(array('<br />', '<pre>', '</pre>'), array("\n", '', ''), $message);
			mail($mail_to, $subject, $email_message, 'From: dash_error@pineconehill.com');
		}
		
		// if prod env, only send out vanity error message
		$output_message = (SF_ENVIRONMENT == 'prod')
			? $options['message']
			: $message;


		// are we redirecting with an error message?
		$redirect = ! $options['log_only'] && $options['redirect'];
		if ($redirect === true)
		{
			// save this dev message in flash so we can use it elsewhere (error page email form, another catcher() call, etc)
			$action->setFlash('dev_error', $dev_message);
			message::error($error_title, $output_message, $options['route']);
		}
		else
			echo $output_message;

		return;
	}

	/**
	 * get the stack trace
	 * @param boolean $include_filters (include symfony filters in backtrace list)
	 * @return string (backtrace ready for output)
	 */
	private static function get_backtrace($include_filters = false)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		array_shift($backtrace); // remove the first entry ( calling pch::get_backtrace() );

		if ($include_filters === false)
		{
			foreach($backtrace as $i => $event)
			{
				$is_filter = stripos($event['file'], 'php/symfony/') !== false || stripos($event['class'], 'filterchain') !== false;

				// if a filter entry and not the first entry (this happened in a filter), remove it
				if ($is_filter && $i > 0)
					unset($backtrace[$i]);
			}
		}

		$trace = '';
		foreach($backtrace as $idx => $event)
		{
			$file = $event['file'] . ":" . $event['line'];
			$call = $event['class'] . $event['type'] . $event['function'] . '()';

			$trace .= '- ' . $file . "<br />\n\t" . $call . "<br />\n";
		}

		$ret = "Backtrace:<br />\n" . $trace;

		return $ret;
	}

}

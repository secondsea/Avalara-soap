<?php

class pchString
{
	/**
	 * returns teaser text of specified length
	 * 
	 * @param string $string - text to truncate if necessary
	 * @param int $length - max length of return string
	 * @param bool $include_ellipsis (include ... at end of shortened string)
	 * 
	 * @return string (the teaser text)
	 */
	public static function teaser($string, $len, $include_ellipsis = true)
	{
		if (strlen($string) <= $len)
			return $string;
		else 
		{
			$len = $include_ellipsis ? $len - 3 : $len;
			$suffix = $include_ellipsis ? '...' : '';

			// wrap the ellipsis in a span with the whole string in the title attr
			if ($include_ellipsis)
				$suffix = '<span title="' . $string . '">' . $suffix . '</span>';
			
			return substr($string, 0, $len) . $suffix;
		};
	}
	
	/**
	 * teaser_end
	 * 
	 * returns teaser text of specified length, removing text from middle of string to preserve end
	 * 
	 * @param string $string - text to truncate if necessary
	 * @param int $length - max length of return string
	 * @param int $end_length - # characters to preserve on end of string, defaults to half of maxlength
	 * @param string $glue - string to place between start and end strings
	 * 
	 * @return string (the teaser text)
	 */
	public static function teaser_end($string, $maxlen, $end_length = false, $glue = '-', $show_title = true)
	{
		if (strlen($string) < $maxlen)
			return $string;
			
		// preserve last half of string if not specified
		if ($end_length === false)
			$end_length = round($maxlen/2);
			
		$string_end = substr($string, $end_length*(-1), $end_length);
		$string_start = substr($string, 0, $maxlen - $end_length - strlen($glue));
		
		if ($show_title)
			$glue = '<span title="' . $string . '">' . $glue . '</span>';
		
		return $string_start . $glue . $string_end;
	}

	/**
	 * get the internal URI, without the controller
	 * useful for 404 comparison to look for outdated routes, for example
	 * @return string
	 */
	public static function getUri()
	{
		// get the url, minus the controller if present. also remove the leading slash.
		$controller = $_SERVER['SCRIPT_NAME'];
		return substr(str_replace($controller, '', $_SERVER['REQUEST_URI']), 1);
	}

	/**
	 * Convert into a sluggable url
	 * @param string $string
	 * @param int $maxlen (optional, default 100) - how long can the slug be?
	 * @return string
	 */
	public static function slugify($string, $maxlen = 100)
	{
		if (empty($string))
			return null;
		
		// Remove all non-word characters with spaces
		$string = trim(preg_replace('/[^\w-\+]/', ' ', $string));

		// get slug column length, cut down slug to that, or cut out a section in the middle to preserve trailing base
		if (strlen($string) > $maxlen)
			$string = self::teaser_end($string, $maxlen, 10);

		// replace spaces with dashes
		return strtolower(preg_replace('/\s+/', '-', $string));
	}

	/**
	 * convert a slug back into a readable string
	 * @param string $slug
	 * @param bool $capitalize (default true) - ucwords?
	 * @return string
	 */
	public static function unslugify($slug, $capitalize = true)
	{
		// first, is this a slug that has an id appended to the end? if so, strip it off.
		$stripped_slug = substr($slug, 0, strpos($slug, '--'));
		
		$string = strtolower(trim(str_replace('-', ' ', $stripped_slug)));

		if ($capitalize == true)
			$string = ucwords($string);

		return $string;
	}

	
	public static function detect_utf8($string)
	{
		//if (is_numeric($string))
			//return false;
		
		
		// FIXME: this check isnt working, need a better regex.
		// has nonstandard characters, check for UTF8
		/*
		$match = (bool) preg_match
		(
			'%(?:
        	[\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
        	|\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
        	|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
        	|\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
        	|\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
        	|[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
        	|\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
        	)+%xs'
		, $string
		);
		*/
		$match = true;
		
		return $match;
	}
	
	public static function collate($string)
	{
		$utf8 = self::detect_utf8($string);

		if ($utf8 === true)
			$string = iconv('cp1250', 'utf-8', $string);
			
		return $string;
	}
	
	public static function valid_email($email)
	{
    	return preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $email);
	}
	
	/**
	 * format bed/item counts
	 * @param array $counts - array('items' => x, 'beds' => y)
	 * @return string
	 */
	public static function item_counts($bag_count, $biz = false)
	{
		if ($bag_count instanceof sfOutputEscaper)
			$bag_count = $bag_count->getRawValue();

		$bag_empty = (array_sum((array) $bag_count) == 0);

		if ( ! $bag_empty)
		{
			$itemcounts = array();
			foreach ($bag_count as $type => $count)
			{
				if ($count !== 0)
				{
					$count_display = ($type == 'items') ? 'item' : 'bed';
					if ($count > 1) $count_display .= 's';

					if ($biz == 'pch')
						$itemcounts[] = $count;
					else
						$itemcounts[] = "$count $count_display";
				}

			}
			$text = implode(', ', $itemcounts);
		}
		else
			$text = 'empty';
		
		return $text;
	}

	/**
	 *	Generates a random 6 digit alphanumeric string
	 *
	 *  TODO add options to add variation such as returned string length, alpha/num bias etc.
	 *
	 * @return string - random alphanum
	 */
	public static function generate_random_alphanum()
	{
		$count = 6;
		$string = '';
		for ($i = 0; $i < $count; $i++)
		{
		//	 -- string or num?
			 $char = rand(1, 3) % 2;
		//	 -- append string or number
			 $string .= $char ? chr(rand(65, 90)) : chr(rand(48, 57));
		}
		return $string;

	}

	/**
	 * Cleanup text for Willow Knit or Fit Knit trademark characters for headers
	 * TODO: add a flag to return escaped chard version or not
	 * @param string $input_string Header to be formatted
	 * @return string
	 */
	public static function knit_format_text($input_string)
	{
		$string = htmlspecialchars($input_string);

		if (stripos($string, 'Willow Knit') !== false)
			$format_string = str_replace('Willow Knit', 'Willow Knit&#0153;', $string);
		elseif (stripos($string, 'Fit Knit') !== false)
			$format_string = str_replace('Fit Knit', 'Fit Knit&#0169;', $string);
		elseif (stripos($string, 'downinc') !== false)
			$format_string = 'Bedding Basics';
		elseif (stripos($string, 'eacute') !== false)
			$format_string = $input_string;
		else $format_string = $input_string;

		$pretty_text = ucwords($format_string);

		return $pretty_text;
	}


	public static function obfuscate_email($email)
	{
		
	}
	
	

	/**
	 * remove line endings from text (e.g. when outputting to csv file)
	 */
	public static function strip_line_endings($str)
	{
		$str = str_replace(array("\r\n", "\r", "\n"), ' ', $str);

		return $str;
	}
}
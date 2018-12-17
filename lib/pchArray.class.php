<?php

class pchArray
{
	/**
	 * key_search
	 *
	 * search given key in bidimensional array
	 *
	 * example: search for any low_qty = true in a doctrine Product table array: key_search($product, 'eta', true)
	 *
	 * example: find main array key for Doctrine object with a 'masterno' field: key_search($bagitems, 'masterno', $masterno, 'key')
	 *
	 * @param array $haystack - the array to search
	 * @param string $searchkey - the grandchild array key to search
	 * @param string $needle - the search term
	 * @param string $return_type - bool (return true), key (return key), val (return child array)
	 * @param bool $strict - if false, do case-insensitive string comparison
	 *
	 * @return mixed (child array key, child array, or bool)
	 */
	public static function key_search(array $haystack, $searchkey, $needle, $return_type = 'bool', $strict = true)
	{
		foreach ($haystack as $key => $val)
		{
			$check = false;

			if ($strict === true)
			{
				if ($val[$searchkey] === $needle)
					$check = true;
			}
			// case-insensitive string comparison
			else if (strcasecmp($needle, $val[$searchkey]) == 0)
				$check = true;

			if ($check)
			{
				switch ($return_type)
				{
					case 'key':
						$return = $key;
						break;

					case 'val':
						$return = $val;
						break;

					default:
						$return = true;
						break;
				}
				return $return;
			}
		}
		return false;
	}

	/**
	 * case-insensitive in_array search
	 * @param mixed $needle
	 * @param array $haystack
	 * @return bool
	 */
	public static function in_arrayi($needle, $haystack, $strict = null)
	{
		return in_array(strtolower($needle), array_map('strtolower', $haystack), $strict);
	}
	
	/**
	 * recursively merge arrays, overwriting keys
	 * @return array
	 */
	public static function merge()
	{
		// Holds all the arrays passed
		$params = func_get_args();

		// First array is used as the base, everything else overwrites on it
		$return = array_shift($params);

		// Merge all arrays on the first array
		foreach ($params as $array)
		{
			foreach ($array as $key => $value)
			{
				// numeric keyed values are added (unless already there)
				if (is_numeric($key) && ( ! in_array($value, $return)))
				{
					if (is_array($value))
					{
						$return[] = self::merge($return[$$key], $value);
					}
					else
						$return[] = $value;
				}
				else // string keyed values are replaced
				{
					if (isset($return[$key]) && is_array($value) && is_array($return[$key]))
					{
						$return[$key] = self::merge($return[$key], $value);
					}
					else
						$return[$key] = $value;
				} // end string key check
			} // end key loop
		} // end array loop
    
		return $return;
	}

	/**
	 * case-insensitive array_key_exists, optionally return the key if found
	 * @param mixed $needle
	 * @param array $haystack
	 * @param bool $return_key (default false)
	 * @return mixed (bool|string)
	 */
	public static function key_exists($needle, $haystack, $return_key = false)
	{
		if ($return_key === false)
			return (bool) in_array(strtolower($needle), array_map('strtolower', array_keys((array) $haystack)));
		else
		{
			// build an array of lowercased keys and original keys
			$keys = array_keys((array) $haystack);
			$keys_lookup = array_combine(array_map('strtolower', $keys), $keys);
			$lower_needle = strtolower($needle);

			// if we find a match, return the original key
			return isset($keys_lookup[$lower_needle]) ? $keys_lookup[$lower_needle] : false;
		}
	}

	/**
	 * case-insensitive array search
	 * check each array value for $needle, if present, return true
	 * @param mixed $needle
	 * @param array $haystack
	 * @param boolean $reverse - if true, see if the haystack entry is contained within needle
	 * @return bool
	 */
	public static function array_like($needle, $haystack, $reverse = false)
	{
		foreach ((array) $haystack as $hay)
		{
			if ($reverse === false && stripos($hay, $needle) !== false)
				return true;

			if ($reverse === true && stripos($needle, $hay) !== false)
				return true;
		}

		return false;
	}
	
	/**
	 * return keys that match the needle
	 * @example: 
	 *   $needle = array('foo', 'bar_');
	 *   $haystack = array('foo', 'foobar', 'wee', 'bar_blah', 'argh', 'arghfooargh');
	 *   pchArray::array_key_like($needle, $haystack);
	 *     >> array('foo', 'foobar', 'bar_blah', 'arghfooargh');
	 *   pchArray::array_key_like($needle, $haystack, array('exclude' => true, 'starting_with' => true);
	 *     >> array('wee', 'argh', 'arghfooargh');
	 * 
	 * @param string|array $needle - entries to check for matches
	 * @param array $haystack
	 * @param array $options
	 * @return array - resulting array
	 */
	public static function array_key_like($needle, array $haystack, array $options = array())
	{
		$def_opts = array
			(
				'exclude' => false // if true, exclude $needle keys, rather than include
			,	'starting_with' => false // if true, only match if haystack key starts with $needle
			);
		$opts = array_merge($def_opts, $options);
		$needle = (array) $needle;
		
		// filter any entries that don't match the list of $needle keys (as determined by the array_reduce).
		$result = array_filter
		(
			$haystack
		,	function($k) use ($needle, $opts)
			{
				// if a partial key match is found, we return "true" here, keeping the key.
				$found = array_reduce
				(
					$needle
				,	function($already_found, $needle) use ($k, $opts)
					{
						$stripos = stripos($k, $needle);
						$just_found = ($opts['starting_with'])
							? $stripos === 0
							: $stripos !== false;
						
						return $already_found || $just_found; // if just found, record that.
					}
				);
				
				return $found;
			}
		);
		
		if ($opts['exclude'] === true)
			$result = array_diff($haystack, $result);
		
		return $result;
	}

	/**
	 * return the associative array of keys that are present in the second $keys array
	 * @param array $haystack - the associative array to search
	 * @param array $keys - the keys you're searching for
	 * @param bool $include_missing_keys - if true, add null entries for any missing $keys. otherwise, omit them.
	 * @return array - the keys found
	 */
	public static function extract_keys($haystack, $keys, $include_missing_keys = false)
	{
		$ret = array();

		foreach((array) $keys as $key)
		{
			if (isset($haystack[$key]))
				$ret[$key] = $haystack[$key];
			elseif ($include_missing_keys)
				$ret[$key] = null;
		}

		return $ret;
	}

	/**
	 * return the associative array of keys that are present in the second $keys array
	 * @param array $haystack - the nested associative array to search
	 *  - e.g. extract_multi_keys
	 *	(
	 *		array
	 *		(
	 *			'rda1234' => array('foo' => 'bar', 'blah' => 'baz')
	 *		,	'rda456' => array('foo' => 'wee')
	 *		)
	 *		,	'foo'
	 *	); // returns array('rda1234' => 'bar', rda456' => 'wee');
	 * @param string|array $key - the keys you're searching for
	 * @param bool $preserve_keys - if true, preserve array key for value we're extracting. otherwise index numerically
	 * @return array - array of the keys searched for (null if not found)
	 */
	public static function extract_multi_keys($haystack, $keys, $preserve_keys = true, $flatten_key = false)
	{
		$ret = array();

		foreach((array) $haystack as $k => $v)
		{
			foreach ((array) $keys as $key)
			{
				$val = isset($v[$key]) ? $v[$key] : null;

				if ($preserve_keys)
				{
					// if we passed an array of keys, we have to use the key itself when preserve keys is called.
					// otherwise, the keys will just overwrote each other if multiples are found.
					if (is_array($keys))
						$ret[$k][$key] = $val;
					else
						$ret[$k] = $val;
				}
				else $ret[] = $val;
			}
		}
		return $ret;
	}

	/**
	 * return the last key in an array.
	 * @param array $haystack
	 * @return mixed - the key
	 */
	public static function last_key($haystack)
	{
		end($haystack);
		return key($haystack);
	}

	/**
	 * return the first key in an array
	 * @param array $haystack
	 * @return mixed - the key
	 */
	public static function first_key($haystack)
	{
		reset($haystack);
		return key($haystack);
	}

	/**
	 * filter a 2 dimensional array by removing or copying children with grandchildren keys set to a certain value
	 * in php 5.3 this will just be done with anonymous functions...
	 * @param array $haystack original array
	 * @param mixed $key key to match
	 * @param mixed $value value to match
	 * @param boolean $use_strict comparison (default false)
	 * @param boolean $remove_matching remove from original (default true)
	 * @param boolean $copy_matching copy to $matching (default false)
	 * @param boolean $reverse_match if true, return anything NOT matching $value (default false)
	 * @return array $matching items having a subitem with a matching key and value
	 */
	public static function array_key_filter
	(array &$haystack, $key, $value, $use_strict = false, $remove_matching = true, $copy_matching = false, $reverse_match = false)
	{
		$matching = array();

		foreach ($haystack as $k => $child)
		{
			if (array_key_exists($key, $child))
			{
				if ($use_strict === true)
					$is_matching = ($child[$key] === $value);
				else
					$is_matching = ($child[$key] == $value);
			}
			// if we don't find the key, the match is false.
			else $is_matching = false;

			// XOR: is_matching and reverse_match are opposite values, then proceed
			if ($is_matching ^ $reverse_match)
			{
				if ($remove_matching === true)
					unset($haystack[$k]);
				if ($copy_matching === true)
					$matching[$k] = $child;
			}
		}

		return $matching;
	}

	/**
	 * make a copy of each array item that does not have a matching subitem
	 * @param array $haystack original array
	 * @param mixed $key key to match
	 * @param mixed $value value to match
	 * @param boolean $use_strict comparison (default false)
	 * @param bool $by_reference (default false) - if true, alter original array, ala array_key_filter
	 */
	public static function array_key_extract($haystack, $key, $value, $use_strict = false, $by_reference = false)
	{
		return self::array_key_filter($haystack, $key, $value, $use_strict, false, true);
	}

	/**
	 * make a copy of each array item that has a matching subitem
	 * @param array $haystack original array
	 * @param mixed $key key to match
	 * @param mixed $value value to match
	 * @param boolean $use_strict comparison (default false)
	 * @param bool $by_reference (default false) - if true, alter original array, ala array_key_filter
	 */
	public static function array_key_exclude($haystack, $key, $value, $use_strict = false, $by_reference = false)
	{
		return self::array_key_filter($haystack, $key, $value, $use_strict, false, true, true);
	}

	/**
	 * shift items with a matching subitem to the end or beginning of array
	 * @param array $haystack
	 * @param mixed $key
	 * @param mixed $value
	 * @param boolean $use_strict comparison (default false)
	 * @param boolean $shift_to_beginning if true, shift items to the beginning, otherwise to the end
	 */
	public static function array_key_shift(array &$haystack, $key, $value, $use_strict = false, $shift_to_beginning = false)
	{
		$matching = self::array_key_filter($haystack, $key, $value, $use_strict, true, true);

		if ($shift_to_beginning === true)
			$haystack = array_merge($matching, $haystack);
		else
			$haystack = array_merge($haystack, $matching);
	}


	/**
	 * unset specified array key entries
	 * @param array $haystack
	 * @param string|array $key key(s) to find and remove
	 * @param bool $return_stripped - return stripped elements?
	 * @param bool $include_missing_keys [false] - if true, add null entries for any missing $keys.
	 * @return mixed (bool|array)
	 */
	public static function strip_keys(array &$haystack, $keys, $return_stripped = false, $include_missing_keys = false)
	{
		$stripped = array();

		foreach ((array) $keys as $key)
		{
			if (isset($haystack[$key]) || $include_missing_keys)
			{
				if ($return_stripped === true)
				{
					if (isset($haystack[$key]))
						$stripped[$key] = $haystack[$key];
					else if ($include_missing_keys)
						$stripped[$key] = null;
				}
				unset($haystack[$key]);
			}
		}

		if ($return_stripped === true)
			return $stripped;
		else return true;
	}

	/**
	 * unset specified array key entries within a 2 dimensional array
	 * @param array $haystack
	 * @param string|array $key key(s) to find and remove
	 * @param bool $return_stripped - return stripped elements?
	 * @param bool $preserve_keys - if returning stripped elements, retain child key?
	 * @return mixed (bool|array)
	 */
	public static function strip_keys_multi(array &$haystack, $keys, $return_stripped = false, $preserve_keys = true)
	{
		$stripped = array();

		foreach ($haystack as $k => &$child)
		{
			foreach ((array) $keys as $key)
			{
				if (isset($child[$key]))
				{
					if ($return_stripped === true)
					{
						if ($preserve_keys === true)
							$stripped[$k] = $child[$key];
						else
							$stripped[] = $child[$key];
					}
					if (is_array($child))
						unset($child[$key]);
				}
			}
		}

		if ($return_stripped === true)
			return $stripped;
		else return true;
	}

	/**
	 * return the first non-empty() value of the passed arguments
	 * @see empty()
	 * @param mixed [$var] Any number of variables to check, in order
	 * @return mixed First non-empty value, or NULL
	 */
	public static function coalesce()
	{
		$args = func_get_args();
		foreach ($args as $arg)
		{
			if ( ! empty($arg))
				return $arg;
		}

		return null;
	}

	/**
	 * are all elements passed empty()? if an array is passed, check all array keys with array_filter.
	 * @param mixed [$var] Check all variables passed, if they're all empty, return.
	 * @return bool (Whether or not all passed parameters are empty)
	 */
	public static function all_empty()
	{
		$args = func_get_args();

		foreach ($args as $arg)
		{
			if (is_array($arg))
			{
				// if this is an array, make sure that all keys aren't empty.
				if ( ! empty($arg) && count(array_filter($arg)) > 0)
					return false;
				// array is either empty, or all keys are empty (due to array_filter result)
				else continue;
			}
			else
			{
				// simple empty check, if not array
				if ( ! empty($arg))
					return false;
			}
		}

		// if we didnt return true on any of the elements, everything is empty.
		return true;
	}

	/**
	 * inject array $child into multi-assoc array $parent with offset $key
	 * <code>
	 * <?php
	 * $parent = array('foo' => array('a' => 'a', 'b' => 'b');
	 * $child = array('foo' => 'z');
	 * $combined = self::inject_key($parent, $child, 'last');
	 * var_dump($combined);
	 * ?>
	 * // array('foo' => array('a' => 'a', 'b' => 'b', 'last' => 'z');
	 * </code>
	 * @param array &$parent - multidimensional associative array
	 * @param array $child
	 * @param mixed $key
	 * @param bool $inject_missing [false] - if parent key isnt set, set and inject child anyway?
	 * @return void
	 */
	public static function inject_key(&$parent, $child, $key, $inject_missing = false)
	{
		foreach ($child as $k => $v)
		{
			if (array_key_exists($k, $parent))
				$parent[$k][$key] = $v;
			else if ($inject_missing === true)
				$parent[$k] = array($key => $v);
		}
	}

	/**
	 * returns an array value by the path given.
	 * This allows you to pass an array of keys, and have them parsed into offsets.
	 * e.g., fetch_by_keys(&$foo, array('key1', 'key2', 3)) returns $foo['key1']['key2'][3]
	 * @param array $haystack
	 * @param string|array $keys - an array of the key names to append to the array.
	 * @return mixed - the object located at the end of the array path specified (null if not found)
	 */
	public static function fetch_by_keys(array $haystack, $keys)
	{
		foreach ((array) $keys as $key)
		{
			// parse key
			// if "?", it is optional, try to do a key match
			if ($key[0] === '?')
			{
				$optional = true;
				
				$key = substr($key, 1);
			}
			else
			{
				$optional = false;
			}
			
			if ( ! isset($haystack[$key]))
			{
				$first_key = self::first_key($haystack);
				// If array and has only one entry and is numeric key, drill down 1 more level
				if ( is_array($haystack) && is_array($haystack[$first_key]) && $first_key === 0 && count($haystack) === 1 )
				{
					if ( isset($haystack[$first_key][$key]) )
					{
						$haystack = $haystack[$first_key];
					}
					else $not_found = true;
				}
				else $not_found = true;
				
			} // end of "key not found"
			else $not_found = false;
			
			// if not found and not optional, return null
			if ( $not_found === true )
			{
				if ( $optional === false )
					return null;
				else
				{
					continue;
				}
			}
			else // we drill down to the next key
			{
				$haystack = $haystack[$key];
			}
		}
		return $haystack;
	}

	/**
	 * implode an associative array, including the keys
	 * @param array $array
	 * @param string $glue - String to glue array entries together.
	 * @param string $key_glue (optional) - String to glue the key to the value for a given entry. If omitted, $glue is used.
	 * @return string
	 */
	public static function implode_assoc($array, $glue, $key_glue = null)
	{
		if ($key_glue === null)
			$key_glue = $glue;

		$output = array();

		foreach ($array as $k => $v)
			$output[] = $k . $key_glue . $v;

		return implode($glue, $output);
	}

	/**
	 * array_sum_key
	 * sum the values of a given key in a multidimensional array
	 * @param array $arr
	 * @param string $key - the key to sum
	 * @return int result
	 */
	public static function array_sum_key(array $array, $key)
	{
		$ret = 0;
		foreach ($array as $k => $data)
			$ret += (isset($data[$key])) ? $data[$key] : 0;

		return $ret;
	}

	/**
	 * Return the max value of a given key in a multidimensional array
	 * @param array $arr
	 * @param string $key - the key to search for max
	 * @return int result
	 */
	public static function array_max_key(array $array, $key)
	{
		$ret = 0;
		foreach ($array as $k => $data)
			if (isset($data[$key]) && $data[$key] > $ret)
				$ret = $data[$key];

		return $ret;
	}

	/**
	 * sort an array by the value in an array key
	 * @param array $array
	 * @param string $key - the key to sort by
	 * @param const $order - SORT_ASC or SORT_DESC (default: asc)
	 * @param const $sort_type (SORT_REGULAR|SORT_NUMERIC|SORT_STRING) - default: SORT_REGULR
	 * @return array (sorted array)
	 */
	public static function array_sort_key(array $array, $key, $order = SORT_ASC, $sort_type = SORT_REGULAR)
	{
		// how to account for blank values, based on sort type
		$empty = ($sort_type == SORT_NUMERIC) ? 0 : '';

		// build a list of "columns"
		$cols = $sort_col = array();
		foreach ($array as $offset => $row)
			$sort_col[$offset] = isset($row[$key]) ? $row[$key] : $empty;

		array_multisort($sort_col, $order, $sort_type, $array);
		return $array;
	}

	/**
	 * sort array by multi-dimensional array by multiple fields and sorting options
	 * @example array_sort_multi_key('foo', SORT_DESC, SORT_NUMERIC, 'bar', $source)
	 * @param string $key - (first) key to sort by
	 * @param const $order (optional) SORT_DESC if needed
	 * @param array $source - the source array
	 * @author improved based on http://www.php.net/manual/en/function.array-multisort.php#89918
	 * @todo combine with array_sort_key above
	 * @return array (sorted array)
	 */
	public static function array_sort_multi_key()
	{
		$args = func_get_args();
		$array = array_pop($args);
		if ( ! is_array($array) || empty($array))
			return array();

		// Here we'll sift out the values from the columns we want to sort on, and put them in numbered 'subar' ("sub-array") arrays.
		//   (So when sorting by two fields with two modifiers (sort options) each, this will create $subar0 and $subar3)
		foreach($array as $key => $row) // loop through source array
		{
			foreach($args as $akey => $val) // loop through args (fields and modifiers)
				if(is_string($val)) // if the arg's a field, add its value from the source array to a sub-array (otherwise it's an option)
					${"subar$akey"}[$key] = $row[$val];
		}

		// $multisort_args contains the arguments that would (/will) go into array_multisort(): sub-arrays, modifiers and the source array
		$multisort_args = array();
		foreach($args as $key => $val)
			$multisort_args[] = (is_string($val) ? ${"subar$key"} : $val);

		$multisort_args[] = &$array; // include source array

		call_user_func_array("array_multisort", $multisort_args);
		return $array;
	}

	/**
	 * Flatten an array while preserving keys
	 * @param array $array
	 * @param array $newArray
	 * @param bool $smart_flatten (false) - if true, and value is an array with all values ALSO being arrays...
	 *		we remove the container array. this avoids: array(0 => array(0 => ..., 1 => ...), 1 => array({3 more})
	 *		instead, you get array(0 => ..., 1 => ..., 2 => ..., 3 => ..., 4 => ...)
	 *		so, rather than have 2 nested arrays, with 2 and 3 child arrays, we return 1 array with 5 child arrays.
	 *		hopefully that makes sense. see Projects in Pinetar when hydrated through objectives.
	 * @returns array
	 */
	public static function array_flatten(array $array, &$newArray = array(), $smart_flatten = false)
	{
		foreach ($array as $key => $child)
		{
			if (is_array($child))
			{
				// only call recursive if all children of this array are also numerically-indexed arrays
				if ($smart_flatten === true)
				{
					// DON'T KNOW WHAT TO WRITE HERE.
					// IF I UNCOMMENT THE FOLLOWING 4 LINES, THE RESULTING NEWARRAY WILL BE THE SAME AS THE ORIGINAL
					// I DON'T KNOW HOW TO ALTER THIS FUNCTION TO PRESERVE THE OLDER FUNCTIONALITY AND GET THE DESIRED RESULT. -JP

//					if (self::array_children_sequential($child))
//						$newArray = self::array_flatten($child, $newArray, $smart_flatten);
//					else
//						$newArray[] = $child;
				}
				$newArray = self::array_flatten($child, $newArray);
			}
			else
				$newArray[$key] = $child;
		}
		return $newArray;
	}


	/**
	 * For this array, are all children sequential arrays themselves?
	 * @see is_sequential_array
	 * @return bool
	 */
	public static function array_children_sequential($array)
	{
		$ret = array_reduce($array, 'pchArray::all_children_seq_arrays');

		return $ret;
	}

	/**
	 * Validates if children of array are all sequential arrays
	 * @see array_children_sequential
	 * @see is_sequential_array
	 * @param bool $all_arrays
	 * @param mixed $child
	 * @return bool
	 */
	public static function all_children_seq_arrays($all_arrays, $child)
	{
		$ret_val_exists = isset($all_arrays);
		$ret_val = $all_arrays;
		$child_is_seq_array = self::is_sequential_array($child);

		// Will keep true value if child is a seq array, will set on first call
		$all_arrays = ( ! $ret_val_exists || $ret_val) && $child_is_seq_array;

		return $all_arrays;
	}

	// preserve keys of returned array (the removed elements) in array_splice
	// doesnt support offset or replacement array because i'm lazy i guess
	public static function array_splice (&$array, $index)
	{
		$return = array();
		$keys = array_keys($array);
		$keys_removed = array_splice($keys, $index);

		// loop through removed keys, set return key => value
		foreach ($keys_removed as $key)
		{
			$return[$key] = $array[$key];
			unset($array[$key]);
		}

		return $return;
	}

	// recursive in_array
	public static function in_array_r($needle, $haystack)
	{
		foreach ($haystack as $stalk)
			if ($needle == $stalk || (is_array($stalk) && self::in_array_r($needle, $stalk)))
				return true;

		return false;
	}

	/**
	 * array_match
	 * true if any value in array needle is present in array haystack
	 * @param array $needle - the array of search values
	 * @param array $haystack - the array to check for matches in
	 * @param boolean $value_mode - whether or not to return value instead of boolean (default: false)
	 * @return mixed (true/false if boolean mode, else value)
	 */
	public static function array_match($needle, $haystack, $value_mode = false)
	{
		$haystack = array_flip($haystack);
		foreach ($needle as $entry)
			if (isset($haystack[$entry]))
				return ($value_mode) ? $entry : true;

		return false;
	}

	/**
	 * unlimited depth array search, returns key path to first matching value
	 *
	 * @param mixed $find (value to search $in_array for)
	 * @param array $in_array (array to search for value)
	 * @param array $keys_found (returned array)
	 * @param string $custom_return (keyword for customized behavior)
	 * @return array (array of keys, nested path to target
	 */
	public static function array_path ($find, $in_array, &$keys_found = array(), $custom_return = 'key')
	{
		if( is_array($in_array) )
		{
			foreach($in_array as $key => $val)
			{
				if( is_array($val) )
				{
					$path = pchArray::array_path($find, $val, $keys_found, $custom_return);
					if ($path === true)
					{
						switch ($custom_return)
						{
							case 'breadcrumb': // store route information instead of key
								$keys_found[] =
									array
									(
									'name' => $key
									,	'route' => $val['route']
									,	'type' => ( isset($val['type']) ) ? $val['type'] : null
								);
								return true;

							case 'navlink' : // return matched item container, direct access to self and siblings
								$keys_found = $val;
								return false;

							default:
								$keys_found[] = $key;
								return true;
						}
					}
				}
				else
				{
					switch ($custom_return)
					{
						case 'locator': // look for matching zip, if partial zip, match to beginning of zips
							if (($find >= substr($val, 0, strlen($find))) && ($find <= substr($val,5, 5 + strlen($find))))
							{
								$keys_found[] = $key;
								return true;
							}
							break;

						case 'navlink': // flag as found, will store this and siblings
							if( strtolower($find) == strtolower($val) )
								return true;

							break;

						case 'division': // $find is an array
							if (in_array($val, $find))
							{
								$keys_found[] = $key;
								return true;
							}
							break;

						default:
						// could replace with a preg_match, but that's not neccessary yet
							if( strtolower($find) == strtolower($val) )
							{
								$keys_found[] = $key;
								return true;
							}
							break;
					}
				}
			}
		}
		return false;
	}

	/**
	 * ksort_by_array
	 *
	 * sort an array by another array with key values in desired order
	 * any keys that don't match are appended in their original order
	 *
	 * @param array $array (array to sort)
	 * @param array $orderArray (array with key order)
	 *
	 * return array (sorted array)
	 */
	public static function ksort_by_array($array, $orderArray)
	{
		$ordered = array();
		foreach($orderArray as $key)
		{
			if(isset($array[$key]))
			{
				$ordered[$key] = $array[$key];
				unset($array[$key]);
			}
		}

		return $ordered + $array;
	}

	/**
	 * sort_by_array
	 *
	 * Sort an array by another array
	 * any values that don't match are appended in their original order
	 * @param array $array (array to sort)
	 * @param array $orderArray (array with value order)
	 *
	 * @return array (sorted array)
	 */
	public static function sort_by_array($array, $orderArray, $strict = true)
	{
		$ordered = array();

		foreach ($orderArray as $sort)
		{
			$key = array_search($sort, $array, $strict);
			if ($key !== false)
			{
				$ordered[] = $array[$key];
				unset($array[$key]); // found, so nuke it
			}
		}
		// append unfound keys to the end
		return $ordered + (array) $array;
	}

	/**
	 * case-insensitive array_unique
	 * @param array $array (array to remove dupe values from)
	 * @return array
	 */
	public static function unique($array)
	{
		// get a unique lowercase version of the array
		$unique_keys = array_unique(array_map('strtolower', $array));

		// match back to original array (to preserve case sensitivity) - so you get the original first value returned for each dupe entry
		$unique_orig = array_intersect_key($array, $unique_keys);

		return $unique_orig;
	}

	/**
	 * vsort_multi_by_array
	 *
	 * sort an array by another array, with values for a given key in second level array, in desired order
	 * any keys that don't match are appended in their original order
	 *
	 * example:
	 * array
	 * (
	 *	0 => array
	 *		(
	 *			'foo' => 'bar'
	 *		,	'bah' => 'w00t'
	 *		)
	 *	1 => array
	 *		(
	 *			'foo' => 'boo'
	 *		,	'blah' => 'wee'
	 *		)
	 * )
	 *
	 * $key = 'foo';
	 * $orderArray = array('boo', 'bar');
	 *
	 * array
	 * (
	 *	0 => array
	 *		(
	 *			'foo' => 'boo'
	 *		,	'blah' => 'wee'
	 *		)
	 *	1 => array
	 *		(
	 *			'foo' => 'bar'
	 *		,	'bah' => 'w00t'
	 *		)
	 * )
	 *
	 * @param array $array (array to sort)
	 * @param string $key (use this key's value for sort)
	 * @param array $orderArray (array with key order)
	 *
	 * return array (sorted array)
	 */
	public static function vsort_multi_by_array($array, $key, $orderArray, $extra_nest = false)
	{
		$ordered = array();
		foreach($orderArray as $value)
		{
			if (empty($array))
				continue;

			foreach($array as $k => $a)
			{
				$lookup = ($extra_nest !== false && isset($a[$extra_nest]))
					? $a[$extra_nest]
					: $a;

				$has_key = array_key_exists($key, $lookup);
				if($has_key)
				{
					$val = $lookup[$key];

					$val_match = strcasecmp($val, $value) == 0;
					$both_null = is_null($value) && is_null($val);

					if ($val_match || $both_null)
					{
						$ordered[$k] = $a;
						unset($array[$k]);
					}
				}
			}
		}

		return $ordered + $array;
	}

	/**
	 * group a 2 dimensional array by a key value in the second level array.
	 * if key doesn't exist in an array, it's put in '-1' group
	 *
	 * Example:
	 *
	 * convert:
	 *	array
	 *	(
	 *		array('id' => 1, 'key' => 'foo')
	 *	,	array('id' => 2, 'key' => 'wee')
	 *	,	array('id' => 3, 'key' => 'doh')
	 *	,	array('id' => 4, 'key' => 'foo')
	 *	,	array('id' => 5, 'key' => 'doh')
	 *	,	array('id' => 6, 'key' => 'foo')
	 *	,	array('id' => 7)
	 *	)
	 *
	 *  to:
	 *
	 *  array
	 *	(
	 *		'foo' => array
	 *		(
	 *			array('id' => 1, 'key' => 'foo')
	 *		,	array('id' => 4, 'key' => 'foo')
	 *		)
	 *  ,	'wee' => array
	 *		(
	 *			array('id' => 2, 'key' => 'wee')
	 *		,	array('id' => 5, 'key' => 'wee')
	 *		)
	 *  ,	'doh' => array
	 *		(
	 *			array('id' => 3, 'key' => 'doh')
	 *		,	array('id' => 6, 'key' => 'doh')
	 *		)
	 *  ,	-1 => array
	 *		(
	 *			array('id' => 7)
	 *		)
	 *
	 * @param array $haystack (array to hydrate)
	 * @param string $key (key to group by)
	 * @param mixed (int|string) $default_key (default group if lookup key isn't set or empty)
	 *
	 * @return array (array entries grouped by key value in second level array)
	 */
	public static function group_by_key($haystack, $key, $default_key = -1)
	{
		$grouped_array = array();
		foreach($haystack as $k => $v)
		{
			// if an entry doesn't have a group key entry, use default of -1; we want default to be uncommon, but simple
			$key_val = isset($v[$key])
				? $v[$key]
				: $default_key;

			$grouped_array[$key_val][$k] = $v;
		}

		return $grouped_array;
	}

	/**
	 * same as group_by_key, but with 1 layer depth support
	 *
	 * TODO: combine these into one function, with nested key optional, as string or array (for indefinite depth lookup)
	 *
	 * @param array $haystack (array to hydrate)
	 * @param string $nested_key (nested array key to find
	 * @param string $key (key to group by)
	 * @param mixed (int|string) $default_key (default group if lookup key isn't set or empty)
	 *
	 * @return array (array entries grouped by key value in second level array)
	 */
	public static function group_by_nested_key($haystack, $nested_key, $key, $default_key = -1)
	{
		$grouped_array = array();
		foreach($haystack as $k => $v)
		{
			// if an entry doesn't have a group key entry, use default of -1; we want default to be uncommon, but simple
			$key_val = isset($v[$nested_key][$key])
				? $v[$nested_key][$key]
				: $default_key;

			$grouped_array[$key_val][$k] = $v;
		}

		return $grouped_array;
	}

	/**
	 * index array values by nested key values. omit any array entries without index key
	 *
	 * example:
	 * array(
	 *	0 => array('key' => 'foo', 'other' => 'blah')
	 *	1 => array('key' => 'bar', 'other' => 'blah')
	 *	2 => array('key' => 'bla', 'other' => 'blah')
	 *	3 => array('key' => 'doh', 'other' => 'blah')
	 * )
	 *
	 * array(
	 *	'foo' => array('key' => 'foo', 'other' => 'blah')
	 *	'bar' => array('key' => 'bar', 'other' => 'blah')
	 *	'bla' => array('key' => 'bla', 'other' => 'blah')
	 *	'doh' => array('key' => 'doh', 'other' => 'blah')
	 * )
	 *
	 * @param array $array (source array)
	 * @param string $index (nested array key to index by)
	 *
	 * @return array $new_array (re-indexed array, same order)
	 */
	public static function index_by_key($array, $index)
	{
		$new_array = array();
		foreach($array as $v)
			if (isset($v[$index]))
				$new_array[$v[$index]] = $v;

		return $new_array;
	}

	/**
	 * Validates if the array is sequential, numerically-indexed
	 * @param array $var
	 * @return bool | Returns false also if $var is not an array
	 */
	public static function is_sequential_array($var)
	{
		if (is_array($var))
			return (array_merge($var) === $var && is_numeric( implode( array_keys( $var ) ) ) );
		else
			return false;
	}

	/**
	 * Validates if the array is an associative array
	 * @param array $var
	 * @return bool | Returns false also if $var is not an array
	 */
	public static function is_associative_array($var)
	{
		if (is_array($var))
			return (array_merge($var) !== $var || ! is_numeric( implode( array_keys( $var ) ) ) );
		else
			return false;
	}

	/*
	 * flatten and flip a 2-level array
	 *
	 * Example:
	 *
	 * convert:
	 * array
	 * (
	 *		'foo' => array
	 *		(
	 *			'wee'
	 *		,	'doh'
	 *		)
	 * ,	'bar' => array
	 *		(
	 *			'blah'
	 *		,	'baz'
	 *		)
	 * )
	 *
	 * to:
	 *
	 * array
	 * (
	 *		'wee' => 'foo'
	 *		'doh' => 'foo'
	 *		'blah' => 'bar'
	 *		'baz' => 'bar'
	 * )
	 *
	 * @param array $array (array to flatten and flip)
	 * @return array $ret (flattened and flipped array)
	 */
	public static function flip_flatten($array)
	{
		if (empty($array))
			return $array();

		$ret = array();
		foreach($array as $k => $v)
		{
			if ( ! is_array($v))
				$v = array($v);

			$values = array_values($v);
			$this_flip = array_fill_keys($values, $k);

			$ret = array_merge($ret, $this_flip);
		}

		return $ret;
	}

	/**
	 * Remove an element from a single-dimensional array if the value matches needle
	 * @param array $haystack
	 * @param mixed $needle - Value of element you want removed
	 * @param boolean $preserve_keys -
	 * @return <type>
	 */
	public static function remove_item_by_value($haystack, $needle, $preserve_keys = true)
	{
		if ( empty($haystack) || ! is_array($haystack) )
			return false;

		// Return original array if needle doesn't even exist
		if ( ! in_array($needle, $haystack) )
			return $haystack;

		foreach ($haystack as $k => $v)
		{
			// Remove the array element if the value matches the needle
			if ($v == $needle)
				unset($haystack[$k]);
		}

		// Return modified array with keys preserved if wanted
		return ($preserve_keys === true) ? $haystack : array_values($haystack);
	}
}
?>
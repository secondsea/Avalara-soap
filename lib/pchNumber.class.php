<?php

class pchNumber
{
	const MINUTE_IN_SECONDS = 60;
	const HOUR_IN_SECONDS = 3600;
	const DAY_IN_SECONDS = 86400;
	const WEEK_IN_SECONDS = 604800;

	/**
	 * Return the duration between 2 periods, in the interval specified
	 * @param string|int $start - starting date or timestamp
	 * @param string|int $end - ending date or timestamp
	 * @param string $interval (default: seconds) - [seconds|minutes|hours|days|weeks|months|years]
	 * @param bool $timestamps (default: false) - were timestamps passed, rather than date strings?
	 * @return int count of the specified interval
	 * // EXTERNAL This can be replaced in php 5.3 by DateInterval calculation, probably.
	 */
	public static function date_diff($start, $end, $interval = 'seconds', $timestamps = false)
	{
		$interval = strtolower($interval);

		if ($timestamps !== true)
		{
			$start = strtotime($start);
			$end = strtotime($end);
		}

		// in case end is before start, we store the sign here, since some of the duration computation is...convoluted (:
		if ($start > $end)
		{
			$sign = -1;
			list($start, $end) = array($end, $start);
		}
		else $sign = 1;

		// we don't use days in these cases, since they vary - compare days/months/years instead
		if (in_array($interval, array('months', 'years')))
		{
			$start_day = date("j", $start);
			$start_month = date("n", $start);
			$start_year = date("Y", $start);

			$end_day = date("j", $end);
			$end_month = date("n", $end);
			$end_year = date("Y", $end);

			$years_elapsed = $end_year - $start_year;
			// if the day hasn't arrived yet, this year doesn't count - we could use date('z'), but we have what we need already.
			if ($end_month < $start_month || ($start_month == $end_month && $start_day > $end_day))
				$years_elapsed--;

			if ($interval == 'years')
				$retval = $years_elapsed;
			else
			{
				// account for month earlier in the year
				if ($end_month < $start_month && $end_year != $start_year)
					$months_elapsed = (12 - $start_month) + $end_month;
				else
					$months_elapsed = $end_month - $start_month;

				if ($end_day < $start_day) // are we counting this month?
					$months_elapsed--;

				$retval = ($years_elapsed * 12) + $months_elapsed;
			}

			return $sign * $retval;
		}
		// for periods with of fixed duration, we can just divide by seconds.
		else
		{
			switch ($interval)
			{
				case 'minutes':
					$denom = self::MINUTE_IN_SECONDS;
				break;

				case 'hours':
					$denom = self::HOUR_IN_SECONDS;
				break;

				case 'days':
					$denom = self::DAY_IN_SECONDS;
				break;

				case 'weeks':
					$denom = self::WEEK_IN_SECONDS;
				break;

				case 'seconds':
				default:
					$denom = 1;
			}

			$diff = floor($end / $denom) - floor($start / $denom);
		}

		return $sign * $diff;
	}

	/**
	 * mysql doesnt like mm/dd/yyyy, convert for insertion
	 * @param string $date_string
	 * @return string
	 */
	public static function mysql_date($date_string)
	{
		if (empty($date_string))
			return null;

		if (ctype_digit($date_string))
			$date = $date_string;
		else $date = strtotime($date_string);

		return empty($date) ? null : date('Y-m-d', $date);
	}

	/**
	 * formats any string date value as mm/dd/yyyy for tsql.
	 * @param string $date_string
	 *
	 * @return string
	 */
	public static function tsql_date($date_string)
	{
		if (empty($date_string))
			return null;
		
		if (ctype_digit($date_string))
			$date = $date_string;
		else $date = strtotime($date_string);

		return empty($date_string) ? null : date('m/d/Y', $date);
	}

	/**
	 * Format time result as days if more than 24hrs, hours if more than 60 minutes and minutes if less than 60min
	 *
	 * @param string $time_to_compare - must be in mysql format  o-m-d H:i:s
	 * @return string - formatted elapsed time string in days, hours, or minutes
	 */
	public static function elapsed_format_hmd($time_to_compare)
	{
		$current_td = date('Y-m-d H:i:s',  time());
		$time_elapsed = pchNumber::date_diff($time_to_compare, $current_td, 'seconds');

		if ($time_elapsed > 86400)
		{
			$elapsed_unit = ' days';
			$time_elapsed = pchNumber::date_diff($time_to_compare, $current_td, 'days');
		}
		else if ($time_elapsed < 3600)
		{
			$time_elapsed = pchNumber::date_diff($time_to_compare, $current_td, 'minutes');
			$elapsed_unit = ($time_elapsed > 1) ? ' minutes' : ' minute';
		}
		else
		{
			$time_elapsed = pchNumber::date_diff($time_to_compare, $current_td, 'hours');
			$elapsed_unit = ($time_elapsed > 1) ? ' hours' : ' hour';
		}

		return $time_elapsed . ' ' . $elapsed_unit;
	}
	/**
	 * format a number so that the desired number of digits is displayed, prepending 0's as needed
	 *
	 * @author Marcus L. Griswold (vujsa)
	 * Can be found at http://www.handyphp.com
	 *
	 * @param int $value (number to format)
	 * @param int $places (desired number of digits in display)
	 * @return string (formatted number)
	 */
	public static function leading_zeros($value, $places)
	{
		$leading = '';
		if(is_numeric($value))
		{
			for($x = 1; $x <= $places; $x++)
			{
				$ceiling = pow(10, $x);
				if($value < $ceiling)
				{
					$zeros = $places - $x;
					$leading = str_pad($leading, $zeros, "0", STR_PAD_LEFT);
					$x = $places + 1;
				}
			}

			$output = $leading . $value;
		}
		else $output = $value;

		return $output;
	}
}
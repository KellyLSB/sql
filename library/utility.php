<?php

namespace Bundles\SQL;
use Exception;
use e;

class Utility {

	public static function is_numeric($v) {
		return preg_match('/^[0-9]+\.?[0-9]+$/', $v) ? true : false;
	}

	public static function querify($c, $table, $array = array(), $delim = ' && ') {
		$query = array();
		foreach($array as $key => $val) {
			if(!is_string($val) && empty($val))
				continue;
			if(!Utility::is_numeric($val))
				$val = $c->quote($val);
			if(strpos($key, '`') !== 0)
				$key = "`$table`.`$key`";
			$query[] = "$key = $val";
		}

		return implode($delim, $query);
	}

}
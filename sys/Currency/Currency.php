<?php
/**
 * User: shafaatbinjaved
 * Date: 7/28/2017
 * Time: 12:18 PM
 */

abstract class Currency {

	function __construct() {
	}

	function get_and_convert($src,$target) {}

	function get_currencires_in_db( $force = false ) {
		global $db;

		$query = '';
		if ( $force == true ) {
			$query = 'SELECT *
					FROM currency c';
		}
		else {
			$query = 'SELECT *
					FROM currency c
					WHERE c.AUTOMATICALLY_UPDATED = 1';
		}

		$result = $db->fetch_table( $query );
		return $result;
	}

	function get_api_key() {}

	function update_currency_ratio( $key, $ratio ) {
		global $db;
		$arr_currency_table = array(
			"ID_CURRENCY"           =>  $key,
			"RATIO_FROM_DEFAULT"    =>  $ratio,
			"LAST_UPDATED"          =>  date("Y-m-d H:i:s")
		);
		return $db->update("currency",$arr_currency_table);
	}

	function update_currency_auto_status( $key ) {
		global $db;
		$arr_currency_table = array(
			"ID_CURRENCY"               =>  $key,
			"AUTOMATICALLY_UPDATED"     =>  0
		);
		return $db->update("currency",$arr_currency_table);
	}

}
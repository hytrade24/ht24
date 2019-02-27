<?php

class Tools_UserStatistic {

	private static $instance = null;

	/**
	 * @return Tools_UserStatistic
	 */
	public static function getInstance()
	{
		if (self::$instance === null) {
			self::$instance = new Tools_UserStatistic();
		}
		return self::$instance;
	}

	private $hash;

	public function __construct() {
		$this->create_client_hash();
		if ($this->has_client_details() && array_key_exists("LOGGING_CLIENT_QUEUE", $_SESSION)) {
			foreach ($_SESSION["LOGGING_CLIENT_QUEUE"] as $queueIndex => $queueEntry) {
				$this->log_data($queueEntry["ID"], $queueEntry["TABLE"], $queueEntry["TYPE"], $queueEntry["REFERER"]);
			}
			unset($_SESSION["LOGGING_CLIENT_QUEUE"]);
		}
	}
	
	public function create_client_hash($arDetails = null) {
		$keyServer = '9a9vdfs621dD1521ss2!$%^ebfh3453SDSdfsfdaef$#32cddsc';
		$arr = array(
			'user_agent='.urlencode($_SERVER['HTTP_USER_AGENT']),
			'ip_address='.urlencode($_SERVER['REMOTE_ADDR']),
			'sysLang='.urlencode($_SERVER['HTTP_ACCEPT_LANGUAGE'])
		);
		$clientStr = implode('&',$arr);
		if (array_key_exists("LOGGING_CLIENT_HASH", $_SESSION)) {
			$clientStr .= "&clientHash=".$_SESSION["LOGGING_CLIENT_HASH"];
		} else if ($arDetails !== null) {
			$clientHash = hash_hmac('sha256', implode('&', $arDetails), $keyServer);
			$_SESSION["LOGGING_CLIENT_HASH"] = $clientHash;
			$clientStr .= "&clientHash=".$clientHash;
		}
		$this->hash = hash_hmac('sha256', $clientStr, $keyServer);
	}
	
	public function has_client_details() {
		return array_key_exists("LOGGING_CLIENT_HASH", $_SESSION);
	}

	public function log_data($id_table, $table_name, $type, $referer = null) {
		global $db;

		if ( is_null($id_table) ) {
			return;
		}
		
		if ( is_null($referer) ) {
			$referer = $_SERVER['HTTP_REFERER'];
		}
		if (!$this->has_client_details()) {
			if (!array_key_exists("LOGGING_CLIENT_QUEUE", $_SESSION)) {
				$_SESSION["LOGGING_CLIENT_QUEUE"] = array();
			}
			$_SESSION["LOGGING_CLIENT_QUEUE"][] = array(
				"ID" => $id_table, "TABLE" => $table_name, "TYPE" => $type, "REFERER" => $referer
			);
			return;
		}

		$last_id = $db->update(
			'log_views_clicks',
			array(
				'SOURCE_LINK'       =>  $referer,
				'CLIENT_HASH'   		=>  $this->hash,
				'TABLE_ID'          =>  $id_table,
				'TABLE'             =>  $table_name,
				'TYPE'              =>  $type,
				'CREATED_AT'        =>  date("Y-m-d H:i:s"),
			),
			true
		);

		if ( $last_id > 0 ) {
			$query = 'SELECT *
						FROM log_views_clicks_deduce_result_per_day a
						WHERE a.type = "'.$type.'"
						AND a.FK_TABLE = "'.$id_table.'"
						AND a.TABLE = "'.$table_name.'"
						AND a.DAY = "'.date("Y-m-d").'"';

			$result = $db->fetch_table( $query );

			if ( count($result) == 0 ) {//inserting new row
				$insert_id = $db->update(
					'log_views_clicks_deduce_result_per_day',
					array(
						"COUNT" => 1,
						"TYPE"  =>  $type,
						"FK_TABLE"  =>  $id_table,
						"TABLE"  =>  $table_name,
						"DAY"  =>  date("Y-m-d")
					)
				);
			}
			else {//updating count
				$count = (int)$result[0]["COUNT"];
				$count++;
				$insert_id = $db->update(
					'log_views_clicks_deduce_result_per_day',
					array(
						"ID_LOG_VIEWS_CLICKS_DEDUCE_RESULT_PER_DAY" =>  $result[0]["ID_LOG_VIEWS_CLICKS_DEDUCE_RESULT_PER_DAY"],
						"COUNT"     =>  $count,
						"TYPE"      =>  $type,
						"FK_TABLE"  =>  $id_table,
						"TABLE"     =>  $table_name,
						"DAY"       =>  date("Y-m-d")
					)
				);
			}
		}
	}

	public function gather_and_organize_all_log_data($get_vars) {
		$data_type = $get_vars['data_type'];
		$id_artikel = $get_vars['id_artikel'];

		$date_start = $get_vars['date_start'];
		$date_end = $get_vars['date_end'];
		$open_modal = $get_vars['open_modal'];

		$labels_arr = array();
		$view_arr = array();
		$click_arr = array();

		$date1 = new DateTime($date_start);
		$date2 = new DateTime($date_end);

		$timeDiff = $date2->diff( $date1 );

		if ( $data_type == "by_month" ) {
			$date_start = explode("-",$date_start);
			$date_start = $date_start[0] . "-" . $date_start[1] . "-" . "01";

			$date_end = explode("-",$date_end);
			$date_end = $date_end[0] . "-" . $date_end[1] . "-" . "31";
		}

		$all_product_logged_data = $this->get_product_log_data($data_type, $id_artikel, $date_start, $date_end);

		if ( $data_type == "by_day" ) {
			for ( $i=$timeDiff->days; $i>=0; $i-- ) {//for day
				/*echo '<pre>';
				var_dump($date_end) ;
				echo '</pre>';*/
				$d = date(
					'd-m-Y',
					strtotime('-'.$i.' day',strtotime( $date_end ))
				);
				array_push($labels_arr,$d);
				$a = $this->find_date( $d, $all_product_logged_data, "VIEW" );

				if ( is_null($a) ) {
					array_push($view_arr,"0");
				}
				else {
					array_push( $view_arr, $a['SUM'] );
				}

				$a = $this->find_date( $d, $all_product_logged_data, "CLICK" );

				if ( is_null($a) ) {
					array_push( $click_arr,"0" );
				}
				else {
					array_push( $click_arr, $a['SUM'] );
				}
			}
		}
		else if ( $data_type == "by_month" ) {
			for ( $i=intval($timeDiff->days/30);$i>=0;$i-- ) {
				$d = date(
					'F, Y',
					strtotime('-'.$i.' months',strtotime( $date_end ))
				);
				array_push($labels_arr,$d);/*
				echo '<pre>';
				var_dump( $d, $all_product_logged_data, "VIEW" );die();*/
				$a = $this->find_date( $d, $all_product_logged_data, "VIEW" );

				if ( is_null($a) ) {
					array_push($view_arr,"0");
				}
				else {
					array_push( $view_arr, $a['SUM'] );
				}

				$a = $this->find_date( $d, $all_product_logged_data, "CLICK" );
				if ( is_null($a) ) {
					array_push( $click_arr,"0" );
				}
				else {
					array_push( $click_arr, $a['SUM'] );
				}
			}
		}

		$graph_data = new stdClass();
		$graph_data->type = "line";
		$graph_data->options = new stdClass();

		$graph_data->data = new stdClass();
		$graph_data->data->datasets = array();
		$graph_data->data->labels = $labels_arr;

		$data = new stdClass();
		$data->backgroundColor = 'rgba(0,0,255,0)';
		$data->borderColor = 'rgba(0,0,255,0.7)';
		$data->fill = true;
		$data->label = 'Artikel Views';
		$data->lineTension = 0.1;
		$data->pointBackgroundColor = "rgba(0,0,255,0.4)";
		$data->pointBorderColor = "rgba(0,0,255,0.7)";
		$data->pointHoverBackgroundColor = "rgba(0,0,255,0.7)";
		$data->pointHoverBorderColor = "rgba(0,0,255,0.7)";
		$data->spanGaps = false;
		$data->yAxisID = 'A';
		$data->data = $view_arr;
		array_push($graph_data->data->datasets,$data);

		$data = new stdClass();
		$data->backgroundColor = 'rgba(0,255,0,0.0)';
		$data->borderColor = 'rgba(0,255,0,0.5)';
		$data->fill = true;
		$data->label = 'Artikel Clicks';
		$data->lineTension = 0.1;
		$data->pointBackgroundColor = "rgba(0,255,5,0.4)";
		$data->pointBorderColor = "rgba(0,255,0,0.7)";
		$data->pointHoverBackgroundColor = "rgba(0,255,0,0.7)";
		$data->pointHoverBorderColor = "rgba(0,255,0,0.7)";
		$data->spanGaps = false;
		$data->yAxisID = 'B';
		$data->data = $click_arr;
		array_push($graph_data->data->datasets,$data);

		$graph_data->options->maintainAspectRatio = false;
		$graph_data->options->scales = new stdClass();
		$graph_data->options->scales->yAxes = array();

		$data2 = new stdClass();
		$data2->id = "A";
		$data2->position = "left";
		$data2->type = "linear";
		$data2->ticks = new stdClass();
		$data2->ticks->min = 0;
		$data2->ticks->fontColor = "rgba(0,0,220,1)";
		array_push($graph_data->options->scales->yAxes, $data2);

		$data2 = new stdClass();
		$data2->id = "B";
		$data2->position = "right";
		$data2->type = "linear";
		$data2->ticks = new stdClass();
		$data2->ticks->min = 0;
		$data2->ticks->fontColor = "rgba(0,200,0,0.7)";
		array_push($graph_data->options->scales->yAxes, $data2);

		$tpl_graph_data = new Template("tpl/".$GLOBALS['s_lang']."/my-marktplatz.table.product.stats.htm");

		if ( $open_modal == "1" ) {
			$tpl_graph_data->addvar("from_modal",1);
		}
		else {
			$tpl_graph_data->addvar("from_modal",0);
		}

		$tpl_graph_data->addvar('CHART_JSON',json_encode($graph_data));

		$date_start = explode("-",$date_start);
		$date_end = explode("-",$date_end);

		$tpl_graph_data->addvar('DATE_START', $date_start[2] . "." . $date_start[1] . "." . $date_start[0] );
		$tpl_graph_data->addvar('DATE_END', $date_end[2] . "." . $date_end[1] . "." . $date_end[0] );

		if ( $data_type == "by_day" ) {
			$tpl_graph_data->addvar('per_day',"1");
			$tpl_graph_data->addvar('per_month',"-1");
		}
		else if ( $data_type == "by_month" ) {
			$tpl_graph_data->addvar('per_month',"1");
			$tpl_graph_data->addvar('per_day',"-1");
		}
		$tpl_graph_data->addvar('ID_ARTIKEL',$id_artikel);

		$tpl_text = $tpl_graph_data->process(true);

		return $tpl_text;
	}

	public function find_date( $date_to_search, &$all_product_data, $type ) {
		foreach ( $all_product_data as $row ) {
			if ( $row['DATE'] == $date_to_search && $type == $row['TYPE'] ) {
				return $row;
			}
		}
		return null;
	}

	public function get_product_log_data($data_type, $id_artikel, $date_start, $date_end) {
		global $db;
		$query = '';
		if ( $data_type == "by_day" ) {
			$query = 'SELECT DATE_FORMAT(DAY,"%d-%m-%Y") as DATE, a.COUNT as SUM, a.TYPE
					FROM log_views_clicks_deduce_result_per_day a
					WHERE a.FK_TABLE = '.$id_artikel.'
					AND a.TABLE = "ad_master"
					AND ( a.TYPE = "VIEW" OR a.TYPE = "CLICK" )
					AND a.DAY BETWEEN "'.$date_start.'" AND "'.$date_end.'"
					ORDER BY a.DAY ASC';
		}
		else if ( $data_type == "by_month" ) {
			$query = 'SELECT DATE_FORMAT(a.DAY,"%M, %Y") as DATE, MONTH(a.DAY) as MONTH_NUM, DATE_FORMAT(a.DAY,"%M") as MONTH_NAME, YEAR(a.DAY) as YEAR, SUM(a.COUNT) as SUM, a.TYPE
						FROM log_views_clicks_deduce_result_per_day a
						WHERE a.FK_TABLE = '.$id_artikel.'
						AND a.TABLE = "ad_master"
						AND a.DAY BETWEEN "'.$date_start.'" AND "'.$date_end.'"
						AND ( a.TYPE = "VIEW" OR a.TYPE = "CLICK" )
						GROUP BY MONTH(a.DAY), YEAR(a.DAY), a.TYPE';
		}
		$result = $db->fetch_table( $query );

		return $result;
	}

	private function randomDate($start_date, $end_date)
	{
		// Convert to timetamps
		$min = strtotime($start_date);
		$max = strtotime($end_date);

		// Generate random number using above bounds
		$val = rand($min, $max);

		// Convert back to desired date format
		return date('Y-m-d', $val);
	}


	public function seed_datebase() {
		global $db;

		$end = 9999999;
		//$end = 10;

		$type_arr = array("VIEW","CLICK");

		for ( $i=1; $i<$end; $i++ ) {
			$insert_id = $db->update(
				'log_views_clicks_deduce_result_per_day',
				array(
					"COUNT"     =>  mt_rand(0,10000),
					"TYPE"      =>  $type_arr[mt_rand(0,1)],
					"FK_TABLE"  =>  $i,
					"TABLE"     =>  "ad_master",
					"DAY"       =>  $this->randomDate("2017-01-01","2017-12-30")
				)
			);
		}
	}

	public function delete_previous_logs() {
		global $db;
		$query = 'DELETE a.*
					FROM log_views_clicks a
					WHERE a.CREATED_AT <= DATE_SUB(NOW(), INTERVAL 2 DAY)';

		$db->querynow( $query );
	}

}
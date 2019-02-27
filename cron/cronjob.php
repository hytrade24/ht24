<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!file_exists(__DIR__."/../inc.server.php")) {
    die();
}

function crashHandler() {
	global $ar_errors, $cronActive;
	$ar_error_last = error_get_last();
	if (!empty($ar_error_last)) {
		errorHandler($ar_error_last['type'], $ar_error_last['message'], $ar_error_last['file'], $ar_error_last['line']);
	}
	if ($cronActive != false) {
		// Nicht verhinderbar, alle Fehler ausgeben!
		$cronName = $cronActive["DATEI"];
		$cronLogFile = $GLOBALS["ab_path"].$cronName.".log";
		$str_info = ($cronActive['EINMALIG'] ? 'Aufgabe' : 'CRON').' fehlerhaft: '.$cronActive['DSC'].' am '.date('d.m.Y').' um '.date('H:i').'Uhr';
		$str_log = "------ CRON STARTED AT ".date("Y-m-d H:i:s")." ------\n";
		$str_errors = implode("\n", $ar_errors[$cronName]);
		file_put_contents($cronLogFile, $str_log.$str_errors."\n", FILE_APPEND);
		eventlog('error', $str_info, $str_errors);
	}
}

function errorHandler($errno, $errstr, $errfile, $errline) {
	global $ar_errors, $cronActive;
	if ($cronActive != false) {
		$cronName = $cronActive["DATEI"];
		$type = "Info";
		switch($errno) {
			case E_PARSE:
				$type = "Parse error";
				break;
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$type = "Fatal error";
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$type = "Warning";
				break;
		}
		// Fehler hinzufügen
		$ar_errors[$cronName][] = str_replace($GLOBALS["ab_path"], "/", $errfile).":".$errline."	".$type.": ".$errstr;
	}
	return false;
}

global $db, $ar_errors, $cronActive;

ignore_user_abort(true);

$ar_errors = array();
$cronActive = false;
register_shutdown_function('crashHandler');

#    die(phpinfo());
#require_once '../inc.app.php';

require_once dirname(__FILE__).'/../api.php';

$cronfp = fopen($ab_path."cache/_crontmp.php", "w");


function nextRun($ar)
{
	switch($ar['EINHEIT'])
	{
		case 'minute': $fac = 60;
		break;
		case 'hour': $fac = 60*60;
		break;
		case 'day': $fac = 60*60*24;
		break;
		case 'week': $fac = 60*60*24*7;
		break;
		case 'month': $fac = 60*60*24*7*30;
		break;
		case 'year': $fac = 60*60*24*7*365;
	} // switch EINHEIT

	$secs = $fac*$ar['ALL_X'];
	$now = time();
	$last = strtotime($ar['LAST']);

	#echo "\nERG: ".($now-$secs);
	#echo "\nERG: ".$last."\n";

	if(($now-$secs) >= $last) return true;
	return false;
} // nextRun()

function handleCron($ar)
{
	$done = false;
	global $ab_path, $cronfp, $ab_path, $cronActive, $ar_errors;
	if($ar['DATEI'])
	{
		$cronActive = $ar;
		set_error_handler(errorHandler, error_reporting());
		// CRON START
		try {
			#extract($GLOBALS);
			$GLOBALS['SYSNAME'] = ($ar['SYSNAME'] ? $ar['SYSNAME'] : false);
			include $ab_path.$ar['DATEI'];
			if($ar['FUNKTION'] && function_exists($ar['FUNKTION']))
				$ar['FUNKTION']();
			$done = true;
		} catch (Exception $e) {
			if (get_class($e) == "Exception") {
				eventlog('error', "Exception during cronjob ".$cronActive["DATEI"], $e->getMessage());
			} else {
				eventlog('error', "Exception during cronjob ".$cronActive["DATEI"], $e);
			}
			errorHandler(E_USER_ERROR, $e->getMessage(), "/".$cronActive["DATEI"], "?");
			crashHandler();
		}
		// CRON ENDE
		restore_error_handler();
		$cronActive = false;
	} //
	if($ar['CODE'])
	{
		fwrite($cronfp, $ar['CODE']);
		fclose($cronfp);
		include $ab_path."cache/_crontmp.php";
		$done = true;
		$cronfp = fopen($ab_path."cache/_crontmp.php", "w");
	}

	return $done;
} // handlecron()

// Startzeitpunkt festhalten (-1sec)
$time_str_start = date("Y-m-d H:i:s", time()-1);


$ar = $db->fetch_table("select * from crontab where (ERLEDIGT IS NULL) AND (FEHLER=0)
		order by PRIO DESC");

for($i=0; $i<count($ar); $i++) {
	$lockfile = $ab_path."cache/_cron".$ar[$i]['ID_CRONTAB'].".lock";
	if (file_exists($lockfile)) {
		$time_start = @file_get_contents($lockfile);
		// Prüfen ob noch eine Instanz dieses Cronjobs läuft
		if (!empty($time_start) && ((time() - $time_start) > 600)) {
			// Cron wird seit über 10 Minuten ausgeführt
			$db->querynow("UPDATE `crontab` SET FEHLER=1 WHERE ID_CRONTAB=".(int)$ar[$i]['ID_CRONTAB']);
			eventlog('error', 'Cronjob wurde temporär deaktiviert!',
				'Der Cronjob "'.$ar[$i]['DATEI'].'" wurde deaktiviert, da die maximale Ausführungszeit von 10 Minuten überschritten wurde!');
		}
		// Nächsten Cronjob verarbeiten, den aktuellen nicht erneut starten!
		continue;
	}
	$done = false;
	if(is_null($ar[$i]['LAST']) || is_null($ar[$i]['FIRST'])) {
		// noch nie gelaufen
		if(strtotime($ar[$i]['FIRST']) <= time()) {
			// Startzeitpunkt festhalten
			@file_put_contents($lockfile, time());
			// Cron starten
			$done = handleCron($ar[$i]);
		}
	}
	else {
		// schon mal gelaufen
		$run = nextRun($ar[$i]);
		if($run)
		{
			// Startzeitpunkt festhalten
			@file_put_contents($lockfile, time());
			// Cron starten
			$done = handleCron($ar[$i]);
		} // run == true
	}
	if($done) {
		// done
		@unlink($lockfile);
		eventlog('info', ($ar[$i]['EINMALIG'] ? 'Aufgabe' : 'CRON').' ausgeführt: '.$ar[$i]['DSC'].' am '.date('d.m.Y').' um '.date('H:i').'Uhr');
		if(is_null($ar[$i]['FIRST'])) {
			$db->querynow("update crontab set FIRST='".$time_str_start."' where ID_CRONTAB=".$ar[$i]['ID_CRONTAB']);
		}
		$db->querynow("update crontab set LAST='".$time_str_start."' where ID_CRONTAB=".$ar[$i]['ID_CRONTAB']);
		if($ar[$i]['EINMALIG']) {
			// einmalige Aufgabe
			$db->querynow("update crontab set ERLEDIGT='".$time_str_start."' where ID_CRONTAB=".$ar[$i]['ID_CRONTAB']);
		}
	}
} // for über jobs

@unlink($ab_path."cache/_cron_cur.tmp");

$res = $db->querynow("delete from crontab
		where ERLEDIGT IS NOT NULL and EINMALIG=1
		and ERLEDIGT < date_sub(now(), interval 3 day)
		order by ERLEDIGT ASC");

if($res['int_result'] > 0)
	eventlog("info", $res['int_result']." veraltete Aufgaben gelöscht");

fclose($cronfp);

$crondone = fopen($ab_path."cache/_crondone.php", "w");
fclose($crondone);
touch($ab_path."cache/_crondone.php");

$apiHandler->triggerEvent(Api_TraderApiEvents::CRONJOB_DONE);

?>

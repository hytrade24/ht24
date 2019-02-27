<?php
/* ###VERSIONSBLOCKINLCUDE### */



function Debug_Open($file) {
	global $logfile;
	if (file_exists($file)) {
		$logfile = fopen($file, "a+");	
	} else {
		$logfile = fopen($file, "w+");
	}	
}

function Debug_Append($text) {
	global $logfile, $debug_echo;
	fwrite($logfile, date("[d.m.Y H:i] ").$text."\n");
	if ($debug_echo) {
		echo $text."<br />";
	}
}

function Debug_Close() {
	global $logfile;
	fclose($logfile);
}

?>
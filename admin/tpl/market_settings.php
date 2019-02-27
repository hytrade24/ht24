<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once "options_check.php";


$tpl_content->addvar("PAYPAL_CURRENCY_".$nar_systemsettings["PAYPAL"]["CURRENCY"], 1);

if(count($_POST))  {
	$setted = false;
	#echo ht(dump($_POST));
	foreach($_POST['set'] as $plugin=>$sub) {
		foreach($sub as $typ=>$value) {
            $value = checkOptionValue($plugin, $typ, $value);
			if (is_array ($value)) {
				$value = implode(',', $value);
			}
			$db->querynow("update `option` set value='". mysql_escape_string($value)."'
                where plugin='$plugin' and typ='$typ'");
			$setted = true;
		}
	}
	if ($setted == true) {
		$data = $db->fetch_table("select * from `option` order by plugin, typ");
		$ar = array ();
		foreach ($data as $row) {
			$ar[$row['plugin']][$row['typ']] = $row['value'];
		}
		$s_code = '<?'. 'php $nar_systemsettings = '. php_dump($ar, 0). '; ?'. '>';
		$fp = fopen($file_name = '../cache/option.php', 'w');
		fputs($fp, $s_code);
		fclose ($fp);
		chmod($file_name, 0777);
		die(forward('index.php?page=market_settings&ok=1'));
	}
} else {
	if($_REQUEST['ok'] == 1) {
		$tpl_content->addvar('ok', 1);
	}
	$res = $db->querynow("select
			*
		from
			`option`");
	while($row = mysql_fetch_assoc($res['rsrc'])) {
		$tpl_content->addvar($row['plugin'].'_'.$row['typ'], $row['value']);
	}
}

?>
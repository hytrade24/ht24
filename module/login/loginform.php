<?php
/* ###VERSIONSBLOCKINLCUDE### */


### Angelegt am 24.04.2006

if ($ar_params[1] == "fail") {
	$tpl_content->addvar("user", $ar_params[2]);
	$tpl_modul->addvar("fail", 1);

	if ($ar_params[3] == "stat") {
		$tpl_modul->addvar("check_admin", 1);
	}
}

?>
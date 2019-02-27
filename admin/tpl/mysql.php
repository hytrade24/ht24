<?php
/* ###VERSIONSBLOCKINLCUDE### */


		$ar_roles = $db->fetch_table("SHOW VARIABLES  LIKE 'max%'");
	$tpl_content->addlist('mysqlvalues', $ar_roles, 'tpl/de/mysql.row.htm');
	
	$ar_roles = $db->fetch_table("SHOW VARIABLES  LIKE 'version%'");
	$tpl_content->addlist('mysqlversion', $ar_roles, 'tpl/de/mysql.row.htm');

    $ar_roles = $db->fetch_table("SHOW VARIABLES LIKE 'ft_m%'");
    $tpl_content->addlist('mysqlvt', $ar_roles, 'tpl/de/mysql.row.htm');
?>
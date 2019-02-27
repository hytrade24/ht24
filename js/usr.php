<?php
/* ###VERSIONSBLOCKINLCUDE### */



header('Content-type: text/javascript');
include '../inc.server.php';

$hash = $_REQUEST['s'];
$err = array();

if(!empty($hash)) {
	$con = @mysql_connect($db_host, $db_user, $db_pass);
	if($con) {
		$sel = @mysql_select_db($db_name, $con);
	}
	if($con && $sel) {
		$res = @mysql_query("
			select
				us.*
			from
				user_shop us
			join
				user u on u.ID_USER=us.FK_USER
				and u.STAT=1
			where
				us.HASH='".mysql_real_escape_string($hash)."'");
		if($res) {
			$ar_setts = @mysql_fetch_assoc($res);
		}
		if(empty($ar_setts)) {
			$err[] = "shop not found";
		} else {
			include_once '../cache/option.php';
			$uri 		= 	$nar_systemsettings['SITE']['SITEURL'];
			$uri 		.=	'/index.php?page=user_shop&frame=shop&id='.$hash;
			$height		=	$ar_setts['FRAME_H'];
			$width 		=	$ar_setts['FRAME_W'];
			echo "function bldShop() {
				_bld();
			}";
		}
	} else {
		$err[] = 'No database';
	}
} else {
	$err[] = "no hash";
}

if(!empty($err)) {
	$err = array_merge(array(0 => 'Fehler beim laden der Artikel!'), $err);
	$function = "function bldShop() {
		".'alert("'.implode('\n-', $err).'"'.");
	}";

	die($function);
}

?>
function _bld() {
	document.write('<iframe src="<?php echo $uri; ?>" style="width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;" frameborder="0"></iframe>');
}
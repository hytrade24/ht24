<?php
/* ###VERSIONSBLOCKINLCUDE### */



header('Content-type: text/javascript');
require_once "../api.php";

$hash = $_REQUEST['s'];
$err = array();
$tpl = new Template("");

if(!empty($hash)) {

	$ar_setts = $db->fetch1("
		select
			cs.*
		from
			club_shop cs
		join club c on c.ID_CLUB=cs.FK_CLUB
		where cs.HASH='".mysql_real_escape_string($hash)."' AND c.STATUS = 1");


	if(empty($ar_setts)) {
		$err[] = "shop not found";
	} else {
		include_once '../cache/option.php';
		$uri 		=	$tpl->tpl_uri_baseurl_full('/index.php?page=club_webservice&frame=shop&id='.$hash);
		$height		=	$ar_setts['FRAME_H'];
		$width 		=	$ar_setts['FRAME_W'];
		echo "function bldShop() {
			_bld();
		}";
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

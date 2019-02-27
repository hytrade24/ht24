<?php
/* ###VERSIONSBLOCKINLCUDE### */



if(count($_POST)) {
	if(!$_POST['ID_USER_SHOP']) {
		$_POST['HASH'] = md5($uid.time());
	}
	$_POST['FK_USER'] = $uid;
	$db->update("user_shop", $_POST);
	die(forward('/my-marktplatz/shop_setting,show.htm'));
} else {
	$ar_settings = $db->fetch1("
		select
			*
		from
			user_shop
		where
			FK_USER=".$uid);
	if(!empty($ar_settings)) {
		$tpl_content->addvars($ar_settings);
		$tpl_content->addvar("CODE",
'<script type="text/javascript" src="'.$nar_systemsettings['SITE']['SITEURL'].'/js/usr.php?s='.$ar_settings['HASH'].'"></script><script type="text/javascript">bldShop();</script>
');
	} else {
		$tpl_content->addvars($ar_settings=array(
			'FRAME_W' => 700,
			'FRAME_H' => 400,
			'BGCOLOR' => 'ffffff',
			'COLOR' => '000000',
			'LINKCOLOR' => '0000ff',
			'FONTFAMILY' => 'Verdana',
			'FONTSIZE' => 11,
		));
	}
	if($ar_params[1] == 'show') {
		$tpl_content->addvar('prev', true);
	}
}

$families = array(
	'Arial', 'Verdana', "'Times New Roman',Times,serif"
);

$ar=array();
for($i=0; $i<count($families); $i++) {
	$selected = ($families[$i] == $ar_settings['FONTFAMILY'] ? ' selected' : '');
	$ar[] = '<option value="'.stdHtmlentities($families[$i]).'" '.$selected.'>'.stdHtmlentities(preg_replace("/^(')([a-z\s]*)(')(.*?)$/si", "$2", $families[$i])).'</option>';
}
$tpl_content->addvar("family", implode("\n", $ar));



$sizes = array(
	9,10,11,12,13,14,15
);

$ar = array();
for($i=0; $i<count($sizes); $i++) {
	$selected = ($sizes[$i] == $ar_settings['FONTSIZE'] ? ' selected' : '');
	$ar[] = '<option value="'.$sizes[$i].'"'.$selected.'>'.$sizes[$i].'</option>';
}
$tpl_content->addvar("sizes", implode("\n", $ar));


$perpage = array(
	5,10,15
);

$ar = array();
for($i=0; $i<count($perpage); $i++) {
	$selected = ($perpage[$i] == $ar_settings['PERPAGE'] ? ' selected' : '');
	$ar[] = '<option value="'.$perpage[$i].'"'.$selected.'>'.$perpage[$i].'</option>';
}
$tpl_content->addvar("perpage", implode("\n", $ar));



?>
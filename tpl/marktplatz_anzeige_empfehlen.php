<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die("test");
#echo ht(dump($_POST));
if($_REQUEST['ID_Q'])
{
	#die("TEST");
	$err = array();
	if(!$_POST['NAME'])
		$err[] = 'noName';
	if(!validate_email($_POST['absender']))
		$err[] = 'noAbsender';
	if(!validate_email($_POST['empfeanger']))
		$err[] = 'noEmpfaenger';
	if(!secure_question($_POST))
		$err[] = 'secQuestion';
	if(count($err))
	{
		$err = get_messages('EMPFEHLUNG', implode( ",", $err));
	 #echo ht(dump($err));
	 $tpl_content->addvar("err", implode("<br>", $err));
	 $tpl_content->addvars($_POST);
	} // err
	else
	{
		$_REQUEST['SITENAME']=$nar_systemsettings['SITE']['SITENAME'];
		$_REQUEST['URL']=$nar_systemsettings['SITE']['SITEURL'];
        sendMailTemplateToUser(0, $_POST['empfeanger']." <".$_POST['empfeanger'].">", "empfehlung_produkt", $_REQUEST);
		$tpl_content->addvar("OK", 1);
	} // kein fehler

} // post
$tpl_content->addvar("_URL", $_REQUEST['_URL']);


#$tpl_content->addvar("err", "test");

?>
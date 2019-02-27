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
        $id = (int)$_REQUEST['id'];
        require_once $ab_path."sys/lib.calendar_event.php";
        $calendarEventManagement = CalendarEventManagement::getInstance($db);
        $calendarEvent = $calendarEventManagement->fetchById($id);
        $arFiles = array();
        // Add ical file
        require_once $GLOBALS['ab_path'] . 'sys/swiftmailer/swift_required.php';
        $calendarFileTemp = tempnam("tmp", "ical");
        $calendarEventManagement->createCalendarFile($id, $calendarFileTemp);
        $arFiles[] = Swift_Attachment::fromPath($calendarFileTemp, "text/calendar");
        // Send mail
		$_REQUEST['SITENAME']=$nar_systemsettings['SITE']['SITENAME'];
        $_REQUEST['URL'] = $calendarEventManagement->getUrl($calendarEvent);
        sendMailTemplateToUser(0, $_POST['empfeanger']." <".$_POST['empfeanger'].">", "empfehlung_event", $_REQUEST, true, false, null, $arFiles);
        $tpl_content->addvar("OK", 1);
	} // kein fehler

} // post

$tpl_content->addvar("id", (int)$_REQUEST['id']);

#$tpl_content->addvar("err", "test");

?>
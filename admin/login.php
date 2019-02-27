<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die(var_dump($_COOKIE));
#  chdir('..');
require_once 'inc.all.php';
require_once 'sys/lib.kernel.php';
#  chdir('admin');
$db = new ebiz_db ( $db_name, $db_host, $db_user, $db_pass );

$tmpUser = $db->fetch1("select * from `user` where NAME='".mysql_real_escape_string(trim($_POST['user']))."' and STAT=1");
if($tmpUser && pass_compare($_POST['pass'], $tmpUser['PASS'], $tmpUser['SALT'])) {
    $usr = $tmpUser;
}

#echo ht(dump($_POST)), '<hr />', $sql, '<hr />', ht(dump($usr));die();
if ($usr) {
	#die('+1');
	session_start();

    $uid = (int)$usr['ID_USER'];
    $_SESSION['uid'] = $uid;

    $cookieContentHash = pass_encrypt($uid.$usr['PASS']);

    setcookie ('ebizuid_'.session_name().'_admin_uid', $uid);
    setcookie ('ebizuid_'.session_name().'_admin_hash', $cookieContentHash);

	eventlog ( "info", "User " . $usr ['NAME'] . " angemeldet" );
}
#die(ht(dump($_SESSION)));
if (! $url)
	if (! ($url = $usr ['URL']))
		if (! ($url = $_POST ['forward']))
			$url = 'index.php' . '?x=1';

	#echo ht(dump($url));
if (! $usr) {
	$s_preg = '/(\?|&)log=(.*)(&|$)/U';
	if (preg_match ( $s_preg, $url, $ar_match ))
		$url = preg_replace ( $s_preg, '$1log=fail$3', $url );
	else
		$url .= (false !== strpos ( $url, '?' ) ? '&' : '?') . 'log=fail';
}
#die(ht(dump($url)));
#die(ht(dump($usr)));
// Wenn keine default Startseite ausgewählt ist, index.php aufrufen
if ($usr ['DEFAULTPAGE'] == "") {
	forward ( 'index.php', 1 );
} else { // Wurde eine default Startseite festgelegt, zu dieser weiterleiten
	forward ( 'index.php?page=' . $usr ['DEFAULTPAGE'], 1 );
}
?>
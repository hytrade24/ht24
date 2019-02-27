<?php
/* ###VERSIONSBLOCKINLCUDE### */

//  chdir('..');
require_once 'inc.all.php';
require_once 'sys/lib.kernel.php';

//  chdir('admin');
if ($uid = $_SESSION['uid']) {
    #die(dump($uid));
    $db = new ebiz_db ($db_name, $db_host, $db_user, $db_pass);
    #    $db->querynow("update user set URL=concat('*', URL) where ID=$uid");
    #    $db->querynow("update sess set STAMP=now() where ID='". session_id(). "' and FK_USER=$uid");
    $db->querynow("delete from locks where FK_USER=$uid");

    #log_event('logout', $uid, dump($lastresult));
}

eventlog("info", "User " . $usr ['NAME'] . " hat sich abgemeldet");
setcookie('ebizuid_'.session_name().'_admin_uid', 1, time() - 86400);
setcookie('ebizuid_' . session_name() . '_admin_hash', false, time() - 86400);


session_destroy();
$uid = 0;
forward('index.php', 1, 'top');
?>
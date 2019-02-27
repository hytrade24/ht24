<?php
/* ###VERSIONSBLOCKINLCUDE### */



$user = $db->fetch1("select  ID_USER,VORNAME,NACHNAME,FK_LANG,LU_PROFESSION,LASTACTIV,STAMP_REG from `user` where ID_USER=". $uid);
$tpl_content->addvars("npage", $user);

?>
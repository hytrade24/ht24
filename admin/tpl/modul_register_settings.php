<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$tpl_content->table = 'user';

# Modul-ID Holen:
$id = $db->fetch_atom("select ID_MODUL from modul where IDENT='register'");
# ID für moduloption holen:
$ID_MODULOPTION = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where FK_MODUL=".$id);

if (count($_POST))
{
#
# Info-Bereich in DB speichern
#
	$db->querynow("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='register'");

#
# "Benutzer von Admin freischalten?" in DB und option.php speichern
#
	if(!$_POST['USER_REGCHECK']) $_POST['USER_REGCHECK'] = 0;
	$db->querynow("update `option` set value=".$_POST['USER_REGCHECK']." where plugin='USER' AND typ='REGCHECK'");
	require('../cache/option.php');
	$nar_systemsettings['USER']['REGCHECK'] = $_POST['USER_REGCHECK'];
    $s_code = '<?'. 'php $nar_systemsettings = '. php_dump($nar_systemsettings, 0). '; ?'. '>';
    $fp = fopen($file_name = '../cache/option.php', 'w');
    fputs($fp, $s_code);
    fclose ($fp);
    chmod($file_name, 0777);

#
# Default Role in DB speichern
#
	$settings = array(
				"ID_MODULOPTION" 	=> $ID_MODULOPTION,
				"OPTION_VALUE"		=> "DEFAULT_ROLE",
				"V1"				=> $_POST['role']
				);
	$db->update("moduloption",$settings);

#
### Zurück auf die Einstellungsseite
#
	forward('index.php?nav='. $nav, 2);
}

# Option für Info-Bereich ausgeben
$B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='register'");
$tpl_content->addvar('B_VIS',$B_VIS);

# Option für "Benutzer von Admin freischalten?" ausgeben
$USER_REGCHECK = $db->fetch_atom("select `value` from `option` where plugin='USER' AND typ='REGCHECK'");
$tpl_content->addvar('USER_REGCHECK',$USER_REGCHECK);

# Option für Default Role ausgeben
$roles = $db->fetch_table("SELECT ID_ROLE,LABEL FROM role");
$chosen = $db->fetch_atom("select s.V1 from `moduloption` t left join string_opt s
						   on s.S_TABLE='moduloption'
						   and s.FK=t.ID_MODULOPTION
						   and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
						   where s.S_TABLE='moduloption' AND s.FK=".$ID_MODULOPTION);
foreach($roles as $key => $value)
{
  $roles[$key]["chosen"] = $chosen;
  //if($roles[$key]["LABEL"] == "Admin" or $roles[$key]["LABEL"] == "Gast") unset($roles[$key]);
}
$tpl_content->addlist('roles', $roles, 'tpl/de/modul_register.role.row.htm');


?>
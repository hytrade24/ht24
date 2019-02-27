<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (count($_POST)) echo "hallO";
if ('fail'==$_REQUEST['log'])
{
  $tpl_content->addvar('err_title', 'Login fehlgeschlagen');
  $tpl_content->addvar('err', '<p>Entweder haben Sie Benutzername oder Passwort inkorrekt eingegeben<br>
  oder ihr Zugang ist gesperrt.</p>');
}
?>
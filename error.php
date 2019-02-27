<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'api.php';

?>
<div class="hinweis"><span class="error">
<?php
	echo ($b_noerror ? 'Hinweis<br>
<br>
' : 'Fehlermeldung<br>
<br>
Ein Fehler ist aufgetreten.
');
	if (!$b_nomail) echo ($_REQUEST['ok'] ? 'Eine Mail wurde an ' . ERRMAIL . ' gesendet.' : 'Es wurde vergeblich versucht, eine Mail an ' . ERRMAIL . ' zu senden.');

	if (($msg = $_SESSION['msg']) || ($msg = $_GET['msg'])) {
		echo '<hr>', $msg;
		if (SESSION) unset ($_SESSION['msg']);
	}
	?>

</span></div>

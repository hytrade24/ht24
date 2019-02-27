<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.job.php';
$jobs = JobManagement::getInstance($db);

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

function informAdmin($jobdata, $oldJob) {
	global $jobs;

	$informAdmin = FALSE;
	if($oldJob != NULL) {
		$informAdmin = ($oldJob['OK'] == 0);
	}

	if(($jobdata['ID_JOB'] == 0 OR $informAdmin) && $jobdata['FREIGABE'] == 1) {
		$mailData = array(
			'TASK_JOB' => 1,
			'JOB_NAME' => $jobdata['V1']
		);
		sendMailTemplateToUser(0, 0, 'ADMIN_NEW_TASK', $mailData);
	}
}


$id_job = ($_REQUEST['ID_JOB'] ? (int)$_REQUEST['ID_JOB'] : (int)$ar_params[1]);
$id_order = 0;
$order = null;
$is_free = $nar_systemsettings['MARKTPLATZ']['FREE_JOBS'];
if ($id_job > 0) {
	// Kostenloser artikel?
	$is_free = $db->fetch_atom("SELECT B_FREE FROM `job` WHERE ID_JOB=".$id_job);
	// Zugeordnetes Paket holen
	$order = $packets->order_find("job", $id_job);
	if ($order != null) {
		$id_order = $order->getOrderId();
	}

	$oldjob = $jobs->fetchByJobId($id_job);
}

if (!empty($_POST)) {
    if (($_REQUEST["FK_PACKET_ORDER"] > 0) && ($_REQUEST["FK_PACKET_ORDER"] != $id_order)) {
        // (Neues) Paket gewählt
        $id_packet_order = (int)$_REQUEST["FK_PACKET_ORDER"];
        $order = $packets->order_get($id_packet_order);		// Paket des Benutzers auslesen
    }

    if ($is_free) {
		$_POST["B_FREE"] = 1;
        $id_job = $jobs->saveJobMultiLang($_POST);
        if($id_job) {
			informAdmin($_POST, $oldjob);
            die(forward("/my-pages/my-jobs-edit,".$id_job.",.htm"));
        } else {
            // News-Kontingent in diesem Paket verbraucht
            $tpl_content->addvar("errors", "Bitte Anzeigenpaket wählen!");
        }
    } elseif($order != null) {
		$_POST["B_FREE"] = 0;
        if ($id_job = $jobs->saveJobMultiLang($_POST)) {
			informAdmin($_POST, $oldjob);

            if (!$order->isUsed("jobs", $id_job)) {
                // Wurde noch nicht zugeordnet!
                if ($order->isAvailable("jobs", 1)) {
                    // Weiterer News-Artikel verfügbar
                    $order->itemAddContent("jobs", $id_job);

                    die(forward("/my-pages/my-jobs.htm"));
                } else {
                    // News-Kontingent in diesem Paket verbraucht
                    $tpl_content->addvar("errors", "Bitte Anzeigenpaket wählen!");
                }
            }

            die(forward("/my-pages/my-jobs.htm"));
        } else {
            $tpl_content->addvar("errors", "Nicht alle erforderlichen Felder sind ausgefüllt!");
        }
    }

    $_REQUEST["FK_PACKET_ORDER"] = false;
   	$tpl_content->addvars($_REQUEST);
}

// Vorhandene Sprachen auslesen
$ar_lang = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
$ar_strings = $ar_lang;
if ($id_job > 0) {
	$ar_strings = $db->fetch_table("
		SELECT
			l.*, s.*
		FROM lang l
		LEFT JOIN `string_job` s ON
			s.FK=".$id_job." AND s.S_TABLE='job' AND s.BF_LANG=l.BITVAL WHERE l.B_PUBLIC = 1");
	$ar_artikel = $jobs->fetchByJobId($id_job);
	if (is_array($ar_artikel)) {
		$tpl_content->addvars($ar_artikel);
	}
}

// Liste der Sprachen ausgeben
$tpl_content->addlist("liste_header_link", $ar_lang, "tpl/".$s_lang."/my-jobs-edit.header_link.htm");
$tpl_content->addlist("liste_body_link", $ar_lang, "tpl/".$s_lang."/my-jobs-edit.body_link.htm");
// Inhalte der Sprachen ausgeben
$tpl_content->addlist("liste_header_content", $ar_strings, "tpl/".$s_lang."/my-jobs-edit.header_content.htm");
$tpl_content->addlist("liste_body_content", $ar_strings, "tpl/".$s_lang."/my-jobs-edit.body_content.htm");

// Pakete
// Liste der verfügbaren Pakete ausgeben
$ar_required = array(PacketManagement::getType("job_once") => 1);
$ar_required_abo = array(PacketManagement::getType("job_abo") => 1);
$ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));
if (count($ar_packets) == 1) {
    $tpl_content->addvar("FK_PACKET_ORDER", $ar_packets[0]["ID_PACKET_ORDER"]);
}

$tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/my-jobs-edit.row_packet.htm");

// Themen und Bilder auflisten
$ar_thema = array();
$ar_img = array();
$res = $db->querynow("select t.*, s.V1, s.V2, s.T1 from `kat` t
  left join string_kat s
   on s.S_TABLE='kat'
    and s.FK=t.ID_KAT
	and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
  where t.ROOT=6 and t.LFT > 1
  order by t.LFT
  ");
while($row = mysql_fetch_assoc($res['rsrc'])) {
	$sel = ($row['ID_KAT'] == $ar_artikel['FK_KAT'] ? true : false);
	$ar_thema[] = '<option value="'.$row['ID_KAT'].'"'.($sel ? ' selected' : '').'>'.stdHtmlentities($row['V1']).'</option>';
	if($row['IMG'])
	{
		$ar_img[] = "bilder[".$row['ID_KAT']."] = '".$row['ID_KAT'].".jpg';";
	}
} // while themen

// Prüfung ob Profil ausgefüllt
if (empty($user["VORNAME"]) || empty($user["NACHNAME"]) || empty($user["STRASSE"]) || empty($user["PLZ"]) || empty($user["ORT"])) {
	$tpl_content->addvar("error_noaddress", 1);
	if (empty($user["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
	if (empty($user["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
	if (empty($user["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
	if (empty($user["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
	if (empty($user["ORT"])) $tpl_content->addvar("error_addr_city", 1);
}

$tpl_content->addvar('themen', implode("\n", $ar_thema));
$tpl_content->addvar("ar_bild", implode("\n", $ar_img));
$tpl_content->addvar("FREE_JOBS", $is_free);

?>
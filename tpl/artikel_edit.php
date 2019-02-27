<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

require_once 'sys/lib.article.php';
$articles = ArticleManagement::getInstance($db);

function informAdmin($newsdata, $oldNews) {
	global $jobs;

	$informAdmin = FALSE;
	if($oldNews != NULL) {
		$informAdmin = ($oldNews['OK'] == 0);
	}

	if(($newsdata['ID_JOB'] == 0 OR $informAdmin) && $newsdata['FREIGABE'] == 1) {
		$mailData = array(
			'TASK_NEWS' => 1,
			'NEWS_NAME' => $newsdata['V1']
		);
		sendMailTemplateToUser(0, 0, 'ADMIN_NEW_TASK', $mailData);
	}
}

$id_artikel = ($_REQUEST['ID_NEWS'] ? (int)$_REQUEST['ID_NEWS'] : (int)$ar_params[1]);
$id_order = 0;
$order = null;
$is_free = $nar_systemsettings['MARKTPLATZ']['FREE_NEWS'];
if ($id_artikel > 0) {
	// Kostenloser artikel?
	$is_free = $db->fetch_atom("SELECT B_FREE FROM `news` WHERE ID_NEWS=".$id_artikel);
	// Zugeordnetes Paket holen
	$order = $packets->order_find("news", $id_artikel);
	if ($order != null) {
		$id_order = $order->getOrderId();
	}

	$oldnews = $articles->fetchByArticleId($id_artikel);
	if ($oldnews["FK_USER"] != $uid) {
		die(forward($tpl_content->tpl_uri_action("my_artikel")));
	}
}

if (!empty($_POST)) {
	if (($_REQUEST["FK_PACKET_ORDER"] > 0) && ($_REQUEST["FK_PACKET_ORDER"] != $id_order)) {
		// (Neues) Paket gewählt
		$id_packet_order = (int)$_REQUEST["FK_PACKET_ORDER"];
		$order = $packets->order_get($id_packet_order);		// Paket des Benutzers auslesen
	}
	if ( $_POST["STREET"] != "" || $_POST["ZIP"] != "" || $_POST["CITY"] != "" || $_POST["FK_COUNTRY"] != "" ) {
		$mapsLanguage = $s_lang;

		$q_country = 'SELECT s.V1
					FROM country c
					INNER JOIN string s
					ON c.ID_COUNTRY = '.$_POST["FK_COUNTRY"].'
					AND s.S_TABLE = "COUNTRY"
					AND s.FK = c.ID_COUNTRY
					INNER JOIN lang l
					ON l.ABBR = "'.$s_lang.'"
					AND s.BF_LANG = l.BITVAL';

		$geoCoordinates = Geolocation_Generic::getGeolocationCached(
			$_POST["STREET"],
			$_POST["ZIP"],
			$_POST["CITY"],
			$db->fetch_atom($q_country),
			$mapsLanguage
		);
		$_POST["LATITUDE"] = $geoCoordinates['LATITUDE'];
		$_POST["LONGITUDE"] = $geoCoordinates['LONGITUDE'];
	}
    if ($is_free) {
		$_POST["B_FREE"] = 1;
        if ($id_artikel = $articles->saveArticleMultiLang($_POST)) {
			informAdmin($_POST, $oldnews);
            die(forward("/my-pages/artikel_edit,".$id_artikel.",.htm"));
        } else {
            $tpl_content->addvar("errors", "Nicht alle erforderlichen Felder sind ausgefüllt!");
        }
    } elseif ($order != null) {
    	$_POST["B_FREE"] = 0;
		// Paket gefunden!
		if ($id_artikel = $articles->saveArticleMultiLang($_POST)) {
			informAdmin($_POST, $oldnews);

			if (!$order->isUsed("news", $id_artikel)) {
				// Wurde noch nicht zugeordnet!
				if ($order->isAvailable("news", 1)) {
					// Weiterer News-Artikel verfügbar
					$order->itemAddContent("news", $id_artikel);
					die(forward("/my-pages/artikel_edit,".$id_artikel.".htm"));
				} else {
					// News-Kontingent in diesem Paket verbraucht
					$tpl_content->addvar("errors", "Bitte Anzeigenpaket wählen!");
				}
			} else {
				// Bereits zugeordnet
				die(forward("/my-pages/artikel_edit,".$id_artikel.".htm"));
			}
		} else {
			$tpl_content->addvar("errors", "Nicht alle erforderlichen Felder sind ausgefüllt!");
		}
	}
	// Paketauswahl (erneut) anzeigen
	$_REQUEST["FK_PACKET_ORDER"] = false;
	$tpl_content->addvars($_REQUEST);
}

// Vorhandene Sprachen auslesen
$ar_lang = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
$ar_strings = $ar_lang;
if ($id_artikel > 0) {
	$newsManagement = Api_NewsManagement::getInstance($db);
	$ar_strings = $db->fetch_table("
		SELECT
			l.*, s.*
		FROM lang l
		LEFT JOIN `news` n ON n.ID_NEWS=".$id_artikel."
		LEFT JOIN `string_c` s ON s.FK=n.ID_NEWS AND s.S_TABLE='news' AND 
			s.BF_LANG=if(n.BF_LANG_C & l.BITVAL, l.BITVAL, 1 << floor(log(n.BF_LANG_C+0.5)/log(2))) 
		WHERE l.B_PUBLIC = 1");
	$ar_artikel = $articles->fetchByArticleId($id_artikel);
	if (is_array($ar_artikel)) {
		$ar_artikel["FK_PACKET_ORDER"] = $id_order;
		$tpl_content->addvars($ar_artikel);
	}
	foreach ($ar_strings as $stringIndex => $stringDetails) {
		$mediaPreview = $newsManagement->getPreviewElement($ar_artikel, $stringDetails["BITVAL"]);
		$arMediaList = array();
		$arMediaListTpl = array();
		if (is_array($ar_artikel)) {
			$arMediaList = $newsManagement->getMediaList($ar_artikel, $stringDetails["BITVAL"]);
		}
		if ($stringDetails["BITVAL"] != $stringDetails["BF_LANG"]) {
			$baseUrlSource = false;
			$baseUrlTarget = (!empty($stringDetails["BASE_URL"]) ? $stringDetails["BASE_URL"] : false);
			foreach ($GLOBALS["lang_list"] as $langCurAbbr => $langDetails) {
				if (!empty($langDetails["BASE_URL"]) && ($langDetails["BITVAL"] == $stringDetails["BF_LANG"])) {
					$baseUrlSource = $langDetails["BASE_URL"];
				}
			}
			if (($baseUrlSource !== false) && ($baseUrlTarget !== false)) {
				// Replace links in description source
				$ar_strings[$stringIndex]["T1"] = str_replace($baseUrlSource."cache/", $baseUrlTarget."cache/", $stringDetails["T1"]);
			}
		}
		foreach ($arMediaList as $mediaIndex => $mediaDetails) {
			if ($stringDetails["BITVAL"] != $stringDetails["BF_LANG"]) {
				$mediaDetails["PATH"] = str_replace($baseUrlSource."cache/", $baseUrlTarget."cache/", $mediaDetails["PATH"]);
			}
			if ($mediaDetails["PATH"] == $mediaPreview) {
				$mediaDetails["SELECTED"] = 1;
			}
			$mediaTemplate = new Template("tpl/".$s_lang."/artikel_edit.row_media.htm");
			$mediaTemplate->addvars($mediaDetails);
			$arMediaListTpl[] = $mediaTemplate;
		}
		$ar_strings[$stringIndex]["MEDIA_LIST"] = $arMediaListTpl;
	}
}

// Liste der verfügbaren Pakete ausgeben
$ar_required = array(PacketManagement::getType("news_once") => 1);
$ar_required_abo = array(PacketManagement::getType("news_abo") => 1);
$ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));
$tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/artikel_edit.row_packet.htm");
if (count($ar_packets) == 1) {
    $tpl_content->addvar("FK_PACKET_ORDER", $ar_packets[0]["ID_PACKET_ORDER"]);
}
// Liste der Sprachen ausgeben
$tpl_content->addlist("liste_header_link", $ar_lang, "tpl/".$s_lang."/artikel_edit.header_link.htm");
$tpl_content->addlist("liste_body_link", $ar_lang, "tpl/".$s_lang."/artikel_edit.body_link.htm");
// Inhalte der Sprachen ausgeben
$tpl_content->addlist("liste_header_content", $ar_strings, "tpl/".$s_lang."/artikel_edit.header_content.htm");
$tpl_content->addlist("liste_body_content", $ar_strings, "tpl/".$s_lang."/artikel_edit.body_content.htm");

// Themen und Bilder auflisten
$ar_thema = array();
$ar_img = array();
$res = $db->querynow("select t.*, s.V1, s.V2, s.T1 from `kat` t
  left join string_kat s
   on s.S_TABLE='kat'
    and s.FK=t.ID_KAT
	and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
  where t.ROOT=2 and t.LFT > 1
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

// Links auflisten
$ar_links = array();
if (!empty($ar_artikel['LINKS'])) {
    $domContent = new DOMDocument();
    $domContent->loadHTML("<html><body>".$ar_artikel['LINKS']."</body></html>");
    $domListLinks = $domContent->getElementsByTagName("a");
    /**
     * Add present links to form
     * @var DOMElement $domLink
     */
    foreach ($domListLinks as $linkIndex => $domLink) {
        $linkHref = $domLink->getAttribute("href");
        $linkLabel = trim($domLink->textContent);
        if (!empty($linkLabel)) {
            $ar_links[] = array(
                "href"  => $linkHref,
                "label" => $linkLabel
            );
            $tpl_content->addvar("LINK_".$linkIndex."_HREF", $linkHref);
            $tpl_content->addvar("LINK_".$linkIndex."_LABEL", $linkLabel);
        }
    }

}

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
$tpl_content->addvar("FREE_NEWS", ($is_free ? 1 : 0));

?>
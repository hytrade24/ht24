<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path.'sys/lib.ad_variants.php';
require_once $ab_path.'sys/lib.ad_payment_adapter.php';

//////////////// IMENSO //////////////////////
if(file_exists('./vendor/autoload.php')) 
{
	require_once('./vendor/autoload.php');
}
use \Statickidz\GoogleTranslate;
function translate_api($source,$target,$text)
{
	$trans = new GoogleTranslate();
	$result = $trans->translate($source, $target, $text);
	return $result;
}
# Field template configuration
$arFieldTemplates = array(
    "FK_MAN"    => "marktplatz_anzeige.group.manufacturer.htm"
);

#$SILENCE=false;
function killbb(&$row,$i)
{
	//$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
	$row['BESCHREIBUNG'] = substr(strip_tags($row['BESCHREIBUNG']), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
	$row['BESCHREIBUNG'] = str_replace("&nbsp;", ' ', $row['BESCHREIBUNG']);
	$row['BESCHREIBUNG'] = str_replace("&nbsp", ' ', $row['BESCHREIBUNG']);

}

function add_fields(&$row) {
    global $article_data, $db, $langval;
	if (isset($article_data[$row["F_NAME"]]) && (!$row["IS_SPECIAL"])) {
		$row["IS_SET"] = 1;
		$row["TYPE_".$row["F_TYP"]] = 1;

		if ($row["F_TYP"] == "LIST") {
			if ($article_data[$row["F_NAME"]] == 0) {
				$row["IS_SET"] = 0;
			} else {
				$row["VALUE"] = $db->fetch_atom("
						SELECT V1
						FROM liste_values l
							LEFT JOIN string_liste_values s ON
							s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
							s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
						WHERE l.FK_LISTE=".$row["FK_LISTE"]." AND l.ID_LISTE_VALUES=".$article_data[$row["F_NAME"]]);
			}
		} else if (($row["F_TYP"] == "MULTICHECKBOX") || ($row["F_TYP"] == "MULTICHECKBOX_AND")) {
			$str_values = trim($article_data[$row["F_NAME"]], "x");

			if($str_values != "") {
				$ar_values = explode("x", $str_values);

				$ar_names = $db->fetch_nar("
						SELECT sl.V1 FROM `liste_values` l
						LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
							AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
						WHERE l.ID_LISTE_VALUES IN (".mysql_real_escape_string(implode(", ", $ar_values)).")
						ORDER BY l.ORDER ASC");
				$row["VALUE"] = implode(", ", array_keys($ar_names));
			} else {
				$row["IS_SET"] = 0;
			}
		} else if ($row["F_TYP"] == "HTMLTEXT") {
            $row['IS_SET'] = 0;
            $row["VALUE"] = $article_data[$row["F_NAME"]];
        } else {
			$row["VALUE"] = $article_data[$row["F_NAME"]];

			if($row["VALUE"] == "") {
				$row["IS_SET"] = 0;
			}
		}
	}
}

function variantFields(&$row) {
	global $variants, $s_lang, $id_article_variant;
	$row["liste"] = array();
	$ar_fields = $variants->getVariantFieldsById($id_article_variant);
	foreach ($row["values"] as $index => $ar_value) {
		$_tmp = new Template("tpl/".$s_lang."/marktplatz_anzeige.variant.row.htm");
		$_tmp->addvars($ar_value);
		if ($ar_fields[ $row['F_NAME'] ] == $ar_value["ID_LISTE_VALUES"]) {
			$_tmp->addvar("checked", true);
		}
		$row["liste"][] = $_tmp->process(false);
	}
}

if($ar_params[3] == 'kontakt')
{
	$tpl_content->addvar("kontaktnow", 1);
}

global $id_kat, $variants, $id_article_variant;

require_once("sys/lib.bbcode.php");
require_once("sys/lib.ad_like.php");
$bbcode = new bbcode();
$variants = AdVariantsManagement::getInstance($db);

$id_article = ($ar_params[1] ? (int)$ar_params[1] : ($_REQUEST["ID_ANZEIGE"] ? (int)$_REQUEST["ID_ANZEIGE"] : 0));

$show_info = $ar_params[3];
//$id_kat = ($ar_params[2] ? (int)$ar_params[2] : ($_REQUEST["ID_KAT"] ? (int)$_REQUEST["ID_KAT"] : 0));
$userIsAdmin = $db->fetch_atom("SELECT count(*) FROM `role2user` ru JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$uid." WHERE r.LABEL='Admin'");

if ($userIsAdmin && ($_REQUEST['decline'] > 0)) {
    $id_article = (int)$_REQUEST["decline"];
    $arAd = $db->fetch1("SELECT AD_TABLE, PRODUKTNAME FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
    $kat_table = $arAd["AD_TABLE"];
    include_once $ab_path."sys/lib.ads.php";
    AdManagment::UnlockDecline($id_article, $kat_table, $_REQUEST["REASON"]);
    die(forward($tpl_content->tpl_uri_action("marktplatz_anzeige,".$id_article.",".chtrans($arAd["PRODUKTNAME"]).",declined")));
}
if (isset($_REQUEST["ajax"])) {
    if ($_REQUEST["ajax"] == "availability_multi_setup") {
        $_SESSION['calendar_settings'] = array(
            'ads' => $_POST['ads']
        );
        header('Content-type: application/json');
        die(json_encode(array('success' => true)));
    }
	if ($_REQUEST["ajax"] == "variant_details") {
		$ar_variant = $variants->getAdVariantDetailsByAd($id_article, $_POST);
		$ar_variants = $variants->getAdVariantFieldsById($id_article);
		$id_article_variant = $ar_variant["ID_AD_VARIANT"];
		// Get images
		$article_images = array();
		$arVariantFields = $variants->getVariantFieldsById($id_article_variant);
		$arVariantJoins = array();
		$arVariantWhere = array();
		foreach ($ar_variants as $variantIndex => $arVariantField) {
			$variantFieldName = $arVariantField["F_NAME"];
			$variantJoinIdent = "iv".$variantIndex;
			$arVariantJoins[] = "LEFT JOIN `ad_images_variants` ".$variantJoinIdent." ON ".$variantJoinIdent.".ID_IMAGE=i.ID_IMAGE".
				" AND ".$variantJoinIdent.".ID_FIELD_DEF=".(int)$arVariantField["ID_FIELD_DEF"];
			$arVariantWhere[] = "(".$variantJoinIdent.".ID_LISTE_VALUE IS NULL OR ".$variantJoinIdent.".ID_LISTE_VALUE=".(int)$arVariantFields[$variantFieldName].")";
		}
	
		$article_images = $db->fetch_table("
			SELECT i.* FROM `ad_images` i
			".implode("\n		", $arVariantJoins)."
			WHERE i.FK_AD=".$id_article.(!empty($arVariantWhere) ? " AND ".implode(" AND ", $arVariantWhere) : "")."
			ORDER BY i.IS_DEFAULT DESC, i.ID_IMAGE ASC");
        foreach ($article_images as $imageIndex => $arImage) {
            $arImageMeta = (!empty($arImage["SER_META"]) ? unserialize($arImage["SER_META"]) : array());
            $article_images[$imageIndex] = array_merge($arImage, array_flatten($arImageMeta, true, "_", "META_"));
        }
        // Get videos
		$article_videos = $db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$id_article);
		// Render image/video view
		$tplImages = new Template("tpl/".$s_lang."/marktplatz_anzeige.images.htm");
		$tplImages->addvar("ID_AD", $id_article);
		if (count($article_images) > 0) {
			$first_image = array_shift($article_images);
			$tplImages->addvar("product_image", $first_image["SRC"]);
			if (count($article_images) > 0) {
				$tplImages->addlist("product_images", $article_images, "tpl/".$s_lang."/marktplatz_images.row.htm");
			}
		}
		if (count($article_videos) > 0) {
			$tplImages->addlist("product_videos", $article_videos, "tpl/".$s_lang."/marktplatz_videos.row.htm");
		}

		header('Content-type: application/json');
		if ($ar_variant["MENGE"] > 0) {
			die(json_encode(array(
				"ID_AD_VARIANT"	=> $ar_variant["ID_AD_VARIANT"],
				"MENGE" 		=> (int)$ar_variant["MENGE"],
				"PREIS" 		=> sprintf("%0.2f", $ar_variant["PREIS"]),
				"IMAGES"		=> $tplImages->process(true),
				"LINK_TRADE"	=> $tpl_content->tpl_uri_action("marktplatz_handeln,".$id_article.",".$ar_variant["ID_AD_VARIANT"]),
				"LINK_BUY"		=> $tpl_content->tpl_uri_action("marktplatz_kaufen,".$id_article.",".$ar_variant["ID_AD_VARIANT"])
			)));
		} else {
			die(json_encode(array(
				"ID_AD_VARIANT"	=> 0,
				"MENGE" 		=> 0,
				"PREIS" 		=> sprintf("%0.2f", 0),
				"IMAGES"		=> $tplImages->process(true),
				"LINK_TRADE"	=> "",
				"LINK_BUY"		=> ""
			)));
		}
	}
    if ($userIsAdmin && ($_REQUEST["ajax"] == "unlockAd")) {
        require_once $ab_path.'sys/lib.ads.php';
        $kat_table = $db->fetch_atom("SELECT AD_TABLE FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
        header('Content-type: application/json');
        die(json_encode(array(
            "success"   => AdManagment::Unlock($id_article, $kat_table)
        )));
    }
}

$tpl_content->addvar("GOOGLE_API", $nar_systemsettings['SITE']['GOOGLE_API']);

if($id_kat)
{
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
}
else
{
	$ar_kat_table = $db->fetch1("SELECT AD_TABLE, FK_KAT, FK_USER, STATUS, DELETED FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
	$kat_table = $ar_kat_table['AD_TABLE'];
	$id_kat = $ar_kat_table['FK_KAT'];
    if (($ar_kat_table == false) || ($ar_kat_table["DELETED"] == 1)) {
        // Anzeige nicht gefunden!
        header("HTTP/1.0 404 Not Found");
        $tpl_content->addvar("not_found", 1);
        return;
    } else if ((($ar_kat_table["STATUS"]&3) != 1) && ($ar_kat_table["FK_USER"] != $uid)) {
        if (!$userIsAdmin && ($ar_kat_table["CONFIRMED"] != 1)) {
            header("HTTP/1.0 404 Not Found");
            $tpl_content->addvar("not_found", 1);
            return;
        } else {
            // Anzeige nicht mehr online!
            $tpl_content->addvar("not_online", 1);
        }
    }
}

$article = Api_Entities_MarketplaceArticle::getById($id_article);
if (!$article instanceof Api_Entities_MarketplaceArticle) {
	header("HTTP/1.0 404 Not Found");
	$tpl_content->addvar("not_found", 1);
	return;
}

if ($show_info == "extern") {
	Tools_UserStatistic::getInstance()->log_data($id_article, "ad_master", "CLICK");
	die(forward( $article->getData_ArticleMaster("AFFILIATE_LINK") ));
} else {
	Tools_UserStatistic::getInstance()->log_data($id_article, "ad_master", "VIEW");
}

// NEU
$article_data = $db->fetch1("SELECT *, DATEDIFF(NOW(),STAMP_START) as AD_RUNTIME_DAYS_GONE, DATEDIFF(STAMP_END,NOW()) as DAYS_LEFT FROM `".$kat_table."` WHERE ID_".strtoupper($kat_table)."=".$id_article);
$article_data_master = $article->getData_ArticleMaster();
$tpl_content->addvar('AD_RUNTIME_DAYS_GONE',$article_data["AD_RUNTIME_DAYS_GONE"]);
$tpl_content->addvar("DAYS_ADS_NEW", $nar_systemsettings["MARKTPLATZ"]["DAYS_ADS_NEW"]);

// <-- WATCHLIST SETUP
$tpl_main->addvar("WATCHLIST_REF_TYPE", "ad_master");
$tpl_main->addvar("WATCHLIST_REF_FK", $id_article);
$tpl_main->addvar("WATCHLIST_TITLE", $article_data["PRODUKTNAME"]);
// WATCHLIST SETUP -->

if ($nar_systemsettings['MARKTPLATZ']['ENABLE_RENT'] && ($article_data["VERKAUFSOPTIONEN"] == 3) && !empty($article_data["MIETPREISE"])) {
	$arMietpreise = unserialize($article_data["MIETPREISE"]);
	$arMietpreiseList = array();
	$arMietpreiseRuntimes = Api_LookupManagement::getInstance($db, $langval)->readByArt("VERMIETEN");
	foreach ($arMietpreiseRuntimes as $indexRuntime => $arRuntime) {
		if (array_key_exists($arRuntime["ID_LOOKUP"], $arMietpreise) && ($arMietpreise[$arRuntime["ID_LOOKUP"]] > 0)) {
			$arMietpreiseList[] = array_merge($arRuntime, array(
				"PRICE"	=> $arMietpreise[$arRuntime["ID_LOOKUP"]]
			));
		}
	}
	$tpl_content->addlist("list_rent", $arMietpreiseList, "tpl/".$s_lang."/marktplatz_anzeige.row_rent.htm");
}
$id_article_variant = $article_data_master['FK_AD_VARIANT'];
$tpl_content->addvar("USER_IS_ADMIN", $userIsAdmin);
$article_data["CONFIRMED"] = $article_data_master['CONFIRMED'];
$tpl_content->addvar("PRODUCT_OWNER_USER_ID",$article_data["FK_USER"]);
$tpl_content->addvar("LOGGED_USER_ID",$uid);
if ($article_data_master['CONFIRMED'] == 2) {
    $article_data["DECLINE_REASON"] = $article_data_master['DECLINE_REASON'];
}
$ar_variants = $variants->getAdVariantFieldsById($id_article);
$tpl_content->addlist("VARIANTS", $ar_variants, 'tpl/'.$s_lang.'/marktplatz_anzeige.variant.htm', 'variantFields');
$field_data = $group_ids = array();
$group_ids[] = 0;
$res = $db->querynow($a = "SELECT
		f.ID_FIELD_DEF,
		f.FK_TABLE_DEF,
		f.F_TYP,
		f.FK_LISTE,
		f.F_NAME,
		f.IS_SPECIAL,
		f.FK_FIELD_GROUP,
		kf.B_NEEDED,
		sf.V1,
		sf.V2
    FROM `kat2field` kf
      LEFT JOIN `field_def` f ON f.ID_FIELD_DEF = kf.FK_FIELD
      LEFT JOIN
      	field2group f2g ON f.ID_FIELD_DEF=f2g.FK_FIELD_DEF
      LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=kf.FK_FIELD
            AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
    WHERE kf.FK_KAT=".$id_kat." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
    GROUP BY f.ID_FIELD_DEF
    ORDER BY
		f.FK_FIELD_GROUP ASC,
		f.F_ORDER ASC");
$htmlDescFields = array();
if ($nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']) {
	$field_data[] = array(
        "F_NAME"    => "FK_MAN",
		"IS_SET" 	=> true,
		"V1"		=> Translation::readTranslation("marketplace", "manufacturer", null, array(), "Hersteller"),
        "FK_MAN"    => $article_data_master["FK_MAN"],
		"VALUE"		=> $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$article_data_master["FK_MAN"])
	);
}
while($row = mysql_fetch_assoc($res['rsrc']))
{
	add_fields($row);
	$field_data[] = $row;
    if (($row['F_TYP'] == "HTMLTEXT") && !empty($row["VALUE"])) {
        $htmlDescFields[] = $row;
    }
	if($row['FK_FIELD_GROUP'])
	{
		$group_ids[$row['FK_FIELD_GROUP']] = $row['FK_FIELD_GROUP'];
	}
}
$f_groups = $f_counter = array();
for($i=0; $i<count($field_data); $i++)
{
	if (($field_data[$i]['IS_SPECIAL'] > 0) || ($field_data[$i]['F_TYP'] == "VARIANT")) {
		continue;
	}
	if(!$field_data[$i]['FK_FIELD_GROUP'])
	{
		$field_data[$i]['FK_FIELD_GROUP'] = 0;
	}

	if(isset($field_data[$i]['VALUE']))
	{
		if($field_data[$i]['F_TYP'] == 'DATE')
		{
			$field_data[$i]['VALUE'] = date('d.m.Y', strtotime($field_data[$i]['VALUE']));
		}
		#echo "value: ".$field_data[$i]['VALUE']."<br>";
		$f_counter[$field_data[$i]['FK_FIELD_GROUP']]++;
		$field_data[$i]['COUNTER']++;
	}

    if (array_key_exists($field_data[$i]["F_NAME"], $arFieldTemplates)) {
        $tplValue = new Template("tpl/".$GLOBALS["s_lang"]."/".$arFieldTemplates[ $field_data[$i]["F_NAME"] ]);
        $tplValue->addvars($field_data[$i]);
        $field_data[$i]["VALUE_TPL"] = $tplValue->process();
    }

	$f_groups[$field_data[$i]['FK_FIELD_GROUP']][] = $field_data[$i];
}

#echo ht(dump($field_data));
$group_liste = $db->querynow("select t.ID_FIELD_GROUP, s.V1
	from
		`field_group` t
	left join
		string_app s on s.S_TABLE='field_group'
		and s.FK=t.ID_FIELD_GROUP
		and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	where
		ID_FIELD_GROUP IN (".implode(",", $group_ids).")
	ORDER BY t.F_ORDER
	");

$_tmp = null;
$tmp = array();
if (!empty($f_groups[0])) {
	if ( $show_info == "print" ) {
		$_tmp = new Template("tpl/".$s_lang."/marktplatz_anzeige.print.group.htm");
		// TODO: Row-Template umbenennen
		$_tmp->addlist_fast('liste', $f_groups[0], 'tpl/'.$s_lang.'/marktplatz_anzeige.print.group.row.htm');
	}
	else {
		$_tmp = new Template("tpl/".$s_lang."/marktplatz_anzeige.group.htm");
		// TODO: Row-Template umbenennen
		$_tmp->addlist_fast('liste', $f_groups[0], 'tpl/'.$s_lang.'/marktplatz_anzeige.group.row.htm');
	}
	$tmp[] = $_tmp;
}

$allgemein_angaben = array();
while($row = mysql_fetch_assoc($group_liste['rsrc']))
{
	if($f_counter[$row['ID_FIELD_GROUP']] <1)
	{
		continue;
	}
	#echo ht(dump($f_groups[$row['ID_FIELD_GROUP']]));
	if ( $show_info == "print" ) {
		$_tmp = new Template("tpl/".$s_lang."/marktplatz_anzeige.print.group.htm");
		$_tmp->addvars($row);
		// TODO: Row-Template umbenennen
		$_tmp->addlist_fast('liste', $f_groups[$row['ID_FIELD_GROUP']], 'tpl/'.$s_lang.'/marktplatz_anzeige.print.group.row.htm');
	}
	else {
		$_tmp = new Template("tpl/".$s_lang."/marktplatz_anzeige.group.htm");
		$_tmp->addvars($row);
		// TODO: Row-Template umbenennen
		$_tmp->addlist_fast('liste', $f_groups[$row['ID_FIELD_GROUP']], 'tpl/'.$s_lang.'/marktplatz_anzeige.group.row.htm');
	}
	$tmp[] = $_tmp;
}

$tpl_content->addvar("product_fields", $tmp);

$ar_kat = $db->fetch1("SELECT s.V1, s.T1
    FROM `kat` k
      LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
        AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
    WHERE k.ID_KAT=".$id_kat."");
$article_kat = $ar_kat["V1"];
list($article_kat_keywords, $article_kat_meta) = explode("||||", $ar_kat["T1"]);

### wofür? |< schmalle 9.2.2010
//$article_temp = $db->fetch1("SELECT * FROM `ad_temp` WHERE FK_AD=".$id_article);

$article_tpl = array_flatten($article_data, true, "_", "AD_");
// Category options
$ar_kat_details = $db->fetch1("SELECT * FROM `kat` WHERE ID_KAT=".$id_kat);
$tpl_main->addvar("KAT_SEL_LFT", $ar_kat_details["LFT"]);
$tpl_main->addvar("KAT_SEL_RGT", $ar_kat_details["RGT"]);
$b_sales = $ar_kat_details["B_SALES"];
$kat_options = unserialize($ar_kat_details["SER_OPTIONS"]);
if ($kat_options === false) {
	$kat_options = array();
}
$article_tpl = array_merge($article_tpl, array_flatten($kat_options, "both", "_", "KAT_OPTIONS_"));

// Ids
$article_tpl["ID_AD"] = $id_article;
$article_tpl["ID_AD_VARIANT"] = $id_article_variant;
$article_tpl["ID_KAT"] = $article_data["FK_KAT"];
$article_tpl["FK_KAT"] = $article_data["FK_KAT"];
// Hersteller
$arMan = $db->fetch1("SELECT * FROM `manufacturers` WHERE ID_MAN=".(int)$article_data["FK_MAN"]);
if (is_array($arMan)) {
    $article_tpl["AD_MANUFACTURER"] = $arMan["NAME"];
    $article_tpl["AD_MANUFACTURER_URL"] = $arMan["URL"];
}
// Anzeigentitel / Produktname
$article_tpl["AD_PRODUCT"] = $db->fetch_atom("
    SELECT s.V1
    FROM product p
    LEFT JOIN string_product s
        ON s.S_TABLE='product' and s.FK=p.ID_PRODUCT
        and s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    WHERE p.ID_PRODUCT=".(int)$article_data["FK_PRODUCT"]);
$article_tpl["AD_TITLE"] = $article_data["PRODUKTNAME"];
// Status
$article_tpl["AD_SOLD"] = (($article_data["STATUS"]&4)==4 ? true : false);
// Beschreibung

//////////////// IMENSO //////////////////////
$article_tpl["AD_DESCRIPTION"] = ($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0 ? nl2br($article->getDescriptionText()) : $article->getDescriptionHtml());

if(file_exists('./vendor/autoload.php')) 
{
	if($s_lang == 'en' )
	{
		$AD_DESCRIPTION_temp =str_replace("&Oslash","XYZ1ABC2XYZ",$article_tpl["AD_DESCRIPTION"]);
		$AD_DESCRIPTION_temp = translate_api('de','en',$AD_DESCRIPTION_temp);
		$AD_DESCRIPTION_temp = str_replace("XYZ1ABC2XYZ","&Oslash",$AD_DESCRIPTION_temp);
		$article_tpl["AD_DESCRIPTION"] = $AD_DESCRIPTION_temp ;
		$article_tpl["AD_TITLE"] = translate_api('de','en',$article_tpl["AD_TITLE"]);
	}
}

// Standort
$article_tpl["AD_COUNTRY"] = $db->fetch_atom("SELECT V1 FROM string WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$article_data["FK_COUNTRY"]);
// Marktplatz einstellungen
$article_tpl = array_merge($article_tpl, array_flatten($nar_systemsettings["MARKTPLATZ"], true, "_", "SETTINGS_MARKTPLATZ_"));


// TODO: Schönere Lösung implementeren
$meta_description = trim($article_tpl["AD_TITLE"])." ".trim(strip_tags($article_data['BESCHREIBUNG']));
if (strlen($meta_description) > 160) {
	// Text kürzen auf 160-200 Zeichen
	$meta_description_len = strrpos(substr($meta_description, 0, 200), " ");
	$meta_description = substr($meta_description, 0, $meta_description_len);
}

if (!empty($meta_description)) {
	if(strpos($article_kat_meta, '<meta name="description"') !== FALSE) {
		$article_kat_meta = preg_replace('/(<meta name="description" content=")(.*)("[^>]*>)/i', '${1}'.$meta_description.'${3}', $article_kat_meta);
	} else {
		$article_kat_meta .= '<meta name="description" content="'.$meta_description.'">';
	}
}


$tpl_main->vars['metatags'] = $article_kat_meta;
/*
 * SETTINGS_MARKTPLATZ_BUYING_ENABLED
 * SETTINGS_MARKTPLATZ_ALLOW_COMMENTS_AD
 * SETTINGS_MARKTPLATZ_USE_CART
 * SETTINGS_MARKTPLATZ_CURRENCY_CONVERSION
$article_tpl = array(
	"buying_enabled"			=> ($nar_systemsettings["MARKTPLATZ"]["BUYING_ENABLED"] ? ($b_sales ? true : false) : false),
	"comments_enabled"			=> $nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_AD"],
    "product_manufacturer"  	=> $db->fetch_atom("SELECT NAME FROM manufacturers
                                                  WHERE ID_MAN=".(int)$article_data["FK_MAN"]),
    "product_manufacturer_web"  => $db->fetch_atom("SELECT URL FROM manufacturers
                                                  WHERE ID_MAN=".(int)$article_data["FK_MAN"]),
    "product_name"  		=> $db->fetch_atom("
    								SELECT
    									s.V1
    								FROM product p
    								LEFT JOIN string_product s
    									ON s.S_TABLE='product' and s.FK=p.ID_PRODUCT
										and s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
                                                  WHERE p.ID_PRODUCT=".(int)$article_data["FK_PRODUCT"]),
    "product_articlename"   => $article_data["PRODUKTNAME"],
    "product_price"         => $article_data["PREIS"],
	"product_pseudoprice"   => $article_data["PSEUDOPREIS"],
	"product_trade"         => $article_data["TRADE"],
  	"product_mwst"			=> $article_data["MWST"],
  	"product_versand"		=> $article_data["VERSANDKOSTEN"],
  	"product_quantity"		=> $article_data["MENGE"],
    "product_sold"			=> (($article_data["STATUS"]&4)==4 ? true : false),
    "product_country"       => $db->fetch_atom("SELECT V1 FROM string
                                                  WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
                                                    FK=".(int)$article_data["FK_COUNTRY"]),
    "product_zip"           => $article_data["ZIP"],
    "product_city"          => $article_data["CITY"],
    "product_street"          => $article_data["STREET"],
    "product_lat"			=> $article_data["LATITUDE"],
    "product_lon"			=> $article_data["LONGITUDE"],
    "product_desc"          => ($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0 ? nl2br($bbcode->parseBB($article_data["BESCHREIBUNG"])) : $article_data["BESCHREIBUNG"]),
    //"product_desc"          => $article_data["BESCHREIBUNG"],
    "product_runtime_left"  => $article_data["DAYS_LEFT"],
	"vk_username"			=> $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$article_data["FK_USER"]),
	"vk_cache"				=> $db->fetch_atom("SELECT CACHE FROM `user` WHERE ID_USER=".$article_data["FK_USER"]),
    "vk_user"				=> $article_data["FK_USER"],
	"agb"					=> $article_data["AD_AGB"],
	"widerruf"				=> $article_data["AD_WIDERRUF"],
	'MWST'					=> $article_data["MWST"],
	'MOQ'					=> $article_data["MOQ"],
	'FK_KAT' 				=> $article_data["FK_KAT"],
	'EAN'					=> $article_data["EAN"]
);
*/

$article_master = $db->fetch1("SELECT
        FK_AD_VARIANT, NOTIZ, VERKAUFSOPTIONEN, VERSANDOPTIONEN, VERSANDKOSTEN, VERSANDKOSTEN_INFO,
        DATEDIFF(NOW(),STAMP_START) as RUNTIME_DAYS,
        B_TOP, BF_CONSTRAINTS, AVAILABILITY, ALLOW_COMMENTS, AFFILIATE, AFFILIATE_IDENTIFIER, AFFILIATE_FK_AFFILIATE, AFFILIATE_IDENTIFIER, AFFILIATE_LINK, AFFILIATE_LINK_CART, AFFILIATE_URL_IMAGE,
        IMPORT_IDENTIFIER, IMPORT_IMAGES
    FROM `ad_master`
    WHERE ID_AD_MASTER=".$id_article);
Rest_MarketplaceAds::extendAdDetailsSingle($article_master);
$article_tpl = array_merge($article_tpl, array_flatten($article_master, true, "_", "AD_"));

if ($article_tpl["FK_AD_VARIANT"] > 0) {
	$article_variant = $variants->getAdVariantDetailsById($article_tpl["FK_AD_VARIANT"]);
	$article_tpl = array_merge($article_tpl, $article_variant);
	$article_tpl["product_quantity"] = $article_tpl["MENGE"];
	$article_tpl["product_price"] = $article_tpl["PREIS"];
}

// Basispreis
if(isset($article_data["BASISPREIS_PREIS"]) && $article_data["BASISPREIS_PREIS"] > 0) {
	$article_tpl["AD_BASISPREIS_EINHEIT"] = $db->fetch_atom("SELECT V1
		FROM liste_values l
		LEFT JOIN string_liste_values s ON
			s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
			s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
		WHERE l.ID_LISTE_VALUES='".$article_data["BASISPREIS_EINHEIT"]."'"
	);
}


#die(ht($article_data['BESCHREIBUNG']));

### Verkäufer Info
$ar_userinfo = $db->fetch1("
	SELECT
		u.FIRMA AS ANBIETER_FIRMA,
		CONCAT(u.VORNAME, ' ', u.NACHNAME) AS ANBIETER_NAME,
		u.STRASSE AS ANBIETER_STRASSE,
		u.PLZ AS ANBIETER_PLZ,
		u.ORT AS ANBIETER_ORT,
		u.UST_ID AS ANBIETER_UMSTG,
        u.CACHE,
		str.V1 AS ANBIETER_COUNTRY,
		DATE_FORMAT(u.STAMP_REG, '%Y') AS STAMP_REG,
		s.V1 AS UGROUP,
        u.TOP_USER as USER_TOP_USER,
        u.TOP_SELLER AS USER_TOP_SELLER,
        u.PROOFED AS USER_PROOFED,
        u.RATING AS USER_RATING
	FROM
		`user` u
	LEFT JOIN
		string_usergroup s
		on s.S_TABLE='usergroup' and s.FK=u.FK_USERGROUP
		and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
	LEFT JOIN
		string str
		on str.S_TABLE='country' and str.FK=u.FK_COUNTRY
		and str.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
	WHERE
		u.ID_USER=".$article_data["FK_USER"]);


include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$ar_userinfo['CACHE']."/".$article_data['FK_USER']."/useroptions.php");
$tpl_content->addvar("showcontact", perm_checkview($useroptions['LU_SHOWCONTAC']));

### handeln
if($uid)
{
	if($uid != $article_data['FK_USER'])
	{
		$ar_bid_own = $db->fetch1("
			SELECT
				*
			FROM
				trade
			WHERE
				FK_USER_FROM=".$uid."
				AND FK_AD=".$id_article."
			ORDER BY
				STAMP_BID DESC
			LIMIT 1");

		if(!empty($ar_bid_own))
		{
			$tpl_content->addvars($ar_bid_own, 'bidown_');
			$tpl_content->addvar("bidown_".$ar_bid_own['BID_STATUS'], 1);
			$ar_bid_seller = $db->fetch1("
				SELECT
					*
				FROM
					trade
				WHERE
					FK_USER_TO=".$uid."
					AND FK_AD=".$id_article."
				ORDER BY
					STAMP_BID DESC
				LIMIT 1");
			if(!empty($ar_bid_seller))
			{
				$tpl_content->addvars($ar_bid_seller, 'bidseller_');
				$tpl_content->addvar("bidseller_".$ar_bid_seller['BID_STATUS'], 1);
			}
		}
	} // nicht die eigene Anzeige
	else
	{
		$ar_bid_user = $db->fetch1("
			SELECT
				*
			FROM
				trade
			WHERE
				FK_USER_TO=".$uid."
				AND FK_AD=".$id_article."
			ORDER BY
				STAMP_BID DESC
			LIMIT 1");
		if(!empty($ar_bid_user))
		{
			$tpl_content->addvars($ar_bid_user, 'biduser_');
			$tpl_content->addvar("biduser_".$ar_bid_user['BID_STATUS'], 1);
			$ar_bid_own = $db->fetch1("
				SELECT
					*
				FROM
					trade
				WHERE
					FK_USER_FROM=".$uid."
					AND FK_AD=".$id_article."
				ORDER BY
					STAMP_BID DESC
				LIMIT 1");
			if(!empty($ar_bid_own))
			{
				$tpl_content->addvars($ar_bid_own, 'bidmy_');
				$tpl_content->addvar("bidmy_".$ar_bid_own['BID_STATUS'], 1);
			}
		}
	}
}
### // handeln


require_once 'sys/lib.ad_rating.php';
$adRatingManagement = AdRatingManagement::getInstance($db);
$ar_userinfo['SCHNITT'] = $adRatingManagement->getRatingByUserId($article_data["FK_USER"]);
$tpl_content->addvars($ar_userinfo);
### // verkäufer

$article_is_new = ($db->fetch_atom("SELECT CRON_DONE FROM `ad_master` WHERE ID_AD_MASTER=".$id_article) === null ? true : false);

if (isset($_REQUEST["preview"])) {
	$tpl_content->addvar("preview", 1);
} else {
	// Info-benachrichtigungen überhalb der Anzeige
	if ($article_data["FK_USER"] == $uid)
		$tpl_content->addvar("eigene", 1);
	if ($article_is_new)
		$tpl_content->addvar("neu", 1);

	if (($article_data["STATUS"] & 3) == 1) {
		$tpl_content->addvar("AD_ACTIVE", 1);
	} else {
		$tpl_content->addvar("inaktiv", 1);
	}
	/**
	 * Info-Text anzeigen?
	 */
	switch ($show_info) {
        default:
            $tpl_content->addvar("info_".$show_info, 1);
            break;
		case 'neu':
			$tpl_content->addvar("neu", 1);
			break;
	}
}

$article_images = array();
if ($id_article_variant > 0) {
	$arVariantFields = $variants->getVariantFieldsById($id_article_variant);
	$arVariantJoins = array();
	$arVariantWhere = array();
	foreach ($ar_variants as $variantIndex => $arVariantField) {
		$variantFieldName = $arVariantField["F_NAME"];
		$variantJoinIdent = "iv".$variantIndex;
		$arVariantJoins[] = "LEFT JOIN `ad_images_variants` ".$variantJoinIdent." ON ".$variantJoinIdent.".ID_IMAGE=i.ID_IMAGE".
			" AND ".$variantJoinIdent.".ID_FIELD_DEF=".(int)$arVariantField["ID_FIELD_DEF"];
		$arVariantWhere[] = "(".$variantJoinIdent.".ID_LISTE_VALUE IS NULL OR ".$variantJoinIdent.".ID_LISTE_VALUE=".(int)$arVariantFields[$variantFieldName].")";
	}

	$article_images = $db->fetch_table("
		SELECT i.* FROM `ad_images` i
		".implode("\n		", $arVariantJoins)."
        WHERE i.FK_AD=".$id_article.(!empty($arVariantWhere) ? " AND ".implode(" AND ", $arVariantWhere) : "")."
		ORDER BY i.IS_DEFAULT DESC, i.ID_IMAGE ASC");
} else {
	$article_images = $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_article." ORDER BY IS_DEFAULT DESC, ID_IMAGE ASC");
}
foreach ($article_images as $imageIndex => $arImage) {
    $arImageMeta = (!empty($arImage["SER_META"]) ? unserialize($arImage["SER_META"]) : array());
    $article_images[$imageIndex] = array_merge($arImage, array_flatten($arImageMeta, true, "_", "META_"));
}
$article_videos = $db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$id_article);
$article_files = $db->fetch_table("SELECT *, LEFT(FILENAME, 20) as FILENAME_SHORT FROM `ad_upload` WHERE FK_AD=".$id_article);

$article_files_free = array();
$article_files_paid = array();

foreach ( $article_files as $article_file  ) {
	if ( $article_file["IS_PAID"] == "0" ) {
		array_push($article_files_free, $article_file);
	}
	else if ( $article_file["IS_PAID"] == "1" ) {
		array_push($article_files_paid, $article_file);
	}
}
//$article_files_paid = $db->fetch_table("SELECT *, LEFT(FILENAME, 20) as FILENAME_SHORT FROM `ad_upload` WHERE FK_AD=".$id_article. " AND IS_FREE=0");

// Artikel ist online
if (count($article_images) > 0) {
	$first_image = array_shift($article_images);
	$tpl_content->addvar("product_image", $first_image["SRC"]);
	$tpl_content->addvar("product_image_title", $first_image["META_TITLE"]);
	$img_dimensions = getimagesize($GLOBALS['ab_path'].substr_replace($first_image["SRC"],'',0,1));
	$tpl_content->addvar("product_image_width",$img_dimensions[0]);
	$tpl_content->addvar("product_image_height",$img_dimensions[1]);
	if (count($article_images) > 0) {
		$tpl_content->addlist("product_images", $article_images, "tpl/".$s_lang."/marktplatz_images.row.htm");
	}
}
if (count($article_videos) > 0) {
	$tpl_content->addlist("product_videos", $article_videos, "tpl/".$s_lang."/marktplatz_videos.row.htm");
}
//$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
if (count($article_files_free) > 0) {
	$tpl_content->addlist("product_files_free", $article_files_free, "tpl/".$s_lang."/marktplatz_files.row.htm");
}
if (count($article_files_paid) > 0) {
	$tpl_content->addlist(
		"product_files_paid",
		$article_files_paid,
		"tpl/".$s_lang."/marktplatz_files.only.name.row.htm"
	);
}

$title = $tpl_main->vars["pagetitle"];
$title .= " - [".trim($article_kat)."] ".trim($article_tpl["AD_MANUFACTURER"].' '.$article_tpl["AD_TITLE"]);

$tpl_main->addvar("pagetitle", $title);
$tpl_main->addvar("ID_AD", $id_article);
$tpl_main->addvar("ID_KAT", $id_kat);

if ( $article_tpl['SETTINGS_MARKTPLATZ_CURRENCY'] == "$" ) {
	$tpl_content->addvar("CURRENCY_CODE_FOR_SCHEMA","USD");
}
else if ( $article_tpl['SETTINGS_MARKTPLATZ_CURRENCY'] == "€" ) {
	$tpl_content->addvar("CURRENCY_CODE_FOR_SCHEMA","EUR");
}

$tpl_content->addvars($article_tpl);

// Constraint
$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;

$tpl_main->addvar("SKIN_KAT_PATH", $ffile);

$_REQUEST['_URL'] = $_SERVER['REQUEST_URI'];

$queryIds = "SELECT ID_AD_MASTER FROM `ad_master` ".
    "WHERE (STATUS&3)=1 AND (DELETED=0) AND FK_KAT=" . $id_kat . " AND ID_AD_MASTER<>".$id_article.
        " ".($article_tpl["B_TOP"] ? " AND FK_USER=" . $article_data["FK_USER"] : " AND FK_USER != '".$uid."'");
$countCS = $nar_systemsettings['MARKTPLATZ']['INDEX_CROSSSEL'];
$arAllIds = $db->fetch_nar($queryIds);
$arRandIds = (count($arAllIds) > $countCS ? array_rand($arAllIds, $countCS) : array_keys($arAllIds));

if (!empty($arRandIds)) {
	if(!is_array($arRandIds)) {
		$arRandIds = array($arRandIds);
	}

    ### weitere Produkte
    $query = "SELECT
            a.*,
            a.ID_AD_MASTER as ID_AD,
            a.BESCHREIBUNG AS DSC, a.TRADE AS product_trade,
            DATEDIFF(NOW(),a.STAMP_START) as RUNTIME_DAYS,
            (SELECT s.V1
                FROM `kat` k
                  LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
                    AND s.BF_LANG=if(k.BF_LANG_KAT & " . $langval . ", " . $langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
                 WHERE k.ID_KAT=a.FK_KAT
            ) as KAT,
            (SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=a.FK_MAN) as MANUFACTURER,
            (SELECT slang.V1 FROM `string` slang WHERE slang.S_TABLE='lang' AND slang.FK=a.FK_COUNTRY
                AND slang.BF_LANG='" . $langval . "' LIMIT 1) as LAND,
            (SELECT i.SRC_THUMB FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=ID_AD LIMIT 1) as IMG_DEFAULT_SRC_THUMB,
            (SELECT i.SRC FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND  i.FK_AD=ID_AD LIMIT 1) as IMG_DEFAULT_SRC
      FROM ad_master a
      WHERE a.ID_AD_MASTER IN (" . implode(", ", $arRandIds) . ")
      ORDER BY a.B_TOP DESC, RAND()";
    # Debug nur für admin:
    #if ($uid == 1) die($query);
    $liste = $db->fetch_table($query);
	Rest_MarketplaceAds::extendAdDetailsList($liste);

    $tpl_content->addlist("interesse", $liste, $ab_path.'tpl/'.$s_lang.'/marktplatz.row_box.htm', 'killbb');
}

// EAN
if(!empty($article_data['EAN']) && $nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']) {
	$articlesWithSameEAN = $db->fetch1($q="
		SELECT COUNT(*) AS AD_COUNT, MIN(PREIS) AS PRICE_MIN, MAX(PREIS) AS PRICE_MAX
		FROM ad_master 
		WHERE EAN = '".mysql_real_escape_string($article_data['EAN'])."' 
			AND ID_AD_MASTER <> '".(int)$id_article."' AND (STATUS&3)=1 AND (DELETED=0)
		GROUP BY EAN");
	$tpl_content->addvar("COUNT_ARTICLES_WITH_EAN", $articlesWithSameEAN["AD_COUNT"]);
	$tpl_content->addvar("PRICE_MIN_ARTICLES_WITH_EAN", $articlesWithSameEAN["PRICE_MIN"]);
	$tpl_content->addvar("PRICE_MAX_ARTICLES_WITH_EAN", $articlesWithSameEAN["PRICE_MAX"]);
}

// Same product
if (!empty($article_data['FK_PRODUCT'])) {
	$articlesWithSameProduct = $db->fetch1($q="
		SELECT COUNT(*) AS AD_COUNT, MIN(PREIS) AS PRICE_MIN, MAX(PREIS) AS PRICE_MAX
		FROM ad_master 
		WHERE FK_PRODUCT = '".(int)$article_data['FK_PRODUCT']."' AND FK_MAN = '".(int)$article_data['FK_MAN']."' 
			AND ID_AD_MASTER <> '".(int)$id_article."' AND (STATUS&3)=1 AND (DELETED=0)
		GROUP BY FK_MAN, FK_PRODUCT");
	$tpl_content->addvar("COUNT_ARTICLES_WITH_PRODUCT", $articlesWithSameProduct["AD_COUNT"]+1);
}

$tpl_content->addvar("noads", 1);
$tpl_content->isTemplateRecursiveParsable = TRUE;
$tpl_content->isTemplateCached = TRUE;

/** Like **/
$adLikeManagement = AdLikeManagement::getInstance($db);
$adLikeCount = $adLikeManagement->countLikesByAdId($id_article);
$tpl_content->addvar("adLikeCount", $adLikeCount);

/** Ad Click **/
$x = $db->querynow("UPDATE ad_master SET AD_CLICKS = (AD_CLICKS + 1) WHERE ID_AD_MASTER = '".mysql_escape_string($id_article)."'");
$adClick = $db->fetch_atom("SELECT AD_CLICKS FROM ad_master WHERE ID_AD_MASTER = '".mysql_escape_string($id_article)."'");
$tpl_content->addvar("adClicks", $adClick);
$db->querynow("INSERT INTO article_stats (FK_USER,FK_AD,DATUM,VIEWS) VALUES (".$article_data["FK_USER"].",".mysql_escape_string($id_article).",NOW(),1)
  							ON DUPLICATE KEY UPDATE VIEWS=VIEWS+1");

/** Ad Reminder **/
$w_url = implode(",",$GLOBALS['ar_params']);
$adReminderCount = $db->fetch_atom("SELECT COUNT(*) as a FROM watchlist WHERE URL = '".$w_url."' ");
$tpl_content->addvar("adReminderCount", $adReminderCount);

/** PaymentAdapter **/
$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);
$adPaymentAdapters = $adPaymentAdapterManagement->fetchAllPaymentAdapterForAd($id_article);

// Canonical
addCanonicalTagByIdent("marktplatz_anzeige,".$id_article.",".chtrans($article_data["PRODUKTNAME"]));

/**
 * Detail-Tabs
 */
$arDetailTabs = array();

// Description
$arDetailTabs[] = array(
	"ACTIVE" => true,
	"IDENT" => "marketplaceArticleDescription",
	"LABEL" => Translation::readTranslation("marketplace", "description", null, array(), "Beschreibung"),
	"CONTENT" => $article_tpl["AD_DESCRIPTION"]
);
// Html description fields
if (!empty($htmlDescFields)) {
	foreach ($htmlDescFields as $htmlDescField) {
	    if (empty($htmlDescField["VALUE"])) {
	        continue;
        }
		$arDetailTabs[] = array(
			"IDENT" => "marketplaceArticleDescription_".$htmlDescField["F_NAME"],
			"LABEL" => $htmlDescField["V1"],
			"CONTENT" => $htmlDescField["VALUE"]
		);
	}
}
// Availability
if (!empty($article_tpl["AD_AVAILABILITY"])) {
	$arDetailTabs[] = array(
		"IDENT" => "marketplaceArticleAvailability",
		"LABEL" => Translation::readTranslation("marketplace", "availability", null, array(), "Verfügbarkeit"),
		"CONTENT" => $tpl_content->tpl_subtpl("tpl/".$s_lang."/ad_availability_calendar.htm,ID_AD")
	);
}
// Terms and conditions / Recall conditions
if ($nar_systemsettings["MARKTPLATZ"]["BUYING_ENABLED"]) {
	if (!empty($article_tpl["AD_AGB"])) {
		$arDetailTabs[] = array(
			"IDENT" => "marketplaceArticleAGB",
			"LABEL" => Translation::readTranslation("marketplace", "agb", null, array(), "AGB"),
			"CONTENT" => $article_tpl["AD_AGB"]
		);
	}
	if (!empty($article_tpl["AD_WIDERRUF"])) {
		$arDetailTabs[] = array(
			"IDENT" => "marketplaceArticleWiderruf",
			"LABEL" => Translation::readTranslation("marketplace", "conditions", null, array(), "Widerrufsbelehrung"),
			"CONTENT" => $article_tpl["AD_WIDERRUF"]
		);
	}
	if (!empty($adPaymentAdapters)) {
		$arDetailTabs[] = array(
			"IDENT" => "marketplaceArticleZahlungsinformation",
			#[[ translation : marketplace : agb :: AGB ]]
			"LABEL" => Translation::readTranslation("marketplace", "payment.information", null, array(), "Zahlungsinformationen"),
			"CONTENT" => Template::createTemplateList('tpl/'.$s_lang.'/marktplatz_anzeige.payment_adapter.htm', $adPaymentAdapters)
		);
	}
}

// Plugin event
$eventAdDetailsParams = new Api_Entities_EventParamContainer(array(
	"article"			=> $article,
	"tabs"		        => $arDetailTabs,
	"template"			=> $tpl_content
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_DETAILS, $eventAdDetailsParams);
$tpl_content->addlist("PLUGIN_TABS_LINKS", $eventAdDetailsParams->getParam("tabs"), "tpl/".$s_lang."/marktplatz_anzeige.details.tabs.nav.htm");
$tpl_content->addlist("PLUGIN_TABS_PANES", $eventAdDetailsParams->getParam("tabs"), "tpl/".$s_lang."/marktplatz_anzeige.details.tabs.content.htm");

// Print view?
if ($show_info == "print") {
    //$tpl_main->loadText($ab_path.'skin/'.$s_lang.'/index_pdf_print.htm');
    //$tpl_content->
    $tpl_content->loadText($ab_path.'tpl/'.$s_lang.'/marktplatz_anzeige.print.htm');
    $tpl_content->addvar("PRINT", 1);

    //$tpl_html = new Template($GLOBALS['ab_path'].'tpl/'.$GLOBALS['s_lang'].'/artikel.page.print.skin.htm');
    //$tpl_html->addvar("AD_TITLE",$article_data["PRODUKTNAME"]);
	//$tpl_main->loadText($ab_path.'skin/'.$s_lang.'index_pdf_print.htm');
	//$tpl_content->loadText($ab_path.'tpl/'.$s_lang.'/artikel.page.print.htm');


	/*$h = $tpl_content->process();
	$h = preg_replace('/src=[\"\']\/(.+)[\"\']/i', 'src="'.$GLOBALS['ab_path'].'$1"', $h);

    echo '<pre>';
    var_dump( $h );
    echo '</pre>';*/
    //die();

	$tpl_skin = new Template($ab_path.'skin/'.$s_lang.'/index_pdf_print.htm');
	$tpl_skin->addvar("content",$tpl_content->process());

	$html = $tpl_skin->process();
	$html = preg_replace('/src=[\"\']\/(.+)[\"\']/i', 'src="'.$GLOBALS['ab_path'].'$1"', $html);
	$html = str_replace('/'.$s_lang."/",'/',$html);
	/*echo '<pre>';
	var_dump( $GLOBALS['nar_systemsettings']['MARKTPLATZ']['CURRENCY'],$html );
	echo '</pre>';*/

	//echo $html;
	include "sys/dompdf/dompdf_config.inc.php";
    $dompdf = new DOMPDF();
    $dompdf->load_html( $html );
    $dompdf->render();
    //$dompdf->

    //if (  )

    //$dompdf->stream();
	//header("Content-Type: application/pdf");
	//header("Content-Length: " . filesize());
	//header("Content-Disposition: inline; filename=\"".$article_tpl['AD_TITLE'].".pdf"."\"");
	//$pdf_gen = $dompdf->output();
	$directory_path = $ab_path."uploads/articles-print-pdf/";
	if ( !file_exists($directory_path) ) {
		mkdir($directory_path,0777);
	}
	$full_file_path = $directory_path . $article_tpl['AD_TITLE'].".pdf";
	file_put_contents($directory_path.$article_tpl['AD_TITLE'].".pdf",$dompdf->output());

	header('Content-Encoding: identity');
	header("Content-Type: application/pdf");
	header("Content-Disposition: attachment; filename=\"".$article_tpl['AD_TITLE'].".pdf"."\"");
	readfile($full_file_path);

	//$dompdf->stream();
    die();

}
?>

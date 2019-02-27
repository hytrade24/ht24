<?php
/* ###VERSIONSBLOCKINLCUDE### */

include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";
include_once "sys/lib.anzeigen.php";
include_once "sys/lib.ads.php";

function get_cache_row(&$row) {
	global $ab_path, $kat_table, $s_lang;

    $cache_file = AdManagment::getAdCachePath($row['0'], false);
    $cache_file .= "/row.".$s_lang.".htm";

	$row["ID_ARTIKEL"] = $row[0];
	$row["CACHE"] = file_get_contents($cache_file);
	$row["CACHE"] = str_replace("{RUNTIME}", $row["RUNTIME"], $row["CACHE"]);
}

if ($ar_params[1] == "activate") {
	$id_ad = $ar_params[2];
	$id_kat = $ar_params[3];
	unset($ar_params[2]);
	unset($ar_params[3]);
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);

	include_once "sys/lib.ads.php";
	if (AdManagment::Enable($id_ad, $kat_table)) {
		die(forward("/my-pages/my-marktplatz-disabled,,,,activated.htm"));
	} else {
		$tpl_content->addvar("error_enable", 1);
	}
}
if($ar_params[4] == 'activated')
{
	$tpl_content->addvar("activated", 1);
}
if ($ar_params[1] == "recreate") {
    /*
     * Vorschalteseite um Kategorie zu wählen
     */
  	require_once "sys/lib.shop_kategorien.php";
	require_once $ab_path."sys/packet_management.php";
	$packets = PacketManagement::getInstance($db);
    $kat = new TreeCategories("kat", 1);

	unset($_SESSION['EBIZ_TRADER_AD_CREATE']);

	$id_ad = (int)$ar_params[2];
	$id_kat = $db->fetch_atom("SELECT FK_KAT FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
	$id_kat_new = (!empty($_REQUEST['FK_KAT']) ? (int)$_REQUEST['FK_KAT'] : 0);
	$id_packet_order = (!empty($_REQUEST['FK_PACKET_ORDER']) ? (int)$_REQUEST['FK_PACKET_ORDER'] : (int)$ar_params[4]);

	$ar_kat = false;
	if ($id_kat > 0) {
		$ar_kat = $db->fetch1("SELECT KAT_TABLE, PARENT FROM `kat` WHERE ID_KAT=".$id_kat);
	}

    if($ar_kat == false) {
        $tpl_content->addvar("error_ad", 1);
    } else {
        $kat_table = $ar_kat['KAT_TABLE'];
        $kat_parent = $ar_kat['PARENT'];
        $tpl_content->addvar("FK_KAT_PARENT", $kat_parent);

        $usercontent = $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=".$uid);
        $ar_kat = $kat->element_read($id_kat);
        $ar_ad = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
        $ar_ad_table = $db->fetch1("SELECT * FROM `".$kat_table."` WHERE ID_".strtoupper($kat_table)."=".$id_ad);

		$ar_required = array(PacketManagement::getType("ad_once") => 1);
		$ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);
		$ar_packets = array_merge($packets->order_find_collections($uid, $ar_required), $packets->order_find_collections($uid, $ar_required_abo));

        $tpl_content->addvar("free_ads", $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]);
        $tpl_content->addvar("FK_PACKET_ORDER", $id_packet_order);
        $tpl_content->addvar("recreate_packet", 1);
        $tpl_content->addvar("FK_KAT", $id_kat);
        $tpl_content->addvar("ID_AD_MASTER", $id_ad);

        if (!empty($ar_packets)) {
            $tpl_content->addvars($ar_ad);
            $tpl_content->addvar("recreate_packet", 1);
            if ($ar_kat["B_FREE"]) {
                $tpl_content->addvar("allow_free", 1);
            }
            $tpl_content->addlist("liste_packets", $ar_packets, "tpl/".$s_lang."/my-marktplatz-disabled.row_packet.htm");
        } else {
            $tpl_content->addvar("error_nofree", 1);
       }
    }

} elseif ($ar_params[1] == "dorecreate") {
    require_once "sys/lib.shop_kategorien.php";
	require_once $ab_path."sys/packet_management.php";
	$packets = PacketManagement::getInstance($db);
    $kat = new TreeCategories("kat", 1);

    $id_ad = (int)$ar_params[2];
    $id_kat = (int)$ar_params[3];
    $id_kat_new = (!empty($_REQUEST['FK_KAT']) ? (int)$_REQUEST['FK_KAT'] : 0);
    $id_packet_order = (!empty($_REQUEST['FK_PACKET_ORDER']) ? (int)$_REQUEST['FK_PACKET_ORDER'] : (int)$ar_params[4]);
    $ar_kat = $db->fetch1("SELECT KAT_TABLE, PARENT FROM `kat` WHERE ID_KAT=".$id_kat);
    $kat_table = $ar_kat['KAT_TABLE'];
    $kat_parent = $ar_kat['PARENT'];
    $tpl_content->addvar("FK_KAT_PARENT", $kat_parent);

    $usercontent = $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=".$uid);
    $ar_kat = $kat->element_read($id_kat);
    $ar_ad_override = $db->fetch1("SELECT FK_PACKET_ORDER FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);

    $ads_free = 0;
    if ($id_packet_order > 0) {
        // Anderes Anzeigenpaket wählen
        $ar_ad_override["FK_PACKET_ORDER"] = $id_packet_order;
	    $order = $packets->order_get($id_packet_order);
	    if ($order != null) {
		    if ($order->isRecurring()) {
		    	$ar_ads = $order->getContentByType(PacketManagement::getType("ad_abo"));
		    	if (!empty($ar_ads)) {
		    		if ($ar_ads["COUNT_MAX"] == -1) {
		    			// Flatrate
		    			$ads_free = 1;
		    		} else {
		    			$ads_free += ($ar_ads["COUNT_MAX"] - $ar_ads["COUNT_USED"]);
		    		}
		    	}
		    } else {
		    	$ar_ads = $order->getContentByType(PacketManagement::getType("ad_once"));
		    	if (!empty($ar_ads)) {
		    		if ($ar_ads["COUNT_MAX"] == -1) {
		    			// Flatrate
		    			$ads_free = 1;
		    		} else {
		    			$ads_free += ($ar_ads["COUNT_MAX"] - $ar_ads["COUNT_USED"]);
		    		}
		    	}
		    }
	    }
    } else {
        $ads_free = $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"];
    }
    if ($ads_free > 0) {
            include_once "sys/lib.ads.php";
            $new_id = AdManagment::Recreate($id_ad, $kat_table, $id_kat_new, $ar_ad_override);
            if ($new_id) {
                die(forward("/my-pages/my-marktplatz-neu,".$new_id.".htm"));
            } else {
                die(forward("/my-pages/my-marktplatz-disabled.htm"));
            }
    } else {
        die(forward("/my-pages/my-marktplatz-disabled,recreate,$id_ad,$id_kat.htm"));
    }
}

if($_REQUEST['ID_AD_MASTER'] || $_REQUEST['PRODUKTNAME']) {
	$tpl_content->addvars($_REQUEST);
	$id = (int)$_REQUEST['ID_AD_MASTER'];
	$name = trim($_REQUEST['PRODUKTNAME']);
	$where = array();
	if($id) {
		$where[] = "am.ID_AD_MASTER=".mysql_real_escape_string($id);
	}
	if(!empty($name)) {
		$where[] = "am.PRODUKTNAME LIKE '%".mysql_real_escape_string($name)."%'";
	}
	$where = (count($where) ? "\nAND ".implode("\nAND ", $where) : '');
}

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

$sort_by = ($ar_params[1] ? $ar_params[1] : "TIMEOUT_DAYS");
$sort_dir = ($ar_params[2] ? $ar_params[2] : "ASC");

$sort_fields = array("PRODUKTNAME", "ZIP", "PREIS", "TIMEOUT_DAYS");
$sort_directions = array("DESC", "ASC");
$sort_vars = array();
$sort = array();

foreach($sort_fields as $index => $field)
$sort_vars["SORT_".$field] = "DESC";

if (in_array($sort_by, $sort_fields) && in_array($sort_dir, $sort_directions)) {
	$sort[] = $sort_by." ".$sort_dir;
	$sort_vars["SORT_DIRECTION"] = strtolower($sort_dir);
	$sort_vars["SORT_BY_".$sort_by] = ($sort_dir == "DESC" ? 1 : 2);
	$sort_vars["SORT_".$sort_by] = ($sort_dir == "DESC" ? "ASC" : "DESC");
} else {
	$sort_vars["SORT_DIRECTION"] = "asc";
	$sort_vars["SORT_BY_RUNTIME"] = 2;
	$sort_vars["SORT_RUNTIME"] = "DESC";
	$sort[] = "TIMEOUT_DAYS ASC";
}

$tpl_content->addvars($sort_vars);

$perpage = 10;
$npage = ((int)$ar_params[3] ? $ar_params[3] : 1);
$limit = ($perpage*$npage)-$perpage;

$ads = $db->fetch_table("
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		DATEDIFF(NOW(), am.STAMP_END) as TIMEOUT_DAYS,
    		if(STAMP_END < NOW(), 1, 0) AS TIMEOUT,
    		DATEDIFF(am.STAMP_END, am.STAMP_DEACTIVATE) as TIME_LEFT,
    		s.V1 as KAT,
    		sc.V1 as LAND,
    		i.SRC AS SRC_FULL,
    		i.SRC_THUMB,
			m.NAME as MANUFACTURER
    	FROM
    		ad_master am
    	LEFT JOIN
			string_kat s on s.S_TABLE='kat'
			and s.FK=am.FK_KAT
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    	LEFT JOIN
			string sc on sc.S_TABLE='country'
			and sc.FK=am.FK_COUNTRY
			and sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
		LEFT JOIN `manufacturers` m ON m.ID_MAN=am.FK_MAN
		WHERE
    		am.FK_USER=".$uid."
    		AND (am.STATUS&1)=0 AND (am.STAMP_END IS NOT NULL)".
    		$where."
        GROUP BY
        	am.ID_AD_MASTER
    	ORDER BY
			".implode(",", $sort).",
			am.STAMP_START ASC
    	LIMIT
    		".$limit.", ".$perpage);

$tpl_content->addlist("liste", $ads, "tpl/".$s_lang."/my-marktplatz-disabled.row.htm");
// Seitenzähler hinzufügen
$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");
$tpl_content->addvar("pager", htm_browse($all, $npage, "/my-marktplatz-disabled,".$sort_by.",".$sort_dir.",", $perpage));

$tpl_content->addvar("ALLOW_COMMENTS_AD", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);

?>
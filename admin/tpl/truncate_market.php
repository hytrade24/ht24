<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * Alle Anzeigen löschen
 */
function deleteAllAds() {
}

/**
 * Alle Benutzer löschen
 */
function deleteAllUsers() {
}

/**
 * Alle Bestellungen löschen
 */
function deleteAllOrders() {
}

/**
 * Alle Gesuche löschen
 */
function deleteAllRequests() {
}

/**
 * Alle Händler löschen
 */
function deleteAllVendors() {
}

/**
 * Alle Jobs löschen
 */
function deleteAllJobs() {
}

/**
 * Alle News löschen
 */
function deleteAllNews() {
}

/**
 * Alle Kategorien löschen
 */
function deleteAllCategories() {
}

/**
 * Alle Rechnungen löschen
 */
function deleteAllInvoices() {
}

/**
 * Alle Werbung löschen
 */
function deleteAllAdvertisement() {
}



if (!empty($_POST)) {
    $ar_actions = Api_DatabaseTruncate::truncate(array_keys($_POST), $db);
    if (empty($ar_actions)) {
        $tpl_content->addvar("err", 'Bitte aktivieren Sie die Checkbox(en)');
    } else {
        include $ab_path."sys/lib.pub_kategorien.php";
        CategoriesBase::deleteCache();
    }

	$tpl_content->addvars($_POST);
}


function format_size(&$row, $i)
{
    global $delme;
    if($row['Data_length'] >= 1073741824) { $row['Data_length']= round(($row['Data_length'] / 1073741824), 2) . "GB"; }
    elseif($row['Data_length'] >= 1048576) { $row['Data_length']= round(($row['Data_length'] / 1048576), 2) . "MB"; }
    elseif($row['Data_length'] >= 1024) { $row['Data_length']= round(($row['Data_length'] / 1024), 2) . " KB"; }
    else { $row['Data_length']= $row['Data_length'] . " Byte"; }


        $delme[]=$row;

} // format_size()

$liste = $db->fetch_table("SHOW TABLE STATUS",'Name');
$tpl_content->addlist('liste', $liste, 'tpl/de/searching_index.row.htm','format_size');
?>

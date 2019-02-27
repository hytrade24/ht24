<?php

/* ###VERSIONSBLOCKINLCUDE### */


/*

Eintrag in worklis erstellen

TYP  -> R   =Rechnung 
        U   =User  
        A   =Artikel
WKID -> OpjektID

PAGE -> Seite
*/


#
# Userdatenbsatz
#

$userPages = array('user_edit', 'perm2user', 'user_buchhaltung', 'user_billing_creditnote', 'user_ads', 'user_transaktion', 'user_kontingent', 'vendor');
$BillPages = array('invoice_view');

if (in_array($_REQUEST['page'], $userPages)) {
    if ($_REQUEST['ID_USER'] > 0)
        wklist_insert(1, $_REQUEST['ID_USER'], $_REQUEST['page']);
} elseif (in_array($_REQUEST['page'], $BillPages)) {
    if ($_REQUEST['ID_BILLING_INVOICE'] > 0)
        wklist_insert(0, $_REQUEST['ID_BILLING_INVOICE'], $_REQUEST['page']);

}

// Userdatensatz einfügen
function wklist_insert($TYP, $WKID, $PAGE)
{
    global $db, $uid, $s_lang, $ab_path;

    if ($TYP == 2) {
        $db->querynow("INSERT INTO worklist ( `FK_USER_ID`,  `FK_PAGE`,  `FK_ID`,  `TYP`,  `DESCRIPTION`)
                VALUES (" . $uid . ",'" . $PAGE . "'," . $WKID . "," . $TYP . ",(select concat(`VORNAME`,' ',`NACHNAME`,' (',`NAME`,')')  from user where ID_USER=" . $WKID . "))
                ON DUPLICATE KEY UPDATE `FK_PAGE` = '" . $PAGE . "'  , TIMES=now()");
    } elseif ($TYP == 1) {
        $db->querynow("INSERT INTO worklist ( `FK_USER_ID`,  `FK_PAGE`,  `FK_ID`,  `TYP`,  `DESCRIPTION`)
                VALUES (" . $uid . ",'" . $PAGE . "'," . $WKID . "," . $TYP . ",(select concat(`VORNAME`,' ',`NACHNAME`,' (',`NAME`,')')  from user where ID_USER=" . $WKID . "))
                ON DUPLICATE KEY UPDATE `FK_PAGE` = '" . $PAGE . "'  , TIMES=now()");
    } elseif ($TYP == 0) {
        $db->querynow("INSERT INTO worklist ( `FK_USER_ID`,  `FK_PAGE`,  `FK_ID`,  `TYP`,  `DESCRIPTION`)
                VALUES (" . $uid . ",'" . $PAGE . "'," . $WKID . "," . $TYP . ",(select concat('" . $WKID . "',' / ',`ADDRESS`)  from billing_invoice where ID_BILLING_INVOICE=" . $WKID . "))
                ON DUPLICATE KEY UPDATE `FK_PAGE` = '" . $PAGE . "' , TIMES=now()");
    }
    $delfrom = $db->fetch1("select TIMES from worklist where FK_USER_ID=" . $uid . " order by TIMES DESC limit 15,1;");
    $db->querynow("delete from worklist where FK_USER_ID=" . $uid . " and TIMES <='" . $delfrom['TIMES'] . "' ");


    // Datei schreiben
    $ar = $db->fetch_table("select * from worklist where FK_USER_ID=" . $uid . " ORDER BY `TIMES` DESC ");

    $tpl = new Template('');
    $tpl->tpl_text = "{newsblogs}";
    $tpl->addlist("newsblogs", $ar, "tpl/" . $s_lang . "/worklist.row.htm");
    #die(var_dump($tpl->vars['newsblogs']));
    $boxpath = $ab_path . "cache/worklist." . $uid . ".htm";
    @file_put_contents($boxpath, $tpl->process());
}

?>
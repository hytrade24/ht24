<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id = (int)$_REQUEST['ID_USER'];

$data = ($id
? $db->fetch1($db->lang_select('user'). "where ID_USER=". $id)
: $db->fetch_blank('user')
);
$tpl_content->addvars($data);

$select="SELECT
            ID_VENDOR,
            BF_LANG_VENDOR,
            STATUS as vSTATUS,
            NAME as vNAME,
            STRASSE as vSTRASSE,
            PLZ as vPLZ,
            ORT as vORT,
            FK_COUNTRY,
            LATITUDE,
            LONGITUDE,
            TEL as vTEL,
            FAX as vFAX,
            URL as vURL,
            LOGO
        FROM vendor
        where FK_USER =".$id;
$vdata=$db->fetch1($select);
if($vdata) {
    $tpl_content->addvars($vdata);
}
$kats = $db->fetch_table("SELECT c.FK_KAT ,s.T1,s.V1,k.BF_LANG_KAT
                            FROM vendor_category c
                            left join kat k on  k.ID_KAT = c.FK_KAT
                            left join string_kat s on s.S_TABLE='kat' and s.FK=c.FK_KAT and k.BF_LANG_KAT=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
                            WHERE c.FK_VENDOR = '".mysql_real_escape_string($vdata['ID_VENDOR'])."'");


 function show_code(&$row, $i)
 {
   $row['V1'] = "{content_page(".stdHtmlentities($row['V1']).")}";
 } // show_code()
 $tpl_content->addlist("kats",$kats,"tpl/de/kat_anbieter.row.htm");


?>
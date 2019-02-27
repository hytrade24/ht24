<?php
/* ###VERSIONSBLOCKINLCUDE### */

$order = 'ID_COUNTRY';
$dir =  'asc';
$taxExemptAdapterName = $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ADAPTER'];

if (isset($_POST['save'])) {
    if (!empty($_POST['USTID_OPTION'])) {
		foreach($_POST['USTID_OPTION'] as $countryId => $ustIdOption) {
			$db->querynow('update country set USTID_OPTION = "'.$ustIdOption.'" WHERE ID_COUNTRY = "'.$countryId.'"');
		}
    }
	if(!empty($_POST['ADAPTER_CONFIG'])) {
		$db->querynow('update billing_invoice_tax_exempt_adapter set ADAPTER_CONFIG = "'.$_POST['ADAPTER_CONFIG'].'" WHERE ADAPTER_NAME = "'.$taxExemptAdapterName.'"');
	}

    $tpl_content->addvar('ok', 'Änderungen wurden gespeichert!');
}

$ar = $db->fetch_table("select t.ID_COUNTRY, t.CODE, s.V1, t.USTID_OPTION
							from `country` t
							left join string s
							on s.S_TABLE='country' and s.FK=t.ID_COUNTRY and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
							order by ".$order." ".$dir."");

$tpl_content->addlist('liste', $ar, "tpl/".$s_lang."/ust_id.row.htm");


$taxExemptAdapterConfig = $db->fetch1("SELECT * FROM billing_invoice_tax_exempt_adapter WHERE ADAPTER_NAME = '".$taxExemptAdapterName."'");
if (!is_array($taxExemptAdapterConfig)) {
	$taxExemptAdapterConfig = array(
		"ADAPTER_NAME" => $taxExemptAdapterName
	);
}

$tpl_content->addvars($taxExemptAdapterConfig, 'TAX_EXEMPT_CONFIG_');
$tpl_main->addvar('INVOICE_TAX_EXEMPT_USTID', $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_USTID']);
$tpl_main->addvar('INVOICE_TAX_EXEMPT_ENABLE', $nar_systemsettings['MARKTPLATZ']['INVOICE_TAX_EXEMPT_ENABLE']);
?>

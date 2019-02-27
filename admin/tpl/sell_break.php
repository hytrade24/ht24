<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_sale = (int)$_REQUEST['ID_AD_SOLD'];
$ar_sale = $db->fetch1("
	select
		ads.*,
		adm.PRODUKTNAME,
		adm.FK_USER AS ID_SELLER,
		ads.FK_USER as ID_BUYER
	from
		ad_sold ads
	left join
		ad_master adm
		on ads.FK_AD=adm.ID_AD_MASTER
	where
		ads.ID_AD_SOLD=".$id_sale);
#die(ht(dump($lastresult)));

if($_REQUEST['gutgeschrieben']) {
	$tpl_content->addvar('gutgeschrieben', 1);
}

if($_REQUEST['verworfen']) {
	$tpl_content->addvar('verworfen', 1);
}


if(!empty($ar_sale)) {
	$tpl_content->addvars($ar_sale);
	$ar_seller = $db->fetch1("
		select
			*
		from
			user
		where
			ID_USER=".$ar_sale['ID_SELLER']);
	$hundert = $ar_seller['SALES']/100;
	if($hundert) {
		$fail = $ar_seller['STORNOS']/$hundert;
		$vh = round(100-$fail,2);
	}
	$ar_seller['ERFOLG'] = ($ar_seller['SALES'] > 0 ? $vh : 0);
	$tpl_content->addvars($ar_seller, 'sell_');
}

if(!empty($ar_sale)) {
	$tpl_content->addvars($ar_sale);
	$ar_buyer = $db->fetch1("
		select
			*
		from
			user
		where
			ID_USER=".$ar_sale['ID_BUYER']);
	$hundert = $ar_buyer['BOUGHTS']/100;
	if($hundert) {
		$fail = $ar_buyer['BOUGHTS_STORNOS']/$hundert;
		$vh = round(100-$fail,2);
	}
	$ar_buyer['ERFOLG'] = ($ar_buyer['BOUGHTS'] > 0 ? $vh : 0);
	$tpl_content->addvars($ar_buyer, 'buy_');
}

if(!empty($_REQUEST['act'])) {
	if($_REQUEST['act'] == 'verwerfen') {
		### mail
		$mail_to = $ar_seller['ID_USER'];
		$ad = array_merge($ar_sale, $ar_seller);
		sendMailTemplateToUser(0, $mail_to, 'STORNO_ADMIN_FAIL', $ad, false);
		### sale reset
		$db->querynow("
			UPDATE
				ad_sold
			set
				STAMP_STORNO=NULL,
				STAMP_STORNO_OK=NULL
			where
				ID_AD_SOLD=".$id_sale);
		$act = 'verworfen';
	}
	if($_REQUEST['act'] == 'gutschreiben') {
		### mail
		$mail_to = $ar_seller['ID_USER'];
		$ad = array_merge($ar_sale, $ar_seller);
		sendMailTemplateToUser(0, $mail_to, 'STORNO_ADMIN_OK', $ad, false);

		### gutschrift
		if($ar_sale['PROV'] > 0) {
			$db->querynow("
				INSERT INTO
					account
				SET
					AMOUNT=".$ar_sale['PROV'].",
					FK_USER=".$ar_seller['ID_USER'].",
					STAMP_BOOK=NOW(),
					FK_VERTRAG=0,
					DSC='Provisionsstorno Transaktion ".$id_sale."'");

            require_once($ab_path."sys/lib.billing.invoice.php");
            $billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);

            $tax = $db->fetch_atom("select TAX_VALUE FROM tax where ID_TAX=1");

            $invoiceItems[] = array(
                'DESCRIPTION' => 'Stornierte Provision Trans.-Id. '.$id_sale,
                'PRICE' => -1 * $ar_sale['PROV'] / (1 + ($tax/100)),
                'QUANTITY' => 1,
                'FK_TAX' => 1
            );

            $invoiceId = $billingInvoiceManagement->createInvoice(array(
                'FK_USER' => $ar_seller['ID_USER'],
                '__items' => $invoiceItems
            ));

		}
		### sale update
		$db->querynow("
			UPDATE
				ad_sold
			set
				STAMP_STORNO_OK=NOW(),
				STATUS=2
			where
				ID_AD_SOLD=".$id_sale);
		$act='gutgeschrieben';
	}
	die(forward("index.php?page=sell_break&ID_AD_SOLD=".$id_sale."&".$act."=1"));
}

?>
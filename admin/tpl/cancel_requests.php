<?php

$arWhere = array();
$arJoin = array();
$url_status = null;

if ( !empty($_REQUEST) ) {
	/*if ( isset($_REQUEST["ID_BILLING_CANCEL"]) ) {
		if ( $_REQUEST["ID_BILLING_CANCEL"] != "" ) {
			$id_billing_cancel = $_REQUEST["ID_BILLING_CANCEL"];
			$arJoin["ID_BILLING_CANCEL"] = "a.ID_BILLING_CANCEL = " . $id_billing_cancel;
			$tpl_content->addvar("ID_BILLING_CANCEL",$id_billing_cancel);
		}
	}*/
	if ( isset($_REQUEST["STATUS"]) ) {
		if ( $_REQUEST["STATUS"] != "" ) {
			$status = $_REQUEST["STATUS"];
			if ( $status != "alle" ) {
				$arWhere["STATUS"] = "a.STATUS = \"" . $status."\"";
			}
			$tpl_content->addvar("STATUS_".$status,1);
			$url_status = $status;
		}
		else {
			$arWhere["STATUS"] = "a.STATUS = \"pending\"";
			$tpl_content->addvar("STATUS_pending",1);
			$url_status = "pending";
		}
	}
	else {
		$arWhere["STATUS"] = "a.STATUS = \"pending\"";
		$tpl_content->addvar("STATUS_pending",1);
		$url_status = "pending";
	}
	if ( isset($_REQUEST["STAMP_CREATE_FROM"]) || isset($_REQUEST["STAMP_CREATE_TO"]) ) {
		if ( $_REQUEST["STAMP_CREATE_FROM"] != "" && $_REQUEST["STAMP_CREATE_TO"] != "" ) {

			$stamp_from = explode( ".",$_REQUEST["STAMP_CREATE_FROM"] );
			$stamp_from = $stamp_from[2]."-".$stamp_from[1]."-".$stamp_from[0];

			$stamp_to = explode( ".",$_REQUEST["STAMP_CREATE_TO"] );
			$stamp_to = $stamp_to[2]."-".$stamp_to[1]."-".$stamp_to[0];

			array_push(
				$arWhere,
				" a.CREATED_AT BETWEEN \"" . $stamp_from . "\" AND \"" . $stamp_to ."\""
			);

			$tpl_content->addvar("STAMP_CREATE_FROM",$_REQUEST["STAMP_CREATE_FROM"]);
			$tpl_content->addvar("STAMP_CREATE_TO",$_REQUEST["STAMP_CREATE_TO"]);

		}
		else if ( $_REQUEST["STAMP_CREATE_FROM"] != "" ) {

			$stamp_from = explode( ".",$_REQUEST["STAMP_CREATE_FROM"] );
			$stamp_from = $stamp_from[2]."-".$stamp_from[1]."-".$stamp_from[0];

			array_push(
				$arWhere,
				" a.CREATED_AT >= \"" . $stamp_from ."\""
			);

			$tpl_content->addvar("STAMP_CREATE_FROM",$_REQUEST["STAMP_CREATE_FROM"]);

		}
		else if ( $_REQUEST["STAMP_CREATE_TO"] != "" ) {

			$stamp_to = explode( ".",$_REQUEST["STAMP_CREATE_TO"] );
			$stamp_to = $stamp_to[2]."-".$stamp_to[1]."-".$stamp_to[0];

			array_push(
				$arWhere,
				" a.CREATED_AT <= \"" . $stamp_to ."\""
			);

			$tpl_content->addvar("STAMP_CREATE_TO",$_REQUEST["STAMP_CREATE_TO"]);

		}
	}
	if ( isset($_REQUEST["FK_USER"]) && isset($_REQUEST["NAME_"]) ) {
		if ( $_REQUEST["FK_USER"] != "" ) {
			$fk_user = $_REQUEST["FK_USER"];
			array_push(
				$arWhere,
				"a.FK_USER = " . $fk_user
			);
			$tpl_content->addvar("FK_USER",$fk_user);
			$tpl_content->addvar("NAME_",$_REQUEST["NAME_"]);
		}
	}
}

$sql = '
  SELECT 
    u.ID_USER, u.NAME, count(1) as REQUESTS_COUNT,
    a.FK_USER, 
    MIN(a.CREATED_AT) as MIN_DATE,
    MAX(a.CREATED_AT) as MAX_DATE,
    ( 
      IFNULL(SUM(((1 + (IFNULL(t1.TAX_VALUE, 0)/100)) * ci.PRICE) * ci.QUANTITY), 0) + 
      IFNULL(SUM(((1 + (IFNULL(t2.TAX_VALUE, 0)/100)) * ii.PRICE) * ii.QUANTITY), 0) + 
      IFNULL(SUM(((1 + (IFNULL(t3.TAX_VALUE, 0)/100)) * bi.PRICE) * bi.QUANTITY), 0)
    ) AS TOTAL_PRICE
  FROM billing_cancel a
  LEFT JOIN `billing_cancel_item` ci ON ci.FK_BILLING_CANCEL = a.ID_BILLING_CANCEL
  LEFT JOIN tax t1 ON t1.ID_TAX = ci.FK_TAX
  LEFT JOIN `billing_invoice_item` ii ON ii.ID_BILLING_INVOICE_ITEM = a.FK_BILLING_INVOICE_ITEM
  LEFT JOIN tax t2 ON t2.ID_TAX = ii.FK_TAX
  LEFT JOIN `billing_billableitem` bi ON bi.ID_BILLING_BILLABLEITEM = a.FK_BILLING_BILLABLEITEM
  LEFT JOIN tax t3 ON t3.ID_TAX = bi.FK_TAX
  LEFT JOIN user u ON u.ID_USER = a.FK_USER';
if ( count($arWhere) > 0 ) {
	$sql .= ' WHERE ' . implode(" AND ", $arWhere);
}
$sql .= '
			GROUP BY u.ID_USER';

$result = $db->fetch_table( $sql );

foreach ( $result as $index => $row ) {
	$result[$index]["STATUS"] = $url_status;
}


$tpl_content->addlist("liste",$result,"tpl/".$s_lang."/cancel_requests.row.htm");

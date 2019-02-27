<?php
/* ###VERSIONSBLOCKINLCUDE### */


	function add_fields(&$row) {
	    global $product_data;
	    $row["value"] = $product_data[$row["F_NAME"]];
	}
	
	$product_data = $db->fetch1("
		SELECT 
			p.*, s.V1 as NAME, s.V2 as DESC_SHORT, s.T1 as DESC_LONG
		FROM `product` p 
			LEFT JOIN `string_product` s
				ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT 
				AND s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PRODUCT+0.5)/log(2)))
            WHERE p.ID_PRODUCT=".$_REQUEST["id"]);
	
	if (!empty($product_data)) {
		$field_data = $db->fetch_table("SELECT f.F_TYP, f.FK_LISTE, f.F_NAME, f.IS_SPECIAL, kf.B_NEEDED, sf.V1, sf.V2
			FROM `kat2field` kf
				LEFT JOIN `field_def` f ON f.ID_FIELD_DEF = kf.FK_FIELD
				LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=kf.FK_FIELD
					AND sf.BF_LANG=if(sf.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE
					f.F_NAME in ('".implode("', '", array_keys($product_data))."')
						AND f.IS_SPECIAL = 0
				GROUP BY f.F_NAME");
		
		$tpl_content->addvars($product_data);
  		$tpl_content->addlist("product_fields", $field_data, "tpl/".$s_lang."/marktplatz_tooltip_product.row.htm", "add_fields");
	} else {
		$tpl_content->addvar("error", 1);
	}
?>
<?php

$articleId = ($tpl_content->vars["ID_AD_MASTER"] ? (int)$tpl_content->vars["ID_AD_MASTER"] : 0);
$articleIdVariant = ($tpl_content->vars["ID_AD_VARIANT"] ? (int)$tpl_content->vars["ID_AD_VARIANT"] : null);
$articleShowHtmlFields = ($tpl_content->vars["SHOW_HTML_FIELDS"] ? (bool)$tpl_content->vars["SHOW_HTML_FIELDS"] : false);
$articlePreview = ($tpl_content->vars["PREVIEW"] ? (bool)$tpl_content->vars["PREVIEW"] : false);
$article = [];
if ($articlePreview) {
  require_once $ab_path."sys/lib.ad_create.php";
  $arStepOptions = array(
      'new'   => ($articleId > 0 ? 0 : 1),
      'free'  => ($nar_systemsettings['MARKTPLATZ']['FREE_ADS'] ? true : false)
  );
  $arGroupOptions = array();
  if ($tpl_content->tpl_has_permission("article_affiliate,C")) {
      $arGroupOptions["affiliateLink"] = true;
  }
  $adCreate = new AdCreate($db, ($uid > 0 ? $uid : null), null, $arStepOptions, $arGroupOptions);
  $article = Api_Entities_MarketplaceArticle::createFromFullArray( $adCreate->getAdData() );
} else {
  // Regular article view
  $article = Api_Entities_MarketplaceArticle::getById($articleId);
}

if (!$article instanceof Api_Entities_MarketplaceArticle) {
	$tpl_content->addvar("not_found", 1);
	return;
}

// Resolve variant id if not specified
if ($articleIdVariant === null) {
	$articleIdVariant = $article->getData_ArticleMaster("FK_AD_VARIANT");
}

$arSpecialVisible = array("FK_MAN");

$arGroups = $article->getData_FieldsGroups();
$arArticleFieldsGrouped = array();
$arArticleFieldsHtml = array();
foreach ($arGroups as $groupIndex => $groupId) {
  if (((int)$groupId == 0) && ($groupId !== null)) {
    continue;
  }
  $idGroup = (int)$groupId;
  $arArticleFields = $article->getFields($idGroup > 0 ? $idGroup : null);
  #$arArticleFieldsVariant = $article->getFieldsVariant();
  foreach ($arArticleFields as $fieldIndex => $fieldDetails) {
	  $fieldDetails["TYPE_".$fieldDetails["F_TYP"]] = 1;
    $fieldName = $fieldDetails["F_NAME"];
    /////////////////  imenso  ///////////////////////////
    //$fieldValue = $article->getData_Article($fieldName);
    $fieldValue = $article->getData_ArticleProduct($fieldName);
    // Check if field is visible
    if (($fieldValue === null) || ($fieldDetails["IS_SPECIAL"] && !in_array($fieldName, $arSpecialVisible))) {
      continue;
    }
    // Convert value (special fields)
    switch ($fieldName) {
      case "FK_MAN":
        $fieldDetails["IS_SET"] = ($fieldValue > 0 ? 1 : 0);
        $fieldDetails["ID_MANUFACTURER"] = (int)$fieldValue;
        $fieldValue = $fieldDetails["VALUE"] = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$fieldValue);
        break;
      default:
        break;
    }
    // Convert value (default types)
    switch ($fieldDetails["F_TYP"]) {
      case "LIST":
        if ($fieldValue > 0) {
          $fieldValue = $fieldDetails["VALUE"] = $db->fetch_atom("
            SELECT V1
            FROM liste_values l
              LEFT JOIN string_liste_values s ON
              s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
              s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
            WHERE l.FK_LISTE=".$fieldDetails["FK_LISTE"]." AND l.ID_LISTE_VALUES=".(int)$fieldValue);
          $fieldDetails["IS_SET"] = 1;
        } else {
          $fieldDetails["IS_SET"] = 0;
        }
        break;
      case "MULTICHECKBOX":
      case "MULTICHECKBOX_AND":
        $checkIdValues = trim($fieldValue, "x");
        $fieldDetails["IS_SET"] = 0;
        if ($checkIdValues != "") {
          $arCheckValues = explode("x", $checkIdValues);
          $arCheckNames = $db->fetch_nar(
              "SELECT sl.V1 FROM `liste_values` l
                    LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
                      AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                    WHERE l.ID_LISTE_VALUES IN (".mysql_real_escape_string(implode(", ", $arCheckValues)).")");
          if (!empty($arCheckNames)) {
              $fieldDetails["VALUE"] = implode(", ", array_keys($arCheckNames));
              $fieldDetails["IS_SET"] = 1;
          }
        }
        break;
      case "DATE":
        $timeValue = strtotime($fieldValue);
        $fieldDetails['IS_SET'] = ($timeValue !== false ? 1 : 0);
        $fieldDetails["VALUE"] = date("d.m.Y", $timeValue);
        break;
      case "DATE_MONTH":
        $timeValue = strtotime($fieldValue."-01");
        $fieldDetails['IS_SET'] = ($timeValue !== false ? 1 : 0);
        $fieldDetails["VALUE"] = date("m.Y", $timeValue);
        break;
      case "DATE_YEAR":
        $timeValue = $fieldValue;
        $fieldDetails['IS_SET'] = ($fieldValue > 0 ? 1 : 0);
        $fieldDetails["VALUE"] = $fieldValue;
        break;
      case "HTMLTEXT":
        $fieldDetails['IS_SET'] = 0;
        $fieldDetails["VALUE"] = $fieldValue;
        if ($articleShowHtmlFields) {
          $arArticleFieldsHtml[] = $fieldDetails;
        }
        break;
      default:
        $fieldDetails["VALUE"] = $fieldValue;
        $fieldDetails["IS_SET"] = ($fieldDetails["VALUE"] == "" ? 0 : 1);
        break;
    }
    // Add to grouped array
    if (!array_key_exists($idGroup, $arArticleFieldsGrouped)) {
      $arArticleFieldsGrouped[$idGroup] = array();
    }
    $arArticleFieldsGrouped[$idGroup][] = $fieldDetails;
  }
}

$arArticleFieldsTpl = array();

if (!empty($arArticleFieldsGrouped[0])) {
	$tplGroupGeneral = new Template("tpl/".$s_lang."/marktplatz_anzeige.group.htm");
	$tplGroupGeneral->addlist_fast('liste', $arArticleFieldsGrouped[0], 'tpl/'.$s_lang.'/marktplatz_anzeige.group.row.htm');
	$arArticleFieldsTpl[] = $tplGroupGeneral;
	unset($arArticleFieldsGrouped[0]);
}
$arFieldGroupIds = array_keys($arArticleFieldsGrouped);
if (!empty($arFieldGroupIds)) {
	$arFieldGroupList = $db->fetch_table("
		SELECT t.ID_FIELD_GROUP, s.V1
		FROM `field_group` t
		LEFT JOIN string_app s ON s.S_TABLE='field_group' AND s.FK=t.ID_FIELD_GROUP
			AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
		WHERE ID_FIELD_GROUP IN (".implode(",", $arFieldGroupIds).")
		ORDER BY t.F_ORDER");
	
	foreach ($arFieldGroupList as $groupIndex => $groupDetails) {
		$tplGroup = new Template("tpl/".$s_lang."/marktplatz_anzeige.group.htm");
		$tplGroup->addvars($groupDetails);
		$tplGroup->addlist_fast('liste', $arArticleFieldsGrouped[$groupDetails['ID_FIELD_GROUP']], 'tpl/'.$s_lang.'/marktplatz_anzeige.group.row.htm');
		$arArticleFieldsTpl[] = $tplGroup;
	}
}
if (!empty($arArticleFieldsHtml)) {
	foreach ($arArticleFieldsHtml as $fieldIndex => $fieldDetails) {
		$tplHtmlField = new Template("tpl/".$s_lang."/marktplatz_anzeige.fields.row_htmltext.htm");
		$tplHtmlField->addvars($fieldDetails);
		$arArticleFieldsTpl[] = $tplHtmlField;
	}
}

$tpl_content->addvar("fields", $arArticleFieldsTpl);
#$tpl_content->addlist("VARIANTS", $arArticleFieldsVariant, 'tpl/'.$s_lang.'/marktplatz_anzeige.variant.htm', 'variantFields');
<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $ab_path, $nar_systemsettings;

// Prüfung ob Profil ausgefüllt
if (empty($user["VORNAME"]) || empty($user["NACHNAME"]) || empty($user["STRASSE"]) || empty($user["PLZ"]) || empty($user["ORT"])) {
    $tpl_content->addvar("error_noaddress", 1);
    if (empty($user["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
    if (empty($user["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
    if (empty($user["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
    if (empty($user["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
    if (empty($user["ORT"])) $tpl_content->addvar("error_addr_city", 1);
    return;
}

require_once $ab_path."sys/lib.ad_create.php";
require_once $ab_path."sys/lib.shop_kategorien.php";
require_once $ab_path."sys/lib.pub_kategorien.php";
require_once $ab_path."sys/lib.youtube.php";
require_once $ab_path."sys/lib.hdb.php";

$id_article = ($ar_params[1] > 0 ? (int)$ar_params[1] : (int)$_REQUEST["ID_AD"]);
// Set step options
$arStepOptions = array(
    'new'   => ($id_article > 0 ? 0 : 1),
    'free'  => ($nar_systemsettings['MARKTPLATZ']['FREE_ADS'] ? true : false)
);
$arGroupOptions = array();
if ($tpl_content->tpl_has_permission("article_affiliate,C")) {
    $arGroupOptions["affiliateLink"] = true;
}
// Initialize class
$adCreate = new AdCreate($db, ($uid > 0 ? $uid : null), null, $arStepOptions, $arGroupOptions);
$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);
$categoriesBase = new CategoriesBase();


if ($_REQUEST["mode"] == "ajax") {
    switch ($_REQUEST['do']) {
        case 'getStep':
            $content = $adCreate->renderStepContent((int)$_REQUEST['index']);
            $arJavascripts = Template_Helper_ResourceLoader::getJavascriptSources();
            $arTemplateBlocks = array(
              "script" => implode("\n", $arJavascripts)
            );
            $content = Template_Extend_TemplateBlockExtender::extendTemplateCode($content, $arTemplateBlocks);
            
            header('Content-type: application/json');
            die(json_encode(array(
                "list"      => $adCreate->renderStepList((int)$_REQUEST['index']),
                'content'   => $content,
                'maximized' => $adCreate->isStepMaximized((int)$_REQUEST['index']),
                'success'   => true
            )));
        case 'getMediaUsage':
            header('Content-type: application/json');
            die(json_encode(
                $adCreate->getMediaUsage()
            ));
        case 'submitStep':
            $done = false;
            $errors = $adCreate->submitStep($_POST, $done);
            header('Content-type: application/json');
            if (!$done) {
                die(json_encode(array(
                    'success'   => !is_array($errors),
                    'done'      => $done,
                    'errors'    => (is_array($errors) ? $errors : array())
                )));
            } else {
                die(json_encode(array(
                    'success'   => !is_array($errors),
                    'done'      => $done,
                    'url'       => $adCreate->getFinishUrl($tpl_content)
                )));
            }
        case 'kats':
            $show_paid = ($adCreate->isPacketPaid() ? 1 : 0);
            $kat = new TreeCategories("kat", 1);
            $id_kat = ($_REQUEST["root"] ? $_REQUEST["root"] : $kat->tree_get_parent());
            $id_root_kat = $kat->tree_get_parent($id_kat);
            die($adCreate->renderStepContent('category', array(
                "ROOT"          => ($_REQUEST["root"] ? $_REQUEST["root"] : $kat->tree_get_parent()),
                "ID_ROOT_KAT"   => $id_root_kat,
                "ID_KAT"        => $id_kat
            )));
            break;
        case 'imageVariants':
            $imageIndex = (int)$_POST["IMAGE_INDEX"];
            $imageVariants = $_POST;
            unset($imageVariants["IMAGE_INDEX"]);
            // Update image variants
            $success = $adCreate->setImageVariants($imageIndex, $imageVariants);
            header('Content-type: application/json');
            die(json_encode(array(
                'success'       => $success,
                'variantsJson'  => $_POST,
                'variantsText'  => $adCreate->getImageVariantsText($imageIndex)
            )));
            break;
        case 'upload':
            $success = false;
            $errors = array();
            if (isset($_REQUEST['action'])) {
                switch ($_REQUEST['action']) {
                    case 'image_default':
                        // Set default image
                        $adCreate->setImageDefault((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'images';
                        break;
                    case 'image_delete':
                        // Delete image
                        $adCreate->deleteImage((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'images';
                        break;
                    case 'image_rotate':
                        // Delete image
                        $adCreate->rotateImage((int)$_REQUEST["id"], (int)$_REQUEST["degree"]);
                        $_REQUEST['show'] = 'images';
                        break;
                    case 'document_delete':
                        // Delete image
                        $adCreate->deleteFile((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'documents';
                        break;
                    case 'video_delete':
                        // Delete video
                        $adCreate->deleteVideo((int)$_REQUEST["id"]);
                        $_REQUEST['show'] = 'videos';
                        break;
                }
            }
            if (isset($_FILES["image"])) {
                $arImage = array();
                $success = $adCreate->handleImageUpload($_FILES["image"], $errors, $arImage);
                $arResult = array('files' => array(), 'success' => false);
                if ($success) {
                    $strVariants = "";
                    if ($adCreate->isVariantArticle()) {
                        $arVariants = array();
                        $arImageVariantInputs = Ad_Marketplace::getVariantFields($adCreate->getAdTableId(), $adCreate->getCategoryId());
                        foreach ($arImageVariantInputs as $variantFieldIndex => $variantField) {
                            $arVariants[ $variantField["F_NAME"] ] = ""; 
                        }
                        $strVariants = json_encode($arVariants);
                    }
                    $arResult['success'] = true;
                    $arResult['files'][] = array(
                        'ID_AD'         => $arImage['FK_AD'],
                        'IMAGE_INDEX'   => $arImage['INDEX'],
                        'IMAGE_TYPE'    => $arImage['TYPE'],
                        'IMAGE_DATA'    => ($arImage['FK_AD'] == 0 ?
                                base64_encode( @file_get_contents($arImage['TMP_THUMB']) ) :
                                base64_encode( @file_get_contents($ab_path.$arImage['SRC_THUMB']) )
                            ),
                        'IMAGE_DEFAULT' => $arImage['IS_DEFAULT'],
                        'VARIANTS'      => htmlspecialchars($strVariants)
                    );
                } else {
                    $arResult['files'][] = array(
                        'ERRORS'        => $errors,
                        'IMAGE_INDEX'   => -1,
                        'IMAGE_TYPE'    => $_FILES["image"]['TYPE']
                    );
                }
                header('Content-type: application/json');
                die(json_encode($arResult));
            }
            if (isset($_FILES["document"])) {
                $arImage = array();
                $success = $adCreate->handleFileUpload($_FILES["document"], $errors, $arDocument);
                $arResult = array('files' => array(), 'success' => false);
                if ($success) {
                    $arResult['success'] = true;
                    $arResult['files'][] = array(
                        'ID_AD'         => $arDocument['FK_AD'],
                        'UPLOAD_INDEX'  => $arDocument['INDEX'],
                        'UPLOAD_TYPE'   => $arDocument['EXT'],
                        'UPLOAD_FILE'   => $arDocument['FILENAME']
                    );
                } else {
                    $arResult['files'][] = array(
                        'ERRORS'        => $errors,
                        'UPLOAD_INDEX'  => -1,
                        'UPLOAD_TYPE'   => $_FILES["image"]['TYPE']
                    );
                }
                header('Content-type: application/json');
                die(json_encode($arResult));
            }
            if (isset($_FILES["UPLOAD_FILE"])) {
                $success = $adCreate->handleFileUpload($_FILES["UPLOAD_FILE"], $errors);
                $_REQUEST['show'] = 'documents';
            }
            if (isset($_POST["youtube_url"])) {
                $success = $adCreate->handleVideoUpload($_POST["youtube_url"]);
                $_REQUEST['show'] = 'videos';
            }
            if (isset($_REQUEST['show'])) {
                switch ($_REQUEST['show']) {
                    case 'images':
                        die($adCreate->renderMediaImages());
                    case 'documents':
                        die($adCreate->renderMediaDownloads());
                    case 'videos':
                        die($adCreate->renderMediaVideos());
                }
            }
            die();
        case 'typeahead_manufacturer':
            if (array_key_exists("list", $_REQUEST)) {
                $manufacturerGroups = $db->fetch_atom("SELECT COUNT(*) FROM `man_group`");
                if ($manufacturerGroups) {
                    // Select by category
                    // Check global list
                    $count = $db->fetch_atom("
                        SELECT COUNT(*) FROM `manufacturers` m
                        JOIN `man_group_mapping` mg ON mg.FK_MAN=m.ID_MAN
                        JOIN `man_group_category` mc ON mg.FK_MAN_GROUP=mc.FK_MAN_GROUP
                        WHERE mc.FK_KAT=".(int)$adCreate->getCategoryId()." AND m.CONFIRMED=1");
                    $arResult = array(
                        "count"     => $count,
                        "list"      => null
                    );
                    if ($count < 1000) {
                        $arResult["list"] =  $db->fetch_table("
                          SELECT m.NAME as name, m.NAME as value
                          FROM `manufacturers` m
                          JOIN `man_group_mapping` mg ON mg.FK_MAN=m.ID_MAN
                          JOIN `man_group_category` mc ON mg.FK_MAN_GROUP=mc.FK_MAN_GROUP
                          WHERE mc.FK_KAT=".(int)$adCreate->getCategoryId()." AND m.CONFIRMED=1
                          ORDER BY m.NAME ASC");
                    }
                } else {
                    // Select globally
                    // Check global list
                    $count = $db->fetch_atom("
                        SELECT COUNT(*) FROM `manufacturers` m
                        WHERE m.CONFIRMED=1");
                    $arResult = array(
                        "count"     => $count,
                        "list"      => null
                    );
                    if ($count < 1000) {
                        $arResult["list"] =  $db->fetch_table("
                          SELECT m.NAME as name, m.NAME as value
                          FROM `manufacturers` m
                          WHERE m.CONFIRMED=1
                          ORDER BY m.NAME ASC");
                    }
                }
                header('Content-type: application/json');
                die(json_encode($arResult));
            } else {
                $manufacturerGroups = $this->db->fetch_atom("SELECT COUNT(*) FROM `man_group`");
                if ($manufacturerGroups) {
                    // Select by category
                    // Query searchahead
                    $list = $db->fetch_table("
                        SELECT
                            m.NAME as name,
                            m.NAME as value
                        FROM
                            `manufacturers` m
                        JOIN `man_group_mapping` mg ON mg.FK_MAN=m.ID_MAN
                        JOIN `man_group_category` mc ON mg.FK_MAN_GROUP=mc.FK_MAN_GROUP
                        WHERE
                            mc.FK_KAT=".(int)$adCreate->getCategoryId()." AND
                            m.NAME LIKE '".mysql_escape_string($_REQUEST["query"])."%' AND
                            m.CONFIRMED=1
                        ORDER BY m.NAME ASC
                        LIMIT 30");
                } else {
                    // Select globally
                    // Query searchahead
                    $list = $db->fetch_table("
                        SELECT
                            m.NAME as name,
                            m.NAME as value
                        FROM
                            `manufacturers` m
                        WHERE
                            m.NAME LIKE '".mysql_escape_string($_REQUEST["query"])."%' AND
                            m.CONFIRMED=1
                        ORDER BY m.NAME ASC
                        LIMIT 30");                    
                }
                header('Content-type: application/json');
                die(json_encode($list));
            }
        case 'typeahead_product':
            $categoryTable = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".(int)$adCreate->getCategoryId());
            $id_man = $db->fetch_atom("SELECT ID_MAN FROM `manufacturers` WHERE NAME='".mysql_escape_string($_REQUEST["man"])."'");
            $list = array();
            if ($id_man > 0) {
                $list = $db->fetch_table("
                    SELECT
                            p.PRODUKTNAME as name, p.PRODUKTNAME as value
                        FROM `hdb_table_".mysql_real_escape_string($categoryTable)."` p
                    WHERE ".( $id_man > 0 ? "p.FK_MAN=".$id_man." AND " : "")."
                            (p.PRODUKTNAME LIKE '%".mysql_escape_string($_REQUEST["query"])."%') AND p.CONFIRMED=1
                    LIMIT 30");
            }
            header('Content-type: application/json');
            die(json_encode($list));
        case 'typeahead_product_table':

            $searchCategory = ($adCreate->getCategoryId() > 0 ? $adCreate->getCategoryId() : NULL);
            $searchProduct = !empty($_REQUEST['search_hdb']) ? $_REQUEST['search_hdb'] : NULL;
            $searchEan = !empty($_REQUEST['search_ean']) ? $_REQUEST['search_ean'] : NULL;
            $curpage = isset($_REQUEST['curpage']) ? max((int)$_REQUEST['curpage'], 1) : 1;
            $perpage = 20;

            $tpl_resulttable = new Template("tpl/" . $s_lang . "/my-marktplatz-neu.hdb.resulttable.htm");

            if (strlen($searchEan) > 2 || strlen($searchProduct) > 2) {
                $categoryHashMap = $categoriesBase->getCategoryPathHashMap();
                $categoryHashMapById = $categoryHashMap['ID'];
                $productTypes = null;
                if ($searchCategory !== null) {
                    $categoryTable = $adCreate->getCustomAdData("AD_TABLE");
                    $productTypes = array(
                        $manufacturerDatabaseManagement->fetchProductTypeByTable($categoryTable)
                    );
                }

                if ($searchEan != NULL) {
                    $results = $manufacturerDatabaseManagement->searchProduct('EAN', $searchEan, $curpage, $perpage, 'EXACT', $productTypes, $searchCategory);
                } else {
                    $results = $manufacturerDatabaseManagement->searchProduct('FULL_PRODUKTNAME', $searchProduct, $curpage, $perpage, 'LIKE', $productTypes, $searchCategory);
                }

                foreach ($results->results as $key => $product) {
                    if (!empty($product['IMPORT_IMAGES'])) {
                        $arImages = unserialize($product['IMPORT_IMAGES']);
                        $results->results[$key]['IMPORT_IMAGES'] = $arImages[0];                        
                    }
                    if (!empty($product['FK_KAT']) && isset($categoryHashMapById[$product['FK_KAT']])) {
                        $results->results[$key]['CATEGORY_NAME'] = $categoryHashMapById[$product['FK_KAT']]['V1'];
                    }
                }

                $tpl_resulttable->addlist("liste", $results->results, "tpl/" . $s_lang . "/my-marktplatz-neu.hdb.resulttable.row.htm");

                $tpl_resulttable->addvar("SEARCH_PRODUCT", $searchProduct);
                $tpl_resulttable->addvar("SEARCH_EAN", $searchEan);
                $tpl_resulttable->addvar("USE_EAN", $nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']);

                $tpl_resulttable->addvar("pager", htm_browse_extended($results->total, $curpage, 'placeholder', $perpage, 5, "javascript:searchProducts('{PAGE}')"));
                $tpl_resulttable->addvar("ALL", $results->total);

            } else {
                $tpl_resulttable->addvar('err', 1);
                $tpl_resulttable->addvar('err_too_short', 1);
            }


            echo $tpl_resulttable->process();


            die();

        case 'validate':
            header('Content-type: application/json');
            die(json_encode($adCreate->validateField($_REQUEST['name'], $_REQUEST['value'])));
        default:
            // TODO: Remove fallback
            include "my-marktplatz-neu-ajax.php";
            break;
    }
}

if ($id_article > 0) {
    // Load existing article from database
    $adCreate->loadFromDatabase($id_article);
} else {
    // Create new article
    $adCreate->clearArticle();
}
$stepIndex = 0;
$tpl_content->addvar("STEPS_INDEX", $stepIndex);
$tpl_content->addvar("STEPS_LIST", $adCreate->renderStepList($stepIndex));
$tpl_content->addvar("STEPS_CUR", $adCreate->renderStepContent($stepIndex));

return;

?>

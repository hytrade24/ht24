<?php
/* ###VERSIONSBLOCKINLCUDE### */
$vendorStep = new Form_Steps_Vendor();
if (!empty($_POST)) {
    $arResponse = $vendorStep->handleRequest($_POST, $_FILES);
    if (array_key_exists("ajax", $_POST)) {
        header("Content-Type: application/json");
        die(json_encode($arResponse));
    } else {
        $tpl_content->tpl_text = $vendorStep->render($arResponse["STEP_NEXT"]);
    }
} else {
    $vendorStep->loadFromDatabase($uid);
    $tpl_content->tpl_text = $vendorStep->render(0);
}

?>

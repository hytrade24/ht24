<?php

if ( !empty($_POST["id_content_info_bereiche"]) ) {

    $id_info_seite = (int)$_POST["id_content_info_bereiche"];
    $contentPageManagement = Api_ContentPageManagement::getInstance($db);

    if ( $_POST["type"] == "get" ) {

        $arContentPage = $contentPageManagement->getContentPageById($id_info_seite);

        $returnedData = new stdClass();
        $returnedData->success = true;

        $returnedData->data = $arContentPage;

        die( json_encode($returnedData) );

    }
    else if ( $_POST["type"] == "save" ) {

        $arContentPage = $contentPageManagement->getContentPageById($id_info_seite);
        $arContentPage["BF_LANG_INFO"] = $langval;
        $arContentPage["T1"] = $_POST['T1'];

        $check = $db->update( "infoseite", $arContentPage );

        if ( $check ) {
            $contentPageManagement->cacheContentPage($arContentPage);

            $data->success = true;
            $data->id_content_info_bereiche = $id_info_seite;
            $data->msg = "Content Successfully updated";
            //$data->content = $value;
            die( json_encode($data) );
        }

    }





}
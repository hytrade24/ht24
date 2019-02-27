<?php

if ( $_SESSION["USER_IS_ADMIN"] ) {
    $return_data = new stdClass();
    $return_data->success = false;

    $fileSrc = '';
    $cacheSrc = '';

    if ( $_POST["file"] == "user-stylesheet.htm" ) {
        $fileSrc = "cache/design/resources/".$GLOBALS["s_lang"]."/css/user.css";
        $cacheSrc = "resources/de/css/user.css";
    }
    else {
        $fileSrc = $_POST["file"];
        $cacheSrc = explode("/",$fileSrc);
        unset($cacheSrc[0]);
        unset($cacheSrc[1]);
        $cacheSrc = implode("/",$cacheSrc);
    }

    $templateFile = CacheTemplate::getSourceFile( $fileSrc );
    $templateFileRelative = str_replace($ab_path, "/", $templateFile);
    $fname = $templateFile;

    if ( $_POST["type"] == "get" ) {

        $content = file_get_contents($fname);

        $data = new stdClass();
        $data->content = $content;
        $data->fileName = $cacheSrc;
        $data->fileNameRel = $templateFileRelative;

        $return_data->data = $data;
        $return_data->success = true;

    }
    else if ( $_POST["type"] == "save" ) {
        if (strpos($fname, $ab_path."design/default/") !== false) {
            $fname = str_replace($ab_path."design/default/", $ab_path."design/user/", $fname);
        }
        $data = new stdClass();
        $fp = @fopen($fname,"w");
        if ( !$fp ) {
            $data->msg = "Cache Error! File unwriteable " . $fname;
            $return_data->data = $data;
            die(json_encode($return_data));
        }
        @fwrite($fp, $_POST["T1"]);
        @fclose($fp);
        chmod($fname, 0777);

        $data->msg = "File has been successfully updated";
        $return_data->data = $data;
        $return_data->success = true;

    }

    $cacheNewVersion = new CacheTemplate();
    $cacheNewVersion->cacheFile( $cacheSrc );
    die(json_encode($return_data));
}
<?php
/* ###VERSIONSBLOCKINLCUDE### */

$msg = null;
$success = false;
$img_link = null;

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

if ( isset($_FILES['file']) ) {

    $cache = 'cache/users/'.$userdata["CACHE"].'/'.$uid.'/banner/';
    $target_dir = $ab_path . $cache;

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . basename($_FILES["file"]["name"]);
    $uploadOk = 1;
    $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
    // Check if image file is a actual image or fake image

    $check = getimagesize($_FILES["file"]["tmp_name"]);
    if($check !== false) {
        //echo "File is an image - " . $check["mime"] . ".";
        $uploadOk = 1;
    } else {
        $msg = "File is not an image.";
        $uploadOk = 0;
    }

    // Check if file already exists
    if (file_exists($target_file)) {
        unlink( $target_file );
        //$msg = "Sorry, file already exists.";
        //$uploadOk = 0;
    }

    // Check file size
    if ($_FILES["file"]["size"] > 2621440 ) {
        $msg = "Sorry, your file is too large.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "JPG" && $imageFileType != "PNG" && $imageFileType != "png"
        && $imageFileType != "jpeg" && $imageFileType != "JPEG"
        && $imageFileType != "gif" && $imageFileType != "GIF" ) {
        $msg = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        //$msg = "Sorry, your file was not uploaded.";
    // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            $msg = "The file ". basename( $_FILES["file"]["name"]). " has been uploaded.";
            $success = true;
            $img_link = '/'.$cache .basename($_FILES["file"]["name"]);

            $img_link = $protocol . $_SERVER['HTTP_HOST'] . $img_link;

        } else {
            $msg = "Sorry, there was an error uploading your file.";
        }
    }

    $data = new stdClass();
    $data->success = $success;
    $data->msg = $msg;
    $data->img_link = $img_link;

    die( json_encode($data) );

}
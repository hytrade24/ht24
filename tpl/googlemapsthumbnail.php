<?php
if (!empty($_POST)) {
    header('Content-type: application/json');

    $json = array(
        'success' => false,
        'src' => null,
        'error' => false,
    );

    if (!isset($_POST['SRC'])) {
        $json['error'] = "No image source indicated";
        die(json_encode($json));
    }

    if (!isset($_POST['WIDTH']) || !isset($_POST['HEIGHT'])) {
        $json['error'] = "No dimensions indicated";
        die(json_encode($json));
    }

    $json['thumbnail'] = $tpl_content->tpl_thumbnail('"' . $_POST['SRC'] . '",' . $_POST['WIDTH'] . ',' . $_POST['HEIGHT']);
    $json['success'] = true;

    die(json_encode($json));
}


<?php

if (!empty($_POST)) {
  $arClientDetails = array(
    'plugins=' . urlencode($_POST['plugins']),
    'mimeTypes=' . urlencode($_POST['mimeTypes']),
    'screeResolution=' . urlencode($_POST['screen_resolution']),
  );
  Tools_UserStatistic::getInstance()->create_client_hash($arClientDetails);
  die();
}
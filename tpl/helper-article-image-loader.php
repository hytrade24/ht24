<?php

session_write_close();

$result = array();

$loaderResult = Template_Helper_ArticleImageLoader::loadImagesForArticleByArticleId($_REQUEST['ID_AD']);
if($loaderResult != false) {
	$result['success'] = true;
	$result['image'] = $tpl_content->tpl_thumbnail_article(''.$_REQUEST['ID_AD'].',"'.$loaderResult.'",'.$_REQUEST['WIDTH'].','.$_REQUEST['HEIGHT'].','.($_REQUEST['CROP']==1?'crop':''));
} else {
	$result['success'] = false;
}


die(json_encode($result));
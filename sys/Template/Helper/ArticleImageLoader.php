<?php

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.image.php';

class Template_Helper_ArticleImageLoader {


	static function renderArticleImageLoader($htmlId, $articleId, $width, $height, $crop = false) {
		global $db, $s_lang;


		$tpl = new Template('tpl/'.$s_lang.'/helper.articleimageloader.htm');

		$tpl->addvar('HTML_ID', $htmlId);
		$tpl->addvar('AD_ID', $articleId);
		$tpl->addvar('WIDTH', $width);
		$tpl->addvar('HEIGHT', $height);
		$tpl->addvar('CROP', $crop);
		$tpl->addvar('RAND', md5(microtime(true)));


		return $tpl->process();
	}


	static function loadImagesForArticleByArticleId($articleId) {
		global $db;

		$adWithoutImage = $db->fetch1("SELECT ID_AD_MASTER, AD_TABLE, IMPORT_IMAGES FROM ad_master	WHERE ID_AD_MASTER = '".(int)$articleId."' AND IMPORT_IMAGES IS NOT NULL AND IMPORT_IMAGES != ''");
		if($adWithoutImage == null) {
			// Get current default image if there is one, otherwise return false.
			$currentMainImage = $db->fetch_atom("SELECT SRC FROM `ad_images` WHERE FK_AD=".(int)$articleId." AND IS_DEFAULT=1");
			return (!$currentMainImage ? false : $currentMainImage);
		}

		$images = unserialize($adWithoutImage['IMPORT_IMAGES']);
		if($images != null && is_array($images)) {
			return self::loadImagesForArticle($articleId, $adWithoutImage['AD_TABLE'], $images);
		}

		return false;
	}

	static function loadImagesForArticle($articleId, $articleTable, $images) {
		global $ab_path, $db;

		if($images == null || count($images) == 0) {
			return false;
		}
		
		// Delete all images of this article
		AdManagment::deleteArticleImages($db, $articleId);
		
		// Initialize variables for downloading
		$uploads_dir = AdManagment::getAdCachePath($articleId, TRUE, TRUE);
		$image_data = array();
		$image_done = array();
		$mainImage = null;

		$context = stream_context_create(array(
				'http' => array(
						'method' => "GET", 'timeout' => 5
				)
		));

		// Download the images
		$i = 0;
		foreach ($images as $key => $imageUrl) {
            $imageMeta = array();
            if (is_array($imageUrl)) {
                list($imageUrl, $imageMeta["TITLE"]) = $imageUrl;
            }
			if (in_array($imageUrl, $image_done)) {
				continue;
			}
			$image_done[] = $imageUrl;
			$file_url = file_url(trim($imageUrl));
			if (strpos($file_url, "https://") !== false) {
				if (file_exists($ab_path."/lib/cacert.pem")) {
					stream_context_set_option($context, "ssl", "cafile", $ab_path."/lib/cacert.pem");
				} else {
					stream_context_set_option($context, "ssl", "verify_peer", false);
					stream_context_set_option($context, "ssl", "verify_peer_name", false);
					stream_context_set_option($context, "ssl", "allow_self_signed", true);
				}
			} else if (strpos($file_url, "://") === 0) {
			    $file_url = ltrim(substr($file_url, 3), "/");
			    if (strpos($file_url, $ab_path) !== 0) {
			        $file_url = $ab_path.$file_url;
                }
            }
			$file_get = file_get_contents($file_url, NULL, $context);
			if ($file_get !== false) {
				if($key == 0) {
					system('rm -f '.$uploads_dir.'/*.jpg');
				}

				file_put_contents($tmp_name = $uploads_dir . '/tmp', $file_get);
				$name = 'imported.jpg';

				$img_thumb = new image(12, $uploads_dir, TRUE);
				$img_thumb->check_file(array("tmp_name" => $tmp_name, "name" => $name));
				$src = "/" . str_replace($ab_path, "", $img_thumb->img);
				$src_thumb = "/" . str_replace($ab_path, "", $img_thumb->thumb);
				
				// TODO: Move into image conversion class and force conversion to jpeg
				if (!file_exists($ab_path.$src)) {
					rename($uploads_dir."/tmp", $ab_path.$src);
				} else {
					unlink($uploads_dir."/tmp");
				}

				if($i == 0) {
					$mainImage = $src;
				}

                $ser_meta = serialize($imageMeta);
				$image_data[] = "('" . $articleId . "',1," . ($i == 0 ? 1 : 0) . ",'" . mysql_real_escape_string($src) . "','" . mysql_real_escape_string($src_thumb) . "','" . mysql_real_escape_string($ser_meta) . "')";
				$i++;
			}
		}

		// Remove image urls from article data
		$db->querynow("UPDATE ad_master SET IMPORT_IMAGES = NULL WHERE ID_AD_MASTER = '".(int)$articleId."' ");
		$db->querynow("UPDATE $articleTable SET IMPORT_IMAGES = NULL WHERE ID_".strtoupper($articleTable)." = '".(int)$articleId."' ");
		
		if (count($image_data) > 0) {
			// Add downloaded images to the article
			$db->querynow("INSERT INTO ad_images (FK_AD, CUSTOM, IS_DEFAULT, SRC, SRC_THUMB, SER_META) VALUES " . implode(',', $image_data));

			return $mainImage;
		}

		return false;

	}

}
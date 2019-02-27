<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * Klasse zum einbinden von Youtube-Videos
 * 
 * @author Jens
 *
 */
class Youtube {
	
	public static $allowUpload = true;
	
	/**
	 * Extrahiert den Code (quasi die Video-ID) aus einem Youtube link
	 * 
	 * @param 	string $url		Der Link zu einem Youtube-Video
	 * @return	string|bool		Den Code für das Video oder false bei Fehler.
	 */
	public static function ExtractCodeFromURL($url) {
		if (preg_match('/youtu.be\/([A-Za-z0-9-_]+)(\&|$)/i', $url, $matches)) {
			return $matches[1];
		}
		if (preg_match('/youtube.com\/watch\?.*v=([A-Za-z0-9-_]+)(\&|$)/i', $url, $matches)) {
			return $matches[1];
		}

        return null;
	}
	
	public static function UploadVideo($videoFilename) {
		
	}

	public static function GenerateInput($name, $size, $class, $button, $target = "ad_master")
	{
		if (empty($target)) {
			$target = "ad_master";
		}
		$videoInputParam = new Api_Entities_EventParamContainer(array(
			"name" 		=> $name,
			"size"		=> $size,
			"class" 	=> $class,
			"button"	=> $button,
			"target"	=> $target,
			"result"	=> null
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::VIDEO_UPLOAD_INPUT, $videoInputParam);
		if ($videoInputParam->isDirty() && ($videoInputParam->getParam("result") !== null)) {
			// Custom input
			return $videoInputParam->getParam("result");
		} else {
			// Default input
			$file = "youtube_input"; // <-- Name des Templates
			$tpl_tmp = new Template("tpl/".$GLOBALS["s_lang"]."/".$file.".htm");
			$tpl_tmp->addvars($videoInputParam->getParams());
			// HTML ausgeben
			return $tpl_tmp->process();
		}
		
	}
}

?>
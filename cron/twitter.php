<?php
/* ###VERSIONSBLOCKINLCUDE### */



include_once $ab_path."lib/tmhOAuth.php";

/* make a URL small */
function make_bitly_url($url,$login,$appkey,$format = 'xml',$version = '2.0.1') {
	//create the URL
	$bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$appkey.'&format='.$format;

	//get the url
	//could also use cURL here
	$response = file_get_contents($bitly);

	//parse depending on desired format
	if(strtolower($format) == 'json') {
		$json = @json_decode($response,true);
		return $json['results'][$url]['shortUrl'];
	} else {
		$xml = simplexml_load_string($response);
		return 'http://bit.ly/'.$xml->results->nodeKeyVal->hash;
	}
}

function chtrans2($s) {
	static $nar = array (
      '/&(\w)uml;/' => '$1e',
      '/&(\w)(ague|circumflex|grave);/' => '$1',
      '/&szlig;/' => 'ss',
      '/&[a-z]+;/i' => '',
      '/&/' => '+',
      '/\s/' => '-',
      '/\./' => '-',
      '/[^\w+-]/' => '',
      '/^[_+-]+/' => '',
      '/[_+-]+$/' => ''
      );

      $s = stdHtmlentities($s);
      foreach($nar as $from => $to)
      $s = preg_replace($from, $to, $s);
      return $s;
}

function post_ad_twitter($id, $msg) {
	global $nar_systemsettings, $db, $ab_path;
	if(!is_null($msg) && $id > 0) {
        $urltext = chtrans($msg);
        #$link = 'http://g002.de/'.$msg_type.'/'.$id; //,'.$urltext.'.htm';
        $link = $nar_systemsettings['SITE']['SITEURL'].'/marktplatz/marktplatz_anzeige,'.$id.','.chtrans2($msg).'.htm';
        if($nar_systemsettings['NETWORKS']['TWITTER_USE_BITLY'] == 1) {
        	$link = make_bitly_url($link, $nar_systemsettings['NETWORKS']['TWITTER_BITLY_LOGIN'], $nar_systemsettings['NETWORKS']['TWITTER_BITLY_SECRET'],'json');
        }
        $rest_len = (140-strlen($link));
        $txt = substr($msg, 0, ($rest_len-2));
        if($msg != $txt) {
                $txt = substr($txt, 0, (strlen($txt)-4)).' ...';
        }
        $msg = $link."\n".$txt;//.$link;

        $tmhOAuth = new tmhOAuth(array(
          'consumer_key'    => $nar_systemsettings['NETWORKS']['TWITTER_CONSUMER_KEY'],
          'consumer_secret' => $nar_systemsettings['NETWORKS']['TWITTER_CONSUMER_SECRET'],
          'user_token'      => $nar_systemsettings['NETWORKS']['TWITTER_USER_TOKEN'],
          'user_secret'     => $nar_systemsettings['NETWORKS']['TWITTER_USER_SECRET'],
        ));
				$params = array(
          'status' => $msg,
        );

				$imageUrl = null;
				$arImage = $db->fetch1("SELECT * FROM `ad_images` WHERE FK_AD=".(int)$id." AND IS_DEFAULT=1");
				if (is_array($arImage)) {
					$imageUrl = $ab_path.substr($arImage["SRC"], 1);
					$paramsMedia = array(
						'media_data' => base64_encode(file_get_contents($imageUrl))
					);
					$tmhOAuth->request('POST', 'https://upload.twitter.com/1.1/media/upload.json', $paramsMedia);
					$arMediaResponse = json_decode($tmhOAuth->response['response'], true);
					$params['media_ids'] = $arMediaResponse['media_id_string'];
					#eventlog("error", "DEBUG Twitter!", "Ergebnis: ".var_export($statusMedia, true)."\Bild: ".$image);
				}
		
        $tmhOAuth->request('POST', $tmhOAuth->url('1.1/statuses/update','json'), $params);

        if ($tmhOAuth->response['code'] == 200) {
        	return $tmhOAuth->response;
        } else {
        	return false;
        }
	}
}

global $nar_systemsettings, $db, $ab_path;
if($nar_systemsettings['NETWORKS']['TWITTER_AKTIV'] == 1) {
	$path = $ab_path.'cache/marktplatz/twitter_last.txt';
	$last = @filemtime($path);

	if(!$last) {
		file_put_contents($path, "First Time Twitter-Cron started\n\nOnly ads after ".date('Y-m-d H:i:s')." will be posted to twitter!");
		chmod($path, 0777);
	} else {
		$ret = array();
		$date = date('Y-m-d H:i:s', $last);
		$liste = $db->fetch_table("
			select
				PRODUKTNAME,
				ID_AD_MASTER
			from
				ad_master
			where
				(STATUS&3)=1 AND (DELETED=0)
				and STAMP_START>'".$date."'
			ORDER BY STAMP_START ASC
			LIMIT 1 ");
		for($i=0; $i<count($liste); $i++) {
            echo "post to twitter ".$liste[$i]['ID_AD_MASTER']."<br>";
			$ret[] = dump(post_ad_twitter($liste[$i]['ID_AD_MASTER'], $liste[$i]['PRODUKTNAME']));
		}
		file_put_contents($path, "Twitter Logg:\n\n".implode("\n", $ret));
		chmod($path, 0777);
	}
}

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



class EbizTraderVersion {

    public static function getVersion() {
        if(preg_match("/^([0-9]+)\.([0-9]+)\.([0-9]+)/", EBIZ_TRADER_VERSION) > 0) {
            return EBIZ_TRADER_VERSION;
        } else {
            return "0.0.0";
        }
    }

    public static function getLatestRelease() {
		$opts = array(
		  'http'=>array(
		    'method'=>"GET",
			'timeout' => 5
		  )
		);

		$context = stream_context_create($opts);

        $current = json_encode(array(
            "version"   => self::getVersion(),
            "site_name" => $GLOBALS['nar_systemsettings']['SITE']['SITENAME'],
            "site_url"  => $GLOBALS['nar_systemsettings']['SITE']['SITEURL']
        ));
        $release = @file_get_contents("http://ebiz-trader.de/user/releases_last.htm?host=".$_SERVER['SERVER_NAME']."&ip=".$_SERVER['SERVER_ADDR']."&current=".urlencode($current), false, $context);
        if($release != "") {
            $release = json_decode($release, true);

            return $release;
        }

        return array('version' => "0.0.0", 'date' => '0000-00-00');
    }

    public static function compareCurrentVersionWith($compareVersion) {
        $currentVersionSplit = self::splitVersionToArray(self::getVersion());
        $compareVersionSplit = self::splitVersionToArray($compareVersion);

        for($i = 0; $i < max(count($currentVersionSplit), count($compareVersionSplit)); $i++) {
            if($currentVersionSplit[$i] < $compareVersionSplit[$i]) {
                return 1;
            } elseif($currentVersionSplit[$i] > $compareVersionSplit[$i]) {
                return -1;
            }
        }

        return 0;
    }


    private static function splitVersionToArray($version) {
        preg_match("/^([0-9]+)\.([0-9]+)\.([0-9]+)\.?(.*)$/", $version, $tmpCompareVersionSplit);

        return array($tmpCompareVersionSplit[1], $tmpCompareVersionSplit[2], $tmpCompareVersionSplit[3], $tmpCompareVersionSplit[4]);
    }
}
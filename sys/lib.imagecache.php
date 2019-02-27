<?php
/* ###VERSIONSBLOCKINLCUDE### */



class Hostbar_Imagecache {

    protected static $failRetryTime = 3600;
	protected $cachePath = '/cache/img';

    function cache($source, $width = 100, $height = 100, $crop = false, $gravity = "Center", $fallback = null) {
        if ($fallback === null) {
            $fallback = '/cache/design/resources/'.$GLOBALS["s_lang"]."/images/marketplace/nopic.jpg";
        }
        $absPath = $GLOBALS["ab_path"];
        $cachePath = $this->cachePath;
        $cachePathAbsolute = $absPath.ltrim($cachePath, "/");
        $baseurl = '';
        $source = (preg_match('/^https?:\/\//i', $source) ? $source : $absPath . $source);
        
        if ($this->__isThumbNail($source, $cachePathAbsolute, $width, $height, $crop, $gravity)) {
            return $this->__getThumbNail($source, $baseurl . $cachePath, $width, $height, $crop, $gravity);
        } else {
            if ($this->__createThumbNail($source, $this->__getThumbNail($source, $cachePathAbsolute, $width, $height, $crop, $gravity), array(
                'width' => $width, 'height' => $height, 'crop' => $crop, 'gravity' => $gravity
            ))) {
                return $this->__getThumbNail($source, $cachePathAbsolute, $width, $height, $crop, $gravity);
            } else {
                return $fallback;
            }
        }
    }

    function __isThumbNail($source, $cachePath, $width, $height, $crop, $gravity = "Center") {
        $hash = $this->__getHash($source);
        return (is_file($cachePath . '/' . $hash . $width . $height . ($crop ? 'c' : '') . ($gravity == "Center" ? "" : $gravity) . '.jpg'));
    }

    function __getThumbNail($source, $cachePath, $width, $height, $crop = false, $gravity = "Center") {
        $hash = $this->__getHash($source);
        return $cachePath . '/' . $hash . $width . $height . ($crop ? 'c' : '') . ($gravity == "Center" ? "" : $gravity) . '.jpg';
    }

    function __createThumbNail($source, $dest, $config)
    {
        global $nar_systemsettings;
        //ini_set("memory_limit", "64M");

        $thumb['height'] = $config['height'];
        $thumb['width'] = $config['width'];

        $binConvert = $nar_systemsettings['SYS']['PATH_CONVERT'];
        if (!file_exists($dest)) {
            if (preg_match('/^https?:\/\//i', $source)) {
                // Http link
                if (file_exists($dest.".failed")) {
                    $failTimeGone = time() - filectime($dest.".failed"); 
                    if ($failTimeGone < static::$failRetryTime) {
                        return false;
                    } else {
                        // Retry!
                        unlink($dest.".failed");
                    }
                }
                $curl = curl_init($source);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                $sourceData = curl_exec($curl);
                $sourceReply = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if (($sourceReply < 200) || ($sourceReply >= 300)) {
                    touch($dest.".failed");
                    return false;
                }
                file_put_contents($dest, $sourceData);
                curl_close($curl);
            } else {
                // Local file
                copy($source, $dest);
            }
            $source = $dest;
        }

        $cmd = $binConvert . ' "' . $source . '" -background white -alpha remove -alpha off ';
        if (!isset($config['crop']) OR $config['crop'] == false) {
            $cmd .= ' -flatten -thumbnail ' . $thumb['width'] . 'x' . $thumb['height'] . '\> ';
        } else {
            if ($config['crop'] == "width") {
                $cmd .= ' -flatten -resize "' . $thumb['width'] . '^x' . $thumb['height'] . '" -gravity ' . $config['gravity'] . ' -crop ' . $thumb['width'] . 'x' . $thumb['height'] . '+0+0 +repage ';
            } else {
                $cmd .= ' -flatten -resize "' . $thumb['width'] . 'x' . $thumb['height'] . '^" -gravity ' . $config['gravity'] . ' -crop ' . $thumb['width'] . 'x' . $thumb['height'] . '+0+0 +repage ';
            }
        }
        $cmd .= ' "' . $dest . '" 2>&1';

        exec($cmd, $o);
        return file_exists($dest);
        #var_dump($cmd, $o); die();
    }

    function __getHash($source) {
        if (is_file($source))
            return md5_file($source);
        return md5($source);
    }

	public function setCachePath($cachePath) {
		$this->cachePath = $cachePath;
	}
}
<?php

class Cache_Upload {
    
    private static $lifetime = 7;
    private static $lottery = [ 1, 100 ];
    
    private $path;
    
    public function __construct($path = null, $autoCreate = true) {
        if ($path === null) {
            $path = $GLOBALS["ab_path"];
        }
        $this->path = rtrim($path, "/");
        if ($autoCreate && !is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
        $this->lottery();
    }

    /**
     * Add a new upload to the cache directory
     * @param string $fileTemp
     * @param string $fileName
     * @return bool|string
     */
    public function addFileUpload($fileTemp, $fileName) {
        $arFileInfo = pathinfo($fileName);
        $fileExtension = $arFileInfo["extension"];
        $fileHash = md5(microtime());
        $fileRelative = $fileHash.".".$fileExtension;
        $fileAbsolute = $this->path."/".$fileRelative;
        if (move_uploaded_file($fileTemp, $fileAbsolute)) {
            return $fileRelative;
        }
        return false;
    }

    /**
     * Delete an existing file
     * @param string $fileRelative
     * @return bool
     */
    public function deleteFile($fileRelative) {
        $fileAbsolute = $this->path."/".$fileRelative;
        if (file_exists($fileAbsolute)) {
            return unlink($fileAbsolute);
        }
        return false;
    }

    /**
     * Cleanup files that are beyond lifetime
     */
    public function cleanup() {
        $dirIt = new DirectoryIterator($this->path);
        // Convert days to seconds
        $lifetimeSeconds = self::$lifetime * 3600 * 24;
        /** @var DirectoryIterator $fileInfo */
        foreach ($dirIt as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            $fileAge = time() - $fileInfo->getMTime();
            if ($fileAge > $lifetimeSeconds) {
                $this->deleteFile();
            }
        }
    }

    /**
     * Randomly cleanup files if available
     */
    protected function lottery() {
        if (is_array(self::$lottery)) {
            $result = rand(1, self::$lottery[1]);
            if ($result <= self::$lottery[0]) {
                $this->cleanup();
            }
        }
    }

}
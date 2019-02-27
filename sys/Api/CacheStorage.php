<?php

class Api_CacheStorage {

    private static $instances = array();

    /**
     * Get an an instance of the cache storage class for the given directory
     * @param string    $cacheDirectory
     * @return Api_CacheStorage
     */
    public static function getInstance($cacheDirectory) {
        $cacheDirectoryHash = sha1($cacheDirectory);
        if (!array_key_exists($cacheDirectoryHash, self::$instances)) {
            self::$instances[$cacheDirectoryHash] = new Api_CacheStorage($cacheDirectory);
        }
        return self::$instances[$cacheDirectoryHash];
    }
    
    private $directory;
    private $indexConditional;
    private $indexDirty;
    private $lifetimeMinimum;

    /**
     * Constructor for a cache storage
     * @param string    $cacheDirectory
     * @param int       $cacheLifetimeMinimum
     */
    public function __construct($cacheDirectory, $cacheLifetimeMinimum = 3600) {
        // Initialize member fields
        $this->directory = rtrim($cacheDirectory, "/");
        $this->indexConditional = null;
        $this->indexDirty = null;
        $this->lifetimeMinimum = $cacheLifetimeMinimum;
        // Create directory if not existing
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    /**
     * Delete the given cache file
     * @param string    $filename
     * @return bool
     */
    public function deleteCacheFile($filename) {
        $targetFile = $this->directory."/".$filename;
        if (!file_exists($targetFile)) {
            // File does not exist
            return true;
        } else {
            if (unlink($targetFile)) {
                $this->remIndexConditional($filename);
                $this->remIndexDirty($filename);
            } else {
                return false;
            }
        }
    }

    /**
     * Delete all cache files matching the given condition(s) 
     * @param array     $arConditions
     * @param bool      $cancelOnError
     * @return bool
     */
    public function deleteCacheFilesByCondition($arConditions, $cancelOnError = false) {
        $result = true;
        $arFiles = $this->getCacheFilesByCondition($arConditions);
        foreach ($arFiles as $fileIndex => $filename) {
            if (!$this->deleteCacheFile($filename)) {
                if ($cancelOnError) {
                    return false;
                } else {
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * Get a list of all cache files matching the given condition(s)
     * @param array     $arConditions
     * @return array
     */
    public function getCacheFilesByCondition($arConditions) {
        if (!$this->loadIndexConditional()) {
            // Failed to read conditional index
            return false;
        }
        // Search the index for matching files
        $arResult = array();
        foreach ($arConditions as $conditionName => $conditionValues) {
            if (array_key_exists($conditionName, $this->indexConditional)) {
                if (!is_array($conditionValues)) {
                    $conditionValues = array($conditionValues);
                }
                if (!empty($conditionValues)) {
                    foreach ($conditionValues as $conditionValueIndex => $conditionValue) {
                        if (array_key_exists($conditionValue, $this->indexConditional[$conditionName])) {
                            // Found matching files, add to result
                            foreach ($this->indexConditional[$conditionName][$conditionValue] as $fileIndex => $filename) {
                                if (!in_array($filename, $arResult)) {
                                    // New matching cache file found
                                    $arResult[] = $filename;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $arResult;
    }

    /**
     * Set the given cache file as dirty/clean, so it will (not) be recached as the minimum lifetime expires.
     * @param string    $filename
     * @param bool      $dirty
     * @return bool
     */
    public function setCacheFileDirty($filename, $dirty = true) {
        if ($dirty) {
            // Set dirty
            return $this->addIndexDirty($filename);
        } else {
            // Set clean
            return $this->remIndexDirty($filename);
        }
    }

    /**
     * Set all cache files matching the given condition(s) dirty/clean
     * @param array     $arConditions
     * @param bool      $dirty
     * @param bool      $cancelOnError
     * @return bool
     */
    public function setCacheFilesDirtyByCondition($arConditions, $dirty = true, $cancelOnError = false) {
        $result = true;
        $arFiles = $this->getCacheFilesByCondition($arConditions);
        foreach ($arFiles as $fileIndex => $filename) {
            if (!$this->setCacheFileDirty($filename, $dirty)) {
                if ($cancelOnError) {
                    return false;
                } else {
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * Check if the given cache file is marked as dirty. 
     * (Dirty cache files will be recached as soon as the minimum lifetime expires)
     * @param string    $filename
     * @return bool
     */
    public function isCacheFileDirty($filename) {
        if (!$this->loadIndexDirty()) {
            return true;
        }
        return (array_key_exists($filename, $this->indexDirty) && ($this->indexDirty[$filename] === true));
    }

    /**
     * Check if the given cache file is still valid for reading.
     * (If false is returned the file should be recached)
     * @param string    $filename
     * @return bool
     */
    public function isCacheFileValid($filename) {
        $targetFile = $this->directory."/".$filename;
        if (!file_exists($targetFile)) {
            // File does not exist
            return false;
        }
        if ($this->isCacheFileDirty($filename)) {
            // File dirty, check lifetime.
            $targetFileModified = filemtime($targetFile);
            if ((time() - $targetFileModified) < 3600) {
                // Lifetime not expired yet, file is valid!
                return true;
            } else {
                // Lifetime expired, file is due for recaching
                return false;
            }
        } else {
            // File is clean
            return true;
        }
    }

    public function readCacheFile($filename) {
        $targetFile = $this->directory."/".$filename;
        if (file_exists($targetFile)) {
            return file_get_contents($targetFile);
        } else {
            return null;
        }
    }

    /**
     * Write new content into the given cache file.
     * @param array     $filename
     * @param string    $content
     * @param array     $arConditions
     */
    public function writeCacheFile($filename, $content, $arConditions = array()) {
        $targetFile = $this->directory."/".$filename;
        $targetDir = dirname($targetFile);
        // Create directory if not existing
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        // Write cache file
        file_put_contents($targetFile, $content);
        // Add to condition index
        if (!empty($arConditions)) {
            if (!$this->addIndexConditional($filename, $arConditions)) {
                return false;
            }
        }
        // Set clean
        return $this->setCacheFileDirty($filename, false);
    }

    /**
     * Add the given cache file to the conditional index.
     * @param string    $filename
     * @param array     $arConditions
     * @return bool
     */
    protected function addIndexConditional($filename, $arConditions)
    {
        // Open index file and lock for writing
        $indexFileHandle = $this->openIndexConditional(true);
        // Read current contents from index file
        if (!$this->loadIndexConditional($indexFileHandle, true)) {
            return false;
        }
        // Update index
        foreach ($arConditions as $conditionName => $conditionValues) {
            if (!is_array($conditionValues)) {
                $conditionValues = array($conditionValues);
            }
            if (!empty($conditionValues)) {
                if (!array_key_exists($conditionName, $this->indexConditional)) {
                    $this->indexConditional[$conditionName] = array();
                }
                foreach ($conditionValues as $conditionValueIndex => $conditionValue) {
                    if ($conditionValue === null) {
                        // Skip null values
                        continue;
                    }
                    if (!array_key_exists($conditionValue, $this->indexConditional[$conditionName])) {
                        $this->indexConditional[$conditionName][$conditionValue] = array();
                    }
                    $this->indexConditional[$conditionName][$conditionValue][] = $filename;
                }
            }
        }
        // Write index to file, close handle and release lock.
        return $this->writeIndexConditional($indexFileHandle);
    }

    /**
     * Add the given cache file to the dirty index.
     * @param string    $filename
     * @return bool
     */
    protected function addIndexDirty($filename)
    {
        // Open index file and lock for writing
        $indexFileHandle = $this->openIndexDirty(true);
        // Read current contents from index file
        if (!$this->loadIndexDirty($indexFileHandle, true)) {
            return false;
        }
        // Update index
        if (!array_key_exists($filename, $this->indexDirty) || ($this->indexDirty[$filename] !== true)) {
            $this->indexDirty[$filename] = true;
        }
        // Write index to file, close handle and release lock.
        return $this->writeIndexDirty($indexFileHandle);
    }

    /**
     * Remove the given cache file from the conditional index.
     * @param string    $filename
     * @return bool
     */
    protected function remIndexConditional($filename)
    {
        // Open index file and lock for writing
        $indexFileHandle = $this->openIndexConditional(true);
        // Read current contents from index file
        if (!$this->loadIndexConditional($indexFileHandle, true)) {
            return false;
        }
        // Update index
        foreach ($this->indexConditional as $conditionName => $conditionValues) {
            foreach ($conditionValues as $conditionValue => $cacheFilenames) {
                $cacheFilenameIndex = array_search($filename, $cacheFilenames);
                if ($cacheFilenameIndex !== false) {
                    // Filename found within index, remove! 
                    array_splice($this->indexConditional[$conditionName][$conditionValue], $cacheFilenameIndex, 1);
                }
            }
        }
        // Write index to file, close handle and release lock.
        return $this->writeIndexConditional($indexFileHandle);
    }

    /**
     * Remove the given cache file from the dirty index.
     * @param string    $filename
     * @return bool
     */
    protected function remIndexDirty($filename)
    {
        // Open index file and lock for writing
        $indexFileHandle = $this->openIndexDirty(true);
        // Read current contents from index file
        if (!$this->loadIndexDirty($indexFileHandle, true)) {
            return false;
        }
        // Update index
        if (array_key_exists($filename, $this->indexDirty)) {
            unset($this->indexDirty[$filename]);
        }
        // Write index to file, close handle and release lock.
        return $this->writeIndexDirty($indexFileHandle);
    }

    /**
     * Load the conditional index from file.
     * @param resource|null     $indexFileHandle
     * @param bool              $forceReload        If true the index will be read again, even if it was already loaded before.
     * @return bool
     */
    private function loadIndexConditional($indexFileHandle = null, $forceReload = false) {
        if (!$forceReload && ($this->indexConditional !== null)) {
            // Already loaded
            return true;
        }
        $closeHandle = false;
        if ($indexFileHandle === null) {
            // Open index file
            $indexFile = $this->directory."/.cacheIndexConditional.json";
            if (file_exists($indexFile)) {
                $indexFileHandle = $this->openIndexConditional();
                $closeHandle = true;
            }
        }
        // Initialize empty index
        $this->indexConditional = array();
        if ($indexFileHandle !== null) {
            // Read index from file
            $indexStored = json_decode(stream_get_contents($indexFileHandle), true);
            if (is_array($indexStored)) {
                $this->indexConditional = $indexStored;
                $indexStored = null;
            }
            if ($closeHandle) {
                // Close file handle
                fclose($indexFileHandle);
            }
        }
        return ($this->indexConditional !== null);
    }
    
    /**
     * Load the dirty index from file.
     * @param resource|null     $indexFileHandle
     * @param bool              $forceReload        If true the index will be read again, even if it was already loaded before.
     * @return bool
     */
    private function loadIndexDirty($indexFileHandle = null, $forceReload = false) {
        if (!$forceReload && ($this->indexDirty !== null)) {
            // Already loaded
            return true;
        }
        $closeHandle = false;
        if ($indexFileHandle === null) {
            // Open index file
            $indexFile = $this->directory."/.cacheIndexDirty.json";
            if (file_exists($indexFile)) {
                $indexFileHandle = $this->openIndexConditional();
                $closeHandle = true;
            }
        }
        // Initialize empty index
        $this->indexDirty = array();
        if ($indexFileHandle !== null) {
            // Read index from file
            $indexStored = json_decode(stream_get_contents($indexFileHandle), true);
            if (is_array($indexStored)) {
                $this->indexDirty = $indexStored;
                $indexStored = null;
            }
            if ($closeHandle) {
                // Close file handle
                fclose($indexFileHandle);
            }
        }
        return true;
    }

    /**
     * Open index file and return handle
     * @param $indexFilename
     * @return resource
     */
    private function openIndex($indexFilename, $writable = false) {
        $indexFile = $this->directory."/".$indexFilename;
        if (!file_exists($indexFile)) {
            // Create index file
            file_put_contents($indexFile, json_encode(array()));
        }
        $indexFileHandle = fopen($indexFile, ($writable ? "r+" : "r"));
        if ($indexFileHandle === false) {
            // Error opening file
            return null;
        }
        if (flock($indexFileHandle, ($writable ? LOCK_EX : LOCK_SH))) {
            // Could not get exclusive access
            return $indexFileHandle;
        } else {
            fclose($indexFileHandle);
            return null;
        }
    }

    /**
     * Open conditional index file and return handle
     * @return resource
     */
    private function openIndexConditional($writable = false)
    {
        return $this->openIndex(".cacheIndexConditional.json", $writable);
    }

    /**
     * Open dirty index file and return handle
     * @return resource
     */
    private function openIndexDirty($writable = false)
    {
        return $this->openIndex(".cacheIndexDirty.json", $writable);
    }

    /**
     * Write an index into the given file
     * @param resource      $indexFileHandle
     * @param array         $arContent
     * @param bool          $closeHandle        If true the file handle will be closed after successful writing.
     * @return bool
     */
    private function writeIndex($indexFileHandle, &$arContent, $closeHandle = true)
    {
        if (!is_resource($indexFileHandle)) {
            throw new Exception("Api_CacheStorage/writeIndex: Invalid file handle!");
            return false;
        }
        if (!ftruncate($indexFileHandle, 0) || !rewind($indexFileHandle)) {
            throw new Exception("Api_CacheStorage/writeIndex: Failed to truncate file!");
            return false;
        }
        if (!fwrite($indexFileHandle, json_encode($arContent))) {
            throw new Exception("Api_CacheStorage/writeIndex: Failed to write file!");
            return false;
        }
        if ($closeHandle) {
            return fclose($indexFileHandle);
        }
        return true;
    }

    /**
     * Write the conditional index into the given file
     * @param resource      $indexFileHandle
     * @param bool          $closeHandle        If true the file handle will be closed after successful writing.
     * @return bool
     */
    private function writeIndexConditional($indexFileHandle, $closeHandle = true)
    {
        return $this->writeIndex($indexFileHandle, $this->indexConditional, $closeHandle);
    }

    /**
     * Write the dirty index into the given file
     * @param resource      $indexFileHandle
     * @param bool          $closeHandle        If true the file handle will be closed after successful writing.
     * @return bool
     */
    private function writeIndexDirty($indexFileHandle, $closeHandle = true)
    {
        return $this->writeIndex($indexFileHandle, $this->indexDirty, $closeHandle);
    }
    
}
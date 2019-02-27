<?php

// Update article count for categories
include_once $GLOBALS["ab_path"]."sys/lib.pub_kategorien.php";
$katCache = new CategoriesCache();
$katCache->updateCacheArticleCount();
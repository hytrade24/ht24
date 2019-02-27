<?php

// Remove expired cache entries from database
Api_DatabaseCacheStorage::getInstance()->cleanup();
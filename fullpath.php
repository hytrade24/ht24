<?php
$dir = dirname(__FILE__);
$_SERVER['SERVER_ADDR'];
echo "<p>Full path to this dir: " . $dir . "</p>";
echo "<p>Full path to a .htpasswd file in this dir: " . $dir . "/.htpasswd" . "</p>";
echo "<p>Full Server path " . $_SERVER['SERVER_ADDR'] . "</p>";
?>
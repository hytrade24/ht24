<?php

require_once $ab_path."sys/Currency/Fixer.php";

$cur = new Fixer();
$cur->update_all_currencies_ratios();
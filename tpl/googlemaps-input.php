<?php

$hash = sha1(uniqid("", true));

$tpl_content->addvar("HASH", $hash);
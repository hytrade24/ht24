<?php
/* ###VERSIONSBLOCKINLCUDE### */

$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "social_recommend", "Social-Media Buttons");
$accountFacebook = $subtplConfig->addOptionText("FB_NAME", "Facebook-Account", true, "ebizconsult");
$accountGoogle = $subtplConfig->addOptionText("GP_ID", "Google-Plus ID", true, "116121863112545414498");
$accountTwitter = $subtplConfig->addOptionText("TW_NAME", "Twitter-Account", true, "ebiz_trader");
$subtplConfig->finishOptions();

$tpl_content->addvar("FB_NAME", $accountFacebook);
$tpl_content->addvar("GP_ID", $accountGoogle);
$tpl_content->addvar("TW_NAME", $accountTwitter);

?>
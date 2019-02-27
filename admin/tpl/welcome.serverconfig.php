<?php

function _welcome_server_config_check(Template $tplServerConfig, $option, $valid, &$validAll) {
	if ($valid) {
		$tplServerConfig->addvar($option, 1);
	} else {
		$validAll = false;
		$tplServerConfig->addvar($option, 0);
	}
}

// Server config
$serverPhpIni = php_ini_loaded_file();
$serverPhpIniHash = @sha1_file($serverPhpIni);
if ($serverPhpIniHash === false) {
	$serverPhpConfig = ini_get_all();
	$serverPhpIniHash = sha1(json_encode($serverPhpConfig));
} 
$serverPhpIniHashPrev = null;
if (file_exists($ab_path."cache/_ServerConfig.htm.hash")) {
	$serverPhpIniHashPrev = file_get_contents($ab_path."cache/_ServerConfig.htm.hash");
}
if ($serverPhpIniHash !== $serverPhpIniHashPrev) {
	$configurationValid = true;
	$tplServerConfig = new Template("tpl/".$s_lang."/welcome.serverconfig.htm");
	$valueMegaByte = 1024*1024;
	// Check if cache is writable
	touch($ab_path."cache/_ServerConfig.tmp");
	// Check settings
	$checkTempWrite = (is_writable(sys_get_temp_dir()) === true);
	$checkCacheWrite = file_exists($ab_path."cache/_ServerConfig.tmp");
	$checkPhpVersion = (version_compare(PHP_VERSION, '5.6') >= 0);
	$checkPhpSafeMode = (ini_get("safe_mode") == 0);
	$checkPhpOpenBasedir = (ini_get("open_basedir") == 0);
	$checkPhpAllowUrlFopen = (ini_get("allow_url_fopen") == 1);
	$checkPhpFileUploads = (ini_get("file_uploads") == 1);
	$checkPhpUploadMaxFilesize = (Tools_Utility::configSizeToBytes(ini_get("upload_max_filesize")) > 1*$valueMegaByte);
	$checkPhpPostMaxSize = (Tools_Utility::configSizeToBytes(ini_get("post_max_size")) > 1*$valueMegaByte);
	$valuePhpMemoryLimit = Tools_Utility::configSizeToBytes(ini_get("memory_limit"));
	$checkPhpMemoryLimit = (($valuePhpMemoryLimit < 0) || ($valuePhpMemoryLimit > 64*$valueMegaByte));
	$checkPhpMaxExecutionTime = (ini_get("max_execution_time") >= 120);
	$checkPhpSystem = (strpos(ini_get("disable_functions"), "system") === false);
	$checkPhpExec = (@exec("echo '1'") != false);
	$checkPhpGdLib = (function_exists("gd_info") && is_array(@gd_info()));
	// Test imagemagick
	exec($nar_systemsettings['SYS']['PATH_CONVERT']." --version", $arImOutput, $imResult);
	$checkImagemagick = ($imResult == 0);
	// Test system/cache access rights
	exec("mkdir ".$ab_path."cache/_ServerConfig");
	if (!file_exists($ab_path."cache/_ServerConfig")) {
		$checkPhpSystem = false;
	}
    $checkPhpSystemRm = $checkPhpSystem;
    exec("rm -R ".$ab_path."cache/_ServerConfig");
	if (file_exists($ab_path."cache/_ServerConfig")) {
		$checkPhpSystemRm = false;
	}
	// Write to template
	_welcome_server_config_check($tplServerConfig, "OK_TEMP_WRITE", $checkTempWrite, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_CACHE_WRITE", $checkCacheWrite, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_VERSION", $checkPhpVersion, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_SAFE_MODE", $checkPhpSafeMode, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_OPEN_BASEDIR", $checkPhpOpenBasedir, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_ALLOW_URL_FOPEN", $checkPhpAllowUrlFopen, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_FILE_UPLOADS", $checkPhpFileUploads, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_UPLOAD_MAX_FILESIZE", $checkPhpUploadMaxFilesize, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_POST_MAX_SIZE", $checkPhpPostMaxSize, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_MEMORY_LIMIT", $checkPhpMemoryLimit, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_MAX_EXECUTION_TIME", $checkPhpMaxExecutionTime, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_SYSTEM", $checkPhpSystem, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_SYSTEM_RM", $checkPhpSystemRm, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_EXEC", $checkPhpExec, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_PHP_GDLIB", $checkPhpGdLib, $configurationValid);
	_welcome_server_config_check($tplServerConfig, "OK_IMAGEMAGICK", $checkImagemagick, $configurationValid);
	$tplServerConfig->addvar("DATE", date("d.m.Y H:i:s"));
	$tplServerConfig->addvar("VALID", $configurationValid);
	// Output result
	$serverConfigHtml = $tplServerConfig->process();
	$tpl_content->addvar("SERVER_CONFIG", $serverConfigHtml);
	if ($configurationValid) {
		file_put_contents($ab_path."cache/_ServerConfig.htm", $serverConfigHtml);
		file_put_contents($ab_path."cache/_ServerConfig.htm.hash", $serverPhpIniHash);
	}
}
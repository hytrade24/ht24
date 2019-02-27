<?php
require_once 'sys/lib.vendor.php';

$vendorManagement = VendorManagement::getInstance($db);


$file_name = $ab_path . 'cache/marktplatz/top_vendor_slider.' . $s_lang . '.htm';
$file = @filemtime($file_name);
$now = time();
$diff = (($now - $file) / 60);
if ($diff > 60) {
	$cachedVendorTpl = '';

    $vendorIdList = $db->fetch_table("
		SELECT
			v.ID_VENDOR
		FROM
			vendor v
	    JOIN user u ON u.ID_USER = v.FK_USER
		WHERE
			v.STATUS = 1
			AND u.TOP_USER = 1
			AND v.LOGO != '' AND v.LOGO IS NOT NULL
		GROUP BY
			v.ID_VENDOR
		ORDER BY
			RAND()
		LIMIT 5
    ");

	foreach($vendorIdList as $key => $vendorIdListItem) {
		$vendorId = $vendorIdListItem['ID_VENDOR'];
		$tmp = new Template("tpl/" . $s_lang . "/top-vendor-slider.row.htm");


		if ($vendorId) {
			$vendor = $vendorManagement->fetchByVendorId($vendorId);

			$vendor['LOGO'] = ($vendor['LOGO'] != "")?'cache/vendor/logo/'.$vendor['LOGO']:null;
			$vendor['DESCRIPTION'] = substr(strip_tags($vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR']), '<p><a><br>'), 0, 200);


			$tmp->addvars($vendor, 'VENDOR_');

			$cachedVendorTpl .= $tmp->process();
		} else {

			$cachedVendorTpl .= '';

		}
	}


	@file_put_contents($file_name, $cachedVendorTpl);
	chmod($file_name, 0777);

}
$tpl_content->addvar("liste",  @file_get_contents($file_name));

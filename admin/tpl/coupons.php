<?php


$couponManagement = Coupon_CouponManagement::getInstance($db);

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'delete' && isset($_GET['ID_COUPON'])) {
		$couponManagement->deleteById($_GET['ID_COUPON']);
    }

    die(forward("index.php?page=coupons&npage=".$npage));
}

if(isset($_REQUEST['id'])) {
	$tpl_content->addvar("id_show", $_REQUEST['id']);
}

$ar_search = array(
    'LIMIT' => $perpage,
    'OFFSET' => $limit
);
if (isset($_REQUEST['SEARCH'])) {
	if (!empty($_REQUEST['ID_COUPON'])) {
		$ar_search['ID_COUPON'] = $_REQUEST['ID_COUPON'];
	}
	if (!empty($_REQUEST['COUPON_NAME'])) {
		$ar_search['COUPON_NAME'] = $_REQUEST['COUPON_NAME'];
	}
	if (!empty($_REQUEST['COUPON_CODE'])) {
			$ar_search['COUPON_CODE'] = $_REQUEST['COUPON_CODE'];
		}
	if (!empty($_REQUEST['TYPE'])) {
		$ar_search['TYPE'] = $_REQUEST['TYPE'];
		$tpl_content->addvar("SEARCH_TYPE_".$_REQUEST['TYPE'], 1);
	}


	$tpl_content->addvars($ar_search);
}

$coupons = $couponManagement->fetchAllByParam($ar_search);
$numberOfUsages = $couponManagement->countByParam($ar_search);

foreach($coupons as $key => $coupon) {
	$couponType = $couponManagement->getCouponType($coupon);

	$coupons[$key]['COUPON_TYPE_NAME'] = $couponType->getName();
}

$tpl_content->addvar("pager", htm_browse($numberOfUsages, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($_GET)."&npage=", $perpage));
$tpl_content->addlist('liste', $coupons, 'tpl/de/coupons.row.htm');
$tpl_content->addvar("all", $numberOfUsages);


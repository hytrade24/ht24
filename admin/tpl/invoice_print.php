<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path . 'sys/lib.billing.invoice.php';
require_once $ab_path . 'sys/lib.billing.invoice.item.php';
require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$billingInvoiceItemManagement = BillingInvoiceItemManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$invoiceIds = $_REQUEST["ID_BILLING_INVOICE"];
$filesSource = $_REQUEST["PDF_SOURCES"];

if (!empty($filesSource)) {
	if (count($filesSource) == 1) {
		$fileSrc = $filesSource[0]['SRC'];
		$fileDst = $filesSource[0]['DST'];
		if (file_exists($fileSrc)) {
		    header('Content-Description: File Transfer');
		    header('Content-Type: application/octet-stream');
		    header('Content-Disposition: attachment; filename="'.$fileDst.'"');
		    header('Content-Transfer-Encoding: binary');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate');
		    header('Pragma: public');
		    header('Content-Length: ' . filesize($fileSrc));
		    echo(file_get_contents($fileSrc));
			unlink($fileSrc);
			die();
		}
		die();
	} else {
		$list = array();
		foreach ($filesSource as $index => $arFile) {
			if (file_exists($arFile['SRC'])) {
				$list[] = $arFile;
			}
		}
		$tpl_content->addlist('liste', $list, 'tpl/de/invoice_print.row.htm');
		return;
	}
}

function getInvoicePrintTemplate($invoiceId) {
    global $ab_path, $billingInvoiceManagement, $nar_systemsettings, $billingInvoiceItemManagement, $paymentAdapterManagement, $userManagement, $s_lang;

    $invoice = $billingInvoiceManagement->fetchById($invoiceId);
    $invoiceItems = $billingInvoiceItemManagement->fetchAllByParam(array('FK_BILLING_INVOICE' => $invoiceId));
    $invoicePaymentAdapter = $paymentAdapterManagement->fetchById($invoice['FK_PAYMENT_ADAPTER']);

    if($invoice == null) {
        die("invoice not found..");
    }

    $taxes = array();
    foreach($invoiceItems as $key => $invoiceItem) {
        $invoiceItems[$key]['POS'] = ($key + 1);

        if(array_key_exists($invoiceItem['TAX_VALUE'], $taxes)) {
            $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] += ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
        } else {
            $taxes[$invoiceItem['TAX_VALUE']] = array();
            $taxes[$invoiceItem['TAX_VALUE']]['TAX_AMOUNT'] = ($invoiceItem['TOTAL_PRICE'] - $invoiceItem['TOTAL_PRICE_NET']);
            $taxes[$invoiceItem['TAX_VALUE']]['TAX_VALUE'] = $invoiceItem['TAX_VALUE'];
        }
    }

    $tpl_print = new Template(CacheTemplate::getHeadFile('tpl/'.$s_lang.'/invoice.page.htm'));

    $tpl_print->addvars($invoice, 'INVOICE_');
    $tpl_print->addlist('INVOICE_ITEMS', $invoiceItems, $ab_path.'tpl/de/invoice.page.item.row.htm');
    $tpl_print->addlist('INVOICE_TAXES', $taxes, $ab_path.'tpl/de/invoice.page.tax.row.htm');
    $tpl_print->addvars($invoicePaymentAdapter, 'INVOICE_PAYMENT_ADAPTER_');
	$tpl_print->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);

    return $tpl_print->process();
}

function getInvoiceListPrintTemplate($invoiceHtml) {
	global $ab_path, $s_lang;
	$tpl_html = new Template($ab_path.'tpl/'.$s_lang.'/invoice.page.skin.htm');
	$tpl_html->addvar("PAGES", $invoiceHtml);
	$invoiceHtml = $tpl_html->process();
	// Fix some special characters and url paths
	$invoiceHtml = str_replace('&euro;', '&#0128;', $invoiceHtml);
	$invoiceHtml = preg_replace('/src=[\"\']\/(.+)[\"\']/i', 'src="'.$ab_path.'$1"', $invoiceHtml);
    return $invoiceHtml;
}

function writeInvoicePdf($pdfFilename, $htmlSource) {
	include "sys/dompdf/dompdf_config.inc.php";
	$dompdf = new DOMPDF();
	$dompdf->load_html($htmlSource);
	//$dompdf->set_paper("A4", "portrait");
	$dompdf->render();
	file_put_contents($pdfFilename, $dompdf->output());
	unset($dompdf);
}

$filename = "invoices.pdf";
$htmlInvoices = "";
if (!is_array($invoiceIds) && strpos($invoiceIds, ',') !== FALSE) {
	$invoiceIds = explode(',', $invoiceIds);
} else {
    $filename = "invoice_" . $invoiceIds . ".pdf";
	$invoiceIds = array( (int)$invoiceIds );
}

$fileIndex = 1;
$fileCount = 0;
$fileCountMax = 50;
$fileCountAll = ceil(count($invoiceIds) / $fileCountMax);
$fileList = array();

$invoiceCount = (isset($_REQUEST["COUNT"]) ? (int)$_REQUEST["COUNT"] : count($invoiceIds));
$invoiceCountLeft = count($invoiceIds);
$timeStart = (isset($_REQUEST["TIME_START"]) ? (int)$_REQUEST["TIME_START"] : null);
$filesCache = (is_array($_REQUEST["PDF_CACHE"]) ? $_REQUEST["PDF_CACHE"] : array());
foreach ($filesCache as $index => $fileCur) {
	$fileList[ $fileCur['DST'] ] = $fileCur['SRC'];
	$fileIndex++;
	$fileCountAll++;
}

if (($timeStart == null) && ($fileCountAll > 1)) {
	if ($_REQUEST['do'] == 'start') {
		$ar_params[] = "TIME_START=".time();
		// Start processing without further confirmation
		$tpl_content->addvar("starting", 0);
	} else {
		$ar_params[] = "do=start";
		// Add variables for confirmation
		$timeEstimate = "Unbekannt";
		if (file_exists($ab_path."/cache/invoice_pdf_duration.tmp")) {
			$timePerInvoice = (float)file_get_contents($ab_path."/cache/invoice_pdf_duration.tmp");
			$timeEstimate = round($timePerInvoice * $invoiceCount / 60)." Minuten";
		}
		$tpl_content->addvar("starting", 1);
		$tpl_content->addvar("timeEstimate", $timeEstimate);
	}
	$ar_params[] = "COUNT=".$invoiceCount;
	$ar_params[] = "ID_BILLING_INVOICE=".urlencode( implode(",", $invoiceIds) );
	$tpl_main->addvar("loading", 1);
	$tpl_content->addvar("loading", 1);
	$tpl_content->addvar("progessPercent", 0);
	$tpl_content->addvar("count", $invoiceCount);
	$tpl_content->addvar("countDone", $invoiceCount - $invoiceCountLeft);
	$tpl_content->addvar("countLeft", $invoiceCountLeft);
	$tpl_content->addvar("pageCur", 0);
	$tpl_content->addvar("pageMax", $fileCountAll);
	$tpl_content->addvar("timeLeftSec", "??");
	$tpl_content->addvar("timeLeftMin", "??");
	$tpl_content->addvar("urlParams", implode("&", $ar_params));
	return;
}

// Create file(s)
foreach ($invoiceIds as $index => $id_cur) {
    if ($htmlInvoices != "") {
        $htmlInvoices .= '<p style="page-break-before: always">&nbsp;</p>';
    }
	$htmlInvoices .= getInvoicePrintTemplate((int)$id_cur);
	if (++$fileCount >= $fileCountMax) {
		$fileCount = 0;
		$filename = "invoices_".$fileIndex."_of_".$fileCountAll.".pdf";
		$filenamePdf = tempnam(sys_get_temp_dir(), 'inv_temp_'.$hash);
		$htmlInvoices = getInvoiceListPrintTemplate($htmlInvoices);
		writeInvoicePdf($filenamePdf, $htmlInvoices);
		// Add to output list
		$fileList[$filename] = $filenamePdf;
		// Clear buffer and proceed
		$htmlInvoices = "";
		$fileIndex++;
		if (count($invoiceIds) >= $index) {
			// Forward to loading page
			$invoiceIdsLeft = array_slice($invoiceIds, $index+1);
			$invoiceCountLeft = count($invoiceIdsLeft);
			$ar_params[] = "TIME_START=".$timeStart;
			$ar_params[] = "COUNT=".$invoiceCount;
			$ar_params[] = "ID_BILLING_INVOICE=".urlencode( implode(",", $invoiceIdsLeft) );
			$indexPdfList = 0;
			foreach ($fileList as $filename => $filenameHtml) {
				$ar_params[] = 'PDF_CACHE['.$indexPdfList.'][SRC]='.urlencode($filenameHtml);
				$ar_params[] = 'PDF_CACHE['.$indexPdfList.'][DST]='.urlencode($filename);
				$indexPdfList++;
			}
			$pageIndexCur = $fileIndex-1;
			$progressPercent = round($pageIndexCur / $fileCountAll, 4);
			$timeGone = (time() - $timeStart);
			$timeLeft = ($timeGone / $progressPercent) - $timeGone;
			$timeLeftMin = floor($timeLeft / 60);
			$timeLeftSec = ceil($timeLeft - ($timeLeftMin * 60));
			$tpl_main->addvar("loading", 1);
			$tpl_content->addvar("loading", 1);
			$tpl_content->addvar("progessPercent", $progressPercent * 100);
			$tpl_content->addvar("count", $invoiceCount);
			$tpl_content->addvar("countDone", $invoiceCount - $invoiceCountLeft);
			$tpl_content->addvar("countLeft", $invoiceCountLeft);
			$tpl_content->addvar("pageCur", $pageIndexCur);
			$tpl_content->addvar("pageMax", $fileCountAll);
			$tpl_content->addvar("timeLeftSec", $timeLeftSec);
			$tpl_content->addvar("timeLeftMin", $timeLeftMin);
			$tpl_content->addvar("urlParams", implode("&", $ar_params));
			return;
		}
	}
}
// Add last file
if (!empty($htmlInvoices)) {
	if ($fileIndex > 1) {
		$filename = "invoices_".$fileIndex."_of_".$fileCountAll.".pdf";
	}
	$filenamePdf = tempnam(sys_get_temp_dir(), 'inv_temp_'.$hash);
	$htmlInvoices = getInvoiceListPrintTemplate($htmlInvoices);
	writeInvoicePdf($filenamePdf, $htmlInvoices);
	// Add to output list
	$fileList[$filename] = $filenamePdf;
	// Clear buffer
	$htmlInvoices = "";
}

$index = 0;
$ar_params = array();
foreach ($fileList as $filename => $filenameHtml) {
	$ar_params[] = 'PDF_SOURCES['.$index.'][SRC]='.urlencode($filenameHtml);
	$ar_params[] = 'PDF_SOURCES['.$index.'][DST]='.urlencode($filename);
	$index++;
}

if ($fileCountAll > 1) {
	// Calculate time for one invoice and store for future estimates
	$timeGone = (time() - $timeStart);
	$timePerInvoice = $timeGone / $invoiceCount;
	file_put_contents($ab_path."/cache/invoice_pdf_duration.tmp", $timePerInvoice);
}

// Write result into temp file (to prevent out of memory errors when generating large lists)
die(forward( "index.php?page=invoice_print&".implode("&", $ar_params) ));

?>
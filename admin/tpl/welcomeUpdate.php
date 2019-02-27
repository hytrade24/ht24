<?php
/* ###VERSIONSBLOCKINLCUDE### */

$nar_systemsettings["CACHE"]["TEMPLATE_AUTO_REFRESH"] = 1;

function getDescription($step) {
    switch ($step['action']) {
        case 'articleFieldAdd':
            return 'Artikel-Feld wird hinzugefügt: '.$step['parameters']['SQL_FIELD'];
            break;
        case 'cacheClear':
            return 'Cache leeren: '.$step['parameters']['type'];
            break;
        case 'designCheck':
            return 'User-Design überprüfen';
            break;
        case 'mailEdit':
            return 'E-Mail-Template bearbeiten: '.$step['parameters']['SYS_NAME'];
            break;
        case 'manualStep':
            return 'Manuelle Änderung';
        case 'navAdd':
            return ($step['parameters']['ROOT'] == 2 ? 'Admin-' : '').'Navigationspunkt hinzufügen: '.$step['parameters']['IDENT'];
            break;
        case 'navEdit':
            return ($step['parameters']['ROOT'] == 2 ? 'Admin-' : '').'Navigationspunkt bearbeiten: '.$step['parameters']['IDENT'];
            break;
        case 'filesCopy':
            return 'Dateien kopieren: '.$step['parameters']['source'];
            break;
        case 'sqlRun':
            return 'SQL-Anweisungen werden ausgeführt: '.$step['parameters']['file'];
            break;
    }
}

require_once $ab_path."sys/lib.update.php";

$update = new Update($db, $ab_path."update/update.yml");

if (isset($_REQUEST["doUpdate"])) {
    $ajaxResult = array('success' => false, 'error' => '', 'message' => '');
    switch ($_REQUEST["doUpdate"]) {
        case 'stepSkip':
            $ajaxResult['success'] = $update->stepSkip();
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'designUpdate':
            $ajaxResult['success'] = $update->designUpdate($_REQUEST['path'].$_REQUEST['filename'], $_REQUEST['content'], $_REQUEST['finish'] > 0);
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'designRestore':
            $update->designRestore($_REQUEST['filename'], $_REQUEST['path'], $_REQUEST['pathUpdate'], $_REQUEST['pathBackup']);
            break;
        case 'designSkip':
            $ajaxResult['success'] = $update->designSkip($_REQUEST['filename']);
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'filesSkip':
            $ajaxResult['success'] = $update->filesSkip($_REQUEST['source'], $_REQUEST['filename']);
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'filesReplace':
            $ajaxResult['success'] = $update->filesReplace($_REQUEST['source'], $_REQUEST['filename']);
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'filesRestore':
            $update->filesRestore($_REQUEST['filename'], $_REQUEST['pathUpdate'], $_REQUEST['pathBackup']);
            break;
        case 'filesUpdate':
            $content = "";
            if (array_key_exists("content", $_REQUEST)) {
                $content = $_REQUEST['content'];
            } else if (array_key_exists("contentFile", $_REQUEST)) {
                $content = file_get_contents($_REQUEST['contentFile']);
            }
            $ajaxResult['success'] = $update->filesUpdate($_REQUEST['filename'], $content, $_REQUEST['pathUpdate'], $_REQUEST['pathBackup'], $_REQUEST['finish'] > 0);
            $ajaxResult['message'] = $update->getLastMessage();
            $ajaxResult['error'] = $update->getLastError();
            break;
        case 'status':
            $ajaxResult['success'] = true;
            $ajaxResult['step'] = $update->getCurrentInstruction();
            $ajaxResult['step']['description'] = getDescription($ajaxResult['step']);
            break;
        case 'start':
            if ($update->run()) {
                $ajaxResult['success'] = true;
                $update->delete();
            } else {
                $error = $update->getLastError();
                if ($error !== false) {
                    $ajaxResult['success'] = false;
                    $ajaxResult['error'] = $error;
                } else {
                    $message = $update->getLastMessage();
                    if ($message !== false) {
                        $ajaxResult['success'] = false;
                        $ajaxResult['message'] = $message;
                    } else {
                        $ajaxResult['success'] = true;
                        $ajaxResult['timeout'] = true;
                    }
                }
            }
            break;
    }
    header('Content-type: application/json');
    die(json_encode($ajaxResult));
}

$tpl_content->tpl_text = file_get_contents($ab_path."admin/tpl/de/welcomeUpdate.htm");
$stepNext = $update->getCurrentInstruction();
if ($stepNext['index'] > 0) {
    $tpl_content->addvar("updateStepStart", $stepNext['index'] + 1);
    $tpl_content->addvar("updateStepDesc", getDescription($stepNext));
}
$tpl_content->addvar("updateSteps", $update->getCount());
// Verhindern dass das linke Menü angezeigt wird um Platz zu sparen
$tpl_main->addvar("suppress_left", 1);


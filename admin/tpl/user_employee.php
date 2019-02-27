<?php

/** @var Api_Plugins_Leads_Plugin $pluginLeads */
$pluginLeads = Api_TraderApiHandler::getInstance()->getPlugin("Leads");

$id = $_REQUEST["ID_USER"];

$data = ($id
    ? $db->fetch1($db->lang_select('user') . " where ID_USER=" . $id)
    : $db->fetch_blank('user')
);
$data["SIG"] = md5($data["PASS"]);

$userCurrent = $pluginLeads->getUserById($id);

$arEmployees = $userCurrent->userChildrenAll;
$arEmployeesAssoc = array();

/** @var \Plugins\Hydromot\UserChild $employee */
foreach ($arEmployees as $employee) {
    $employeeUser = $employee->userChild;
    $employeeUserAssoc = $employeeUser->toArray();
    if ($employeeUser->profile instanceof \Plugins\Hydromot\Profile) {
        $employeeUserAssoc = array_merge($employeeUser->profile->toArray(), $employeeUserAssoc);
    }
    $arEmployeesAssoc[] = $employeeUserAssoc;
}

$tpl_content->addvars($data);
$tpl_content->addlist("liste", $arEmployeesAssoc, "tpl/".$s_lang."/user_employee.row.htm");
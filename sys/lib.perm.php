<?php
/* ###VERSIONSBLOCKINLCUDE### */


define ('PERM_DEL',     8);
define ('PERM_EDIT',    4);
define ('PERM_CREATE',  2);
define ('PERM_READ',    1);

define ('PERM_DENY', 403);

class perm_generic
{
  var $data, $bf_setting;

  function perm_generic($s_perm)
  {
    $this->bf_setting = $GLOBALS['db']->perm_check($s_perm, 15);
    $this->data = $db->fetch1(lang_select('perm', '*')
      . " where IDENT='". mysql_escape_string($s_perm). "'");
  }

  function mask($bf=15)
  {
    return $bf & $this->bf_setting;
  }
  function check($bf)
  {
    return ($bf != $this->mask($bf) ? PERM_DENY : 0);
  }

  function read() // read
  {
    if ($err = $this->check(PERM_READ)) return $err;
    ...
  }

  function create()
  {
    if ($err = $this->check(PERM_CREATE)) return $err;
    ...
  }

  function edit()
  {
    if ($err = $this->check(PERM_EDIT)) return $err;
    ...
  }

  function delete()
  {
    if ($err = $this->check(PERM_DEL)) return $err;
    ...
  }

  function show()
  {
    if ($err = $this->check(PERM_SHOW)) return $err;
    ...
  }
}
?>
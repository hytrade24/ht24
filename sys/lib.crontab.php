<?php
/* ###VERSIONSBLOCKINLCUDE### */


// EINGABEPRUEFUNG =============================================================
  // einzelne Zahl/Bereich pruefen
  function cronrow_checkdigit($s, $i)
  {
    $range = array(60, 24, -31, -12, 7);
    if (strpos($s, '-'))
    {
      list($a, $b) = explode('-', $s);
      $ok = (int)$a<(int)$b && cronrow_checkdigit($a, $i) && cronrow_checkdigit($b, $i);
    }
    else
    {
      if ($i<0)
      {
        $r = $range[-1-$i];
        if ($r>0)
          $r = -$r;
      }
      else
        $r = $range[$i];
      $s = (int)$s;
      $ok = ($r<0 ? $s>0 && $s<=-$r : $s<$r);
    }
    return $ok;
  }

  // Zeitangabe eines Crontab-Eintrags pruefen
  function cronrow_check(&$s_cronrow)
  {
    static $trans = array(
      3 => array(1=>'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
      4 => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat')
    );
    $ar_err = array();
    if ($s_cronrow = preg_replace('/\s+/', ' ', trim($s_cronrow)))
    {
      $ar_atoms = explode(' ', $s_cronrow);
      if (5!=$n=count($ar_atoms))
        return array(-1=>'5 Angaben ben&ouml;tigt, '. $n. ' vorhanden');
      foreach($ar_atoms as $i=>$s_sub)
      {
        if ($trans[$i]) foreach ($trans[$i] as $to=>$from)
          $s_sub = preg_replace('/\b'. $from. '\b/i', $to, $s_sub);
        $s_err = false;
        if (!preg_match('/^('. '(\d+(\-\d+)?(,?|$))+'. '|(\*|\d+)(\/\d+)?$)/', $s_sub, $ar_match))
          $s_err = 'Syntax Error';
        else
        {
          if (strpos($s_sub, '/'))
          {
            $ar = explode('/', $s_sub);
            if (!($a = '*'==$ar[0] || cronrow_checkdigit($ar[0], $i)))
              $s_err = 'ung&uuml;ltiger Zaehler';
            elseif (!($b = cronrow_checkdigit($ar[1], -1-$i)))
              $s_err = 'ung&uuml;ltiger Nenner';
          }
          elseif ('*'==$s_sub)
            ;
          else
          {
            $ar = explode(',', $s_sub);
            foreach ($ar as $k=>$v)
            {
              if (!($b_ok = cronrow_checkdigit($v, $i)))
              {
                $s_err = 'ung&uuml;ltiger Wert'. (count($ar)>1 ? ' an '. (1+$k). '. Stelle' : '');
                break;
              }
            }
          }
        }

        if ($s_err)
          $ar_err[$i] = $s_sub. ': '. $s_err;
      }
    }
    return $ar_err;
  }

  // Fehlermeldungen von cronrow_check ans global Array $err anhaengen
  function cronrow_err($ar_err, $s_prefix = '')
  {
    static $nar_labels = array('Minute', 'Stunde', 'Tag', 'Monat', 'Wochentag');
    global $err;
  if (count($ar_err))
    foreach($ar_err as $i=>$s_err)
      $err[] = $s_prefix. (($lbl = $nar_labels[$i]) || $s_prefix ? $lbl. ': ' : ''). $s_err;
  }

// EMU =========================================================================
  $s_cronpath = './admin/'; // trailing slash!
  // crontab.txt == crontab
#  // cron.log == last runtime of each job
  // cron.touch == (filemtime) last time cron ran
  // cron.runs == if file_exists, cron is running
/*
  function crontab_restart()
  {
    // reset all counters
    global $s_cronpath;
    // wait for cron to finish
    while (file_exists($s_cronpath. 'cron.runs'));
    $ar_jobs = file($s_cronpath. 'crontab.txt');
#    $s_log = str_repeat("0\n", count($ar_jobs)/2);
#    $fp = fopen($s_cronpath. 'cron.log', 'w');
#    fputs($fp, $s_log);
#    fclose($fp);
  }
*/

  function cron_checkparam_atomic($v, $val)
  {
    // *
    if ('*'==$v)
      return true;
    // int
    if (preg_match('/^[0-9]+$/', $v) && (int)$s==$val)
      return true; // single number
    // range
    if (preg_match('/^[0-9]+\-[0-9]+$/', $v))
    {
      list($a,$b) = explode('-', $v);
      if ($a<=$val && $b>=$val)
        return true;
    }
    return false;
  }
  function cron_checkparam($s, $val)
  {
    // every nth ...
    if (strpos($s, '/'))
    {
      list($a, $b) = explode('/', $s);
      if (!($val%$b))
        return cron_checkparam_atomic($a, $val);
      else
        return false;
    }
    // list
    elseif (strpos($s, ','))
    {
      $ar = explode(',', $s);
      foreach($ar as $v)
        if (cron_checkparam_atomic($v, $val))
          return false;
      return false;
    }
    else
      return cron_checkparam_atomic($s, $dt);
  }
  function cron_log($s)
  {
    global $s_cronpath;
    if ($fp_log = @fopen($s_cronpath. 'cron.log', 'a'))
    {
      fputs($fp_log, date('Y-m-d H:i:s'). '  '. $s. "\n");
      fclose($fp_log);
    }
    else echo 'nolog';
  }
  function cron()
  {
    global $s_cronpath;
#echo date('Y-m-d H:i:s'), '<br />';
    if (file_exists($s_cronpath. 'cron.runs')
      && 120<($t = time()-($tr=filemtime($s_cronpath. 'cron.runs')))
      && $tr<=filemtime($s_cronpath. 'cron.touch')
    )
    {
#echo "$t / ". date('H:i:s', $tr). " / $ts<br />";
      touch($s_cronpath. 'cron.touch');
#echo 'touch<br />';
      kmail(0,0,'cron alert: phpcron runs since '. $t. ' seconds!', '');
    }
    if (!file_exists($s_cronpath. 'cron.runs')
      && ceil(@(1+filemtime($s_cronpath. 'cron.touch')) / 60) < ceil((1+time()) / 60))
    {
      touch($s_cronpath. 'cron.runs');
      touch($s_cronpath. 'cron.touch');
      cron_log('cron');
#echo "cron<br />";
      list($di, $dh, $dd, $dm, $dw) = explode('-', date('i-H-d-m-w'));
      // check cron jobs
      if ($ar_jobs = @file($s_cronpath. 'crontab.txt'))
      {
#        $ar_log = file($s_cronpath. 'cron.log');
        $n_timeout = set_time_limit(0);
        $n_user_abort = ignore_user_abort (3);
        foreach ($ar_jobs as $k=>$s) if ($s = trim($s))
        {
          list($i, $h, $d, $m, $w, $s_cmd) = explode(' ', $s, 6);
          if ($s_cmd &&
            cron_checkparam($m, $dm) &&
            cron_checkparam($w, $dw) &&
            cron_checkparam($d, $dd) &&
            cron_checkparam($h, $dh) &&
            cron_checkparam($i, $di)
          )
          {
            // execute
            if (file_exists($s_cmd))
            {
              cron_log('job #'. (1+$k). ' `'. $s_cmd. '`');
              include $s_cmd;
              cron_log('job #'. (1+$k). ' done');
            }
            else
              cron_log('job #'. (1+$k). ' file not found `'. $s_cmd. '`');
          }
        }
        cron_log("done\n");
        ignore_user_abort($n_user_abort);
        set_time_limit($n_timeout);
      }
      else
        cron_log("no jobs\n");

      // done, touch touch file and remove lock
      unlink($s_cronpath. 'cron.runs');
    }
  }
?>
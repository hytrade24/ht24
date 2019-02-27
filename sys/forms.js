
/* ###VERSIONSBLOCKINLCUDE### */

function settext(id, value)
{
  document.getElementById(id).value = value;
}
function setselect(id, value)
{
  var el = document.getElementById(id);
  for(var i=0; i<el.options.length; i++)
    if (el.options[i].value==value)
    {
      el.selectedIndex = i;
      break;
    }
}
function setmselect(id, values)
{
  var i, k, el = document.getElementById(id);
  for(i=0; i<el.options.length; i++)
    for(k=0; k<values.length; k++)
      el.options[i].selected = (el.options[i].value==values[k]);
}

/** /
function setdate(id)
{
  var elp, lft=0, top=0,
    el = document.getElementById(id),
    kal = document.getElementById('kalender'),
    now = el.value.split('-')
  ;

  for(elp=el; elp; elp=elp.offsetParent)
  {
    top += elp.offsetTop;
    lft += elp.offsetLeft;
  }
  kal.style.left = lft + 'px';
  kal.style.top = top + 'px';
  kal.style.display = 'block';

  kaldraw(now[0], now[1], now[2], id, now[0], now[1]);
}

function kaldraw(y, m, d, trgid, yd, md)
{
  var ref = y+ ','+ m+ ','+ d+ ',\''+ trgid+ '\',';
  var txt = '<tr><td align="center" colspan="8">'
    +'\n    <form name="control" style="display:inline;">'
    +'\n    <input type=button value="&lt;&lt;-" onClick="kaldraw('+ ref + (yd-1)+ ','+ md+ ');">'
    +'\n    <input type=button value="&lt;--" onClick="kaldraw('+ ref+ (1==md ? yd-1 : yd)+ ','+ (1==md ? 12 : md-1)+ ');">'
    +'\n    &nbsp;&nbsp;'+ yd+ '-'+ md+ '&nbsp;&nbsp;'
    +'\n    <input type=button value="--&gt;" onClick="kaldraw('+ ref + (12==md ? yd+1 : yd)+ ','+ (12==md ? 1 : md+1)+ ');">'
    +'\n    <input type=button value="-&gt;&gt;" onClick="kaldraw('+ ref + (yd+1)+ ','+ md+ ');">'
    +'\n    <input type=button value="X" onClick="kalset('+ ref+ ');">'
    +'\n    </form>'
    +'\n</td></tr>';
//  alert(kopfzeile);
  document.getElementById('kalender').innerHTML = '<table>'+txt+'</table>';
}

function kalset(y, m, d, trgid)
{
  document.getElementById(trgid).value
    = y + '-'+ (m>9 ? '' : '0')+ m+ (d>9 ? '' : '0')+ d;
  document.getElementById('kalender').style.display='none';
}
/**/
/*
function printr(el)
{
  var x, val, s='';
  for(x in el)
  {
    eval ('val = el.'+x+';');
    if (val && val.length)
      s += '\n' + x + '=' + val;
  }
  alert(s);
}
*/

function chktxt(id, type, fmt, lenmin, lenmax)
{
  var el = document.getElementById(id), sreg;
  while (el.value.match(/^\\s/)) el.value = el.value.substr(1,255);
  while (el.value.match(/\\s$/)) el.value = el.value.substr(0, el.value.length-1);
  if (lenmin && ''==el.value) return el.title + ' fehlt';
  if (el.value.length < lenmin) return el.title + ' zu kurz';
  if (lenmax && el.value.length > lenmax) return el.title + ' zu lang';
  if ('' == el.value) return '';
  sreg = '';
  switch (type)
  {
    case 'url':
      sreg = '\\w+:\/\/';
      // fallthrough
    case 'uri':
      sreg = '^'+sreg+'([_a-z0-9-]+\\.)+([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|e?biz|coop|info|museum|name|pro)$';
      break;
    case 'email':
      sreg = '^[_a-z0-9-]+(\\.[_a-z0-9-]+)*\\.?@([_a-z0-9-]+\\.)+([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|biz|coop|info|museum|name|pro)$'
      break;
    case 'iv':
//alle:      sreg = '^\\d+(\\s+(microsecond|second|minute|hour|day|week|month|quarter|year)|\.\\d+\\s+(second|minute|hour|day)_microsecond|\:\\d+\\s+(minute_second|hour_minute)|\:\\d+\:\\d+\\s+(hour_second)|\\s+\\d+\:\\d+\:\\d+\\s+day_second|\\s+\\d+\:\\d+\\\s+day_minute|\\s+\\d+\\s+day_hour|-\\d+\\s+year_month)$';
      sreg = '^\\d+\\s+(microsecond|second|minute|hour|day|week|month|year)$';
      break;
    case 'num':
      if (fmt.match(/^-/)) { sreg = '[+-]?'; fmt = fmt.substr(1,255); }
      var f2 = fmt.match(/^(\d+)?(\.)?(\d+)?/);
      sreg += '\\d' + (f2[1] ? '{0,' + f2[1]+ '}' : '*');
      if (f2[2])
        sreg += '.\\d' + (f2[3] ? '{0,' + f2[3]+ '}' : '*');
      sreg = '^' + sreg + '$';
      break;
    case 'date':
      sreg = '^\\d{4}-(0?\\d|1[0-2])-([0-2]?\\d|30|31)$';
      break;
  }
  if (el.value.toLowerCase().match(sreg))
    return '';
  else
    return el.title + ': ungültiges Format';
}

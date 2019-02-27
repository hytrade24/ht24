
/* ###VERSIONSBLOCKINLCUDE### */

var aktreiter = 0;
function reiterwechsel(reiter)
{
  if (aktreiter)
  {
    document.getElementById('reiter'+aktreiter).style.display = 'none';
    document.getElementById('R'+aktreiter).className = 'reiterPassiv';
  }
  aktreiter = reiter;
  if (reiter)
  {
    obj = document.getElementById('reiter'+aktreiter);
    obj.style.display = 'block';
    document.getElementById('R'+aktreiter).className = 'reiterAktiv';
  }
}
var flag = 0;
function setFlag(val)
{
  flag = val;
}
function checkFlag()
{
  if (flag)
    return confirm ('Your changes have not been written.\nDo you want to proceed anyway and lose changes?\nIf not, click "Cancel" now and submit the form!');
  return true;
}

function showlen(srcel, trgname, maxlen)
{
  document.getElementById(trgname).innerText=srcel.innerText.length+' characters ('+maxlen+' max)';
}
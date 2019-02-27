
/* ###VERSIONSBLOCKINLCUDE### */

var aktreiter = 0;

function reiterwechsel(reiter)
{
  if (aktreiter && aktreiter!=reiter)
  {
    document.getElementById('reiter'+aktreiter).style.display = 'none';
    document.getElementById('R'+aktreiter).className = 'reiterPassiv';
  }
  aktreiter = reiter;
  if (reiter)
  {
    document.getElementById('reiter'+reiter).style.display = 'block';
    document.getElementById('R'+reiter).className = 'reiterAktiv';
    if (el = document.getElementById('tabno'))
      el.value = reiter;
  }
}
var flag = 0;
function setFlag(val)
{
  flag = val;
}
function checkFlag()
{
  var win=window, label='';
  if (checkFlag.arguments.length)
    win = checkFlag.arguments[0];
  if (checkFlag.arguments.length>1)
    label = ' ' + checkFlag.arguments[1];
  if (win.flag)
    return confirm ('Ihre Eingaben'+label+' wurden noch nicht gespeichert.\nMöchten Sie trotzdem fortfahren?\nUm Ihre Eingaben zu sichern, drücken Sie bitte abbrechen!');
  return true;
}

function popup(w,h)
{
  return popup2(w,h,'eakpop');
}
function popup2(w,h,n)
{
  return window.open('about:blank', n, 'width='+w+',height='+h+',resizable=yes,scrollbars=yes');
}

function dialogTreeDel(id, folder)
{
  showModalDialog(
      ('tpl/de')
      + '/dlg.treedel.html', dialogTreeDel.arguments,
      'height: 85px; width: 433px; top: 300px; left: 300px;', function (result) {
        if (result) location.href=result;
      });
}

function showlen(srcel, trgname, maxlen)
{
  document.getElementById(trgname).innerText=srcel.innerText.length+' Zeichen (max. '+maxlen+')';
}

ajax_callback = null;
ajax_return = '';
function ajax(url)
{
  if (ajax.arguments.length>1)
    ajax_callback = ajax.arguments[1];
  else
    ajax_callback = null;
  var p = url.lastIndexOf('/');
  var s = url.substr(0,p+1) + 'ajax/' + url.substr(p+1, 255);
  if (navigator.appName.match('^Opera') && document.getElementById('ajaxframe').src == s)
    document.getElementById('ajaxframe').contentWindow.location.reload();
  else
    document.getElementById('ajaxframe').src = s;
  return false;
}
function ajax_rcv()
{
  if (navigator.appName.match('^Opera') && ajax_rcv.arguments.length)
    ajax_return = ajax_rcv.arguments[0];
  else
    ajax_return = document.getElementById('ajaxframe').contentWindow.document.body.innerHTML;
  if (ajax_callback)
    eval(ajax_callback+';');
}

function trhelp(s_help)
{
  //help = window.open('index.php?frame=popup&page=help_'+s_help,'help','width=600,height=500,scrollbars=auto,resizable=yes');
  (helpme = popup2(600,500,'trhelp')).location.href='index.php?frame=popup&page=help_'+s_help;
  //helpme.focus();
}
function popupfkt(seite,x,y)
{
  document.popup_input1 = null;
  document.popup_input2 = null;
  (helpme = popup2(x,y,'popup_fkt')).location.href='index.php?frame=popup&page='+seite;
}
function popupfktex(seite,x,y,input1,input2)
{
  document.popup_input1 = input1;
  document.popup_input2 = input2;
  (helpme = popup2(x,y,'popup_fkt')).location.href='index.php?frame=popup&page='+seite;
}
//von jan
//row zu einem table adden bei den umfragen
function addRow()
{
  var tbl = document.getElementById('answerTable');
  var lastRow = tbl.rows.length - 1;
  var iteration = lastRow;
  var row = tbl.insertRow(lastRow);
	var style = lastRow%2;
	row.className = 'zeile'+style;
  
  //td1
  var td = row.insertCell(0);
  var textNode = document.createTextNode(iteration);
	td.align = "center";
	td.setAttribute('style', 'font-weight:bold;', 0);
  td.appendChild(textNode);
  
	//td2
  var td = row.insertCell(1);
	var el = document.createElement('input');
  el.type = 'text';
  el.name = 'answer' + iteration;
  el.id = 'answer' + iteration;
  el.size = 75;
  td.appendChild(el);  
	
	//td3
  var td = row.insertCell(2);
	var el = document.createElement('input');
  el.type = 'text';
  el.name = 'votes' + iteration;
  el.id = 'votes' + iteration;
  el.size = 1;
	el.value = 0;
  td.appendChild(el);  
	
  //td4
  var td = row.insertCell(3);
	
	var anz_answers = document.getElementById('anz_answers');
	var anz = parseInt(anz_answers.value) + 1;
	anz_answers.value = anz;

}
function addRow2()
{
  var tbl = document.getElementById('answerTable');
  var lastRow = tbl.rows.length - 3;
  var iteration = lastRow + 1;
  var row = tbl.insertRow(lastRow);
  
  //td1
  var td = row.insertCell(0);
  var textNode = document.createTextNode('Antwort: '+iteration);
	td.align = "center";
	td.setAttribute('style', 'font-weight:bold;', 0);
  td.appendChild(textNode);
  
	//td2
  var td = row.insertCell(1);
	var el = document.createElement('input');
  el.type = 'text';
  el.name = 'answer' + iteration;
  el.id = 'answer' + iteration;
  el.size = 75;
  td.appendChild(el);  
	
	var anz_answers = document.getElementById('anz_answers');
	var anz = parseInt(anz_answers.value) + 1;
	anz_answers.value = anz;

}

function priceToBrutto(inputId, taxPercent) {
	if ((jQuery("#"+inputId).length > 0) && (jQuery("#"+inputId+"_BRUTTO").length > 0)) {
		var netto = jQuery("#"+inputId).val().replace(",", ".");
		var brutto = netto * (100 + taxPercent) / 100;
		brutto = Math.round(brutto * 100) / 100
		jQuery("#"+inputId+"_BRUTTO").val(brutto);
		if (!isNaN(brutto)) {
		    jQuery("#"+inputId+"_BRUTTO").val(brutto);
        }
	}
}

function priceToBruttoDyn(inputId, taxInputId, jsonTaxList) {
	if ((jQuery("#"+inputId).length > 0) && (jQuery("#"+inputId+"_BRUTTO").length > 0)) {
		var taxIndex = jQuery("#"+taxInputId).val();
		var taxPercent = parseFloat( jsonTaxList[taxIndex] );
		var netto = parseFloat( jQuery("#"+inputId).val().replace(",", ".") );
		var brutto = netto * (100 + taxPercent) / 100;
		brutto = Math.round(brutto * 100) / 100;
		if (!isNaN(brutto)) {
		    jQuery("#"+inputId+"_BRUTTO").val(brutto);
        }
	}
}

function priceToNetto(inputId, taxPercent) {
	if ((jQuery("#"+inputId).length > 0) && (jQuery("#"+inputId+"_BRUTTO").length > 0)) {
		var brutto = jQuery("#"+inputId+"_BRUTTO").val().replace(",", ".");
		var netto = brutto * 100 / (100 + taxPercent);
		netto = Math.round(netto * 10000) / 10000
		if (!isNaN(netto)) {
            jQuery("#" + inputId).val(netto);
        }
	}
}

function priceToNettoDyn(inputId, taxInputId, jsonTaxList) {
	if ((jQuery("#"+inputId).length > 0) && (jQuery("#"+inputId+"_BRUTTO").length > 0)) {
		var taxIndex = jQuery("#"+taxInputId).val();
		var taxPercent = parseFloat(jsonTaxList[taxIndex]);
		var brutto = parseFloat( jQuery("#"+inputId+"_BRUTTO").val().replace(",", ".") );
		var netto = brutto * 100 / (100 + taxPercent);
		netto = Math.round(netto * 10000) / 10000
		if (!isNaN(netto)) {
            jQuery("#" + inputId).val(netto);
        }
	}
}
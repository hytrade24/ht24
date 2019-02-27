
/* ###VERSIONSBLOCKINLCUDE### */

//// Bewertung by Jura

function voteJS()
{
  //private variablen
  var self = this;
  var point = (point ? point : 0);
  var IE = document.all&&!window.opera;
  var state = 0; // der letzte punkte stand
  var elm = false;
  var pl = 0;
  var pbereich = Array();
  var rank = 0; //
  var p = 0; // anzahl der punkte
  var debug = false;
  var url = false;
  var input = 'ranking'; // input-feld für das ergebnis
  // öffentliche variablen  
  //this.placed = (this.placed ? self.placed : false);
  
  //if(obj.style.display == 'none')
  //  obj.style.display = 'block';
  
  //öffentliche methoden
  this.init = function (e, s, pe, be, u) {
	  elm = document.getElementById(e);
	  state = s;
	  p = (pe ? pe : 5);
	  b = (be ? be : false);
	  url = u;
	  //debug = document.getElementById('debugJS');
	  
      punktliste();
  }
  
  this.set = function (i) {
	  // Optionaler Parameter für alternatives Input-Feld (bei mehreren votes auf einer Seite benötigt)
	  input = (i ? i : 'ranking');
	  addEventSimple(elm, "mousemove", moveBar);
      addEventSimple(elm, "mouseout", resetBar);
      addEventSimple(elm, "click", setVote);  
  }
  
  //private methoden
  function punktliste()
  {
	var bereich = (elm.offsetWidth / p);
	for(i=0; i<(p+1); i++)
	{
	   pbereich[i] = (bereich*i);
	   //alert(pbereich[i]);
	}
  }
  
  function getRank(point)
  {
	if(pl > pbereich[(point-1)] && pl < pbereich[point])
	  return point;  
	else
	  return 0;
  }
  
  function moveBar(ereignis)
  {
	var mouseX = (IE) ? window.event.clientX : ereignis.pageX;
	pl = mouseX-elm.offsetLeft;
	
	for(i=1; i<(p+1); i++) {
		if(getRank(i) != 0)
		{
		  rank = getRank(i);
		  break;
		}
	}
	//alert(url);
	elm.style.backgroundImage = "url(/gfx/stars_"+rank+".png)";
	//debug.innerHTML = '<br>Rank (votebar):'+rank+'<br>MausX:'+mouseX+'<br>pbereich:'+pbereich[(rank-1)];
	
	if(pl <= 0 || pl >= elm.offsetWidth)
	  resetBar();
  }

  function resetBar()
  { 
	elm.style.backgroundImage = "url(/gfx/stars_"+state+".png)";
	  
	removeEventSimple(elm, "mousemove", moveBar);
    removeEventSimple(elm, "mouseout", resetBar);
    removeEventSimple(elm, "click", setVote);
  }
  
  function setVote()
  {
	rank = 0;
	for(i=1; i<(p+1); i++)
	{
	   if(getRank(i) != 0)
		{
		  point = getRank(i);
		  state = point;
		  break;
		}
	}
	//alert("hier?"); ///index.php?page="+document.voteform.page.value+"&frame="+document.voteform.frame.value+"&FK="+document.voteform.FK.value+"&table="+document.voteform.table.value+"&RATING="+point);
    document.getElementById(input).value = point;
	
	
	if(document.getElementById(input).value != 0 && b){
		if(!document.getElementById("msgBOX")) {
		  createTag(false, "div", "id=msgBOX");
		  createTag("msgBOX", "iframe", "src="+escape(url+"&RATING="+point)+"|style=width:"+getStyle(document.getElementById('msgBOX'), "width")+"px;height:"+getStyle(document.getElementById('msgBOX'), "height")+"px;border:0px");//ajaxSubmit(document.getElementById('voteform'),'/index.php',0,false,'ratingdiv');
	      setTimeout("document.body.removeChild(document.getElementById('msgBOX'))", 3000);
		}
	}
  }
}
////end

var vote = new voteJS();


function explode(hack, string)
{
  var explode_str;
  if(explode_str = string.split(hack))
   return explode_str;
  else
   return false;
}

function getStyle(obj, styleProp) {
	if (obj.currentStyle) // Styles für Internet Explorer
	{
	  var y = obj.currentStyle[formatStyle(styleProp)];
	}	
	else if (window.getComputedStyle) // Styles für Mozilla
	{
	   var y = document.defaultView.getComputedStyle(obj,null).getPropertyValue(styleProp);
	}
	//Browserweiche für IE (border)
	y = y.replace(/medium/, '5px');
	y = y.replace(/px/, '');
	return y;
}
	
function formatStyle(styleName){
	if(x = explode('-', styleName))
	{
	  var y = x[0];
	  for(i=1; i<x.length; i++)
	  {
	    y = y + x[i].substring(0, 1).toUpperCase() + x[i].substring(1).toLowerCase();  	 
	  }
	  return y;
	}
}

function checkLength(obj)
{
  document.getElementById('zz').innerHTML = obj.value.length;
  if((obj.value.length) > 255) {
    document.getElementById('zz').style.color = "red";
	document.getElementById('zz').style.fontWeight = "bold";
  }
  else {
    document.getElementById('zz').style.color = "black";
	document.getElementById('zz').style.fontWeight = "normal";
  }
}

function createTag(id, element, att) {
		  var ct = document.createElement(element);
		  var atts = explode("|", att);
		  var newTag = false;
			 
		  for(i=0; i<atts.length; i++) {
			var attVal = explode("=", atts[i]);
			ct.setAttribute(attVal[0], unescape(attVal[1]));
		  }
		  
		  if(id) {
			var ide = false;
			//alert(document.getElementsByTagName(id)); 
			
			if(document.getElementById(id) != null) 
			  ide = document.getElementById(id);
			else if (document.getElementsByTagName(id).item(0) != null)
			  ide = document.getElementsByTagName(id).item(0);
			if(ide)
			  newTag = ide.appendChild(ct);
		  }
		  else
			newTag = document.body.appendChild(ct);
		  return (newTag ? true : false);
		}

function addEventSimple(obj,evt,fn) {
	if (obj.addEventListener)
		obj.addEventListener(evt,fn,false);
	else if (obj.attachEvent)
		obj.attachEvent('on'+evt,fn);
}

function removeEventSimple(obj,evt,fn) {
	if (obj.removeEventListener)
		obj.removeEventListener(evt,fn,false);
	else if (obj.detachEvent)
		obj.detachEvent('on'+evt,fn);
}
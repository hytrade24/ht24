
/* ###VERSIONSBLOCKINLCUDE### */


//// Bilder Lupe by Jura

var abstand;
var hoehe;
var breite;
var x = 0;
var y = 0;
var y2;
var xWidth;
var xSideW;
var xHeight;
var xSideH;
var h;
var w;
var alt;
var url;
var show;

function bilder_lupe(show, url, h, w, alt) //bildlupe(true, url, hohe, breite);
{
  
  abstand = 20;
  h = '100';
  w = '100';
  h = parseInt(h);
  w = parseInt(w);
  document.onmousemove = verfolgen;
  if(show == true)
  {
    document.getElementById('bildLupe').style.display = 'block';
	document.getElementById('bildLupe').style.height = h+'px'; 
	document.getElementById('bildLupe').style.width = w+'px';
	document.getElementById('bildLupe').innerHTML = '<img src="'+url+'" />';
	hoehe = parseInt(h);
    breite = parseInt(w);
  } // wenn show true ist
  else
  {
	document.getElementById('bildLupe').style.display = 'none';
  } // wenn show = false
  if( typeof( window.pageYOffset ) == 'number' ) {
  	x = window.pageXOffset; 
  	y = window.pageYOffset;
  } else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ) {
    //DOM compliant
    y = document.body.scrollTop;
    x = document.body.scrollLeft;
  } else if( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) ) {
    //IE6 standards compliant mode
    y = document.documentElement.scrollTop;
    x = document.documentElement.scrollLeft;
  }
  
  y2 = window.outerHeight; // einstellung für die Höhe des Anzeigebereichs
  //alert(y2);
  
}
function verfolgen (Ereignis) {
	
  if (!Ereignis)
    Ereignis = window.event;

////////// Prüft die Breite für Links oder Rechts
	if(isNaN(Ereignis.pageX)) // Falls IE, weil er pageY/X nicht kennt
	{
	  xWidth = 	breite + abstand + (Ereignis.clientX + x) + 16;
	  xSideW = (Ereignis.clientX + x) + abstand;
	} else
	{
	  xWidth = breite + abstand + Ereignis.pageX + 16;
      xSideW = Ereignis.pageX + abstand;
	}
	  
	//document.getElementById('bildLupe').innerHTML = 'hoehe: '+hoehe+' abstand: '+abstand+' Ereignis.pageY: '+Ereignis.pageY+' HILFE: '+xWidth ;
  if(x  < xWidth)
  {
	if(isNaN(Ereignis.pageX)) // Falls IE, weil er pageY/X nicht kennt
	{
	  xSideW = (Ereignis.clientX + x) - (breite + abstand);
	} else
	{
	  xSideW = Ereignis.pageX - (breite + abstand);
	}
 
    
  } // wenn größer als fenter
/////// end
////////// Prüft die Höhe für Oben oder Unten
//!!!!!!!!! DONT TOUCH !!!!!!!!!!!!	
if(isNaN(Ereignis.pageY)) // Falls IE, weil er pageY/X nicht kennt
	{
	  xHeight = (Ereignis.clientY + y) - hoehe;
      xSideH = (Ereignis.clientY + y) - hoehe;
	} else
	{
	  xHeight = Ereignis.pageY - hoehe;
      xSideH = Ereignis.pageY - hoehe;
	}
//document.getElementById('bildLupe').innerHTML = 'xHeight: '+xHeight+' '+y ;
  if((y) > xHeight)
  {
	if(isNaN(Ereignis.pageY)) // Falls IE, weil er pageY/X nicht kennt
	{
	  xSideH = (Ereignis.clientY+y);
	} else
	{
	  xSideH = (Ereignis.pageY);
	}
  } // wenn höher als fenster
/////// end   

  if (document.getElementById) {
	  //alert("hier endets");
	document.getElementById('bildLupe').style.left  = xSideW+'px';
    document.getElementById('bildLupe').style.top = xSideH+'px';

  } 
  //document.getElementById('testhelper').innerHTML = 'xWidth: '+xWidth+', xHeight: '+xHeight;
}
//// end
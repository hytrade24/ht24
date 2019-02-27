
/* ###VERSIONSBLOCKINLCUDE### */

// eigene AJax Zusatzfunktionen

var ajaxChangedFields = new Array();

function ajaxOnOk()
{
  return true;
} // ajaxOnOk

function ajaxOnErr()
{
  //alert('kommt der hier an?');
  return true;
} // ajaxOnOk

function doAjaxSubmit()
{
  return false;
}

function handleAjaxErr(ar)
{
  ausgabe = '';
  for(i=0; i<ar.length; i++)
    if(ar[i] != '')
	{
	  //alert(ar[i]);
	  ausgabe += '<li>'+ar[i]+'</li>';
	}
  
  if(ausgabe != '' && document.getElementById('msgContainer'))
  {
	document.getElementById('msgContainer').style.display = 'block';
	document.getElementById('errContainer').style.display = 'block';
	document.getElementById('msgErr').innerHTML = ausgabe;
	document.getElementById('msgErr').style.display = 'block';    
  }
  //alert('AUSGABE: '+ausgabe);
  if(ausgabe != '')
    ajaxOnErr();
} // ajax Error Handling

function handleAjaxOk(ar)
{
  ausgabe = '';
  for(i=0; i<ar.length; i++)
    if(ar[i] != '')
	{
	  //alert(ar[i]);
	  ausgabe += ar[i];
	}
  
  if(ausgabe != '' && document.getElementById('msgContainer'))
  {	
	document.getElementById('msgContainer').style.display = 'block';
	document.getElementById('msgOk').innerHTML = ausgabe;
	document.getElementById('msgOk').style.display = 'block';
	document.getElementById('okContainer').style.display = 'block';
	
  }
  if(ausgabe != '')
    ajaxOnOk();
  
}

function ajaxSubmit(value,path,debug,button,feld)
{
  // versucht den eigentlichen Submitter zu laden
  // geht das nicht, wird gemeckert :-)
  req = doAjaxSubmit(value,path,debug,button,feld);
  if(!req)
    alert('Missing Function called doAjaxSubmit!');
  //alert(req);

} // ajaxSubmit()

function doAjaxSubmit(value,path,debug,button,feld) 
{  
  // ggf. voherige Aktionen zurücksetzen
  if(button)
  {
    button.disabled = true;
    button.style.color = '#222222';
    button.style.backgroundColor = '#666';
    resetAjaxAction()
  }
  // Create new JsHttpRequest object.
  req = new JsHttpRequest();
  // Code automatically called on load finishing.
  req.onreadystatechange = function() 
  {        
    //alert(req.readyState);
	if (req.readyState == 4) 
	{
      // Gesamte Rückgae aus _RESULT befindet sich in req.responseJS
	  if(req.responseJS)
	  {
      // Fehler und OK Meldungen
	  err = new Array(); i=0;
	  for(param in req.responseJS.msg.err)
	  {  
	    err[i] = req.responseJS.msg.err[i];
		i++;
	  }
	  handleAjaxErr(err);
	  
	  ok = new Array(); i=0;
	  for(param in req.responseJS.msg.ok)
	  {  
	    ok[i] = req.responseJS.msg.ok[i];
		i++;
	  }
	  handleAjaxOk(ok);	    
	  
	  // Formularfelder
	  // typen für rote Rahmen
	  redBorder = new Array();
	  redBorder['text'] = 1;
	  redBorder['password'] = 1;
	  redBorder['radio'] = 1;	  
	  redBorder['checkbox'] = 1;	  
	  for(feld in req.responseJS.formfields)
	  {  
	    if(req.responseJS.formfields[feld]['err'])
		{
		  //alert('fehler in '+feld);
		  form_felder = document.getElementsByName(feld);
		  for(k=0; k<form_felder.length; k++)
		  {
			tag = form_felder[k].tagName;
			switch(tag)
			{
			  case 'INPUT':
			   if(redBorder[form_felder[k].type])
			   {
			     form_felder[k].style.border = '1px #FF0000 solid';
			     ajaxChangedFields.push(feld);
			   }
			  break;
			  default:
			  alert('Unknown Form Tag in panel.j line 101');
			} // switch
		  } // for über gefundene Felder
		} // feld hat fehler verursacht
		if(req.responseJS.formfields[feld]['wert'])
		{
		  for(k=0; k<form_felder.length; k++)
		  {		
		    form_felder[k].value = req.responseJS.formfields[feld]['wert'];
		  } // for über felder
		} // der Wert des Felds sol geändert werden
	  }	// for über formfields  	  
    } // wenn response nicht leer
	} // wenn ajax Aufruf geklappt hat
	  // Debugging
	/*
	if(debug == 1 && req.responseText > '')
	{
	  if(document.getElementById('debug').innerHTML)
	  {
	    document.getElementById('debug').innerHTML = '<h1>Ajax Debugging Information</h1>' + req.responseText;		
	    document.getElementById('debug').style.display = 'block';
	  }
	} // debug	
	*/
	if(feld && req.responseText > '' && (document.getElementById(feld) != null))
	{
	  document.getElementById(feld).innerHTML = req.responseText;
	}
  } // function req.readyState
    
  // Prepare request object (automatically choose GET or POST).
  req.open(null, path, true);    
  // Send data to backend.
  req.send( { q: value } );
  
  if(button)
  {
    button.style.color = '#000000';
    button.style.backgroundColor = '#CCCCCC';  
    button.disabled = false;
  }
  
  return req;
} // function doAjaxSubmit

function resetAjaxAction()
{  
  if(document.getElementById('debug'))
    document.getElementById('debug').innerHTML = 'none';
  
  elemente_leeren = new Array('debug', 'msgErr', 'msgOk', 'okContainer', 'errContainer');
  for(i=0; i<elemente_leeren.length; i++)
  {
	if(feld = document.getElementById(elemente_leeren[i]))
	  feld.style.display = 'none';
  }
  
  for(i=0; i<ajaxChangedFields.length; i++)
  {
	felder = document.getElementsByName(ajaxChangedFields[i]);
	for(k=0; k<felder.length; k++)
	{
	  felder[k].style.border='1px #000000 solid;';
	}
  }
}

/* ###VERSIONSBLOCKINLCUDE### */

var IE = document.all&&!window.opera;

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

function hover(obj) {
	//obj.style.backgroundImage = "url(../bilder/navi_a.gif)";
	alert('test');
}

window.onload = function () {
	
	if(IE){
	  var obj = document.all.mainNav;

	  var Ausrichtung = document.createAttribute("onmousclick");
      Ausrichtung.nodeValue = "hover(this);";
      obj.getElementsByTagName('li')[0].setAttributeNode(Ausrichtung);

	}
}
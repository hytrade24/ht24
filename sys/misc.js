
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
    return confirm ('Ihre Eingaben'+label+' wurden noch nicht gespeichert.\nM�chten Sie trotzdem fortfahren?\nUm Ihre Eingaben zu sichern, dr�cken Sie bitte abbrechen!');
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

menueoffen = false;
offen = false;

function showmenue(element,wert)
{
  //alert(menueoffen);
  if(wert)
  {
    if(offen != false)
	  document.getElementById(offen).style.display='none'
	menueoffen = true;	
	menuecontrol(element,'block');
	offen = element;
    
  }
  else
  {
    menueoffen = false;
	window.setTimeout("menuecontrol('"+element+"','none')",200);
  }
}

function menuecontrol(element,wert)
{
  if(wert == 'block' || menueoffen==false)
  {
    document.getElementById(element).style.display=wert;
	breite = document.getElementById(element).offsetWidth;
	for(i=0; i<document.getElementById(element).childNodes.length; i++)
	{
	  if(document.getElementById(element).childNodes[i].offsetWidth > breite)
	    breite=document.getElementById(element).childNodes[i].offsetWidth;
	}
	//alert(breite);
	links = document.getElementById(element).offsetLeft;
	fenster = document.getElementsByTagName("body")[0].offsetWidth;
	rest = (fenster-links)-breite;	
	if(rest < 0)
	{
	  minus=40;
	  if(navigator.appName.substring(0, 9) == 'Microsoft')
	    minus = 20;
	  document.getElementById(element).style.left =(fenster-breite)-minus;
	  //document.getElementById(element).style.setAttribute("text-align","right");
	}
	//alert(rest);
	//document.getElementById(element).style.setAttribute("background-color", "red");
  }
}

function go(showflash,width,height){

  if (showflash != "") {

    var str = "height=" + height + ",innerHeight=" + height;
    str += ",width=" + width + ",innerWidth=" + width;
    if (window.screen) {
      var ah = screen.availHeight - 30;
      var aw = screen.availWidth - 10;

      var xc = (aw - width) / 2;
      var yc = (ah - height) / 2;

      str += ",left=" + xc + ",screenX=" + xc;
      str += ",top=" + yc + ",screenY=" + yc;
    }
    str += ",scrollbars=Yes,dependent=Yes"
    //open(DoWhat.value, "remote", str);
     var wintoload4 = open(showflash, "ebizadminpopup",str);
     wintoload4.focus();
  }
}

function dialogTreeDel(id, folder)
{
  var rValue=showModalDialog(
    ('tpl/de')
    + '/dlg.treedel.html', dialogTreeDel.arguments,
    'resizable:yes;scroll:no;status=no;dialogHeight=85px;dialogWidth=433px;dialogTop=300px;dialogLeft=300px');
  if (rValue) location.href=rValue;
}

function help(ident)
{
//alert (location.href.match(/\/admin\/(index\.php)?/));
  var w = popup2(400,300,'eaffhelp'), reg = /\/admin\/(index\.php)?/;
  w.location.href=(location.href.match(reg) ? '../' : '') + 'help_'+ident+'.html';
}

function suchform()
{
  layer = document.getElementById('suchform');
  status = layer.style.visibility;
  neu = 'visible';
  if(status == 'visible')
    neu = 'hidden';
  layer.style.visibility = neu;
}

//von jan
var pop = null;

function do_popdown() {
		if (pop && !pop.closed) pop.close();
}

function do_popup(obj) {
	var width = 400;
	var height = 250;
	
  if (arguments[1])
	{
		width = arguments[1];
	}
	
	if (arguments[2])
	{
		height = arguments[2];
	}
		
	var url = (obj.getAttribute) ? obj.getAttribute('href') : obj.href;
	if (!url) return true;
	var args = 'width='+width+',height='+height+',resizable=yes,top=150,left=200,scrollbars=yes,status=yes';
	do_popdown();
	pop = window.open(url,'',args);
	return (pop) ? false : true;
}

var api_key = 'ABQIAAAA0tXF1tJR1QgSG9HJf4lrjBQdTyYrkQQbm1riap6HwRLyrDuwORSeu-Ruz3kTCR15xMQx2T0Hea0TaQ';
var timer_qs;
var row_selection = -1;
var row_count = 0;
var row_list;

jQuery(function() {
        jQuery("#SEARCH_FORM").submit(function() {
                if(jQuery("#list_offers").is(":visible")) {
                        return false;
                } else {
                        return true;
                }
        });
        jQuery(".presearch_quick_link").on('click', function() {
                jQuery("#SEARCH").val(jQuery(this).text());
                $('#list_offers').hide();
                $('#SEARCH_FORM').submit();
                return false;
        });
});
function presearch_quick(text_element, e, do_search){

    var key = (e ? (e.which ? e.which : e.keyCode) : 0);
    var text = text_element.value;

    // Key controls
    if ((key == 13) || (key == 27) || (key == 38) || (key == 40)) {
        debugger;
        if (key == 13) {
            // Return
            if (row_selection >= 0) {
                text_element.value = row_list[row_selection];
            }
            $('#SEARCH').val(text_element.value);
            $('#list_offers').hide();
            $('#SEARCH_FORM').submit();
            return false;
        }
        if (key == 27) {
            // Escape
            window.setTimeout(function(){
                $('#list_offers').hide();
            }, 300);
            return;
        }
        if (key == 38)
            row_selection = row_selection - 1; // Up
        if (key == 40) {
            row_selection = row_selection + 1; // Down
        }
        if (row_selection >= row_count)
            row_selection = row_count - 1;
        if (row_selection < 0)
            row_selection = -1;

        // Update selection
        var rows = $('#list_offers > tr');
        $.each(rows, function(row, content) {
            if (row == row_selection) {
                rows[row].className = "selected";
                text_element.value = $(content).find('a').first().html();
            } else
                rows[row].className = "";
        });
        $('#SEARCH').val(text);
        return;
    }
    $('#SEARCH').val(text);

        // Hack for reducing request count
        if (!do_search && (text.length >= 3)) {
            window.clearTimeout(timer_qs);
            timer_qs = window.setTimeout(function(){
                presearch_quick(text_element, false, true);
            }, 500);
            return;
        }

        if (text.length >= 2) {
            $.ajax({
                    url:            ebiz_trader_baseurl + "index.php?page=artikel-suche&frame=ajax&SEARCH_AJAX=" + encodeURI(text),
                    type:           'GET',
                    dataType:       'json',
                    success:        function(obj) {
                            // Erfolg
            if (obj.fail) {
                $('#list_offers').hide();
            } else {
                var table = document.createElement("table");
                table.id = "list_offers";
                table.className = "list_offers";
                table.cellPadding = 0;
                table.cellSpacing = 0;
                table.style.position = "absolute";
                $('#div_offers').html("");
                $('#div_offers').append(table);

                row_count = 0;
                row_list = new Array();

                $.each(obj.offers, function(offer, val) {
                    var text = obj.offers[offer];
                    var row = document.createElement("tr");
                    var col = document.createElement("td");
                    var link = document.createElement("a");
                    // Click event
                    link.href = "#"; //"javascript: $('#SEARCH').val(\"" + text.replace('"', '\"') + "\");";
                    link.className = "presearch_quick_link";
                    //$(link).click(function() { $('#SEARCH').val(text); $('#sfeld1').val(text); return false; });
                    link.innerHTML = text;
                    // Add to table
                    col.appendChild(link);
                    row.appendChild(col);
                    if (row_count == row_selection)
                        row.className = "selected";
                        table.appendChild(row);
                        // Update "cache"
                        row_count = row_count + 1;
                        row_list.push(text);
                    });

                    var element_pos = $(text_element).position();
                    $('#list_offers').css({
                        left:                   element_pos.left+"px",
                        top:                    (element_pos.top+20)+"px",
                        'min-width':    $(text_element).outerWidth()
                    }).show();
                }
                        }
                });
    }
    else {
        $('#list_offers').hide();
    }

    return;
}

/*

function presearch_quick(text_element, e, do_search){
    var key = (e ? (e.which ? e.which : e.keyCode) : 0);
    var text = text_element.value;
    
    // Key controls			
    if ((key == 13) || (key == 27) || (key == 38) || (key == 40)) {
        if (key == 13) {
            // Return
            if (row_selection >= 0) {
                text_element.value = row_list[row_selection];
            }
            $('#SEARCH').val(text_element.value);
            
            //$('#SEARCH_FORM').submit();
            return;
        }
        if (key == 27) {
            // Escape
            window.setTimeout(function(){
                $('#list_offers').hide();
            }, 300);
            return;
        }
        if (key == 38) 
            row_selection = row_selection - 1; // Up
        if (key == 40) {
            row_selection = row_selection + 1; // Down
        }
        if (row_selection >= row_count) 
            row_selection = row_count - 1;
        if (row_selection < 0) 
            row_selection = -1;
        
        // Update selection
        var rows = $('#list_offers > tr');
        for (var row in rows) {
            
        }
        $.each(rows, function(row, content) {
        	if (row == row_selection) {
                rows[row].className = "selected";
                $('#sfeld1').val($(content).find('a').first().html());
        	}
            else 
                rows[row].className = "";
        });
        return;
    }
    
    $('#SEARCH').val(text);
    
    // Hack for reducing request count
    if (!do_search && (text.length >= 3)) {
        window.clearTimeout(timer_qs);
        timer_qs = window.setTimeout(function(){
            presearch_quick(text_element, false, true);
        }, 500);
        return;
    }
    
    if (text.length >= 2) {
		$.ajax({
			url:		ebiz_trader_baseurl + "index.php?page=artikel-suche&frame=ajax&SEARCH_AJAX=" + encodeURI(text),
			type: 		'GET',
			dataType:	'json',
			success:	function(obj) {
				// Erfolg
                if (obj.fail) {
                    $('#list_offers').hide();
                } else {
                    var table = document.createElement("table");
                    table.id = "list_offers";
                    table.className = "list_offers";
                    table.cellPadding = 0;
                    table.cellSpacing = 0;
                    table.style.position = "absolute";
                    $('#div_offers').html("");
                    $('#div_offers').append(table);
                    
                    row_count = 0;
                    row_list = new Array();                 
                    
                    $.each(obj.offers, function(offer, val) { 
                    	var text = obj.offers[offer];
                        var row = document.createElement("tr");
                        var col = document.createElement("td");
                        var link = document.createElement("a");
                        // Click event
                        //link.href = "javascript: $('#SEARCH').val(\"" + text.replace('"', '\"') + "\"); $('#SEARCH_FORM').submit();";
                        $(link).click(function() { $('#SEARCH').val(text); $('#sfeld1').val(text);  });
                        link.innerHTML = text;
                        // Add to table
                        col.appendChild(link);
                        row.appendChild(col);
                        if (row_count == row_selection) 
                            row.className = "selected";
                        table.appendChild(row);
                        // Update "cache"
                        row_count = row_count + 1;
                        row_list.push(text);
                    });
                    
                    var element_pos = $(text_element).position();
                    $('#list_offers').css({
                    	left:			element_pos.left+"px",
                    	top:			(element_pos.top+20)+"px",
                    	'min-width':	$(text_element).outerWidth()
                    }).show();
                }
			}
		});
    }
    else {
        $('#list_offers').hide();
    }
    
    return;
}
*/

function minilogin()
{
	var setvis = 'none';
	var vis = $('#minilogin')[0].style.display;
	if(vis == 'none')
	{
		setvis = 'block';
	}
	$('#minilogin').css('display', setvis);
}

function registerSelectPacketSimple(runtimeId, showPacketSelection) {
    if (typeof showPacketSelection == "undefined") {
        showPacketSelection = false;
    }
    var inputRuntime = jQuery("input[name=FK_RUNTIME][value="+runtimeId+"]");
    var packetId = parseInt( inputRuntime.attr("rel") );
    var usergroupId = parseInt( jQuery("#FK_USERGROUP_"+packetId).val() );
    var buttonSelect = jQuery("#registerMembershipButton-"+packetId);
    // Show usergroup tab
    jQuery(".registerTabs a[href=#usergroupPane"+usergroupId+"]").tab("show");
    // Select runtime
    inputRuntime.prop("checked", true);
    buttonSelect.prop("disabled", false).removeClass("disabled");
    if (!showPacketSelection) {
        // Confirm
        registerSelectPacket(packetId, buttonSelect, 0);
    }
}

function registerSelectPacket(packetId, btn, forbiddenGroups) {
    if(jQuery(btn).is('.disabled') == false) {

        var runtimeInput = jQuery("input[name='FK_PACKET_RUNTIME']");
        var runtimeValue = jQuery("#FK_RUNTIME_"+packetId+":checked").val();
        var usergroupInput = jQuery("input[name='FK_USERGROUP']"); 
        var usergroupeValue = jQuery("#FK_USERGROUP_"+packetId).val();

        runtimeInput.val(runtimeValue);
        usergroupInput.val(usergroupeValue);

        regShowContainerProfile(btn, forbiddenGroups, usergroupeValue);
        CheckAll();
    }


}



function regShowContainerUsergroup() {
    if (!jQuery(this).is(".disabled")) {
        jQuery("#registerContainerProfile").hide();
        jQuery("#registerContainerUsergroup").show();
    }
    return false;
}

function regShowContainerProfile(btn, forbiddenGroups, usergroupId) {
	if (typeof forbiddenGroups != "undefined") {
		if (forbiddenGroups & 1) {
			jQuery("#confirm_private").show();
			jQuery("#confirm_company").hide();
			jQuery("#confirm_private input").attr("required", true);
			jQuery("#confirm_company input").attr("required", false);
		} else if (forbiddenGroups & 2) {
			jQuery("#confirm_company").show();
			jQuery("#confirm_private").hide();
			jQuery("#confirm_company input").attr("required", true);
			jQuery("#confirm_private input").attr("required", false);
		} else {
			jQuery("#confirm_company").hide();
			jQuery("#confirm_private").hide();
			jQuery("#confirm_company input").attr("required", false);
			jQuery("#confirm_private input").attr("required", false);
		}	
	} else {
		jQuery("#confirm_company").hide();
		jQuery("#confirm_private").hide();
	}
    if (!jQuery("#registerContainerPacketButtonNext").is(".disabled")) {
        var runtimeId = jQuery("input[name='FK_RUNTIME']:checked").val();
        regShowPacket(parseFloat(jQuery("#packet-price-"+runtimeId).text()));
        // Benutzergruppen-Abhängige Felder ein-/ausblenden
        jQuery(".control-visible-usergroup").hide().filter("[data-usergroup="+usergroupId+"],[data-usergroup-"+usergroupId+"]").show();
        jQuery(".control-hidden-usergroup").show().filter("[data-usergroup="+usergroupId+"],[data-usergroup-"+usergroupId+"]").hide();
        // Eingabemaske anzeigen / Paketauswahl ausblenden
        jQuery("#registerContainerProfile").show();
        jQuery("#registerContainerUsergroup").hide();
    }
    return false;
}

function regShowPacket(packet_price) {
	if (packet_price > 0) {
		jQuery("#registerContainerProfile input.req_paid").attr("required", true);
		jQuery("#registerContainerProfile select.req_paid").attr("required", true);
		jQuery("#registerContainerProfile span.req_paid").show();
	    jQuery("#registerContainerProfileButtonNextFree").hide().addClass('disabled');
	    jQuery("#registerContainerProfileButtonNextPayed").show().removeClass('disabled');
	} else {
		jQuery("#registerContainerProfile input.req_paid").attr("required", false);
		jQuery("#registerContainerProfile select.req_paid").attr("required", false);
		jQuery("#registerContainerProfile span.req_paid").hide();
	    jQuery("#registerContainerProfileButtonNextPayed").hide().addClass('disabled');
	    jQuery("#registerContainerProfileButtonNextFree").show().removeClass('disabled');
	}

	CheckAll();
}

function regHideProfile() {
	jQuery(".profile").hide();
	jQuery("#profile_info").show();
}

function loginSubmit(form) {
    return true;
    /*
    var username = jQuery(form).find("input[name=user]").val();
    var password = jQuery(form).find("input[name=pass]").val();
    loginSubmitLeads(username, password);
    return false;
    */
}

function loginSubmitLeads(username, password) {
    jQuery.post(ebiz_trader_baseurl+"leads/login/trader", "name="+encodeURIComponent(username)+"&password="+encodeURIComponent(password), function(result) {
        if (result.success) {
            loginSubmitTrader(username, password);
        } else {
            document.location.href = ebiz_trader_baseurl+"system/login,fail,"+encodeURIComponent(username)+".htm";
        }
    });
}

function loginSubmitTrader(username, password) {
    jQuery.post(ebiz_trader_baseurl+"login.php", "frame=ajax&user="+encodeURIComponent(username)+"&pass="+encodeURIComponent(password), function(result) {
        if (result.success) {
            // Redirect to trader backend
            document.location.href = result.url;
        } else {
            // Only registered in leads
            document.location.href = ebiz_trader_baseurl+"leads/user/dashboard";
        }
    });
}
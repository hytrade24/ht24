
/* ###VERSIONSBLOCKINLCUDE### */

// Variables
var kat_is_paid = true;
var kat_prev = 0;
var products = new Array();
var timer_hide;
var timer_check;
var map;
var map_loaded = false;
var geocoder;
var tooltips = new Array();
var pager = new Array();
var input_timer;
var input_timer_desc;
var input_cache = { initialized: false };
var step = 0;
search_blocked = false;

function SubmitDescription() {
	$('#form_step2').submit();
}

// Initialisation function

function InitializeDescription(allow_html) {
	// Jump to label
	window.location.href = "#article_desc";
	
    //if ((!jQuery("#LATITUDE").val()) || (!jQuery("#LONGITUDE").val())) {
    	getLatiLongi();
    //}
    //getProductsByManufacturer(jQuery('FK_MAN').value, 1);
    //google.load("maps", "2");
    var tmp_needed = jQuery('#tmp_needed').val();
    var fields = tmp_needed.split(',');
    for (var field in fields) {
    	var input = jQuery("#"+fields[field]);
        if (input) 
        	validateInput(input);
    }

    InitializeEditor(allow_html);

    var id_ad = jQuery('#DESC_ID_ANZEIGE').val();
    var id_kat = jQuery('#DESC_ID_KAT').val();
    GetUploads(id_ad, id_kat);
}

function InitializeEditor(allow_html, element) {
    if(typeof element ==  "undefined") { element = "BESCHREIBUNG"; }

    // Initialize editor
    if (allow_html) {
        tinyMCE.init({
        	// General options
        	theme : "advanced",
        	mode : "none",
            plugins : "paste",

        	elements : element,
        	theme : "advanced",
        	width: "99%",
        	height: "280px",
        	language: "de",
        	object_resizing : false,
        	convert_fonts_to_spans : true,
        	document_base_url : "/",
        	relative_urls : false,
        	remove_script_host : true,
        	entity_encoding : "raw",
        	add_unload_trigger : false,
        	remove_linebreaks : false,
        	inline_styles : false,

        	theme_advanced_buttons1 : "bold,italic,underline,|,pastetext,pasteword,|,undo,redo,|,link,unlink,forecolor,removeformat,cleanup",
        	theme_advanced_buttons2 : "",
        	theme_advanced_buttons3 : "",
        	theme_advanced_toolbar_location : "top",
        	theme_advanced_toolbar_align : "center",
        	theme_advanced_statusbar_location : "bottom",
        	theme_advanced_styles : "",
            // Example content CSS (should be your site CSS)
        	content_css : "/style/de/style-new.css?" + new Date().getTime()
        });
    } else {
        tinyMCE.init({
        	// General options
        	theme : "advanced",
        	mode : "none",
        	plugins : "bbcode",

        	elements : element,
        	theme : "advanced",
        	width: "99%",
        	height: "280px",
        	language: "de",
        	object_resizing : false,
        	convert_fonts_to_spans : true,
        	document_base_url : "/",
        	relative_urls : false,
        	remove_script_host : true,
        	entity_encoding : "raw",
        	add_unload_trigger : false,
        	remove_linebreaks : false,
        	inline_styles : false,

        	/*theme_advanced_buttons1 : "bold,italic,underline,undo,redo,link,unlink,forecolor,removeformat,cleanup",
        	theme_advanced_buttons2 : "",
        	theme_advanced_buttons3 : "",*/
        	theme_advanced_toolbar_location : "top",
        	theme_advanced_toolbar_align : "center",
        	theme_advanced_statusbar_location : "bottom",
        	theme_advanced_styles : "",
            // Example content CSS (should be your site CSS)
        	content_css : "/style/de/style-new.css?" + new Date().getTime()
        });
    }
    tinyMCE.execCommand("mceAddControl", true, element);
    // Validate description
    window.waitMCE = window.setInterval(function(){
        if (tinyMCE.activeEditor != null) {
            checkNeededFields();
            window.clearInterval(window.waitMCE);
            tinyMCE.activeEditor.onKeyUp.add(function(ed){
                if (input_timer_desc != null) {
                	window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    checkNeededFields();
                    validateInput(jQuery('#' + element));
				}, 1000);
            });
            tinyMCE.activeEditor.onChange.add(function(ed){
                checkNeededFields();
                validateInput(jQuery('#' + element));
            });
        }
    }, 1000);

}

function InitializeBaseEditor(e, validate) {
    tinyMCE.init({
        // General options
        theme : "advanced",
        mode : "exact",
        elements: e,
        plugins : "paste",
        theme : "advanced",
        width: "99%",
        height: "280px",
        language: "de", 
        object_resizing : false,
        convert_fonts_to_spans : true,
        document_base_url : "/",
        relative_urls : false,
        remove_script_host : true,
        entity_encoding : "raw",
        add_unload_trigger : false,
        remove_linebreaks : false,
        inline_styles : false,

        plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

        // Theme options
        theme_advanced_buttons1 : "code,save,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
		theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,|,insertdate,inserttime,preview,|,forecolor,backcolor",
		theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,media,advhr,|,ltr,rtl,|,fullscreen",
		theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking",
        theme_advanced_toolbar_location : "top",
        theme_advanced_toolbar_align : "left",
        theme_advanced_statusbar_location : "bottom",
        theme_advanced_resizing : true,

        theme_advanced_styles : "",
        // Example content CSS (should be your site CSS)
        content_css : "/style/de/style-new.css?" + new Date().getTime()
    });

}
function reInitializeBaseEditor(e) {
    tinyMCE.execCommand("mceAddControl", true, e);

    window.waitMCE = window.setInterval(function(){
        if (tinyMCE.activeEditor != null) {
            checkNeededFields();
            window.clearInterval(window.waitMCE);
            tinyMCE.activeEditor.onKeyUp.add(function(ed){
                if (input_timer_desc != null) {
                    window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    checkNeededFields();
                    validateInput(jQuery('#' + e));
                }, 1000);
            });
            tinyMCE.activeEditor.onChange.add(function(ed){
                checkNeededFields();
                validateInput(jQuery('#' + e));
            });
        }
    }, 1000);
}

function InitializeUploads(id_ad) {
	get_ups(id_ad);
}


function updateCollect() {
	if ($("input[name=ONLY_COLLECT]:checked").length > 0) {
		$("#VERSANDKOSTEN").val("0.00");
		$("#VERSANDKOSTEN").attr("disabled", true);
	} else {
		$("#VERSANDKOSTEN").attr("disabled", false);
	}
}


// Function definitions
function getElementByNameIE(name_tag, name_element) {
    if (navigator.appName == "Microsoft Internet Explorer") {
      // IE hack
      var elements = document.getElementsByTagName(name_tag);
      var results = new Array();
      if (elements != null) {
    	  for (var i = 0; i < elements.length; i++) {
    		  if (elements[i].name == name_element) {
    			  results[results.length] = elements[i];
    		  }
    	  }
      }
      return results;
    }
    // Default
    return document.getElementsByName(name_element);
}

function getSubElementByNameIE(parent_element, name_tag, name_element) {
    if (navigator.appName == "Microsoft Internet Explorer") {
    	// IE hack
    	var elements = parent_element.getElementsByTagName(name_tag);
    	var results = new Array();
    	if (elements != null) {
    		for (var i = 0; i < elements.length; i++) {
    			if (elements[i].name == name_element) {
    				results[results.length] = elements[i];
    			}
    		}
    	}
    	return results;
    }
    // Default
    return document.getElementsByName(name_element);
}
  
function UpdateKatSelector(id_kat, name_current, root_kat, b_done, b_collapsing) {	  
    // collapse root other entries before
    /*
    var roots = getSubElementByNameIE(jQuery("kat_selector"), "li", "child1");
    for (var i = 0; i < roots.length; i++) {
      var id_kat_root = roots[i].id.replace("row", "");
      if (id_kat_root != root_kat) {
        UpdateKatSelector_SetVisibility(id_kat_root, "none");           
      }
    }*/
	/*
	var id_current = jQuery("#form_FK_KAT");
    if (id_kat == 0) {
		// Change symbols back
	    var icons = getSubElementByNameIE(jQuery("#kat_selector"), "img", "icon_expand");
	    for (var i = 0; i < icons.length; i++) {
			var icon = icons[i];
			if (icon.alt == "-") {
				icon.src = "/bilder/icon_plus.png";
				icon.alt = "+";
			}
	    }
	}*/
    
    if (!b_done) {
    	/*
		UpdateKatSelector_SetVisibility(1, "none");
		if (!b_done) {
		 
		  if (id_kat <= 1) {
		       UpdateKatSelector_SetVisibility(1, "");
		       jQuery('#kat_back_to_root').hide();
		  } else {
		       jQuery('#kat_back_to_root').show();
		  }
		}
		UpdateKatSelector_SetParentVisibility(id_kat, 1, "");

		var childs = getSubElementByNameIE(jQuery("#kat_selector"), "li", "child"+id_kat);
		if (childs.length > 0) {
			var target_state = (childs[0].style.display == "none" ? "" : "none");
			UpdateKatSelector_SetVisibility(id_kat, target_state);
		}
		*/
    	var url = ebiz_trader_baseurl + "index.php?page=marktplatz_neu_kat&mode=ajax&do=kats&root="+id_kat+"&paid="+(kat_is_paid ? 1 : 0);
    	jQuery.get(url, function(result) {
    		var html_result = result.tree;
    		jQuery("#kat_list").html(html_result);
    		GetCategorysDone();
    	});
    } else {
        // Deactivate possibly active categories
    	jQuery("#kat_list a.active").removeClass("active");
        var id_current = jQuery("#form_FK_KAT");
        var selection = jQuery("#row"+id_kat);
        var submit_button = jQuery("#form_SUBMIT");
        var link = selection.children("a");
        if (id_kat > 0) {
          link.addClass("active");
          if (confirm('Do you want to choose "' + name_current + '" as a category?')) {
            id_current.val(id_kat);
          	GetInput();
          	//$('form_step1').submit();        
          } else {
          	link.removeClass("active");
          	jQuery("#row"+id_current.val()+" > a").addClass("active");
          }
        }
    }
  }
  
  function UpdateKatSelector_SetParentVisibility(id_kat, id_root, target_state) {
	if (id_kat > 1) {
		// Change Symbol
		var icon = jQuery("#icon"+id_kat);
		if (icon != null) {
			if (target_state != "none") {
				icon.src = "/bilder/icon_minus.png";
				icon.alt = "-";
			} else {
				icon.src = "/bilder/icon_plus.png";
				icon.alt = "+";
			}
		}
	    var childs = getSubElementByNameIE(jQuery("#kat_selector"), "li", "child"+id_root);
	    for (var i = 0; i < childs.length; i++) {
	      var id_child = childs[i].id.substr(3);
	      // 
	      if ((id_child == id_kat) || (UpdateKatSelector_SetParentVisibility(id_kat, id_child, target_state))) {
	    	childs[i].style.display = target_state;
	    	return true;           
	      }
	    }
	    return false;
	}
  }

  function UpdateKatSelector_SetVisibility(id_kat, target_state) {
    if ((id_kat > 1) && (target_state != "none")) {
      jQuery("#row"+id_kat).show();
    }
	  
    var id_current = jQuery("#form_FK_KAT");
    var childs = getElementByNameIE("li", "child"+id_kat);
    for (var i = 0; i < childs.length; i++) {
      var id_child = childs[i].id.substr(3);
      if (childs[i].style.display != target_state) {
        childs[i].style.display = target_state;
        if ((childs[i].id == "row"+parseInt(id_current.value)) && (target_state == "none")) {
          var last_selection = jQuery("#row"+id_current.value);
          id_current.val(0);
        }
        if (target_state == "none") {
            UpdateKatSelector_SetVisibility(id_child, target_state);
        } 
      }
    } 
  }
  
// ========================================
//          ---- INPUT EVENTS ----
// ========================================

 function UpdateInput_Manufacturer(text, do_search) {
	jQuery('#MANUFACTURER').val(text);
	
	if (!do_search) {
		window.clearTimeout(input_timer);
		input_timer = window.setTimeout(function() { 
				UpdateInput_Manufacturer(text, true);
			}, 1000);
		if ((text.length < 2) || (search_blocked)) {
			if (!search_blocked) UpdateList_Manufacturer();
			return;
		}
		search_blocked = true;
		window.setTimeout(function() {
				search_blocked = false;
			}, 500);
	}
	
	if (step > 1) return;
    if (text.length >= 2) {
       Pager_Enable(14, ebiz_trader_baseurl + "index.php?page=marktplatz_neu_ajax&frame=ajax&suggest_man="+encodeURIComponent(text), UpdateList_Manufacturer);
	}
 }
 
 function UpdateInput_Product(text, do_search) {
	jQuery('#PRODUCT').val(text);
	if (!do_search) {
		window.clearTimeout(input_timer);
		input_timer = window.setTimeout(function() {
				UpdateInput_Product(text, true);
			}, 1000);
		if ((text.length < 2) || (search_blocked)) {
			if (!search_blocked) UpdateList_Product();
			return;
		}
		search_blocked = true;
		window.setTimeout(function() {
				search_blocked = false;
			}, 500);
	}
	
	if (step > 2) return;
	if (text.length >= 2) {
       var fk_man = jQuery("#form_FK_MAN").val();
       Pager_Enable(14, ebiz_trader_baseurl + "index.php?page=marktplatz_neu_ajax&frame=ajax&man="+fk_man+"&suggest_product="+encodeURIComponent(text), UpdateList_Product);
	}
 }

// ========================================
//          ---- LIST UPDATES ----
// ========================================

function UpdateList_Manufacturer(manufacturers) {
	jQuery('#liste_man').html("");
	var liste = document.createElement("ul");
	var man_entry;
	var man_entry_desc;
    var man_exact_name = jQuery('#MANUFACTURER').val();
		
	if (man_exact_name.length > 1) {
		var man_count = 0;
		var man_first = false;
		if (typeof(manufacturers) == "object") {
			if (manufacturers.length > 0) {        
   				for (var i = 0; i < manufacturers.length; i++) {
   					var man_id = manufacturers[i].ID_MAN;
   					var man_name = manufacturers[i].NAME;
   					var style = "";

					man_count = man_count + 1;
					man_entry = document.createElement("li");
					man_entry.innerHTML = "<a"+style+" href='javascript:UpdateSelection_Manufacturer(" + man_id + ",\""+man_name.replace('"', '\"')+"\");'" +
						" style='cursor: pointer; font-size: 13px;'>" + man_name + "</a>";
					liste.appendChild(man_entry);
					if (man_first == false) {
						man_first = man_entry;
					}
   				}
			}
		} else {
			man_entry = document.createElement("li");
			man_entry.innerHTML = "<h3>Please wait, searching the database...</h3>";
			liste.appendChild(man_entry);
		}
           
		man_entry = document.createElement("li");
		man_entry.innerHTML = "<a href='javascript:UpdateSelection_Manufacturer(-1, jQuery(\"#MANUFACTURER\").val());'"+
			" style='cursor: pointer; color: red; font-size: 13px;'>"+man_exact_name+"</a>";
		if (man_count > 0) {    
			man_entry_desc = document.createElement("li");
			man_entry_desc.innerHTML = "<div class='div'><h3>Select manufacturer from the database:</h3>"+
			    "Click on a manufacturer from the list, to select it.</div>";
			liste.insertBefore(man_entry_desc, man_first);

			if (pager.maxpage > 1) {
				man_entry_desc = document.createElement("li");
				man_entry_desc.innerHTML = "<div class='footer' align='center'>"+jQuery('#pager').html()+"</div>";
				liste.appendChild(man_entry_desc);
			}
		} else {
			man_entry_desc = document.createElement("li");
			man_entry_desc.innerHTML = "<div class='div'><h3>Take entry:</h3>"+
				"Click on the following manufacturer, which you entered, to select it.</div>";
			liste.appendChild(man_entry_desc);
            liste.appendChild(man_entry);
		}
	} else {
		man_entry = document.createElement("li");
		man_entry.innerHTML = "<h3>Please enter manufacturer!</h3>"+
             "<a title='Continue without choosing a manufacturer' href='javascript:UpdateSelection_Manufacturer(179,\"Unbekannter%20Hersteller\");'>"+
             "<img border='0' src="+ ebiz_trader_baseurl + "'/gfx/unbekannter_hersteller.png' />"+
             "</a>";
		liste.appendChild(man_entry);
	}
	jQuery('#liste_man').append(liste);
}

function UpdateList_Product(products){
   	jQuery('#liste_product').html("");
   	jQuery('#liste_product').show();
   	var liste = document.createElement("ul");
   	
   	if (jQuery('#PRODUCT').val().length >= 2) {
 			if (typeof(products) == "object") {
 				// Gefundene Produkte auflisten
                var product_entry_desc;
 				if (products.length > 0) {
 					product_entry_desc = document.createElement("li");
 			        product_entry_desc.innerHTML = "<div class='div'><h3>Use product from the database:</h3>"+
                       "Click on a product from the following list, to select it.</div>";
 			        liste.appendChild(product_entry_desc);
 				} else {
 				   	product_entry_desc = document.createElement("li");
 				       product_entry_desc.innerHTML = "<div class='div'><h3>Apply input:</h3>"+
 				           "Click on the following, from your entered, product to select it.</div>";
 				       liste.appendChild(product_entry_desc);
 	 			        
 			 	  	var product_entry = document.createElement("li");
 			 	  	product_entry.innerHTML = "<a href='javascript:UpdateSelection_Product(-1, jQuery(\"#PRODUCT\").val());' style='cursor: pointer; color: red; font-size: 13px;'>"
 			 				+ jQuery('#PRODUCT').val() + "</a>";
 			 	  	liste.appendChild(product_entry);
 				}
 				for (var i = 0; i < products.length; i++) {
 					var product_id = products[i].ID_PRODUCT;
 					var product_name = products[i].NAME;
 					var product_desc = products[i].DESC_SHORT;
 		            product_entry = document.createElement("li");
 					product_entry.innerHTML = "<a href='javascript:UpdateSelection_Product("+product_id+",\""+product_name.replace('"', '\"')+"\")' style='cursor: pointer; font-size: 13px;'>"+
 																		"<span style='width: 50%; font-size: 13px; float: right;'> ("+product_desc+")</span>"+product_name+"</a>";
 					liste.appendChild(product_entry);
 					EnableTooltip(product_entry, "#tooltip_product", ebiz_trader_baseurl + "index.php?page=marktplatz_tooltip_product&frame=ajax&id="+product_id);
 					//tooltips.push(new TooltipAjax(product_entry, "tooltip_product", ebiz_trader_baseurl + "index.php?page=marktplatz_tooltip_product&frame=ajax&id="+product_id));
 				}
 				if (pager.maxpage > 1) {
					product_entry_desc = document.createElement("li");
					product_entry_desc.innerHTML = "<div class='footer' align='center'>"+jQuery('#pager').html()+"</div>";
					liste.appendChild(product_entry_desc);
 				}
 			} else {
 				// Wird noch gesucht
 				product_entry = document.createElement("li");
 				product_entry.innerHTML = "<h3>Please wait, searching through database...</h3>";
 				liste.appendChild(product_entry);
 			}
       } else {
 			product_entry = document.createElement("li");
 			product_entry.innerHTML = "<h3>Enter search word / product name!</h3>";
 			liste.appendChild(product_entry);
	}
	jQuery('#liste_product').append(liste);
}

// ========================================
//      ----     BACKSTEPS     ----
// ========================================

function Backstep_PacketWidthProduct() {
	if (step > 0) {
		Backstep_Manufacturer();
	}
	
	step = 0;
	jQuery("#form_FK_PACKET_ORDER").val('');
	
	jQuery("#cell_packet > input:first").show();
	jQuery("#PACKET_SET").hide();
	jQuery("#PACKET_SET_TEXT").html("");
	
	jQuery('#liste_packet').show();
    jQuery('#desc_packet').show();
	jQuery('#table_man').hide();
	jQuery('#liste_man').hide();
}

function Backstep_Manufacturer() {
	if (step > 1) {
		Backstep_Product();
	}
	
	step = 1;
	jQuery("#form_FK_MAN").val('');
	
	jQuery("#cell_man > input:first").show();
	jQuery("#MANUFACTURER_SET").hide();
	jQuery("#MANUFACTURER_SET_TEXT").html("");

	jQuery('#liste_man').show();
    jQuery('#desc_man').show();
	jQuery('#table_product').hide();
	jQuery('#liste_product').hide();
	jQuery('#table_cat').hide();
   
    var text = jQuery('#MANUFACTURER').val();
 	if (text.length >= 2) {
		Pager_Enable(14, ebiz_trader_baseurl + "index.php?page=marktplatz_neu_ajax&frame=ajax&suggest_man="+encodeURIComponent(text), UpdateList_Manufacturer);
	}
}

function Backstep_Product() {
	step = 2;
	jQuery("#form_FK_PRODUCT").val('');
	
	jQuery("#cell_pro > input:first").show();
	jQuery("#PRODUCT_SET").hide();
	jQuery("#PRODUCT_SET_TEXT").html("");
	
	jQuery('#liste_product').show();
	jQuery('#desc_product').show();
	jQuery('#table_cat').hide();
   
	var text = jQuery('#PRODUCT').val();
	if (text.length >= 3) {
		var fk_man = jQuery("#form_FK_MAN").val();
		Pager_Enable(14, ebiz_trader_baseurl + "index.php?page=marktplatz_neu_ajax&frame=ajax&man="+fk_man+"&suggest_product="+encodeURIComponent(text), UpdateList_Product);
	}
}

// ========================================
//      ---- SELECTION UPDATES ----
// ========================================

function UpdateSelection_PacketWidthProduct(packet_id, packet_text) {
	step = 1;
	
	jQuery("#form_FK_PACKET_ORDER").val(packet_id);
	
	jQuery("#cell_packet > input:first").show();
	jQuery("#PACKET_SET").show();
	jQuery("#PACKET_SET_TEXT").html(packet_text);
	
	jQuery('#liste_packet').hide();
    jQuery('#desc_packet').hide();
	jQuery('#table_man').show();
	jQuery('#desc_man').show();
	jQuery('#liste_man').show();
	Pager_Disable();
	UpdateInput_Manufacturer(jQuery('#MANUFACTURER').val(), true);
}

function UpdateSelection_Manufacturer(man_id, man_name) {
	step = 2;
	
	jQuery("#form_FK_MAN").val(man_id);
	jQuery("#form_MANUFACTURER").val(man_name);
	
	//if (man_id == -1) man_name = "<img align='left' border='0' src='/gfx/eigene_eingabe.png'>" + man_name;
	
	jQuery("#cell_man > input:first").hide();
	jQuery("#MANUFACTURER_SET").show();
	jQuery("#MANUFACTURER_SET_TEXT").html(man_name);
	
	jQuery('#liste_man').hide();
	jQuery('#desc_man').hide();
	jQuery('#table_product').show();
	jQuery('#liste_product').show();
	jQuery('#desc_product').show();
	Pager_Disable();
	UpdateInput_Product(jQuery('#PRODUCT').val(), true);
}

function UpdateSelection_Product(product_id, product_name) {
	step = 3;
	
	//if (product_id == -1) product_name = "<img align='left' border='0' src='/gfx/eigene_eingabe.png'>" + product_name;
	
	jQuery("#form_FK_PRODUCT").val(product_id);
	jQuery("#cell_pro > input:first").hide();
	jQuery("#PRODUCT_SET").show();
	jQuery("#PRODUCT_SET_TEXT").html(product_name);
	
	jQuery('#liste_product').hide();
	jQuery('#desc_product').hide();
	jQuery('#table_cat').show();
	Pager_Disable();
}

// ========================================
//         ---- PAGER FUNCTIONS ----
// ========================================

function Pager_Enable(perpage, request_url, callback_func) {
	pager.elements = new Array();
	pager.perpage = perpage;
	pager.maxpage = 0;
	pager.maxcount = 0;
	pager.callback = callback_func;
	pager.request_url = request_url;

	Pager_Redraw('pager');
	Pager_Update(1);
}

function Pager_Disable() {
	jQuery('#pager').html("");
}

function Pager_CreateLink(i, text, style_float, style_weight) {
     var page = document.createElement("a");
     page.innerHTML = (text != null ? text : "["+i+"]");
     page.href = "javascript: Pager_Update("+i+");";
     page.style.cursor = "pointer";
     page.style.padding = "4px";
     if (style_float != null)
       page.style.cssFloat = style_float;
     if (style_weight != null)
       page.style.fontWeight = style_weight;
     if (i == pager.curpage) {
       page.style.fontWeight = "bold";
       page.style.color = "black";
     }
     return page;
}

function Pager_Redraw(div_id) {
	var div = jQuery("#"+div_id); 
	div.html("")
	if (pager.maxpage > 1) {
		var page, spacing;
		var description = document.createElement("h3");
		description.innerHTML = ": "+pager.maxcount+" results found (Page "+pager.curpage+" from "+pager.maxpage+")";
		description.style.textAlign = "center";
		div.append(description);
           
		if (pager.curpage > 1) {
             page = Pager_CreateLink(1, '<<', 'left', 'bold');
             spacing = document.createTextNode(" ");
             div.append(page);
             div.append(spacing);
             
             var page = Pager_CreateLink(pager.curpage-1, '<', 'left', 'bold');
             var spacing = document.createTextNode(" ");
             div.append(page);
             div.append(spacing);
		}
           
		var first = ((pager.curpage - 3) >= 1 ? (pager.curpage - 3) : 1);
        var last = ((pager.curpage + 3) <= pager.maxpage ? (pager.curpage + 3) : pager.maxpage);
   		for (var i = first; i <= last; i++) {
             page = Pager_CreateLink(i);
             spacing = document.createTextNode(" ");
             div.append(page);
             div.append(spacing);
   		}

        if (pager.curpage < pager.maxpage) {        
			page = Pager_CreateLink(pager.maxpage, '>>', 'right', 'bold');
			spacing = document.createTextNode(" ");
			div.append(page);
			div.append(spacing);
			
			page = Pager_CreateLink(pager.curpage+1, '>', 'right', 'bold');
			spacing = document.createTextNode(" ");
			div.append(page);
			div.append(spacing);
        }
	}
	var ending = document.createElement("br");
	ending.style.clear = "both";
	div.append(ending);
}

function Pager_Update(new_page) {
	pager.curpage = new_page;
	jQuery.ajax({
		url: pager.request_url+"&curpage="+new_page+"&perpage="+pager.perpage,
		dataType: 'json',
		success: function(obj) {
			pager.maxpage = obj.pages;
	        pager.maxcount = obj.results;
	        Pager_Redraw('pager');
	        pager.callback(obj.elements);
		}
	});
}
//****************************************
// Javascript ohne Produktdatenbank
//****************************************

// ========================================
//      ----     BACKSTEPS     ----
// ========================================

function Backstep_PacketNoProduct() {
	step = 0;
	if (step > 0) {
		Backstep_Manufacturer();
	}
	if (step > 1) {
		Backstep_Product();
	}
	jQuery("#form_FK_PACKET_ORDER").val('');
	
	jQuery("#cell_packet > input:first").show();
	jQuery("#PACKET_SET").hide();
	jQuery("#PACKET_SET_TEXT").html("");
	
	jQuery('#liste_packet').show();
    jQuery('#desc_packet').show();
	jQuery('#table_cat').hide();
}

//========================================
//   ---- SELECTION UPDATES ----
//========================================

function UpdateSelection_PacketNoProduct(packet_id, packet_text) {
	step = 1;
	
	jQuery("#form_FK_PACKET_ORDER").val(packet_id);
	
	jQuery("#cell_packet > input:first").hide();
	jQuery("#PACKET_SET").show();
	jQuery("#PACKET_SET_TEXT").html(packet_text);
	
	jQuery('#liste_packet').hide();
	jQuery('#desc_packet').hide();
	jQuery('#table_cat').show();
}

//========================================
//---- CATEGORY LOADER ----
//========================================
	
function GetCategorys(b_paid) {
	kat_is_paid = b_paid;
	jQuery.ajax({
		url: ebiz_trader_baseurl + 'index.php?page=marktplatz_neu_kat&mode=ajax&do=kats&paid='+b_paid,
		dataType: 'json',
		success: function(obj) {
			var back = '<li id="kat_back_to_root" style="display: none;">'+
				jQuery("#kat_back_to_root").html()+
				'</li>';
			jQuery("#kat_list").html(back+"\n"+obj.tree);
			GetCategorysDone();
		}
	});
}

function GetCategorysDone() {
	
}

//========================================
//---- INPUT LOADER ----
//========================================
	
function GetInput() {
	jQuery.ajax({
		url: 		ebiz_trader_baseurl + 'index.php?page=marktplatz_neu_kat&frame=ajax',
		type: 		'POST',
		data:		jQuery('#form_step1').serialize(),
		success: 	function(inputs) {
		    if (tinyMCE.getInstanceById("BESCHREIBUNG") != null)
		    	// Remove previous editor
		        tinyMCE.execCommand('mceRemoveControl', false, "BESCHREIBUNG");
		    jQuery('#frame_step2').html(inputs);
		    jQuery('#kat_warning').show();
		    jQuery('#backstep_packet').hide();
		    jQuery('#backstep_man').hide();
		    jQuery('#backstep_product').hide();
		    InitializeDescription(1);
		}
	});
}

//========================================
//---- UPLOAD LOADER ----
//========================================
	
function GetUploads(id_ad, id_kat) {
	jQuery.ajax({
		url: 		ebiz_trader_baseurl + 'marktplatz_neu_finish,'+id_ad+','+id_kat+',,,ajax.htm',
		type: 		'GET',
		success: 	function(uploads) {
		    jQuery('#frame_step3').html(uploads);
		    InitializeUploads(id_ad);
		}
	});
}

//========================================
//---- Description ----
//========================================

function checkFieldValue(self, needed){
	self = jQuery(self);
    if (validateFieldValue(self, needed)) {
        self.css("border", "1px solid black");
        return true;
    } else {
        self.css("border", "1px solid red");
        return false;
    }
}

function checkNeededFields(){
    var valid = true;
    var tmp_needed = document.getElementById('tmp_needed');
    var tmp_optional = document.getElementById('tmp_optional');
    if (!tmp_needed || !tmp_optional) 
        return;
    var fields = tmp_needed.value.split(',');
    var fields_opt = tmp_optional.value.split(',');
    fields.push("lu_laufzeit");
    if (input_cache.initialized == false) {
        input_cache.initialized = true;
    	// Required
        for (var field in fields) {
        	var field_id = fields[field];
            var input_field = jQuery("#"+field_id);
            if ((input_field.length > 0) && (typeof input_field == "object")) {
            	input_cache[field_id] = { value: input_field.val(), valid: false };
                validateInput(input_field);
            }
        }
        // Optional
        for (var field in fields_opt) {
        	var field_id = fields_opt[field];
        	if (field_id.length > 0) {
                var input_field = jQuery("#"+field_id);
                if ((input_field.length > 0) && (typeof input_field == "object")) {
                	input_cache[field_id] = { value: input_field.val(), valid: false };
                    validateInput(input_field);
                }
        	}
        }
        // Update errors
        window.setTimeout(function() {
        	checkNeededFields();
        }, 2000);
        return;
    }
    for (var field in fields) {
    	var field_id = fields[field].toUpperCase();
        var input_field = jQuery("#"+fields[field]);
        if ((input_field.length > 0) && (typeof input_field == "object")) {
        	if (typeof input_cache[field_id] != "object") {
                validateInput(input_field);
        	} else if (input_cache[field_id].value != input_field.val()) {
                validateInput(input_field);
        	}
    	}
    }
    for (var field in fields_opt) {
    	var field_id = fields_opt[field].toUpperCase();
    	if (field_id.length > 0) {
            var input_field = jQuery("#"+fields_opt[field]);
            if ((input_field.length > 0) && (typeof input_field == "object")) {
        		if (typeof input_cache[field_id] != "object") {
                    validateInput(input_field);
            	} else if (input_cache[field_id].value != input_field.val()) {
                    validateInput(input_field);
            	}
            }
    	}
    }
}

function updateFormValid() {
	var tmp_needed = document.getElementById('tmp_needed');
	var tmp_optional = document.getElementById('tmp_optional');
	if (!tmp_needed || !tmp_optional)
		return;
	var fields = tmp_needed.value.split(',');
	var fields_opt = tmp_optional.value.split(',');  
	var valid = true;
    fields.push("lu_laufzeit");
    for (var field in fields) {
    	var field_id = fields[field].toUpperCase();
        var input_field = jQuery("#"+fields[field]);
        if ((input_field.length > 0) && (typeof input_field == "object")) {

        	if ((typeof input_cache[field_id] == "object") && (input_cache[field_id].valid == false)) {
        		valid = false;
        	} 
    	}
    }
    for (var field in fields_opt) {
    	var field_id = fields_opt[field].toUpperCase();
    	if (field_id.length > 0) {
            var input_field = jQuery("#"+fields_opt[field]);
            if ((input_field.length > 0) && (typeof input_field == "object")) {
            	if ((typeof input_cache[field_id] == "object") && (input_cache[field_id].valid == false)) {
            		valid = false;
            	}
            }
    	}
    }
    var submit_button = jQuery("#button_done");
    var error_missing = jQuery("#error_missing");
    if (valid) {
    	submit_button.show();
    	error_missing.hide();
    } else {
    	submit_button.hide();
    	error_missing.show();
    }
}

function validateFieldValue(self, needed){
	var field_id = self.attr("id");
	if (typeof input_cache[field_id] != "object") {
        return false;
	} else if (!input_cache[field_id].valid) {
		return false;
	} else {
		return true;
	}
}

function validateInputLive(input){
    if (timer_check != null) {
        window.clearTimeout(timer_check);
        timer_check = null;
    }
    timer_check = window.setTimeout("checkNeededFields(); validateInput(jQuery('#" + input.id + "'));", 1000);
}

function validateInput(input){
	input = jQuery(input);
	if (input.length == 0)
		return;
    if (input[0].id == "BESCHREIBUNG") {
        // TinyMCE
        if (tinyMCE.activeEditor) 
            tinyMCE.activeEditor.save();
    }
	
    var name = input[0].name;
    var value = input.val();
    var needed = 0;
    
    if (!name) 
        return;
    
    var tmp_needed = document.getElementById('tmp_needed');
    var fields = tmp_needed.value.split(',');
    fields.push("LU_LAUFZEIT");
    for (var field in fields) {
        if (fields[field] == name) {
            needed = 1;
        }
    }
    var type = '';
    var field_type = $('input[name="tmp_type\\['+name+'\\]"]');
    if (field_type.length > 0) {
    	type = field_type.val();
        //alert(type);
    }

	jQuery.ajax({
		url: 		ebiz_trader_baseurl + "index.php?page=marktplatz_neu_desc_ajax&frame=ajax",
		type: 		'POST',
		dataType:	'json',
		data: 		{
			type:	'validate',
			needed: 	needed,
			name:		name,
			valtype:	type,
			value:		value
		},
		success: 	function(obj) {
	        if (!obj.valid) {
	        	if (jQuery('#' + name + '_ERROR').length > 0) {
	        		jQuery('#' + name).css("border", "1px solid red");
	        		jQuery('#' + name + '_ERROR')[0].title = obj.error_msg;
	        		jQuery('#' + name + '_ERROR_IMG')[0].src = ebiz_trader_baseurl + "bilder/stop_check.png";
	        	}
				input_cache[name.toUpperCase()] = { value: value, valid: false };
	            //jQuery("#submit_button")[0].disabled = true;
	        } else {
	        	if (jQuery('#' + name + '_ERROR').length > 0) {
	        		jQuery('#' + name).css("border", "1px solid black");
		            jQuery('#' + name + '_ERROR')[0].title = "Input correct or optional.";
		            jQuery('#' + name + '_ERROR_IMG')[0].src = ebiz_trader_baseurl + "bilder/ok.png";
	        	}
				input_cache[name.toUpperCase()] = { value: value, valid: true };
	        }
	        updateFormValid();
		}
	});
}

function showMap(self, container){
    if (container.css("display") == 'none') {
        jQuery(self).html('Hide map');
        container.show();
        if (!map_loaded) {
        	map_loaded = true;
            container.css("width", "97%");
            container.css("height", "400px");

            var latlng = new google.maps.LatLng(jQuery("#LATITUDE").val(), jQuery("#LONGITUDE").val());

            var myOptions = {
                zoom:13,
                zoomMax:15,
                center:latlng,
                mapTypeId:google.maps.MapTypeId.ROADMAP
            };
            map = new google.maps.Map(document.getElementById(container.attr("id")), myOptions);


            marker = new google.maps.Marker({
                position:latlng,
                map:map
            });

            google.maps.event.addListener(map, 'click', function(event) {
                latlng = event.latLng;

                if (latlng != null) {
                    address = latlng;
                    geocoder.geocode( { 'latLng': latlng }, showPosition);
                }
            });

            geocoder = new google.maps.Geocoder();

        }
    }
    else {
    	jQuery(self).html('Show map');
        container.hide();
    }
}

function getPosition(overlay, latlng){

    if (latlng != null) {
        address = latlng;
        geocoder.geocode( { 'latLng': latlng }, showPosition);
    }
}

function showPosition(response, lat, lon){
    marker.setMap(null);

    if (response) {

        place = response[0];

        point = new google.maps.LatLng(place.geometry.location.lat(), place.geometry.location.lng());

        marker = new google.maps.Marker({
            position: point,
            map:map
        });
        setPosition(place);
    }
}

function showPositionMarker(lat, lon) {
	if (typeof map == "undefined") return;

    marker.setMap(null);

    point = new google.maps.LatLng(lat, lon);

    marker = new google.maps.Marker({
        position: point,
        map:map
    });
}

function setPosition(place){
    //var country = place.AddressDetails.Country.CountryName;
    var country = "";
    var city = "";
    var zip = "";

    jQuery.each(place.address_components, function(key, value) {
        if(value.types['0'] == 'locality') {
            city = value.long_name;
        }
        if(value.types['0'] == 'postal_code') {
            zip = value.long_name;
        }
        if(value.types['0'] == 'country') {
            country = value.long_name;
        }
    });

    /*if (place.address_component.Country.Thoroughfare) {
        city = place.AddressDetails.Country.Thoroughfare.ThoroughfareName;	
    }
    if (place.AddressDetails.Country.Locality) {
    	city = place.AddressDetails.Country.Locality.LocalityName;
    }
    if (place.AddressDetails.Country.SubAdministrativeArea) {
    	if (place.AddressDetails.Country.SubAdministrativeArea.SubAdministrativeAreaName) {
    		city = place.AddressDetails.Country.SubAdministrativeArea.SubAdministrativeAreaName
    	}
    	if (place.AddressDetails.Country.SubAdministrativeArea.Locality) {
    		city = place.AddressDetails.Country.SubAdministrativeArea.Locality.LocalityName;
    	}
    }
    if (place.AddressDetails.Country.AdministrativeArea) {
        // ZIP Code
        if (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea) {
            if (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality) {
                city = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.LocalityName;
                if (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.PostalCode) {
                    zip = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.PostalCode.PostalCodeNumber;
                } else if ((place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.DependentLocality) &&
                    (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.DependentLocality.PostalCode)) {
                        zip = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.DependentLocality.PostalCode.PostalCodeNumber;
                    }
            } else {
            	city = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.SubAdministrativeAreaName;
            }
            if (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality) {
                city = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.LocalityName;
                if (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.PostalCode) {
                    zip = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.PostalCode.PostalCodeNumber;
                } else if ((place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.DependentLocality) &&
                    (place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.DependentLocality.PostalCode)) {
                        zip = place.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.DependentLocality.DependentLocality.PostalCode.PostalCodeNumber;
                    }
            }
        }
        else {
            if (place.AddressDetails.Country.AdministrativeArea.Locality) {
            	city = place.AddressDetails.Country.AdministrativeArea.Locality.LocalityName;
                if (place.AddressDetails.Country.AdministrativeArea.Locality.PostalCode) {
                    zip = place.AddressDetails.Country.AdministrativeArea.Locality.PostalCode.PostalCodeNumber;
                } else if ((place.AddressDetails.Country.AdministrativeArea.Locality.DependentLocality) &&
                		(place.AddressDetails.Country.AdministrativeArea.Locality.DependentLocality.PostalCode)) {
                    zip = place.AddressDetails.Country.AdministrativeArea.Locality.DependentLocality.PostalCode.PostalCodeNumber;
                }
            } else {
            	city = place.AddressDetails.Country.AdministrativeArea.AdministrativeAreaName;
            }
        }
    }*/

    var country_dropdown = jQuery('#fk_country');
    var country_options = country_dropdown[0].getElementsByTagName("option");

    country_dropdown.find("option").each(function(key, value) {
        if(jQuery(value).text() == country) {
            jQuery(value).attr("selected", "selected");
        } else {
            jQuery(value).attr("selected", false);
        }
    })
    /*
    for (var fk_country = 0; fk_country < country_options.length; fk_country++) {
        if (country_options[fk_country].text == country) {
            country_dropdown.selectedIndex = fk_country;
        }
    }*/
    
    jQuery('#LONGI').html(place.geometry.location.lng());
    jQuery('#LATI').html(place.geometry.location.lat());
    
    jQuery('#LONGITUDE').val(place.geometry.location.lng());
    jQuery('#LATITUDE').val(place.geometry.location.lat());
    jQuery('#ZIP').val(zip);
    jQuery('#CITY').val(city);
    
    checkNeededFields();
    validateInput(jQuery('#ZIP'));
    validateInput(jQuery('#CITY'));
}




//========================================
//---- Upload images, videos & files ----
//========================================

function upload_start(){
    jQuery("#loading").show();
    jQuery("#upload").submit();
}

function video_add(form) {
	jQuery.ajax({
		url:		ebiz_trader_baseurl + 'index.php',
		type: 		'POST',
		data:		jQuery('#video').serialize(),
		success:	function(videos) {
	    	jQuery('#videosDiv').html(videos);
		}
	});
}

function video_del(id_ad, id_video) {
	jQuery.ajax({
		url:		ebiz_trader_baseurl + 'marktplatz/marktplatz_neu_finish,'+id_ad+',,delete_vid,'+id_video+'.htm',
		type: 		'GET',
		success:	function(videos) {
	    	jQuery('#videosDiv').html(videos);
		}
	});
}

function upload_done(id_ad){
    jQuery("#loading").hide();
    
    //if (if_upload.document == null) return;

	jQuery.ajax({
		url: 		ebiz_trader_baseurl + 'marktplatz_neu_finish,'+id_ad+',,show.htm',
		type: 		'GET',
		success: 	function(uploads) {
		    jQuery('#imagesDiv').html(uploads);
		}
	});
    /*
    var content = if_upload.document.body.innerHTML;
    if (content) 
        jQuery("#imagesDiv").html(content);
    */
}
function get_ups(id_ad)
{
	jQuery.ajax({
		url: 		ebiz_trader_baseurl + 'index.php?page=ad_uploads&frame=ajax&FK_AD='+id_ad,
		type: 		'GET',
		success: 	function(uploads) {
		    jQuery('#UP_LIST').html(uploads);
		}
	});
	//alert('call: {ID_ANZEIGE}');
}
function data_upload_start(){
    jQuery("#loading").show();
    jQuery("#DATA_UP").submit();
}
function upload_done2(id_ad){
    jQuery("#loading").hide();
    
    //if (if_upload2.document == null) return;

	jQuery.ajax({
		url: 		ebiz_trader_baseurl + 'index.php?page=ad_uploads&frame=ajax&FK_AD='+id_ad,
		type: 		'GET',
		success: 	function(uploads) {
		    jQuery('#UP_LIST').html(uploads);
		}
	});
    /*
    var content = if_upload2.document.body.innerHTML;
    if (content) 
        jQuery("#UP_LIST").html(content);
    */
}
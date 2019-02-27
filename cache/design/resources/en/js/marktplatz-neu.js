
/* ###VERSIONSBLOCKINLCUDE### */

typeahead_timer = false;

/*
 * Modal callbacks for focus
 */
jQuery(function() {
	jQuery("#modalAvailabilityEventCreate").on('shown', function() {
		jQuery("#modalAvailabilityEventCreate form input[name='title']").focus();
	});
})

/*
 * Übersicht
 */

function ShowOverview() {
	jQuery("#overview_info").hide();
	jQuery("#overview_alert").show();
}

function HideOverview() {
	jQuery("#overview_alert").hide();
	jQuery("#overview_info").show();
}

modal_loading_active = false;

function LoadingStart(options) {
	if (!modal_loading_active) {
		jQuery('#modal_loading').unbind('hidden');
		jQuery('#modal_loading').unbind('shown');

		modal_loading_active = true;
		if (!jQuery("#modal_loading").is(":visible")) {
			jQuery('#modal_loading').on('hidden', function () {
				LoadingStop();
			});
			if((typeof options != "undefined") && (typeof options.shown != "undefined")) {
				jQuery('#modal_loading').on('shown', options.shown);
			}
			jQuery("#modal_loading").modal("show");
		}
	}
}

function LoadingStop() {
	if (modal_loading_active) {
		modal_loading_active = false;

		jQuery("#modal_loading").modal("hide");
		// Workaround for some bug leaving the modal backdrop
		jQuery('body').removeClass('modal-open');
		jQuery('.modal-backdrop').remove();
	}
}

/*
 * Backsteps
 */

step_current = 1;

function EditPacket(force) {
	if ((typeof force == "undefined") && (step_current > steps.category)) {
		// Warnung anzeigen
		jQuery('#modal_warning_kat').modal('show');
		$('#modal_warning_kat .btn-danger').unbind('click').bind('click', function(event) {
			// Wenn auf "Trotzdem fortsetzen" geklickt wird
			jQuery('#modal_warning_kat').modal('hide');
			EditPacket(true);
		});
		return;
	}
	jQuery("#form_ad > .step-input").hide();
	jQuery("#content > .step-input").hide();
	HideOverview();
	step_current = steps.packet;
	jQuery("#overview_packet").hide();
	jQuery("#overview_category").hide();
	jQuery("#overview .overview-details").hide();
	// Status updaten
	jQuery(".step-input").removeClass("active").removeClass("done").hide();
	jQuery("#packet").addClass("active").show();
	FocusInput();
}

function EditCategory(force) {
	if ((typeof force == "undefined") && (step_current > steps.category)) {
		// Warnung anzeigen
		jQuery('#modal_warning_kat').modal('show');
		$('#modal_warning_kat .btn-danger').unbind('click').bind('click', function(event) {
			// Wenn auf "Trotzdem fortsetzen" geklickt wird
			jQuery('#modal_warning_kat').modal('hide');
			EditCategory(true);
		});
		return;
	}
	jQuery("#form_ad > .step-input").hide();
	jQuery("#content > .step-input").hide();
	HideOverview();
	step_current = steps.category;
	jQuery("#overview_category").hide();
	jQuery("#overview .overview-details").hide();
	// Status updaten
	jQuery(".step-input").removeClass("active").removeClass("done").hide();
	jQuery("#packet").addClass("done").hide();
	jQuery("#category").addClass("active").show();
	FocusInput();
}

function EditDescription() {
	if (step_current >= steps.description) {
		jQuery("#form_ad > .step-input").hide();
		jQuery("#content > .step-input").hide();
		HideOverview();
		jQuery("#description").show();
		FocusInput();
	}
}

function EditVariants() {
	jQuery(".step-input").removeClass("active").hide();
	HideOverview();
	VariantsRefresh();
	jQuery("#variants").addClass("active").show();
}

function EditAvailability() {
	jQuery(".step-input").removeClass("active").hide();
	HideOverview();
	jQuery("#availability").addClass("active").show();
	AvailabilityRefresh();
}

function validateVariantTable(table) {
	LoadingStart({
		'shown': function() {
			var ids = table.jqGrid('getDataIDs'), i, l = ids.length;
			var rowdata = [];

			for (i = 0; i < l; i++) {
				//rowdata.push(table.jqGrid('getRowData', ids[i]));
				var tmpRow = { 'id': ids[i] };
				jQuery("#"+ids[i]).find("input").each(function(key, value) {
					if(jQuery(value).is("[type='text']")) {
						tmpRow[jQuery(value).attr('name')] = jQuery(value).val();
					} else if(jQuery(value).is("[type='checkbox']")) {
						tmpRow[jQuery(value).attr('name')] = jQuery(value).is(':checked')?1:0;
					}
				});
				rowdata.push(tmpRow);
			}

			jQuery.post(
				ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=savevariantstable&ID_AD='+jQuery("#form_ID_AD").val(),
				{ 'data': rowdata },
				function(obj) {
					// Eingabefelder ausblenden
					jQuery("#description").addClass("done").hide();
					step_current = steps.media;
					ShowActive();
				}
			)
		}
	});


}

function EditMedia() {
	if (step_current >= steps.description) {
		jQuery("#form_ad > .step-input").hide();
		jQuery("#content > .step-input").hide();
		HideOverview();
		UploadRefresh();
		jQuery("#uploads").show();
		FocusInput();
	}
}

function ShowActive() {
	LoadingStop();
	// Aktiven Schritt ausblenden
	jQuery(".step-input").removeClass("active").hide();
	// Aktuellen Schritt aktivieren
	if (step_current == steps.packet) {
		jQuery("#packet").addClass("active");
		FocusInput();
	}
	if (step_current == steps.category) {
		jQuery("#category").addClass("active");
		FocusInput();
	}
	if (step_current == steps.description) {
		jQuery("#description").addClass("active");	
	}
	if (step_current == steps.variants) {
		VariantsRefresh();
		jQuery("#variants").addClass("active");
	}
	if (step_current == steps.media) {
		UploadRefresh();
		jQuery("#uploads").addClass("active");	
		FocusInput();
	}
	if (step_current == steps.confirm) {
		ShowOverview();
		LoadPreview();
		jQuery("#modal_confirm").modal({ backdrop: 'static' }).modal("show");
	}
	// Fortschrittsbalken updaten
	jQuery("#progress .progress-description").removeClass("active");
	for (var step = 1; step <= steps.count; step++) {
		jQuery("#progess-desc-"+step)
			.removeClass("done")
			.removeClass("active")
			.removeClass("pending");
		jQuery("#progess-step-"+step)
			.removeClass("bar-info")
			.removeClass("bar-warning")
			.removeClass("bar-danger");
		if (step < step_current) {
			jQuery("#progess-desc-"+step).addClass("done");
			jQuery("#progess-step-"+step).addClass("bar-success");
		} else if (step == step_current) {
			jQuery("#progess-desc-"+step).addClass("active");
			jQuery("#progess-step-"+step).addClass("bar-warning");
		} else {
			jQuery("#progess-desc-"+step).addClass("pending");
			jQuery("#progess-step-"+step).addClass("bar-danger");
		}
	}
	// Abgeschlossene Schritte ausblenden
	jQuery("#form_ad > .done").hide();
	jQuery("#content > .done").hide();
	// Aktuellen Schritt einblenden
	jQuery("#form_ad > .active").show();
	jQuery("#content > .active").show();
}

function FocusInput() {
	/*window.setTimeout(function() {
		window.location.hash = "#overview_top";
	}, 100);*/
    jQuery('html,body').animate({
    	scrollTop: (jQuery("[name='overview_top']").offset().top - 100)
    }, 600 , function() { });
}

/*
 * Selections
 */

function SetPacket(packet_id, packet_text) {
	// Einstellungen übernehmen
	jQuery("#form_FK_PACKET_ORDER").val(packet_id);
	jQuery("#packet_text").html(packet_text);
	// Fortschritt / Aktuelle Einstellungen anzeigen
	jQuery("#overview").show();
	jQuery("#overview_packet").show();
	// Paketauswahl verstecken
	jQuery("#packet").addClass("done").hide();
	// Kategorieauswahl anzeigen
	jQuery("#category").show();
	EditCategory();
	if (step_current == steps.packet) step_current++;	
	ShowActive();
}

function SetCategory(category_id, category_text, id_ad) {
	// Einstellungen übernehmen
	jQuery("#DESC_ID_KAT").val(category_id);
	jQuery("#form_FK_KAT").val(category_id);
	jQuery("#category_text").html(category_text);
	// Fortschritt / Aktuelle Einstellungen anzeigen
	jQuery("#overview").show();
	jQuery("#overview_category").show();
	// Kategorieauswahl verstecken
	jQuery("#category").addClass("done").hide();
	// Eingabefelder anzeigen
	EditDescription();
	step_current = steps.description;
	jQuery("input[name=FK_KAT]").val(category_id);
	getInputFields(category_id, id_ad);
	ShowActive();
}

function SetDetails() {
	LoadingStart();
	// Eingaben in der Datenbank speichern
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?mode=ajax&do=save',
		jQuery("#form_ad").serialize(),
		function(obj) {
			if (typeof obj == "object") {
				if (obj.success) {
					jQuery("input[name=ID_AD]").val(obj.id_ad);
					jQuery("#form_ID_AD").val(obj.id_ad);

					// Eingaben übernehmen
					jQuery("#description input").each(SetDetailsField);
					jQuery("#description textarea").each(SetDetailsField);
					jQuery("#description select").each(SetDetailsField);

					var isAvailabilityAd = (jQuery("#form_ad input[type=hidden][name^='availability']").length > 0);
					if(!isAvailabilityAd) {
						var isVariantAd = (jQuery("#form_ad input[name^='tmp_type'][value='variant']").length > 0);
						if(!isVariantAd) {
							// Eingabefelder ausblenden
							jQuery("#description").addClass("done").hide();
							step_current = steps.media;
							ShowActive();
						} else {
							step_current = steps.variants;
							EditVariants();
						}
					} else {
						step_current = steps.availability;
						EditAvailability();
					}
				} else {
					alert(obj.errors);
				}
			}	
		}
	);
}

function SetDetailsField(index, input) {
	if ((input.nodeName == "INPUT") || (input.nodeName == "TEXTAREA")) {
		var name = input.name;
		// Before IE8 fix: name.substr(-2)
		if (name.substr( name.length-2 ) == "[]") {
			name = input.id;
		}
		var type = input.type;
		if ((type != "hidden") && name.match(/^[^\[\]]+$/)) {
			var overview = jQuery("#overview_details_"+name);
			if (overview.length > 0) {
				if (type == "checkbox") {
					overview.children(".overview-value").html((input.checked ? "Ja" : "Nein"));
				} else {
					if (name == "BESCHREIBUNG") {
						overview.children(".overview-value").html("[...]");
					} else {
						overview.children(".overview-value").html(input.value);	
					}	
				}
				overview.show();
			}
		}
	}
	if (input.nodeName == "SELECT") {
		var name = input.name;
		var selection = jQuery(input).children("option:selected");
		var overview = jQuery("#overview_details_"+name);
		if (overview.length > 0) {
			overview.children(".overview-value").html(selection.html());
			overview.show();
		}
	}
}

function SetAvailability() {
	var isVariantAd = (jQuery("#form_ad input[name^='tmp_type'][value='variant']").length > 0);
	if(!isVariantAd) {
		// Eingabefelder ausblenden
		jQuery("#description").addClass("done").hide();
		step_current = steps.media;
		ShowActive();
	} else {
		step_current = steps.variants;
		EditVariants();
	}
}

function SetUploads() {
	LoadingStart();	
	// Fortschritt / Aktuelle Einstellungen anzeigen
	jQuery("#overview").show();
	jQuery("#overview_media_table").show();
	// Kategorieauswahl verstecken
	jQuery("#uploads").addClass("done").hide();
	// Zusammenfassung anzeigen
	if (step_current == steps.media) step_current++;
	ShowActive();
}

if (typeof kat_is_paid == "undefined") {
    kat_is_paid = 0;
}
/*
 * CATEGORY LOADER
 */
function GetCategorys(b_paid) {
	kat_is_paid = b_paid;
	jQuery.ajax({
		url: ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=kats&paid='+b_paid,
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

function GetManufacturers(query, callback) {
	if (typeahead_timer != false) {
		window.clearTimeout(typeahead_timer);
		typeahead_timer = false;
	}
	if (query.length > 2) {
		typeahead_timer = window.setTimeout(function() {
			var url = ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&mode=ajax&do=typeahead_manufacturer&query="+encodeURIComponent(query);
			jQuery.get(url, function(result) {
				callback(result);
			});	
		}, 500);
	}
}

function GetProducts(query, callback) {
	if (typeahead_timer != false) {
		window.clearTimeout(typeahead_timer);
		typeahead_timer = false;
	}
	if (query.length > 2) {
		typeahead_timer = window.setTimeout(function() {
			var manufacturer = jQuery("#HERSTELLER").val();
			var url = ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&mode=ajax&do=typeahead_product&query="+encodeURIComponent(query)
				+ "&man="+encodeURIComponent(manufacturer);
			jQuery.get(url, function(result) {
				callback(result);
			});	
		}, 500);
	}
}

function UpdateKatSelector(id_kat, name_current, root_kat, b_done, b_collapsing) {
	if (!b_done) {
		var url = ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&mode=ajax&do=kats&root=" + id_kat + "&paid=" + (kat_is_paid ? 1 : 0);
		jQuery.get(url, function(result) {
			var html_result = jQuery(result).find("#kat_list");
			jQuery("#kat_list").replaceWith(html_result);
            UpdateKatSelectorDone();
		});
	} else {
		// Deactivate possibly active categories
		jQuery("#kat_list a.active").removeClass("active");
		var selection = jQuery("#row" + id_kat);
		var link = selection.children("a");
		if (id_kat > 0) {
			link.addClass("active");
			if (confirm('Do you want to choose "' + name_current + '" as a category?')) {
				SetCategory(id_kat, name_current, jQuery("#form_ID_AD").val());
			} else {
				link.removeClass("active");
			}
		}
	}
}

/*
 * Editor
 */

input_timer_desc = null;

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
        	convert_urls : false,
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
        	convert_urls : false,
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
        	content_css : ebiz_trader_baseurl+"style/de/style-new.css?" + new Date().getTime()
        });
    }
    tinyMCE.execCommand("mceAddControl", true, element);
    // Validate description
    window.waitMCE = window.setInterval(function(){
        if (tinyMCE.activeEditor != null) {
            window.clearInterval(window.waitMCE);
            tinyMCE.activeEditor.onKeyUp.add(function(ed){
                if (input_timer_desc != null) {
                	window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    validateInput(jQuery('#' + element));
				}, 1000);
            });
            tinyMCE.activeEditor.onChange.add(function(ed){
                if (input_timer_desc != null) {
                	window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    validateInput(jQuery('#' + element));
				}, 1000);
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
        convert_urls : false,
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
            window.clearInterval(window.waitMCE);
            tinyMCE.activeEditor.onKeyUp.add(function(ed){
                if (input_timer_desc != null) {
                    window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    validateInput(jQuery('#' + e));
                }, 1000);
            });
            tinyMCE.activeEditor.onChange.add(function(ed){
                if (input_timer_desc != null) {
                    window.clearTimeout(input_timer_desc);
                }
                input_timer_desc = window.setTimeout(function() {
                    validateInput(jQuery('#' + e));
                }, 1000);
            });
        }
    }, 1000);
}

/*
 * Input validation
 */

timer_check = null;
valid_all = false;
valid_timer = null;
valid_count = 0;
valid_count_done = 0;

function updateVersand() {
	if ($("input[name=VERSANDOPTIONEN]:checked").val() != 3) {
		// Selbstabholung / Auf Anfrage / ...
		$("#VERSANDKOSTEN").attr("disabled", true);
		$("#VERSANDKOSTEN").prop("required", false);
	} else {
		// Versandkosten: Preis
		$("#VERSANDKOSTEN").attr("disabled", false);
		$("#VERSANDKOSTEN").prop("required", true);
	}
	if ($("input[name=VERSANDOPTIONEN]:checked").val() != 1) {
		// Selbstabholung
		$("#LIEFERTERMIN").attr("disabled", false);
		$("#LIEFERTERMIN").prop("required", true);
	} else {
		// Versandkostenfrei / Auf Anfrage / Preis
		$("#LIEFERTERMIN").attr("disabled", true);
		$("#LIEFERTERMIN").prop("required", false);
	}
}

function updateCollect() {
	if ($("input[name=ONLY_COLLECT]:checked").length > 0) {
		$("#VERSANDKOSTEN").val("0.00");
		$("#VERSANDKOSTEN").attr("disabled", true);
	} else {
		$("#VERSANDKOSTEN").attr("disabled", false);
	}
}

function updateTrade() {
	if ($("input[name=TRADE]:checked").length > 0) {
		$("#AUTOBUY").attr("disabled", false);
	} else {
		$("#AUTOBUY").val("0.00");
		$("#AUTOBUY").attr("disabled", true);
	}
}

function validateRequired() {
    if (valid_timer != null) {
        window.clearInterval(valid_timer);
        valid_timer = null;
    }
    LoadingStart({
		'shown': function() {
			valid_all = true;
			valid_count = 0;
			valid_count_done = 0;

			var validationChecks = [];

			jQuery("#description input[required=required]").each(function(index, input) {
				validationChecks.push(
					 validateInput(input, true)
				);
			});
			jQuery("#description textarea[required=required]").each(function(index, input) {
				validationChecks.push(
					 validateInput(input, true)
				);
			});
			jQuery("#description select[required=required]").each(function(index, input) {
				validationChecks.push(
					 validateInput(input, true)
				);
			});
			jQuery("#description input[data-required=required]").each(function(index, input) {
				validationChecks.push(
					validateInput(input, true)
				);
			});

			jQuery("#form_ad").submit();
			jQuery.when.apply(null, validationChecks).then(function() {
				validateRequiredDone(valid_all);
			}, function() {

			})
		}
	});
}

function validateRequiredDone(result) {
    if (valid_timer != null) {
        window.clearInterval(valid_timer);
        valid_timer = null;
    }
	//LoadingStop();
	if (result) {
		SetDetails();
	}
}

function validateInputLive(input){
    if (timer_check != null) {
        window.clearTimeout(timer_check);
        timer_check = null;
    }
    timer_check = window.setTimeout("validateInput(jQuery('#" + input.id + "'));", 1000);
}

function validateInput(input, count) {
	input = jQuery(input);
	if (input.length == 0)
		return;
    if (input[0].id == "BESCHREIBUNG") {
        // TinyMCE
        if (tinyMCE.activeEditor) 
            tinyMCE.activeEditor.save();
    }

	if(input.is("[data-useattributes='data']")) {
		var name = input.attr('data-fieldname');
		var value = input.val();
		var needed = input.attr("data-required");

		if(input.is("[type='checkbox']")) {
			value = jQuery("[data-fieldname='"+name+"']:checked").length;
		}
	} else {
		var name = input[0].name;
		var value = input.val();
		var needed = input.attr("required");
	}

    if (!name) 
        return;
    
    var type = '';
    var field_type = $('input[name="tmp_type\\['+name+'\\]"]');
    if (field_type.length > 0) {
    	type = field_type.val();
    }
    if (typeof count != "undefined") {
        valid_count++;	
    }
	return jQuery.ajax({
		url: 		ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&frame=ajax&mode=ajax",
		type: 		'POST',
		dataType:	'json',
		data: 		{
			'do':		'validate',
			'needed': 	(needed == "required" ? 1 : 0),
			'name':		name,
			'valtype':	type,
			'value':	value
		},
		success: 	function(obj) {
			valid_count_done++;
	        if (!obj.valid) {
				var first_error = valid_all;
	        	valid_all = false;
	        	if (jQuery('#' + name + '_INPUT').length > 0) {
	        		var icon_error = '<img src="'+ebiz_trader_baseurl+'bilder/stop_check.png" class="js-tooltip" data-toggle="tooltip" title="'+obj.error_msg+'" />&nbsp;';
	        		jQuery('#' + name + '_INPUT').removeClass("info").removeClass("success").addClass("error");
	        		jQuery('#' + name + '_INPUT .help-inline').html(icon_error);
					jQuery('#' + name + '_INPUT .help-inline img').tooltip();

					if(first_error) {
						jQuery('html,body').animate({
							scrollTop: (jQuery('#' + name + '_INPUT').offset().top - 100)
						}, 1 , function() { });
					}
	        	}
	        	// Hack für den Editor
	        	if (name == "BESCHREIBUNG") {
	        		document.location.hash = "#BESCHREIBUNG_ANCHOR";
	        	}
				//input_cache[name.toUpperCase()] = { value: value, valid: false };
	        } else {
	        	if (jQuery('#' + name + '_INPUT').length > 0) {
	        		var icon_success = '<img height="20" src="'+ebiz_trader_baseurl+'bilder/ok.png" />&nbsp;';
	        		jQuery('#' + name + '_INPUT').removeClass("info").removeClass("error").addClass("success");
	        		jQuery('#' + name + '_INPUT .help-inline').html(icon_success+'Input correct or optional.');
	        	}

				LoadingStop();
				//input_cache[name.toUpperCase()] = { value: value, valid: true };
	        }
	        //updateFormValid();
		}
	});
}

function validateInputCheckbox(input) {
	input = jQuery(input);

	var value = input.val();
    var needed = input.attr("required");
	var name = input[0].name;


}

function getPosition(overlay, latlng) {
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

    updateMapCenter();
}

function setPosition(place) {
    var country = "";
    var city = "";
    var zip = "";
    var street = "";
    var number = "";
    var administrative_area_level_1 = "";

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
        if(value.types['0'] == 'route') {
            street = value.long_name + ' ' + number;
        }
        if(value.types['0'] == 'street_number') {
            number = value.long_name;
        }
        if(value.types['0'] == 'administrative_area_level_1') {
            administrative_area_level_1 = value.long_name;
        }
    });

    var country_dropdown = jQuery('#fk_country');
    country_dropdown.find("option").each(function(key, value) {
        if(jQuery(value).text() == country) {
            jQuery(value).attr("selected", "selected");
        } else {
            jQuery(value).attr("selected", false);
        }
    });
    
    jQuery('#LONGITUDE').val(place.geometry.location.lng());
    jQuery('#LATITUDE').val(place.geometry.location.lat());
    jQuery('#ZIP').val(zip);
    jQuery('#CITY').val(city);
    jQuery('#STREET').val(street);
    jQuery('#ADMINISTRATIVE_AREA_LEVEL_1').val(administrative_area_level_1);

    validateInput(jQuery('#ZIP'));
    validateInput(jQuery('#CITY'));
    validateInput(jQuery('#STREET'));
}

/*
 * Kategorie-Abhängige Eingabefelder
 */

function getInputFields(id_kat, id_ad) {
	if (typeof id_ad == "undefined") {
		id_ad = 0;
	}
    jQuery(tinyMCE.editors).each(function(){
        tinyMCE.remove(this);
    });
    map_loaded = false;
	jQuery.ajax({
		url: ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=input&FK_KAT='+id_kat+'&ID_AD='+id_ad,
		dataType: 'json',
		success: function(obj) {
			jQuery("#description").attr("class", "step-input ad-table-"+obj.ad_table).html(obj.input);
			jQuery("#overview .overview-details-kat").remove();
			jQuery("#overview_details_BESCHREIBUNG ").after(obj.overview);
			jQuery("#overview_details_EDIT").attr("rowspan", jQuery("#overview .overview-details").length);
			InitializeEditor(true);
			// Hersteller Eingabefeld
			if (jQuery('#HERSTELLER').length > 0) {
				// Herstellerdatenbank aktiv
				jQuery('#HERSTELLER').typeahead({
					source: GetManufacturers
				});
				jQuery('#PRODUKTNAME').typeahead({
					source: GetProducts
				});	
			}
			// Map updaten
			var addr_zip = jQuery("#ZIP_INPUT").val();
			var addr_city = jQuery("#CITY_INPUT").val();
			var addr_country = jQuery("#FK_COUNTRY_INPUT").val();
			var addr_street = jQuery("#STREET_INPUT").val();
			getLatiLongi(addr_zip, addr_city, addr_country, addr_street);
            showMap(null, jQuery('#googleMap'));
		}
	});
}

/*
 * Medien (Bilder/Dokumente/Videos)
 */

upload_current = null;

function UploadDone() {
	if (upload_current != null) {
		var id_ad = jQuery("#form_ID_AD").val();
		// Neuer upload abgeschlossen
		if (upload_current == "image") {
			jQuery("#list_images").load(ebiz_trader_baseurl+'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&show=images&ID_AD='+id_ad);
		}
		if (upload_current == "document") {
			jQuery("#list_documents").load(ebiz_trader_baseurl+'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&show=documents&ID_AD='+id_ad);
		}
		upload_current = null;
		// Kontingent updaten
		UploadRefresh();
	}
}

function UploadRefresh() {
	LoadingStart();
	var id_ad = jQuery("#form_ID_AD").val();
	var fk_kat = jQuery("#form_FK_KAT").val();
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload', {
			// POST-Parameter
			ID_AD: id_ad,
			FK_KAT: fk_kat
		}, function(result) {
			LoadingStop();
			// Callback bei Erfolg
			if (result.success) {
				jQuery("#list_images").html(result.html.images);
				jQuery("#list_documents").html(result.html.documents);
				jQuery("#list_videos").html(result.html.videos);
				// Kosten-Übersicht
				var overall_new = result.packet.ads_new + result.packet.images_new + 
					result.packet.downloads_new + result.packet.videos_new;
				if (overall_new > 0) {
					jQuery("#cost").show();
					jQuery("#cost_ads").html(result.packet.ads_new);
					jQuery("#cost_images").html(result.packet.images_new);
					jQuery("#cost_documents").html(result.packet.downloads_new);
					jQuery("#cost_videos").html(result.packet.videos_new);	
				} else {
					jQuery("#cost").hide();
				}
				// Übersicht
				jQuery("#overview_media_images_text").html(result.packet.images_used);
				jQuery("#overview_media_documents_text").html(result.packet.downloads_used);
				jQuery("#overview_media_videos_text").html(result.packet.videos_used);
				// Bilder
				if (result.packet.images_free > 0) {
					jQuery("#images_free").show();
					jQuery("#images_free_count").html(result.packet.images_free);
				} else {
					jQuery("#images_free").hide();
				}
                if (result.packet.images_left > 0) {
                    jQuery("#upload_avaible_image").show();
                    jQuery("#error_image").hide();
                    jQuery("#images_left").html(result.packet.images_left);
                    jQuery("#images_max").html(result.packet.images_max);
                } else {
                    jQuery("#upload_avaible_image").hide();
                    jQuery("#error_image").show();
                }
				// Dokumente
				if (result.packet.downloads_free > 0) {
					jQuery("#documents_free").show();
					jQuery("#documents_free_count").html(result.packet.downloads_free);
				} else {
					jQuery("#documents_free").hide();
				}
                if (result.packet.downloads_left > 0) {
                    jQuery("#upload_avaible_document").show();
                    jQuery("#error_document").hide();
                    jQuery("#documents_left").html(result.packet.downloads_left);
                    jQuery("#documents_max").html(result.packet.downloads_max);
                    jQuery("#documents_format").html(result.packet.downloads_format);
                } else {
                    jQuery("#upload_avaible_document").hide();
                    jQuery("#error_document").show();
                }
				// Videos
				if (result.packet.videos_free > 0) {
					jQuery("#videos_free").show();
					jQuery("#videos_free_count").html(result.packet.videos_free);
				} else {
					jQuery("#videos_free").hide();
				}
                if (result.packet.videos_left > 0) {
                    jQuery("#upload_avaible_video").show();
                    jQuery("#error_video").hide();
                    jQuery("#videos_left").html(result.packet.videos_left);
                    jQuery("#videos_max").html(result.packet.videos_max);
                    jQuery("#videos_format").html(result.packet.videos_format);
                } else {
                    jQuery("#upload_avaible_video").hide();
                    jQuery("#error_video").show();
                }
			} else {
				
			}
		},
		"json"
	);
}

function UploadImage() {
	var id_ad = jQuery("#form_ID_AD").val();
	LoadingStart();
	upload_current = "image";
	// Start ajax upload
	var formular = jQuery("#upload_image");
	jQuery.ajaxFileUpload({
		url: formular.attr("action")+'&ID_AD='+id_ad,
		fileElementId: "UPLOAD_IMAGE",
		secureuri: false,
		dataType: 'json',
		success: function() {
			UploadDone();
		},
		error: function() {
			alert("Upload failed!");
		}
	});
}

function UploadFile() {
	var id_ad = jQuery("#form_ID_AD").val();
	LoadingStart();
	upload_current = "document";
	// Start ajax upload
	var formular = jQuery("#upload_file");
	jQuery.ajaxFileUpload({
		url: formular.attr("action")+'&ID_AD='+id_ad,
		fileElementId: "UPLOAD_FILE",
		secureuri: false,
		dataType: 'json',
		success: function() {
			UploadDone();
		},
		error: function() {
			alert("Upload failed!");
		}
	});
}

function UploadVideo() {
	LoadingStart();
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload',
		jQuery("#upload_video").serialize(),
		function(result) {
			LoadingStop();
			jQuery("#list_videos").html(result);
			// Kontingent updaten
			UploadRefresh();
		}
	);
}

function ImageSetDefault(id_ad, id_image) {
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=image_default&ID_AD='+id_ad+'&id='+id_image,
		function(result) {
			jQuery("#list_images").html(result);
		}
	);
}

function ImageDelete(id_ad, id_image) {
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=image_delete&ID_AD='+id_ad+'&id='+id_image,
		function(result) {
			UploadRefresh();
		}
	);
}

function DocumentDelete(id_ad, id_document) {
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=document_delete&ID_AD='+id_ad+'&id='+id_document,
		function(result) {
			UploadRefresh();
		}
	);
}

function VideoDelete(id_ad, id_video) {
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=video_delete&ID_AD='+id_ad+'&id='+id_video,
		function(result) {
			UploadRefresh();
		}
	);
}

/*
 + Varianten
 */

function VariantsRefresh() {
	var id_ad = jQuery("#form_ID_AD").val();
	jQuery.post(
		ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=variantstable&ID_AD='+id_ad,
		function(result) {
			jQuery("#variants").html(result);
		}
	);
}

/*
 * Vorschau / Eingaben bestätigen
 */

function LoadPreview() {
	var id_ad = jQuery("#form_ID_AD").val();
	jQuery.get(
		ebiz_trader_baseurl + 'index.php?page=marktplatz_anzeige&frame=ajax&ID_ANZEIGE='+id_ad+'&preview=1',
		function(result) {
			jQuery("#confirm_content").html(result);
		}
	);
}

function AdCancel(target) {
	var edit_target = (typeof target != "undefined" ? target : "overview");
	jQuery("#modal_confirm").modal("hide");
	if (edit_target == "description") {
		EditDescription();
	} else if (edit_target == "media") {
		EditMedia();
	} else if (edit_target == "overview") {
		ShowOverview();
	}
}

function AdConfirm() {
	var id_ad = jQuery("#form_ID_AD").val();
	jQuery.ajax({
		url: ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=finish&ID_AD='+id_ad,
		dataType: 'json',
		success: function(obj) {
			if (obj.success) {
				location.href = obj.url;
			} else {
				alert("Error while confirming the advert!");
				jQuery("#modal_confirm").modal("hide");
			}
		}
	});
}

/*
 * Verfügbarkeit
 */

function addAvailability(availabilityData) {
	for(var dateFrom in availabilityData) {
		for(var dateTo in availabilityData[dateFrom]) {
			// Add Date range
			var divDateRange = addAvailabilityRangeEx(dateFrom, dateTo);
			for(var weekday in availabilityData[dateFrom][dateTo]) {
				var times = availabilityData[dateFrom][dateTo][weekday];
				while (times.length >= 2) {
					var timeFrom = times.shift();
					var timeTo = times.shift();
					addAvailabilityTimeEx(divDateRange, timeFrom, timeTo, weekday);					
				}
			}
		}
	};
}

function addAvailabilityRange(button) {
	var inputFrom = jQuery(button).parent('.controls').find('input[name=availability_from]');
	var inputTo = jQuery(button).parent('.controls').find('input[name=availability_to]');
	var valueFrom = inputFrom.val();
	var valueTo = inputTo.val();
	return addAvailabilityRangeEx(valueFrom, valueTo);
}

function addAvailabilityRangeEx(valueFrom, valueTo) {
	var htmlTemplate = jQuery("#AVAILABILITY_TIMES_INPUT").html();
	jQuery("#AVAILABILITY_DATES").append(htmlTemplate);
	jQuery("#AVAILABILITY_DATES .control-group:last input").keypress(function(e) {
		if ( e.which == 13 ) {
			e.preventDefault();
			addAvailabilityTime(this);
			return false;
		}
    });
    var divResult = jQuery("#AVAILABILITY_DATES .control-group:last");
	divResult.find(".value-date-from").html(valueFrom);
	divResult.find(".value-date-to").html(valueTo);
	divResult.find(".input-date-from").val(valueFrom);
	divResult.find(".input-date-to").val(valueTo);
	divResult.find(".range-input input").datepicker({
		dateFormat: 'dd.mm.yy'
	}).keypress(function(e) {
		if ( e.which == 13 ) {
			e.preventDefault();
			editAvailabilityRangeSubmit(this);
			return false;
		}
    });
    return divResult;
}

function editAvailabilityRange(button) {
	var rowRange = jQuery(button).parents('.control-group');
	rowRange.find('.range-value').hide();
	rowRange.find('.range-input').show();
}

function editAvailabilityRangeCancel(button) {
	var rowRange = jQuery(button).parents('.control-group');
	rowRange.find('.range-input').hide();
	rowRange.find('.range-value').show();
}

function editAvailabilityRangeSubmit(button) {
	var rowRange = jQuery(button).parents('.control-group');
	var dateFrom = rowRange.find('.range-input input.input-date-from').val();
	var dateTo = rowRange.find('.range-input input.input-date-to').val();
	rowRange.find('.list-times input[type=hidden]').each(function(index, input) {
		var nameOld = jQuery(input).attr("name");
		var regexpName = /availability\[([0-9\.-]+)\]\[([0-9\.-]+)\]\[([0-9]+)\]\[\]/gi;
		var matches = regexpName.exec(nameOld);
		if (matches != null) {
			var name = "availability["+dateFrom+"]["+dateTo+"]["+matches[3]+"][]";
			jQuery(input).attr("name", name);
		}
	});
	rowRange.find(".value-date-from").html(dateFrom);
	rowRange.find(".value-date-to").html(dateTo);
	rowRange.find('.range-input').hide();
	rowRange.find('.range-value').show();
}

function remAvailabilityRange(button) {
	var rowRange = jQuery(button).parents('.control-group');
	rowRange.remove();
}

function addAvailabilityTime(button) {
	var inputGroup = jQuery(button).parents('.control-group');
	var inputWeekday = inputGroup.find('select[name=weekday]');
	var inputFrom = inputGroup.find('input[name=from]');
	var inputTo = inputGroup.find('input[name=to]');
	var textWeekday = inputWeekday.find('option:selected').html();
	var valueWeekday = inputWeekday.val();
	var valueFrom = inputFrom.val();
	var valueTo = inputTo.val();
	if (valueWeekday > 0) {
		addAvailabilityTimeEx(inputGroup, valueFrom, valueTo, valueWeekday);	
	} else {
		var valueWeekdayStart = 1;
		var valueWeekdayEnd = 7;
		if (valueWeekday == -1) {	// Montag - Freitag
			valueWeekdayEnd = 5;
		}
		if (valueWeekday == -2) {	// Montag - Samstag
			valueWeekdayEnd = 6;
		}
		for(var i=valueWeekdayStart; i<=valueWeekdayEnd; i++) {
			addAvailabilityTimeEx(inputGroup, valueFrom, valueTo, i);	
		};
	}
}

function addAvailabilityTimeEx(inputGroup, valueFrom, valueTo, valueWeekday) {
	var listTimes = inputGroup.find('.list-times');
	var valueDateFrom = inputGroup.find('.value-date-from').html();
	var valueDateTo = inputGroup.find('.value-date-to').html();
	var textWeekday = inputGroup.find('select[name=weekday] option[value='+valueWeekday+']').html();
	var rowWeekday = inputGroup.find('.times-weekday-row-'+valueWeekday);
	if (rowWeekday.length == 0) {
		var rowWeekdayPrev = null;
		for (var i=0; i < valueWeekday; i++) {
			var cur = inputGroup.find('.times-weekday-row-'+i);
			if (cur.length > 0) {
				rowWeekdayPrev = cur;
			}
		};
		rowWeekday = jQuery("#AVAILABILITY_TIMES_ROW").clone()
			.attr({'id': "", 'class': 'times-weekday-row times-weekday-row-'+valueWeekday});
		rowWeekday.find('.value-time-weekday').html(textWeekday);
		
		if (rowWeekdayPrev != null) {
			rowWeekday.insertAfter(rowWeekdayPrev);
		} else {
			listTimes.append(rowWeekday);	
		}
		
	} else {
		var htmlTemplateEntry = jQuery("#AVAILABILITY_TIMES_ROW .times-row-entry").clone();
		rowWeekday.find(".times-row-list").append(htmlTemplateEntry);	
	}
	var rowTime = rowWeekday.find('.times-row-entry:last');
	rowTime.find('.value-time-from').html(valueFrom);
	rowTime.find('.value-time-to').html(valueTo);
	rowTime.append( 
		jQuery('<input type="hidden" />').attr('name', 'availability['+valueDateFrom+']['+valueDateTo+']['+valueWeekday+'][]').val(valueFrom)
	);
	rowTime.append( 
		jQuery('<input type="hidden" />').attr('name', 'availability['+valueDateFrom+']['+valueDateTo+']['+valueWeekday+'][]').val(valueTo)
	);
}

function remAvailabilityTime(button) {
	var rowTime = jQuery(button).parents('.times-row-entry');
	var rowWeekday = jQuery(button).parents('.times-weekday-row');
	rowTime.remove();
	if (rowWeekday.find('.times-row-entry').length == 0) {
		rowWeekday.remove();
	}	
}

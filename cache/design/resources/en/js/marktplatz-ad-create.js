/* ###VERSIONSBLOCKINLCUDE### */

step_request = false;
step_data = false;
step_change_callbacks = [];
typeahead_timer = false;
timer_check = false;
kat_is_paid = 0;

jQuery(function() {
    // Initialize step data to detect changes
    step_data = false;
    var formNew = jQuery("#adCreateStepContent form");
    if (formNew.length > 0) {
        step_data = formNew.serialize();
    }
});

function SetCategory(id_kat, name) {
    var form = jQuery("#kat_select_form");
    if (form.length > 0) {
        form.find("input[name=FK_KAT]").val(id_kat);
        SubmitStep(form[0]);
    }
}

function SetPacket(id_packet, name) {
    var form = jQuery("#packet_select_form");
    if (form.length > 0) {
        form.find("input[name=FK_PACKET_ORDER]").val(id_packet);
        SubmitStep(form[0]);
    }
}

function AddStepChangeCallback(callback) {
    step_change_callbacks.push(callback);
}

function CallStepChangeCallback(options) {
    options.allowChange = true;
    var callbacksCurrent = step_change_callbacks;
    step_change_callbacks = [];
    for (var i = 0; i < callbacksCurrent.length; i++) {
        if (!callbacksCurrent[i](options)) {
            step_change_callbacks.push(callbacksCurrent[i]);
        }
    }
    return options.allowChange;
}

function ShowStepNext(ajax, options) {
    // Replace undefined parameters by defaults
    if (typeof ajax == "undefined") {
        ajax = true;
    }
    if (typeof options == "undefined") {
        options = {
            force: false
        };
    }
    // Show step
    var stepIndexCurrent = parseInt(jQuery("#adCreateStepContent").attr("data-step"));
    ShowStep(stepIndexCurrent+1, ajax, options);
}

function ShowStep(stepIndex, ajax, options) {
    if (!CallStepChangeCallback({ action: "change", step: stepIndex, ajax: ajax, options: options })) {
        // Do not change step
        return;
    }
    // Show loading notice
    ShowLoadingNotice('Loading step...');
    // Replace undefined parameters by defaults
    if (typeof ajax == "undefined") {
        ajax = true;
    }
    if (typeof options == "undefined") {
        options = {
            force: false,
            save: true,
            scrollTop: true
        };
    }
    if (typeof options.scrollTop == "undefined") {
        options.scrollTop = true;
    }
    if (options.save) {
        // Save and show step
        var form = jQuery("#adCreateStepContent form");
        var formData = form.serialize();
        var question = 'Do you want to save the made changes?';
        if (step_data != formData) {
            // Changes within current step, ask to save/discard
            jQuery("#modal_step_dirty").modal("show");
            jQuery("#modal_step_dirty [data-action=discard]").off("click").on("click", function(event) {
                event.preventDefault();
                // Discard changes and change step
                options.save = false;
                ShowStep(stepIndex, ajax, options);
                // Hide modal
                jQuery("#modal_step_dirty").modal("hide");
            });
            jQuery("#modal_step_dirty [data-action=save]").off("click").on("click", function(event) {
                event.preventDefault();
                // Save changes and change step
                SubmitStepRaw(formData, { stepNext: stepIndex });
                // Hide modal
                jQuery("#modal_step_dirty").modal("hide");
            });
        } else {
            // Current step unchanged, just switch
            options.save = false;
            ShowStep(stepIndex, ajax, options);
        }
    } else {
        // Show step
        var stepIndexCurrent = parseInt(jQuery("#adCreateStepContent").attr("data-step"));
        if ((stepIndexCurrent != stepIndex) || (options.force)) {
            var url = ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&mode=ajax&do=getStep&index=" + stepIndex;
            jQuery.get(url, function(result) {
                HideLoadingNotice();
                if (result.success) {
                    if (options.scrollTop) {
                        jQuery(document).scrollTop(0);
                    }
                    jQuery("#adCreateStepList").html(result.list);
                    jQuery("#adCreateStepContent").html(result.content).attr("data-step", stepIndex);
                    if (result.maximized) {
                        jQuery(".design-ad-create-form").addClass("maximized");
                    } else {
                        jQuery(".design-ad-create-form").removeClass("maximized");
                    }
                    step_data = false;
                    var formNew = jQuery("#adCreateStepContent form");
                    if (formNew.length > 0) {
                        step_data = formNew.serialize();
                    }
                    if (typeof options.callback != "undefined") {
                        options.callback();
                    }
                }
            });
        }
    }
}

function SubmitEditorContents(form, options) {
    // TinyMCE
    if (tinyMCE.activeEditor && tinyMCE.activeEditor.isDirty()) {
        tinyMCE.activeEditor.save();
        window.setTimeout(function() {
            SubmitStep(form);
        });
        return false;
    } else {
        return true;
    }
}

function SubmitStep(form, options) {
    SubmitStepRaw(jQuery(form).serialize(), options, form);
}

function removeWarningMsg( ptr ) {
    if ( jQuery(ptr).prop("checked") ) {
        jQuery("#download-error-heading").remove();
    }
}

function SubmitStepRaw(postData, options, form) {
    if (!CallStepChangeCallback({ action: "submit", data: postData, options: options, form: form })) {
        // Do not change step
        return;
    }
    // Show loading notice
    ShowLoadingNotice('Inputs are saving...');
    // Replace undefined parameters by defaults
    if (typeof options == "undefined") {
        options = {};
    }
    // Show modal loading info (instead of animated loading bar below the steps on the left)
    if (typeof options.loadingModal != "undefined") {
        LoadingStart();
    }
    // Execure pre-submit callback
    if (typeof options.callbackPre != "undefined") {
        if (!options.callbackPre(form, options)) {
            return false;
        }
    }
    // Cancel current request if already running
    if (step_request !== false) {
        if (step_request.readyState == 4) {
            // Running request already finished! Do not send another request.
            return false;
        }
        // Request still pending. Cancel and create new
        step_request.abort();
        step_request = false;
    }
    // Submit step
    var url = ebiz_trader_baseurl + "index.php?page=my-marktplatz-neu&mode=ajax&do=submitStep";
    step_request = jQuery.post(url, postData, function(result) {
        step_request = false;
        HideLoadingNotice();
        if (result.success) {
            if (typeof options.callback != "undefined") {
                options.callback();
            }
            if (typeof options.loadingModal != "undefined") {
                LoadingStop();
            }
            if (result.done && (typeof options.stepNext == "undefined")) {
                jQuery(window).off("unload beforeunload");
                document.location.href = result.url;
            } else {
                var stepOptions = { force: false, save: false };
                if (typeof options.scrollTop != "undefined") {
                    stepOptions.scrollTop = options.scrollTop;
                }
                if (typeof options.stepNext == "undefined") {
                    stepOptions.force = false;
                    ShowStepNext(true, stepOptions);
                } else {
                    stepOptions.force = true;
                    ShowStep(options.stepNext, true, stepOptions);
                }
            }
        } else {
            if (typeof options.loadingModal != "undefined") {
                LoadingStop();
            }
            var first = true;
            var errorVisible = false;
            var errorText = [];
            for (var fieldName in result.errors) {
                if (fieldName.charAt(0) == '_') {
                    // Generic error
                    var errorContainerId = "#"+fieldName.substr(1);
                    var errorContainer = jQuery(errorContainerId);
                    if (errorContainer.length > 0) {
                        jQuery(errorContainerId).html(result.errors[fieldName]).parents('.alert').show();
                    } else {
                        errorText.push( fieldName.substr(1)+": "+result.errors[fieldName] );
                    }
                } else if (fieldName.charAt(0) == '!') {
                    errorText.push( result.errors[fieldName] );
                } else {
                    var execute = true;
                    if ( typeof result.errors[fieldName] == 'object' ) {
                        console.log( result.errors[fieldName] );
                        var obj = result.errors[fieldName];
                        if ( obj.hasOwnProperty("error_document") ) {
                            var html = '<h2 id="download-error-heading" style="color: red;">';
                            html += obj["error_document"];
                            html += '</h2>';
                            jQuery("#list_documents").parent().parent().prepend( html );
                            execute = false;
                        }
                    }
                    if ( execute ) {
                        var errorInput = jQuery('#' + fieldName + '_INPUT');
                        var errorHelp = jQuery('#' + fieldName + '_INPUT .help-inline');
                        if ((errorInput.length > 0) || (errorHelp.length > 0)) {
                            var icon_error = '<img src="'+ebiz_trader_baseurl+'bilder/stop_check.png" class="js-tooltip" data-toggle="tooltip" title="'+result.errors[fieldName]+'" />';
                            errorInput.removeClass("text-info").removeClass("text-success").addClass("text-danger");
                            errorHelp.html(icon_error);
                            errorHelp.find('img').tooltip();
                            if (first) {
                                validateInputErrorScroll(fieldName);
                                first = false;
                            }
                        } else {
                            errorText.push( fieldName+": "+result.errors[fieldName] );
                        }
                    }
                }
            }
            if (errorText.length > 0) {
                alert( "Error while saving:\n" + errorText.join("\n") );
            }
        }
    });
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
			SetCategory(id_kat, name_current, jQuery("#form_ID_AD").val());
			/*
			if (confirm('Do you want to choose "' + name_current + '" as a category?')) {
				SetCategory(id_kat, name_current, jQuery("#form_ID_AD").val());
			} else {
				link.removeClass("active");
			}
			*/
		}
	}
}

function UpdateKatSelectorDone() {

}

/*
 * Loading modal
 */

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
 * Medien (Bilder/Dokumente/Videos)
 */

upload_current = null;

function UploadRefresh() {
    var id_ad = jQuery("#form_ID_AD").val();
    var fk_kat = jQuery("#form_FK_KAT").val();
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=getMediaUsage', {
            // POST-Parameter
            ID_AD: id_ad,
            FK_KAT: fk_kat
        }, function(result) {
            // Bilder
            if (result.images_free > 0) {
                jQuery("#images_free").show();
                jQuery("#images_free_count").html(result.images_free);
            } else {
                jQuery("#images_free").hide();
            }
            if (result.images_available > 0) {
                jQuery("#upload_avaible_image").show();
                jQuery("#error_image").hide();
                jQuery("#images_left").html(result.images_available);
                jQuery("#images_max").html(result.images_limit);
            } else {
                jQuery("#upload_avaible_image").hide();
                jQuery("#error_image").show();
            }
            // Dokumente
            if (result.downloads_free > 0) {
                jQuery("#documents_free").show();
                jQuery("#documents_free_count").html(result.downloads_free);
            } else {
                jQuery("#documents_free").hide();
            }
            if (result.downloads_available > 0) {
                jQuery("#upload_avaible_document").show();
                jQuery("#error_document").hide();
                jQuery("#documents_left").html(result.downloads_available);
                jQuery("#documents_max").html(result.downloads_limit);
                jQuery("#documents_format").html(result.downloads_formats);
            } else {
                jQuery("#upload_avaible_document").hide();
                jQuery("#error_document").show();
            }
            // Videos
            if (result.videos_free > 0) {
                jQuery("#videos_free").show();
                jQuery("#videos_free_count").html(result.videos_free);
            } else {
                jQuery("#videos_free").hide();
            }
            if (result.videos_available > 0) {
                jQuery("#upload_avaible_video").show();
                jQuery("#error_video").hide();
                jQuery("#videos_left").html(result.videos_available);
                jQuery("#videos_max").html(result.videos_limit);
            } else {
                jQuery("#upload_avaible_video").hide();
                jQuery("#error_video").show();
            }
        },
        "json"
    );
}

function UploadVideo() {
    LoadingStart();
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload',
        "youtube_url="+encodeURIComponent(jQuery("input[name=youtube_url]").val()),
        function(result) {
            LoadingStop();
            jQuery("#list_videos").replaceWith(result);
            // Kontingent updaten
            UploadRefresh();
        }
    );
}

function ImageSetDefault(id_ad, id_image) {
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=image_default&ID_AD='+id_ad+'&id='+id_image,
        function(result) {
            jQuery("#list_images > tbody").html(result);
        }
    );
}

function ImageDelete(id_ad, id_image) {
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=image_delete&ID_AD='+id_ad+'&id='+id_image,
        function(result) {
            jQuery("#list_images > tbody").html(result);
            // Update media usage
            UploadRefresh();
        }
    );
}

function ImageRotate(id_ad, id_image, degree) {
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=image_rotate&ID_AD='+id_ad+'&id='+id_image+'&degree='+degree,
        function(result) {
            jQuery("#list_images > tbody").html(result);
            // Update media usage
            UploadRefresh();
        }
    );
}

function DocumentDelete(id_ad, id_document) {
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=document_delete&ID_AD='+id_ad+'&id='+id_document,
        function(result) {
            jQuery("#uploadDocumentTable tbody").html( jQuery(result).find("tbody").html()  );
            if (jQuery("#uploadDocumentTable tbody tr").length == 0) {
                // Hide table, show "no uploads yet" notice
                jQuery("#uploadDocumentNoneYet").show();
                jQuery("#uploadDocumentTable").hide();
            }
            // Update media usage
            UploadRefresh();
        }
    );
}

function VideoDelete(id_ad, id_video) {
    jQuery.post(
        ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=upload&action=video_delete&ID_AD='+id_ad+'&id='+id_video,
        function(result) {
            jQuery("#list_videos").replaceWith(result);
            // Update media usage
            UploadRefresh();
        }
    );
}

/*
 * Editor
 */

input_timer_desc = null;

function InitializeEditor(allow_html, element) {
    if(typeof element ==  "undefined") { element = "BESCHREIBUNG"; }
    // Initialize editor
    if (typeof tinyMCE.editors["BESCHREIBUNG"] != "undefined") {
        tinyMCE.editors["BESCHREIBUNG"].setContent(jQuery("#BESCHREIBUNG").text(), { format: 'raw' });
    }
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

        	theme_advanced_buttons1 : "formatselect,|,bold,italic,underline,|,pastetext,pasteword,|,undo,redo,|,link,unlink,forecolor,removeformat,cleanup",
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
    // Remove editor if already converted
    tinyMCE.execCommand("mceRemoveControl", false, element);
    // Convert to tinyMCE editor
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
 * Google map
 */

function showMap(self, container){
    var latlng = new google.maps.LatLng(jQuery("#LATITUDE").val(), jQuery("#LONGITUDE").val());
    var myOptions = {
        zoom:13,
        zoomMax:15,
        center: latlng,
        mapTypeId:google.maps.MapTypeId.ROADMAP
    };

    map = new google.maps.Map(document.getElementById(container.attr("id")), myOptions);
    marker = new google.maps.Marker({
        position:latlng,
        map:map
    });

    google.maps.event.addListener(map, 'click', function(event) {
        getPosition(null, event.latLng);
    });
    geocoder = new google.maps.Geocoder();
}

function updateMapCenter() {
    var latlng = new google.maps.LatLng(jQuery("#LATITUDE").val(), jQuery("#LONGITUDE").val());
    map.setCenter(latlng);
}


function getPosition(overlay, latlng) {
    if (latlng != null) {
        jQuery.post(ebiz_trader_baseurl+"geolocation.htm", "reverse=1&lat="+latlng.lat()+"&lng="+latlng.lng(), function(result) {
            showPositionTrader(result);
        });
        //address = latlng;
        //geocoder.geocode( { 'latLng': latlng }, showPosition);
    }
}

function showPositionTrader(response){
    marker.setMap(null);
    if (response) {
        jQuery('#FK_GEO_REGION').val(response.FK_GEO_REGION);
        place = response.PLACE;
        point = new google.maps.LatLng(place.geometry.location.lat, place.geometry.location.lng);
        marker = new google.maps.Marker({
            position: point,
            map:map
        });
        setPosition(place);
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

    jQuery('#LONGITUDE').val(place.geometry.location.lng);
    jQuery('#LATITUDE').val(place.geometry.location.lat);
    jQuery('#ZIP').val(zip);
    jQuery('#CITY').val(city);
    jQuery('#STREET').val(street);
    jQuery('#ADMINISTRATIVE_AREA_LEVEL_1').val(administrative_area_level_1);

    validateInput(jQuery('#ZIP'));
    validateInput(jQuery('#CITY'));
    validateInput(jQuery('#STREET'));
}

/**
 * Eingabemaske
 */

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

function updateVerkauf() {
    var value = jQuery("#VERKAUFSOPTIONEN_INPUT input[name=VERKAUFSOPTIONEN]:checked").val();
	if(typeof value == 'undefined') {
		jQuery("#VERKAUFSOPTIONEN_INPUT input[name=VERKAUFSOPTIONEN]:first").attr("checked", true);
		value = jQuery("#VERKAUFSOPTIONEN_INPUT input[name=VERKAUFSOPTIONEN]:checked").val();
	}

    switch (value) {
        case '0':
            /**
             * Regulärer Verkauf
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").show();
            jQuery("#MENGE_INPUT input").prop("required", true);
            jQuery("#MENGE_INPUT input").prop("disabled", false);
            jQuery("#MOQ_INPUT").show();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").show();
            jQuery("#MIETPREISE_INPUT").hide();
            jQuery("#PREIS_INPUT input").prop("required", true);
            jQuery("#PREIS_INPUT input").prop("disabled", false);
			jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", false).parents('.form-group').show();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", false).parents('.form-group').show();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", false).parents('.form-group').show();
            jQuery("#TRADE_INPUT input").prop("disabled", false).parents('.form-group').show();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", false).parents('.form-group').show();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", false).parents('.form-group').show();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().show();
            // Versand
            jQuery(".design-ad-create-shipping").show();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", true);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", false);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().show();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').show();
            jQuery('[data-label="request"]').hide();
            break;
        case '1':
            /**
             * Preis ohne Verkaufsfunktion
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").show();
            jQuery("#MENGE_INPUT input").prop("required", true);
            jQuery("#MENGE_INPUT input").prop("disabled", false);
            jQuery("#MOQ_INPUT").show();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").show();
            jQuery("#MIETPREISE_INPUT").hide();
            jQuery("#PREIS_INPUT input").prop("required", false);
            jQuery("#PREIS_INPUT input").prop("disabled", false);
			jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", false).parents('.form-group').show();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", false).parents('.form-group').show();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", false).parents('.form-group').show();
            jQuery("#TRADE_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().hide();
            // Versand
            jQuery(".design-ad-create-shipping").show();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", true);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", false);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().show();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').show();
            jQuery('[data-label="request"]').hide();
            break;
        case '2':
            /**
             * Preis auf Anfrage, kein Verkauf
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").show();
            jQuery("#MENGE_INPUT input").prop("required", true);
            jQuery("#MENGE_INPUT input").prop("disabled", false);
            jQuery("#MOQ_INPUT").show();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").show();
            jQuery("#MIETPREISE_INPUT").hide();
            jQuery("#PREIS_INPUT input").prop("required", false);
            jQuery("#PREIS_INPUT input").prop("disabled", true);
			jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#TRADE_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().hide();
            // Versand
            jQuery(".design-ad-create-shipping").show();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", true);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", false);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().show();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').show();
            jQuery('[data-label="request"]').hide();
            break;
        case '3':
            /**
             * Vermieten
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").show();
            jQuery("#MENGE_INPUT input").prop("required", true);
            jQuery("#MENGE_INPUT input").prop("disabled", false);
            jQuery("#MOQ_INPUT").show();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").hide();
            jQuery("#MIETPREISE_INPUT").show();
            jQuery("#PREIS_INPUT input").prop("required", false);
            jQuery("#PREIS_INPUT input").prop("disabled", true);
            jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", false).parents('.form-group').show();
            jQuery("#TRADE_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().hide();
            // Versand
            jQuery(".design-ad-create-shipping").show();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", true);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", false);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().show();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').show();
            jQuery('[data-label="request"]').hide();
            break;
        case '4':
            /**
             * Inserat
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").hide();
            jQuery("#MENGE_INPUT input").prop("required", false);
            jQuery("#MENGE_INPUT input").prop("disabled", true);
            jQuery("#MOQ_INPUT").hide();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").hide();
            jQuery("#MIETPREISE_INPUT").hide();
            jQuery("#PREIS_INPUT input").prop("required", false);
            jQuery("#PREIS_INPUT input").prop("disabled", true);
            jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#TRADE_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", false).parents('.form-group').show();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().hide();
            // Versand
            jQuery(".design-ad-create-shipping").hide();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", false);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", true);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().show();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').show();
            jQuery('[data-label="request"]').hide();
            break;
        case '5':
            /**
             * Gesuch
             */
            // Menge / Mindestbestellmenge
            jQuery("#MENGE_INPUT").show();
            jQuery("#MENGE_INPUT input").prop("required", true);
            jQuery("#MENGE_INPUT input").prop("disabled", false);
            jQuery("#MOQ_INPUT").hide();
            // Preis / Grundpreis
            jQuery("#PREIS_INPUT").show();
            jQuery("#PREIS_INPUT input").prop("required", false);
            jQuery("#PREIS_INPUT input").prop("disabled", false);
            jQuery("#MIETPREISE_INPUT").hide();
            jQuery("#BASISPREIS_PREIS_INPUT input, #BASISPREIS_PREIS_INPUT select").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis
            jQuery("#PSEUDOPREIS_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Pseudopreis (Rabatt in %)
            jQuery("#B_PSEUDOPREIS_DISCOUNT_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // MwSt / Handeln / Automatischer zuschlag / Automatische Bestätigung
            jQuery("#MWST_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#TRADE_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOBUY_INPUT input").prop("disabled", true).parents('.form-group').hide();
            jQuery("#AUTOCONFIRM_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // B2B
            jQuery("#BF_CONSTRAINTS_B2B_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Affiliate link
            jQuery("#AFFILIATE_LINK_INPUT input").prop("disabled", true).parents('.form-group').hide();
            // Zahlungsweisen
            jQuery("#PAYMENT_ADAPTER_INPUT").parent().hide();
            // Versand
            jQuery(".design-ad-create-shipping").hide();
            jQuery("#VERSANDKOSTEN_INPUT input").prop("required", false);
            jQuery("#VERSANDKOSTEN_INPUT input").prop("disabled", true);
            jQuery("#LIEFERTERMIN_INPUT input").prop("required", false);
            jQuery("#LIEFERTERMIN_INPUT input").prop("disabled", true);
            // Rechtliches
            jQuery("#AD_AGB_INPUT").parent().hide();
            // Angebot / Gesuch
            jQuery('[data-label="offer"]').hide();
            jQuery('[data-label="request"]').show();
            break;
    }
}

/**
 * Validation
 */

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
            if (!obj.valid) {
                if (jQuery('#' + name + '_INPUT').length > 0) {
                    var icon_error = '<img src="'+ebiz_trader_baseurl+'bilder/stop_check.png" class="js-tooltip" data-toggle="tooltip" title="'+obj.error_msg+'"  />&nbsp;';
                    jQuery('#' + name + '_INPUT').removeClass("info").removeClass("text-success").addClass("text-danger");
                    jQuery('#' + name + '_INPUT .help-inline').html(icon_error);
					jQuery('#' + name + '_INPUT .help-inline img').tooltip();
                }
                validateInputErrorScroll(name);
            } else {
                if (jQuery('#' + name + '_INPUT').length > 0) {
                    var icon_success = '<img src="'+ebiz_trader_baseurl+'bilder/ok.png" class="js-tooltip" data-toggle="tooltip" title="Input correct or optional."  />&nbsp;';
                    jQuery('#' + name + '_INPUT').removeClass("info").removeClass("text-danger").addClass("text-success");
                    jQuery('#' + name + '_INPUT .help-inline').html(icon_success);
					jQuery('#' + name + '_INPUT .help-inline img').tooltip();
                }
            }
        }
    });
}

function validateInputErrorScroll(name) {
    //document.location.hash = "#"+name+"_INPUT";
}

function showImageVariant(articleId, imageIndex) {
    var jqModal = jQuery("#ad-create-image-variant-modal");
    var jqModalIndex = jqModal.find("input[NAME=IMAGE_INDEX]");
    var jqModalImage = jqModal.find("[data-rel=variant-image]");
    var jqImageRow = jQuery("#articleImage"+imageIndex);
    var jqModalVariantInputs = jqModal.find("select");
    if (jqImageRow.length > 0) {
        var jqButton = jQuery("#inputImageVariants"+imageIndex);
        if (jqButton.is("[data-current]")) {
            var variantSettings = JSON.parse( jqButton.attr("data-current") );
            var variantFieldName;
            for (var i = 0; i < jqModalVariantInputs.length; i++) {
                variantFieldName = jQuery(jqModalVariantInputs[i]).attr("name");
                if (typeof variantSettings[variantFieldName] != "undefined") {
                    jQuery(jqModalVariantInputs[i]).val( variantSettings[variantFieldName] );
                } else {
                    jQuery(jqModalVariantInputs[i]).val( "" );
                }
            }
        } else {
            for (var i = 0; i < jqModalVariantInputs.length; i++) {
                jQuery(jqModalVariantInputs[i]).val( "" );
            }
        }
        jqModalIndex.val(imageIndex);
        jqModalImage.attr("src", jqImageRow.find(".thumbnail > a").attr("href"));
        jqModal.modal("show");
    }
}

function submitImageVariant() {
    var jqModal = jQuery("#ad-create-image-variant-modal");
    var jqModalForm = jqModal.find("form");
    var imageIndex = jqModal.find("input[NAME=IMAGE_INDEX]").val();
    jQuery.post(jqModalForm.attr("action"), jqModalForm.serialize(), function(result) {
        jQuery("#inputImageVariants"+imageIndex+"Text").html(result.variantsText);
        jqModal.modal("hide");
    });
}

/**
 * Overlay notice
 */

function ShowLoadingNotice(text) {
    var progress = jQuery("#progressStep");
    if (progress.length > 0) {
        progress.find(".progress-bar").html(text);
        progress.fadeIn().show();
        return true;
    }
    return false;
}

function HideLoadingNotice() {
    jQuery("#progressStep").fadeOut().hide();
}
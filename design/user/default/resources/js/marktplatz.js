
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.4.8.1
 */

/**
 * Definitionen
 */

ebizDateTranslations = {
    'months': [
        '[[ translation : general : date.names.months.january :: Januar ]]',
        '[[ translation : general : date.names.months.february :: Februar ]]',
        '[[ translation : general : date.names.months.march :: März ]]',
        '[[ translation : general : date.names.months.april :: April ]]',
        '[[ translation : general : date.names.months.may :: Mai ]]',
        '[[ translation : general : date.names.months.june :: Juni ]]',
        '[[ translation : general : date.names.months.july :: Juli ]]',
        '[[ translation : general : date.names.months.august :: August ]]',
        '[[ translation : general : date.names.months.september :: September ]]',
        '[[ translation : general : date.names.months.october :: Oktober ]]',
        '[[ translation : general : date.names.months.november :: November ]]',
        '[[ translation : general : date.names.months.december :: Dezember ]]'
    ],
    'days': [
        '[[ translation : general : date.names.days.monday :: Montag ]]',
        '[[ translation : general : date.names.days.tuesday :: Dienstag ]]',
        '[[ translation : general : date.names.days.wednesday :: Mittwoch ]]',
        '[[ translation : general : date.names.days.thursday :: Donnerstag ]]',
        '[[ translation : general : date.names.days.friday :: Freitag ]]',
        '[[ translation : general : date.names.days.saturday :: Samstag ]]',
        '[[ translation : general : date.names.days.sunday :: Sonntag ]]'
    ]
};

/**
 * Initialisierung
 */

var detailed_search_mask = false;
function show_detailed_search_mask() {
    var container_search_mask = jQuery(".container-for-click-menu-item");
    var detail_suche = container_search_mask.find(".design-search-content .row .col-md-12");
    var first_child = detail_suche.children().eq(0);
    var other_child = detail_suche.splice(1,detail_suche.length);

    var all_elements = null;

    if ( detailed_search_mask == false ) {
        //detail_suche.show();
        all_elements = first_child.find(".row > div");
        console.log( all_elements, other_child );
        $.each(all_elements,function(index,obj) {
            if ( index < 3 ) {
                jQuery( obj ).show();
            }
            else {
                jQuery( obj ).hide();
            }
        });
        $.each(other_child,function(index,obj) {
            jQuery( obj ).hide();
        });
    }
    else {
        all_elements = first_child.find(".row > div");
        $.each(all_elements,function(index,obj) {
            if ( index < 3 ) {
                jQuery( obj ).hide();
            }
            else {
                jQuery( obj ).show();
            }
        });
        $.each(other_child,function(index,obj) {
            jQuery( obj ).show();
        });
    }
    detailed_search_mask = !detailed_search_mask;
}

function reset_search_mask() {
    var suchoptionen = jQuery(".suchoptionen");
    suchoptionen.find(".suchoption").remove();

    var detailed_search_form = jQuery(".container-for-click-menu-item");
    detailed_search_form.find(".c-such-option").val( "" );
    detailed_search_form.find("select[name='FK_MAN']").trigger("change");
}

jQuery(function() {

    var li_dropdown = jQuery('ul.nav li.dropdown');
    li_dropdown.find(".in-hover").hover(function(e){
        e.stopPropagation();
        //e.preventDefault();
    },function (e) {
        e.stopPropagation();
        //e.preventDefault();
    });
    /*li_dropdown.hover(function(e) {
     jQuery(this).find('.dropdown-menu').fadeIn();//.delay(200).fadeIn(500);
     }, function(e) {
     console.log( );
     if ( e.originalEvent.originalTarget.className.indexOf("in-hover") == -1 ) {
     jQuery(this).find('.dropdown-menu').fadeOut();//delay(200).fadeOut(500);
     }
     });

     li_dropdown.find("select").hover(function(e){
     e.stopPropagation();
     }, function () {

     });*/

    jQuery(".specialHideParent").each(function() {
        jQuery(this).closest("[data-hide-when-empty]").hide();
    });
    /*
     setTimeout(function() {
     shiftWindow();
     }, 1);
     jQuery(window).scroll(shiftWindow);
     */

    jQuery(".action-toggle-loginbox").click(function() {
        var link = jQuery(this);
        jQuery("#menlineLoginbox").css('top', (link.position().top + link.outerHeight()) + 'px');
        jQuery("#menlineLoginbox").css('left', (link.offset().left + link.outerWidth() - jQuery("#menlineLoginbox").width()) + 'px');

        jQuery("#menlineLoginbox").toggle(0, function() { });

        return false;
    });
    jQuery("#menlineLoginbox .close").click(function() { jQuery("#menlineLoginbox").hide(); } );


    jQuery(".presearch_quick_link").on('click', function() {
        jQuery("#SEARCH").val(jQuery(this).text());
        $('#div_offers').hide();
        $('#SEARCH_FORM').submit();
        return false;
    });
    jQuery(".js-popover").popover();
    jQuery(".js-popover-html").popover({ content: function() { return jQuery(this).find('script').html(); }, html: true });
    jQuery(".js-tooltip").tooltip();

    // Initialize scroll to top
    jQuery(".design-scroll-top").each(function() {
        var container = this;
        var link = (jQuery(container).is("a") ? jQuery(container) : jQuery(container).find("a"));
        var scrollCallback = function() {
            if (jQuery(document).scrollTop() > 150) {
                jQuery(container).show();
            } else {
                jQuery(container).hide();
            }
        };
        scrollCallback();
        link.click(function() {
            jQuery(document).scrollTop(0);
        });
        jQuery(document).scroll(scrollCallback);
    });

});

tooltip = null;
tooltips = new Array();


function setBlobText(element, text, show, title) {
    if (typeof title == "undefined") {
        title = "[[ translation : marketplace : blob.help :: Hilfe ]]";
    }
    jQuery(element).popover({ content: text, title: title });
    jQuery(element).popover( (show ? "show" : "hide") );
}

shiftHash = "";

function shiftWindow(event) {
    if (shiftHash != location.hash) {
        shiftHash = location.hash;
        scrollBy(0, -40);
    }
}

function updateCurrentPageContent() {
    // Inhalt der aktuellen Seite per AJAX aktualisieren
    jQuery.get(window.document.location.href, function(result) {
        var newContent = jQuery(result).find('#content');
        if (newContent.length > 0) {
            jQuery('#content').replaceWith( newContent );
        }
    });
}

var updateInputTimer = false;
function updateInputState(callback, timeout) {
    if(typeof timeout == "undefined") {
        timeout = 200;
    }

    var now = new Date();
    if (updateInputTimer != false) {
        window.clearTimeout(updateInputTimer);
    }
    updateInputTimer = window.setTimeout(callback, timeout);
}

function ShowDialog(url, beschreibung, width, height, configuration, ajaxConfiguration, callback) {
    if(typeof configuration == "undefined") {
        configuration = {};
    }
    if(typeof ajaxConfiguration == "undefined") {
        ajaxConfiguration = {};
    }
    if (typeof width == "undefined") {
        width = 800;
    }
    if (typeof height == "undefined") {
        height = 480;
    }

    $("#modalDialog .modal-footer").detach();


    ajaxConfiguration.url = url;
    ajaxConfiguration.success = function(response) {
        // Hide previous dialog if present
        if (!configuration.preventHide) {
            HideDialog();
        } else {
            // Remove backdrop since this wont happen without hiding
            jQuery("#modalDialog").parent().children(".modal-backdrop").remove();
        }
        // Remove previous dialog
        jQuery("#modalDialog").remove();
        // Create new dialog
        if(configuration.onlyFrame && configuration.onlyFrame == true) {
            var dialogContainer = $('<div class="modal" id="modalDialog" tabindex="-1" role="dialog">' +
                '</div>');

        } else {
            var dialogContainer = $('<div class="modal" id="modalDialog" tabindex="-1" role="dialog">' +
                '<div class="modal-dialog">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>' +
                '<h3 id="modalDialogTitle"></h3>' +
                '</div>' +
                '<div class="modal-body" id="modalDialogContent"></div>' +
                '</div>' +
                '</div>' +
                '</div>');
        }
        dialogContainer.appendTo("body");



        if(configuration.onlyFrame && configuration.onlyFrame == true) {
            if (jQuery(response).find(".modal-body").length == 0) {
                $("#modalDialog").html(
                    '<div class="modal-dialog">' +
                    '<div class="modal-content">' +
                    '<div class="modal-body" id="modalDialogContent">' + response + '</div>' +
                    '</div>' +
                    '</div>'
                );
            } else {
                $("#modalDialog").html(response);
            }
        } else {
            $("#modalDialogTitle").html(beschreibung);
            $("#modalDialogContent").html(response);

            if($("#modalDialogContent").find("#modalFooterContainer").length > 0) {
                jQuery("#modalDialog .modal-content").append(jQuery("#modalFooterContainer").detach().html());
            }
        }
        if(width != "auto") {
            $("#modalDialog .modal-dialog").width(width + 'px');
            //$("#modalDialog").css('margin', '0 0 0 ' + -(width / 2) + 'px');
        }

        if(height != "auto") {
            $("#modalDialog .modal-dialog").height(height + 'px');

            var innerHeight = height - jQuery("#modalDialog .modal-header").outerHeight(true) - jQuery("#modalDialog .modal-footer").outerHeight(true) -30;

            $("#modalDialog .modal-body").css({ 'max-height': innerHeight + 'px', 'overflow': 'auto' });
        }


		if(configuration.beforeopen) {
			$("#modalDialog").on('show.bs.modal', configuration.beforeopen);
		}
		if(configuration.open) {
			$("#modalDialog").on('shown.bs.modal', configuration.open);
		}
		if(configuration.beforeclose) {
			$("#modalDialog").on('hide.bs.modal', configuration.beforeclose);
		}
		if(configuration.close) {
			$("#modalDialog").on('hidden.bs.modal', configuration.close);
		}

        $("#modalDialog").modal(configuration);
        $("#modalDialog").modal("show");

        if (typeof callback != "undefined") {
            callback( $("#modalDialog") );
        }
    };

    $.ajax(ajaxConfiguration);
}
function HideDialog() {
    $("#modalDialog").modal("hide");
}


function ShowContentDialog(content, beschreibung, width, height, configuration) {
    if(typeof configuration == "undefined") {
        configuration = {};
    }

    if (typeof width == "undefined") {
        width = 800;
    }
    if (typeof height == "undefined") {
        height = 480;
    }

    // Hide previous dialog if present
    if (!configuration.preventHide) {
        HideDialog();
    } else {
        // Remove backdrop since this wont happen without hiding
        jQuery("#modalDialog").parent().children(".modal-backdrop").remove();
    }
    // Remove previous dialog
    jQuery("#modalDialog").remove();

    if(configuration.onlyFrame && configuration.onlyFrame == true) {
        var dialogContainer = $('<div class="modal" id="modalDialog" tabindex="-1" role="dialog">' +
            '</div>');

    } else {
        var dialogContainer = $('<div class="modal" id="modalDialog" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>' +
            '<h3 id="modalDialogTitle"></h3>' +
            '</div>' +
            '<div class="modal-body" id="modalDialogContent"></div>' +
            '</div>' +
            '</div>' +
            '</div>');
    }
    dialogContainer.appendTo("body");


    if(width != "auto") {
        $("#modalDialog .modal-dialog").width(width + 'px');
        //$("#modalDialog .modal-dialog").css('margin', '0px 0 0 '+ -(width/2) + 'px');
    }

    if(height != "auto") {
        $("#modalDialog .modal-dialog").height(height + 'px');
    }

    if(configuration.onlyFrame && configuration.onlyFrame == true) {
        $("#modalDialog").html(content);
    } else {
        $("#modalDialogTitle").html(beschreibung);
        $("#modalDialogContent").html(content);

        if($("#modalDialogContent").find("#modalFooterContainer")) {
            jQuery("#modalDialog").append(jQuery("#modalFooterContainer").html());
        }
    }
    if(configuration.beforeopen) {
        $("#modalDialog").on('show', configuration.beforeopen);
        $("#modalDialog").on('show.bs.modal', configuration.beforeopen);
    }
    if(configuration.open) {
        $("#modalDialog").on('shown', configuration.open);
        $("#modalDialog").on('shown.bs.modal', configuration.open);
    }
    if(configuration.beforeclose) {
        $("#modalDialog").on('hide', configuration.beforeclose);
        $("#modalDialog").on('hide.bs.modal', configuration.beforeclose);
    }
    if(configuration.close) {
        $("#modalDialog").on('hidden', configuration.close);
        $("#modalDialog").on('hidden.bs.modal', configuration.close);
    }

    $("#modalDialog").modal("show");
}

function SendMail(id_user, betreff, id_artikel, id_transaktion, id_order, options) {
    if (typeof options == "undefined") {
        // Default options
        options = { onlyFrame: true };
    } else {
        // Add forced options
        options.onlyFrame = true;
    }
    var urlPage = "my-neu-msg";
    if (typeof options.page != "undefined") {
        urlPage = options.page;
    }
    var url = ebiz_trader_baseurl + "index.php?page="+urlPage+"&id_user="+id_user+"&subject="+encodeURIComponent(betreff)+
        "&id_ad="+id_artikel+"&id_trans="+id_transaktion+"&id_order="+id_order+"&frame=ajax";

    ShowDialog(url, "Nachricht senden", "auto", "auto", options, {

    });
}

function openLeadCreateWindow(articleId) {
	var url = ebiz_trader_baseurl + "leads/user/leads/create/"+articleId;
	ShowDialog(url, "Lead erstellen", "auto", "auto", {
	    onlyFrame: true
    });
}

function openLeadCreateWindowMultiByForm(form) {
    var articleIds = [];
    jQuery(form).find("input:checked").each(function() {
        articleIds.push( jQuery(this).val() );
    });
    openLeadCreateWindowMulti(articleIds);
}

function openLeadCreateWindowMulti(articleIds) {
    openLeadCreateWindow(articleIds.join("-"));
}

function openAdsRatingWindow(adSoldId, page) {
    if (typeof page == "undefined") {
        page = "my-marktplatz-rating";
    }
    ShowDialog(page+","+adSoldId+".htm?frame=ajax", "Bewerten", 640, "auto");
}

/**
 * Initialisiert den "Produkt merken"-Button einer anzeige
 * @param id_ad		ID der Anzeige
 * @param what		Schmalle fragen / ad_reminder.php ansehen
 * @param elm		Schmalle fragen / ad_reminder.php ansehen
 */
function ad_reminder(id_ad, what, elm)
{
    var id_kat = 0;
    var element = (typeof elm == 'undefined' ? '' : elm);
    var id = 'ad_reminder_'+id_ad+element;
    $.ajax({
        url: 		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&ID_AD=' + id_ad + '&ID_KAT=' + id_kat + '&what=' + what+'&elm='+element,
        type: 		'GET',
        success: 	function(result) {
            $('#'+id).html(result);
        }
    });
    return false;
}



/**
 * Wechselst zwischen Anzeige gefällt mir und Anzeige gefällt mir nicht mehr
 * @param adId
 */
function toggleAdLike(adId, callback) {
    $.ajax({
        url: ebiz_trader_baseurl + 'marktplatz/marktplatz_anzeige_gefallen,'+adId+',toggle.htm?frame=ajax',
        dataType: 'json',
        success: function(response) {
            if(response.success == true) {
                callback.call(this, response)
            }
        }
    });
}
function getAdLikeButton(adId, button) {
    $.ajax({
        url: ebiz_trader_baseurl + 'marktplatz/marktplatz_anzeige_gefallen,'+adId+',get.htm?frame=ajax',
        dataType: 'json',
        success: function(response) {
            if(response.success == true) {
                var buttonText = jQuery(button).children("span").first();
                if (buttonText.length == 0) {
                    buttonText = button;
                }
                if(response.like == true) {
                    $(buttonText).html("[[ translation : marketplace : like.action.dislike :: Anzeige gefällt mir doch nicht ]]");
                } else {
                    $(buttonText).html("[[ translation : marketplace : like.action :: Anzeige gefällt mir ]]");
                }
            }
        }
    });
}

function getAdLikeCount(adId, callback) {
    $.ajax({
        url: ebiz_trader_baseurl + 'marktplatz/marktplatz_anzeige_gefallen,'+adId+',count.htm?frame=ajax',
        dataType: 'json',
        success: function(response) {
            if(response.success == true) {
                callback.call(this, response)
            }
        }
    });
}

function acceptOrder(orderId, callback) {
    $.ajax({
        url: ebiz_trader_baseurl + 'index.php?page=sale_handle&scope=order&do=accept&ID_AD_ORDER='+orderId+'&frame=ajax',
        dataType: 'json',
        success: callback
    });
}

function declineOrder(orderId, callback) {
    $.ajax({
        url: ebiz_trader_baseurl + 'index.php?page=sale_handle&scope=order&do=decline&ID_AD_ORDER='+orderId+'&frame=ajax',
        dataType: 'json',
        success: callback
    });

}

function verkauf_bestaetigen(adId, sellId, confirm) {
    var url = 'sale_handle,'+adId+','+sellId+',accept,'+(confirm ? 1 : 0)+'.htm';
    ShowDialog(url, '[[ translation : marketplace : sale.action.accept :: Verkauf akzeptieren ]]', 580, 480, {}, {}, function(dialog) {
        show_details(sellId);
        show_details(sellId);
    });
}

function verkauf_bestaetigen_batch(adId, confirm) {
    var url = 'sale_handle,'+adId+',,accept_batch,'+(confirm ? 1 : 0)+'.htm';
    ShowDialog(url, '[[ translation : marketplace : sales.action.accept :: Verkäufe akzeptieren ]]', 580, 480, {}, {}, function(dialog) {
        show_details(sellId);
        show_details(sellId);
    });
}

function verkauf_bestaetigen_post(adId, sellId) {
    if (confirm('[[ translation : marketplace : sales.question.confirm :: Verkauf wirklich bestätigen? ]]')) {
        if (typeof sellId == "undefined") {
            // Batch
            verkauf_bestaetigen_batch(adId, 1);
        } else {
            // Einzeln
            verkauf_bestaetigen(adId, sellId, 1);
        }
        show_details(sellId);
        show_details(sellId);
    }
}

function verkauf_ablehnen(adId, sellId, confirm, reason_text, disable_ad) {
    var url = 'sale_handle,'+adId+','+sellId+',decline,'+(confirm ? 1 : 0)+'.htm';
    if (confirm) {
        $.post(url, { reason: reason_text, disable: disable_ad }, function(html_ajax) {
            ShowContentDialog(html_ajax, '[[ translation : marketplace : sale.action.decline :: Verkauf ablehnen ]]', 580, 480);
        });
    } else {
        ShowDialog(url, '[[ translation : marketplace : sale.action.decline :: Verkauf ablehnen ]]', 580, 480, { });
    }
}

function verkauf_ablehnen_batch(adId, confirm, reason_text, disable_ad) {
    var url = 'sale_handle,'+adId+',,decline_batch,'+(confirm ? 1 : 0)+'.htm';
    if (confirm) {
        $.post(url, { reason: reason_text, disable: disable_ad }, function(html_ajax) {
            ShowContentDialog(html_ajax, '[[ translation : marketplace : sale.action.decline :: Verkauf ablehnen ]]', 580, 480);
        });
    } else {
        ShowDialog(url, '[[ translation : marketplace : sale.action.decline :: Verkauf ablehnen ]]', 580, 480, { });
    }
}

function verkauf_ablehnen_post(adId, sellId) {
    if (confirm('[[ translation : marketplace : sales.question.decline :: Verkauf wirklich ablehnen? ]]')) {
        var id_reason = $("input[name=reason]:checked").attr("id");
        var disable = $("input[name=disable]:checked").length;
        var reason = "";
        if (id_reason == "reason_other") {
            reason = $("#reason_custom").val();
        } else {
            reason = $("#"+id_reason).val();
        }
        if (typeof sellId == "undefined") {
            // Batch
            verkauf_ablehnen_batch(adId, 1, reason, disable);
        } else {
            // Einzeln
            verkauf_ablehnen(adId, sellId, 1, reason, disable);
        }
    }
}

function YoutubeCheckInput(input) {
    var name = $(input).attr("name");
    var validation = $("#youtube_"+name).closest(".input-group.youtube");
    var validation_img = $("#youtube_"+name+" > img");
    var submit_button = $(validation).find("input[type=submit]");
    if (validation.length > 0) {
        var url = $(input).val();
        if (url.length > 0) {
            var matches = false;
            if (url.match(/youtu.be\/([A-Za-z0-9-_]+)(\&|$)/gi)) matches = true;
            if (url.match(/youtube.com\/watch\?.*v=([A-Za-z0-9-_]+)(\&|$)/gi)) matches = true;
            if (matches) {
                validation.attr("data-original-title", "[[ translation : marketplace : youtube.valid :: Youtube-Link ist korrekt. ]]");
                validation_img.attr("src", ebiz_trader_baseurl + "bilder/ok.png");
                submit_button.attr("disabled", false);
            } else {
                validation.attr("data-original-title", "[[ translation : marketplace : youtube.invalid :: Ist kein (gültiger) Youtube-Link! ]]");
                validation_img.attr("src", ebiz_trader_baseurl + "bilder/stop_check.png");
                submit_button.attr("disabled", true);
            }
        } else {
            validation.attr("title", "[[ translation : marketplace : youtube.no.input :: Keine Eingabe. ]]");
            validation_img.attr("src", ebiz_trader_baseurl + "bilder/ok.png");
            submit_button.attr("disabled", true);
        }
    }
}

function YoutubePlayVideo(href, width, height) {
    var width = (typeof width == "undefined" ? 400 : width);
    var height = (typeof height == "undefined" ? 300 : height);

    if($("#dialog").length == 0) {
        var dialogContainer = $('<div id="dialog" style="display: none"></div>');
        dialogContainer.insertAfter("body");
    }

    ShowContentDialog(
        '<iframe width="'+width+'" height="'+height+'" src="'+href+'" frameborder="0" allowfullscreen></iframe>',
        '[[ translation : marketplace : ad.video.view :: Video ansehen ]]',
        width + 30, height, {
            close: function() {
                $("#modalDialog iframe").attr("src", "about:blank");
            }
        }
    );
    return;

    $("#dialog").html('<iframe width="'+width+'" height="'+height+'" src="'+href+'" frameborder="0" allowfullscreen></iframe>');
    $("#dialog").dialog({
        width: width + 41,
        height: height + 71,
        modal: true,
        resizable: false,
        draggable: false,
        autoOpen: true,
        stack: true,
        close: function(event, ui) {
            $(this).html("");
        }
    }).parent().show('scale', { percent: 100 }, 500, function() {
        $("#dialog iframe").removeAttr("style");
    });
    return false;
}

function TopAd(id_ad, runtime, bf_options) {
    if (typeof runtime == "undefined") {	// Optionaler parameter - Default Wert
        runtime = "";
    }
    if (typeof bf_options == "undefined") {	// Optionaler parameter - Default Wert
        bf_options = 0;
    }
    ShowDialog(ebiz_trader_baseurl + "my-ad-top,"+id_ad+","+runtime+","+bf_options+".htm", "[[ translation : marketplace : ad.top.book :: Top-Anzeige buchen ]]", 600, 480, {}, {}, function(dialog) {
        jQuery("#success").val(getRelativeLocation());
        TopAdUpdate();
    });
}

function AdToggleComments(id_ad) {
    jQuery.post(ebiz_trader_baseurl + "my-marktplatz.htm", {
        'action': 'toggleComments',
        'idAd': id_ad
    }, function(result) {
        if (result.success) {
            var dropdown = jQuery("#comment_ad_"+id_ad);
            if (result.enabled) {
                dropdown.find(".btn").addClass("btn-info");
                dropdown.find(".activeOnDisabledComments").hide();
                dropdown.find(".activeOnEnabledComments").show();
                dropdown.find(".icon-comment").addClass("icon-white");
            } else {
                dropdown.find(".btn").removeClass("btn-info");
                dropdown.find(".activeOnEnabledComments").hide();
                dropdown.find(".activeOnDisabledComments").show();
                dropdown.find(".icon-comment").removeClass("icon-white");
            }
        }
    });
}

function ClubToggleComments(id_club) {
    jQuery.post(ebiz_trader_baseurl + "my-club.htm", {
        'action': 'toggleComments',
        'idClub': id_club
    }, function(result) {
        if (result.success) {
            var dropdown = jQuery("#comment_club_"+id_club).parent();
            if (result.enabled) {
                jQuery("#comment_club_"+id_club).addClass("btn-info");
                dropdown.find(".activeOnDisabledComments").hide();
                dropdown.find(".activeOnEnabledComments").show();
                dropdown.find(".icon-comment").addClass("icon-white");
            } else {
                jQuery("#comment_club_"+id_club).removeClass("btn-info");
                dropdown.find(".activeOnEnabledComments").hide();
                dropdown.find(".activeOnDisabledComments").show();
                dropdown.find(".icon-comment").removeClass("icon-white");
            }
        }
    });
}

function CalendarEventToggleComments(id_calendar_event) {
    jQuery.post(ebiz_trader_baseurl + "my-calendar-events-add.htm", {
        'DO': 'toggle_comments',
        'ID_CALENDAR_EVENT': id_calendar_event
    }, function(result) {
        if (result.success) {
            var dropdown = jQuery("#comment_calendar_event_"+id_calendar_event).parent();
            if (result.enabled) {
                jQuery("#comment_calendar_event_"+id_calendar_event).addClass("btn-info");
                dropdown.find(".activeOnDisabledComments").hide();
                dropdown.find(".activeOnEnabledComments").show();
                dropdown.find(".icon-comment").addClass("icon-white");
            } else {
                jQuery("#comment_calendar_event_"+id_calendar_event).removeClass("btn-info");
                dropdown.find(".activeOnEnabledComments").hide();
                dropdown.find(".activeOnDisabledComments").show();
                dropdown.find(".icon-comment").removeClass("icon-white");
            }
        }
    });
}

function TopAdUpdate() {
    var check_count = jQuery("#modalDialog input[type=checkbox]").length;
    var checked_count = jQuery("#modalDialog input[type=checkbox]:checked:not(:disabled)").length;
    var runtime = jQuery("#modalDialog select[name=LU_LAUFZEIT_T]").val();
    if (((check_count == 0) || (checked_count > 0)) && (runtime > 0)) {
        jQuery("#modalDialog input[type=submit]").attr("disabled", false);
    } else {
        jQuery("#modalDialog input[type=submit]").attr("disabled", true);
    }
}

function TopAdRuntime(runtime) {
    var id_ad = jQuery("#FK_TARGET").val();
    if (id_ad > 0) {
        var bf_options = 0;
        var optionsChecked = jQuery("#modalDialog input[type=checkbox]:checked:not(:disabled)");
        for (var i = 0; i < optionsChecked.length; i++) {
            bf_options += parseInt( jQuery(optionsChecked[i]).val() );
        }
        TopAd(id_ad, runtime, bf_options);
    } else {
        alert("[[ translation : marketplace : ad.top.error.runtime :: Fehler beim ueberpruefen der Laufzeit! Bitte wenden Sie sich an den Administrator. ]]");
    }
}

function getRelativeLocation() {
    var urlAbs = document.location.href;
    return urlAbs.replace(/http\:\/\/[^\/]+/, "");
}

function StepsHideAllInput(parent) {
    jQuery((typeof parent == "undefined" ? "" : parent+" ")+".input-step").hide();
}

function StepsShow(index, parent) {
    // Fortschrittsbalken updaten
    var bars = jQuery((typeof parent == "undefined" ? "" : parent+" ")+".progress .progress-bar");
    for (var i = 0; i < bars.length; i++) {
        var current = jQuery(bars[i]);
        var current_index = i + 1;
        var current_desc = jQuery((typeof parent == "undefined" ? "" : parent+" ")+".progress-description .progress-desc-"+current_index);
        current.removeClass("progress-bar-success").removeClass("progress-bar-warning").removeClass("progress-bar-danger");
        current_desc.removeClass("done").removeClass("active").removeClass("pending");
        if (current_index < index) {
            current.addClass("progress-bar-success")			// Done
            current_desc.addClass("done");
        } else if (current_index == index) {
            current.addClass("progress-bar-warning")			// Active!
            current_desc.addClass("active");
        } else {
            current.addClass("progress-bar-danger");		// Pending
            current_desc.addClass("pending");
        }
    }
    // Neuen Eingabebereich einblenden
    var input = jQuery((typeof parent == "undefined" ? "" : parent+" ")+".input-step-"+index);
    StepsHideAllInput(parent);
    input.show();
}


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
            $('#div_offers').hide();
            $('#SEARCH_FORM').submit();
            return false;
        }
        if (key == 27) {
            // Escape
            window.setTimeout(function(){
                $('#div_offers').hide();
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
            } else {
                rows[row].className = "";
            }
        });
        $('#SEARCH').val(text_element.value);
        return;
    }
    $('#SEARCH').val(text);

    // Hack for reducing request count
    if (!do_search && (text.length >= 3)) {
        window.clearTimeout(timer_qs);
        timer_qs = window.setTimeout(function () {
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
                if (obj.fail || (obj.offers.length <= 0)) {
                    $('#div_offers').hide();
                } else {
                    var table = document.createElement("table");
                    table.id = "list_offers";
                    table.className = "list_offers";
                    table.cellPadding = 0;
                    table.cellSpacing = 0;
                    table.width = "100%";
                    $('#div_offers .popover-content').html("").append(table);

                    row_count = 0;
                    row_list = new Array();

                    $.each(obj.offers, function(offer, val) {
                        var text = obj.offers[offer];
                        var row = document.createElement("tr");
                        var col = document.createElement("td");
                        var link = document.createElement("a");
                        // Click event
                        link.href = "#search:"+text;
                        link.className = "presearch_quick_link";
                        jQuery(link).click(function() {
                            jQuery('#SEARCH').val(text);
                            jQuery('#sfeld1').val(text);
                            jQuery('#SEARCH_FORM').submit();
                            return false;
                        });
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
                    $('#div_offers').css({
                        'position':		"absolute",
                        'left':			(element_pos.left - 12)+"px",
                        'top':			(element_pos.top + 30)+"px",
                        'min-width':	$(text_element).outerWidth()
                    }).show().children(".popover-content").css({
                        'max-height':	"128px",
                        'overflow':		"auto"
                    });
                }
            }
        });
    } else {
        $('#div_offers').hide();
    }

    return;
}

function searchVendorByText(search_text) {
    jQuery.ajax({
        url: ebiz_trader_baseurl + "index.php",
        type: 'POST',
        data: {
            page: 			'presearch_vendor_ajax',
            frame: 			'ajax',
            SEARCHVENDOR:	search_text
        },
        dataType: 'json',
        success: function(response) {
            var url = ebiz_trader_baseurl + 'anbieter/';
            if (response["COUNT"] > 0) {
                url = ebiz_trader_baseurl + 'anbieter/anbieter,,'+response["HASH"]+'.htm';
            }
            document.location.href = url;
        }
    });
}

function searchVendorExtraDetail(search_key,search_text) {
    var dataVars = new Object();
    //dataVars['page'] = 'presearch_vendor_ajax';
    //dataVars['frame'] = 'ajax';
    dataVars[search_key] = search_text;

    jQuery.ajax({
        url: ebiz_trader_baseurl + "index.php",
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(dataVars),
        dataType: 'json',
        success: function(response) {
            var url = ebiz_trader_baseurl + 'anbieter/';
            if (response["COUNT"] > 0) {
                url = ebiz_trader_baseurl + 'anbieter/anbieter,,'+response["HASH"]+'.htm';
            }
            document.location.href = url;
        }
    });
}

function searchClubByText(search_text) {
    jQuery.ajax({
        url: ebiz_trader_baseurl + "index.php",
        type: 'POST',
        data: {
            page: 		'presearch_club_ajax',
            frame: 		'ajax',
            SEARCHCLUB:	search_text
        },
        dataType: 'json',
        success: function(response) {
            var url = ebiz_trader_baseurl + 'groups/';
            if (response["COUNT"] > 0) {
                url = ebiz_trader_baseurl + 'groups,,'+response["HASH"]+'.htm';
            }
            document.location.href = url;
        }
    });
}

/*
 * Google map
 */

map_loaded = false;

function showMap(self, container){
//    if (container.is(":visible")) {
//    	jQuery(self).html('Karte anzeigen');
//        container.hide();
//    } else {
//        jQuery(self).html('Karte ausblenden');
//        container.show();
    if (!map_loaded) {
        map_loaded = true;

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
            latlng = event.latLng;
            if (latlng != null) {
                address = latlng;
                geocoder.geocode( { 'latLng': latlng }, showPosition);
            }
        });
        geocoder = new google.maps.Geocoder();
    }
//    }
}

function updateMapCenter() {
    var latlng = new google.maps.LatLng(jQuery("#LATITUDE").val(), jQuery("#LONGITUDE").val());
    map.setCenter(latlng);
}

/** Cart **/
function CartAddArticle(adId, quantity, adVariantId) {
    if (typeof quantity == "undefined") {
        quantity = 1;
    }
    if (typeof adVariantId == "undefined") {
        adVariantId = 0;
    }

    jQuery.ajax({
        url:ebiz_trader_baseurl + "marktplatz/cart.htm",
        type:'POST',
        data:{ 'ID_AD':adId, 'ID_AD_VARIANT': adVariantId, 'QUANTITY':quantity, 'DO':'ADD' },
        dataType:'json',
        success:function (response) {
            if (response.success == true) {
                if (typeof response.status != "undefined") {
                    jQuery("#ShoppingCartWidgetCountItems").text(response.status.cartItemCount);
                    jQuery("#ShoppingCartWidgetTotalPrice").text(response.status.cartTotalPrice);
                }

                ShowDialog(ebiz_trader_baseurl + "marktplatz/cart_item_status," + adId + "," + adVariantId + ".htm", "[[ translation : marketplace : ad.cart.item.added :: Der Artikel wurde in den Warenkorb gelegt ]]", "auto", "auto");
            } else {
                if(response.err == 'mengelessthanquantity') {
                    alert("[[ translation : marketplace : ad.cart.error.quantity : COUNT='"+response.maxQuantity+"' : Es sind leider nur noch {COUNT} Stück übrig. Bitte reduzieren Sie die gewünschte Menge. ]]")
                }
            }
        }
    })
}

function CartRemoveArticle(adId, idAdVariant, callback) {
    jQuery.ajax({
        url:ebiz_trader_baseurl + "marktplatz/cart.htm",
        type:'POST',
        data:{ 'ID_AD':adId, 'ID_AD_VARIANT': idAdVariant, 'DO':'REMOVE' },
        dataType:'json',
        success:function (response) {
            if (response.success == true) {
                var numberOfArticles = parseInt(jQuery("#ShoppingCartWidgetCountItems").text());
                numberOfArticles -= 1;
                jQuery("#ShoppingCartWidgetCountItems").text(numberOfArticles);

                if(typeof callback != "undefined") {
                    callback.call(this, adId, response);
                }
            }
        }
    })
}

function AdReminderLoad(adId, elementId, inReminderText, outReminderText) {
    jQuery.ajax({
        url: 		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&ID_AD=' + adId + '&DO=LOAD',
        type: 		'GET',
        dataType:   'json',
        success: 	function(result) {
            if(result.status) {
                jQuery('#'+elementId).html(inReminderText).attr('data-reminder', '1');
            } else {
                jQuery('#'+elementId).html(outReminderText).attr('data-reminder', '0');
            }


        }
    });
    return false;
}

function AdReminderToggle(adId, elementId, inReminderText, outReminderText, adTitle, adUrl) {
    if(jQuery('#'+elementId).attr('data-reminder') == '0') {
        Watchlist_addItem(adUrl, 'ad_master', adId, adTitle, null, {
            close: function() {
                AdReminderLoad(adId, elementId, inReminderText, outReminderText);
            }
        });
    } else {
        jQuery.ajax({
            url: 		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&ID_AD=' + adId + '&DO=TOGGLE',
            type: 		'GET',
            dataType:   'json',
            success: 	function(result) {
                if(result.status) {
                    jQuery('#'+elementId).html(inReminderText).attr('data-reminder', '1');
                } else {
                    jQuery('#'+elementId).html(outReminderText).attr('data-reminder', '0');
                }


            }
        });
    }

    return false;
}

function LinkReminderToggle( ptr, pagetitle, url ) {
    if ( jQuery(ptr).attr("data-reminder") == "0" ) {
        Watchlist_addItem(url, 'normal', null, pagetitle, null, {
            close: function() {
                LinkReminderLoad(url);
            }
        });
    }
    else {
        jQuery.ajax({
			url:		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&DO=TOGGLE&type=normal&URL='+url,
			type:		'GET',
			dataType:	'json',
			success:	function(result) {
				if (result.status) {
                    jQuery(ptr).html(
                    	jQuery(ptr).data("out_reminder_text")
					);
                    jQuery(ptr).attr('data-reminder','1');
				} else {
                    jQuery(ptr).html(
                    	jQuery(ptr).data("in_reminder_text")
					);
                    jQuery(ptr).attr('data-reminder','0');
				}
				window.location.reload();
			}
		});
    }
}

function LinkReminderLoad( linkUrl ) {
	if ( typeof linkUrl == "undefined" ) {
        var favorite_link = jQuery(".favorite-link");
        jQuery.each(favorite_link,function(index,item) {
            jQuery.ajax({
                url: 		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&DO=LOAD&type=normal&URL='+jQuery(item).data("url"),
                type: 		'GET',
                dataType:   'json',
                success: 	function(result) {
                    if(result.status) {
                        jQuery(item).attr("title", jQuery(item).data("out_reminder_text"));
                        jQuery(item).attr('data-reminder','1');
                    } else {
                        jQuery(item).attr("title", jQuery(item).data("in_reminder_text"));
                        jQuery(item).attr('data-reminder','0');
                    }
                }
            });
        });
	}
	else {
        jQuery.ajax({
            url: 		ebiz_trader_baseurl + 'index.php?frame=ajax&page=ad_reminder&DO=LOAD&type=normal&URL='+linkUrl,
            type: 		'GET',
            dataType:   'json',
            success: 	function(result) {
                var element_obj = jQuery("a[data-url='"+linkUrl+"']");
                if(result.status) {
                    element_obj.attr("title", element_obj.data("out_reminder_text"));
                    element_obj.attr('data-reminder','1');
                } else {
                    element_obj.attr("title", element_obj.data("in_reminder_text"));
                    element_obj.attr('data-reminder','0');
                }
                window.location.reload();
            }
        });
	}
}

function sendAdContactMessage(adId, katId, adVariantId, dialogTitle) {
    if (typeof dialogTitle == "undefined") {
        dialogTitle = "[[ translation : marketplace : contact :: Kontakt aufnehmen ]]";
    }
    ShowDialog(ebiz_trader_baseurl + 'index.php?page=marktplatz_kontakt&ID_AD='+adId+'&ID_AD_VARIANT='+adVariantId+'&ID_KAT='+katId+'&frame=ajax', dialogTitle, "auto", "auto");

}

function sendAdOfferMessage(adId, katId, adQuantity, adVariantId, dialogTitle) {
    if (typeof dialogTitle == "undefined") {
        dialogTitle = "[[ translation : marketplace : contact :: Kontakt aufnehmen ]]";
    }
    ShowDialog(ebiz_trader_baseurl + 'index.php?page=marktplatz_kontakt&OFFER_QTY='+adQuantity+'&ID_AD='+adId+'&ID_AD_VARIANT='+adVariantId+'&ID_KAT='+katId+'&frame=ajax', dialogTitle, "auto", "auto");

}

function ExtendAd(id_ad, id_kat, overrideConfig) {
    var config = jQuery.extend({
        close: function() { location.reload(); }
    }, overrideConfig);

    ShowDialog(ebiz_trader_baseurl + "my-pages/my-marktplatz-extend,"+id_ad+","+id_kat+".htm", "[[ translation : marketplace : ad.extend :: Anzeige verlängern ]]", 600, "auto", {modal: true, close: config.close }, {}, function(dialog) {

    });
}

function ExtendSearchResults(button) {
    var form = jQuery(button).closest("form");
    var config = { modal: true, close: function() { location.reload(); } };
    var ajaxConfig = { type: "POST", data: form.serialize() };

    ShowDialog(ebiz_trader_baseurl + "my-pages/my-marktplatz-extend-multiple.htm", "[[ translation : marketplace : ads.extend :: Anzeigen verlängern ]]", 600, "auto", config, ajaxConfig, function(dialog) {

    });
}

function ExtendAdSubmit(form) {
    jQuery.ajax({
        url: jQuery(form).attr("action"),
        data: jQuery(form).serialize(),
        type: 'POST',
        success: function(result) {
            jQuery("#modalDialogContent").html(result);
        }
    });

    return false;
}

function commentSubmit(form) {
    var table = jQuery(form).find("input[name=TABLE]").val();
    var fk = 0;
    if (jQuery(form).find("input[name=FK]").length == 0) {
        fk = jQuery(form).find("input[name=FK_STR]").val();
    } else {
        fk = jQuery(form).find("input[name=FK]").val();
    }
    jQuery.ajax({
        url:		jQuery(form).attr("action"),
        data:		jQuery(form).serialize(),
        type:		'POST',
        success:	function(result) {
            var formResult = jQuery(result).find("#comment_"+table+"_"+fk);
            if (formResult.length > 0) {
                jQuery("#comment_"+table+"_"+fk).replaceWith(formResult);
                commentListUpdate(table, fk);
                commentRatingsUpdate(table, fk);
            }
        }
    });
    return false;
}

function commentListUpdate(table, fk, page) {
    var idList = "#comment_list_"+table+"_"+fk;
    var divList = jQuery(idList);
    if (divList.length > 0) {
        var url = divList.attr("data-url");
        if (typeof page != "undefined") {
            url = url.replace('.htm', ','+page+'.htm');
        }
        jQuery.get(url, function(result) {
            divList.replaceWith(result);
            commentListInitPager(table, fk);
        });
        commentListInitPager(table, fk);
    }
}

function commentListInitPager(table, fk) {
    var idList = "#comment_list_"+table+"_"+fk;
    var divList = jQuery(idList);
    if (divList.length > 0) {
        divList.find('.pagination a').click(function(link) {
            var url = jQuery(this).attr('href');
            jQuery.get(url, function(result) {
                divList.replaceWith(result);
                commentListInitPager(table, fk);
            });
            return false;
        });
    }
}

function commentRatingsUpdate(table, fk, template) {
    var selector = "[data-content=comment_ratings]";
    if (typeof fk != "undefined") {
        selector += "[data-id="+fk+"]";
    }
    if (typeof table != "undefined") {
        selector += "[data-table="+table+"]";
    }
    if (typeof template != "undefined") {
        selector += "[data-template="+template+"]";
    }
    commentRatingsUpdateBySelector(selector);
}

function commentRatingsUpdateBySelector(selector) {
    jQuery(selector).each(function() {
        var element = jQuery(this);
        var targetId = element.data("id");
        var targetIdStr = element.data("id-str");
        var targetTable = element.data("table");
        var showEmptyBars = element.data("show-empty-bars");
        var template = element.data("template");
        jQuery.post(ebiz_trader_baseurl+"system/comment_ratings.htm", {
            "FK": targetId, "FK_STR": targetIdStr, "TABLE": targetTable, "TEMPLATE": template, "SHOW_EMPTY_BARS": showEmptyBars
        }, function(result) {
            element.replaceWith( jQuery(result).find("[data-content=comment_ratings]") );
        });
    });
}

function ShowCalendar(arAds) {
    var url = ebiz_trader_baseurl + 'my-pages/ad_availability_calendar.htm';
    var arData = {};
    if (typeof(arAds) != "undefined") {
        arData.ads = arProjects;
    }
    /* Base-Config: */
    var config = {
        classes: 'modalCalendar',
        open: function() {
            // Remove size limits
            jQuery('#modalDialog').css({'width': 'auto', 'height': 'auto', 'margin': 0});
            jQuery('#modalDialog .modal-body').css({'max-height': 'none'});
            // Update calendar
            jQuery('#availability_calendar').fullCalendar('render');
        }
    };
    /* Ajax-Config: */
    var configAjax = {
        type: 'POST',
        data: arData
    };
    /* Send request and show dialog */
    ShowDialog(url, "[[ translation : marketplace : calendar :: Kalender ]]", 800, 480, config, configAjax);
}

function AvailabilityRequestCancel(button) {
    var rowRange = jQuery(button).parents('.control-group');
    rowRange.find('.range-input').hide();
    rowRange.find('.range-value').show();
}

function AvailabilityRequestSubmit(button) {
    var form = jQuery(button).parents('form');
    var adId = form.find("input[name='FK_AD']").val();
    var adVariantId = form.find("input[name='variant']").val();
    var quantity = form.find("input[name='quantity']").val();
    var dateFrom = form.find("input[name='date_from']").val();
    var timeFrom = form.find("input[name='time_from']").val();
    var dateTo = form.find("input[name='date_to']").val();

    jQuery('#modalAddCartWithAvailability').modal('hide');
    jQuery.ajax({
        url:ebiz_trader_baseurl + "marktplatz/cart.htm",
        type:'POST',
        data:{ 'ID_AD':adId, 'ID_AD_VARIANT': adVariantId, 'QUANTITY':quantity, 'AVAILABILITY':{ 'DATE_FROM': dateFrom, 'TIME_FROM': timeFrom, 'DATE_TO': dateTo }, 'DO':'ADD' },
        dataType:'json',
        success:function (response) {
            if (response.success == true) {
                if (response.status && response.status.isNewItem) {
                    var numberOfArticles = parseInt(jQuery("#ShoppingCartWidgetCountItems").text());
                    numberOfArticles += 1;
                    jQuery("#ShoppingCartWidgetCountItems").text(numberOfArticles);
                }

                ShowDialog(ebiz_trader_baseurl + "marktplatz/cart_item_status," + adId + "," + adVariantId + ".htm", "Der Artikel wurde in den Warenkorb gelegt", "auto", "auto");
            }
        }
    });
}

function AvailabilityUpdateCheck(checkbox) {
    var checked = jQuery(checkbox).prop("checked");
    if (checked) {
        jQuery("#AVAILABILITY_INPUT").show();
    } else {
        jQuery("#AVAILABILITY_INPUT").hide();
        jQuery("#AVAILABILITY_DATES").html("");
    }
}

function AvailabilityRefresh() {
    var id_ad = jQuery("#ID_AD").val();
    if (typeof LoadingStop == "function") {
        LoadingStop();
    }
    var calendar = jQuery('#availability .calendar').html("").fullCalendar({
        timeFormat: 'H:mm{ - H:mm}', // 24h format
        defaultView: 'agendaWeek',
        editable: true,
        events: ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=availability&ID_AD='+id_ad,
        eventClick: function(calEvent, jsEvent, view) {
            AvailabilityEventEdit(calEvent.id, calEvent.start, calEvent.end, calEvent.title, calEvent.amount);
        },
        eventDrop: function(event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view) {
            var url = ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=availability_event_move';
            jQuery.post(url, {
                // Post parameters
                id: event.id,
                FK_AD: id_ad,
                deltas: {
                    days: dayDelta,
                    minutes: minuteDelta
                }
            }, function(result) {
                // Result callback
                if (!result.success) {
                    revertFunc();
                }
            });
        },
        eventResize: function(event, dayDelta, minuteDelta, revertFunc) {
            var url = ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=availability_event_resize';
            jQuery.post(url, {
                // Post parameters
                id: event.id,
                FK_AD: id_ad,
                deltas: {
                    days: dayDelta,
                    minutes: minuteDelta
                }
            }, function(result) {
                // Result callback
                if (!result.success) {
                    revertFunc();
                }
            });
        },
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        selectable: true,
        selectHelper: true,
        select: function(start, end, allDay) {
            AvailabilityEventCreate(start, end, allDay);
            calendar.fullCalendar('unselect');
        }
    });
}

function AvailabilityEventCreate(start, end, allDay, amount) {
    var id_ad = jQuery("#ID_AD").val();
    var title = jQuery("#EVENT_TITLE").val();
    var amountEvent = jQuery("#EVENT_AMOUNT").val();
    var dateStart = new Date(start);
    var dateEnd = new Date(end);
    var form = jQuery("#modalAvailabilityEventCreate form");
    form.submit(function(e) {
        e.preventDefault();
        AvailabilityEventSubmit()
    });
    form.find("input[name='id']").val("");
    form.find("input[name='FK_AD']").val(id_ad);
    form.find("input[name='title']").val( (typeof title == "undefined" ? "" : title) );
    form.find("input[name='amount']").val( (typeof amount == "undefined" ? (typeof amountEvent == "undefined" ? 1 : amountEvent) : amount) );
    form.find("input[name='from']").val(dateStart.getTime());
    form.find("input[name='to']").val(dateEnd.getTime());
    form.find(".value-date-from").html(dateStart.toLocaleString());
    form.find(".value-date-to").html(dateEnd.toLocaleString());
    form.find(".btn-edit").hide();
    form.find(".btn-new").show();
    jQuery("#modalAvailabilityEventCreate").modal("show");
}

function AvailabilityEventEdit(id, start, end, title, amount) {
    var id_ad = jQuery("#ID_AD").val();
    var dateStart = new Date(start);
    var dateEnd = new Date(end);
    var form = jQuery("#modalAvailabilityEventCreate form");
    form.submit(function(e) {
        e.preventDefault();
        AvailabilityEventSubmit()
    });
    form.find("input[name='id']").val(id);
    form.find("input[name='FK_AD']").val(id_ad);
    form.find("input[name='title']").val(title);
    form.find("input[name='amount']").val(amount);
    form.find("input[name='from']").val(dateStart.getTime());
    form.find("input[name='to']").val(dateEnd.getTime());
    form.find(".value-date-from").html(dateStart.toLocaleString());
    form.find(".value-date-to").html(dateEnd.toLocaleString());
    form.find(".btn-new").hide();
    form.find(".btn-edit").show();
    jQuery("#modalAvailabilityEventCreate").modal("show");
}

function AvailabilityEventCancel() {
    jQuery("#modalAvailabilityEventCreate").modal("hide");
}

function AvailabilityEventSubmit() {
    var form = jQuery("#modalAvailabilityEventCreate form");
    var id = form.find("input[name='id']").val();
    var title = form.find("input[name='title']").val();
    var dateStart = parseInt( form.find("input[name='from']").val() );
    var dateEnd = parseInt( form.find("input[name='to']").val() );
    var url = form.attr("action");
    jQuery.post(url, form.serialize(), function(result) {
        if (result.success) {
            jQuery("#modalAvailabilityEventCreate").modal("hide");
            if (!parseInt(id)) {
                jQuery('#availability .calendar').fullCalendar('renderEvent', {
                        id: result.id,
                        className: 'event',
                        title: title,
                        start: new Date(dateStart),
                        end: new Date(dateEnd),
                        allDay: false
                    },
                    true // make the event "stick"
                );
            }
        }
    });
}

function AvailabilityEventDelete() {
    var url = ebiz_trader_baseurl + 'index.php?page=my-marktplatz-neu&mode=ajax&do=availability_event_delete';
    var form = jQuery("#modalAvailabilityEventCreate form");
    var id = parseInt( form.find("input[name='id']").val() );
    var fk_ad = parseInt( form.find("input[name='FK_AD']").val() );
    jQuery.post(url, { 'id': id, 'FK_AD': fk_ad }, function(result) {
        if (result.success) {
            jQuery("#modalAvailabilityEventCreate").modal("hide");
            jQuery('#availability .calendar').fullCalendar('removeEvents', id);
        }
    });
}

function requestClubMembership(clubId) {
    var url = ebiz_trader_baseurl + "index.php?page=group-member-request&ID_CLUB="+clubId+"&frame=ajax";

    ShowDialog(url, "[[ translation : marketplace : club.request.title :: Beitrittsanfrage ]]", "auto", "auto", {
        onlyFrame: true,
        close: function() {
            location.reload();
        }
    }, {

    });
}

function requestCalendarEventSignup(button, state, callback) {
    var url = jQuery(button).attr("data-url");
    jQuery.post(url, { ajax: 'signup', state: state }, function (result) {
        if (result.success) {
            if (typeof callback == "undefined") {
                location.reload();
            } else {
                callback();
            }
        } else {
            alert("[[ translation : marketplace : calendar.event.error.signup.failed :: Fehler bei der Anmeldung! Bitte versuchen Sie es erneut. Sollte der Fehler wiederholt auftreten wenden Sie sich an den Administrator. ]]");
        }
    });
}


function Watchlist_addItem(watchlistUrl, fk_ref_type, fk_ref, suggestTitle, fkWatchlistUser, overrideOptions, redirect) {
	var url = ebiz_trader_baseurl + "index.php?page=merkliste-ajax&frame=ajax";
	if ( fk_ref_type == "normal" ) {
		fk_ref = null;
	}
	else if ((typeof fk_ref_type == "undefined") || (fk_ref_type == "") || (fk_ref_type == null)
		|| (typeof fk_ref == "undefined") || (fk_ref == "") || (parseInt(fk_ref) <= 0)) {
		// Force reference parameters to be null if not a valid reference
		fk_ref_type = null;
		fk_ref = null;
	}

    ShowDialog(url, "[[ translation : marketplace : ad.watchlist :: Merkliste ]]", "auto", "auto", jQuery.extend({
        onlyFrame: true,
        close: function() {

        }
    }, overrideOptions), {
        type: 'POST',
        data: { ITEMNAME: suggestTitle, 'URL': watchlistUrl, 'FK_REF_TYPE': fk_ref_type, 'FK_REF': fk_ref, 'FK_WATCHLIST_USER': fkWatchlistUser, 'redirect': redirect }
    });
}

function Watchlist_removeItem(watchlistId, overrideConfig) {
    var config = jQuery.extend({
        callback: function() {}
    }, overrideConfig)

    jQuery.ajax({
        url: ebiz_trader_baseurl + 'index.php?page=merkliste-ajax',
        data: { do: 'remove_watchlist', 'ID_WATCHLIST': watchlistId },
        type: 'POST',
        dataType: 'json',
        success: function(result) {
            if(result.success) {
                config.callback.call(this, watchlistId);
            }
        }
    })
}

function hoverKatBox(box) {
    // Set box and arrow position
    var boxWidth = jQuery(box).parents(".design-category-boxes").width();
    var katBoxOffset = jQuery(box).position();
    var katBoxHeight = jQuery(box).height();
    var katBoxWidth = jQuery(box).width();
    var popoverWidth = jQuery(box).find(".popover").width();
    var popover = jQuery(box).find(".popover");
    if ((katBoxOffset.left + popoverWidth) >= boxWidth) {
        // Align right
        popover.addClass("popover-right").removeClass(".popover-left");
        if (popover.is(".bottom")) {
            // Align bottom
            popover.css({ top: (katBoxHeight - 12) + 'px' });
        } else {
            // Align top
            popover.css({ top: "auto", bottom: (katBoxHeight + 12) + 'px' });
        }
        popover.find(".arrow").css({ left: 'auto', right: (katBoxWidth / 2) + 'px' });
    } else {
        // Align left
        popover.addClass("popover-left").removeClass(".popover-right");
        if (popover.is(".bottom")) {
            // Align bottom
            popover.css({ top: (katBoxHeight - 12) + 'px' });
        } else {
            // Align top
            popover.css({ top: "auto", bottom: (katBoxHeight + 12) + 'px' });
        }
        popover.find(".arrow").css({ left: (katBoxWidth / 2) + 'px', right: 'auto' });
    }
}

function editorImageUpload(field_name, url, type, win) {
    var element = jQuery(win.document).find("input[name="+field_name+"]");
    var editor = jQuery("#"+win.tinyMCEPopup.editor.id);
    var url = "/index.php?page=editor_images&frame=ajax";
    var language = editor.attr("data-lang");
    if (typeof language !== "undefined") {
        url += "&lang="+language;
    }
    tinyMCE.activeEditor.windowManager.open({
        file:           url,
        title:          "[[ translation : marketplace : editor.upload.image.title :: Bild hochladen ]]",
        width:          720,
        height:         500,
        resizable:      "yes",
        inline:         "yes",
        close_previous: "no"
    }, {
        element:          element
    });
}

function editorImageUploadFinish(url) {
    var element = tinyMCEPopup.getWindowArg("element");
    element.val(url);
    tinyMCEPopup.close();
}

/**
 * Sicherstellen das die ID des Artikels als "data-id"-Attribut im link steht!
 * @param link
 */
function marketShowQrCode(link) {
    var id = parseInt( jQuery(link).attr("data-id") );
    if (id > 0) {
        var url = ebiz_trader_baseurl+"index.php?page=marktplatz_qrcode";
        var options = { onlyFrame: true, close: function() { } };
        ShowDialog(url, "[[ translation : marketplace : ad.qrcode :: QR-Code zum Artikel ]]", "auto", "auto", options,
            {
                type: 'POST',
                data: { ID_AD: id }
            }
        );
    }
}

function socialLogin(provider, additionalParameters) {
    var url = ebiz_trader_baseurl+"login.php?SOCIAL_MEDIA_PROVIDER="+provider;
    if ((typeof(additionalParameters) != "undefined") && (additionalParameters != "")) {
        url += "&"+additionalParameters;
    }
    document.location.href = url;
}

function socialLoginCancel(additionalParameters) {
    var url = ebiz_trader_baseurl+"login.php?SOCIAL_MEDIA_CANCEL=1";
    if ((typeof(additionalParameters) != "undefined") && (additionalParameters != "")) {
        url += "&"+additionalParameters;
    }
    document.location.href = url;
}

function generateSystemUrl(parameters, callback) {
    jQuery.post(ebiz_trader_baseurl+"api.php", "apiAction=urlGenerate&url="+encodeURIComponent(parameters), function(result) {
        if (result.success) {
            callback(result.url);
        } else {
            callback(false);
        }
    });
}

/**
 * Ajax search form
 */
(function($) {
    "use strict";

    function ebizSearchInit(form, settings) {
        // Merge default settings with overrides and store within element
        settings = $.extend({
            bindInputEvents: true,
            buttonAdAgent: "a.btn-info",
            buttonSearch: "a.btn-success",
            doSubmit: false,
            doPresearch: false,
            onSubmit: [],
            locationLat: "input[name=LATITUDE]",
            locationLon: "input[name=LONGITUDE]",
            locationRange: "select[name=LU_UMKREIS]",
            locationStreet: false,
            locationZip: "input[name=ZIP]",
            locationCity: "input[name=CITY]",
            locationCountry: "select[name=FK_COUNTRY]",
            serachCategory: null,
            searchHash: null,
            searchResultCount: 0,
            searchURL: null,
            showPositionMarker: false,
            timeoutDelay: 700
        }, settings);
        form.ebizSearch = settings;
        form.ebizSearchPrivate = {
            currentRequest: false,
            dirtyFields: true,
            dirtyLocation: true,
            timeoutUpdate: false
        };
        if (settings.bindInputEvents) {
            var locationElements = [];
            if (settings.locationRange !== false) locationElements.push(settings.locationRange);
            if (settings.locationStreet !== false) locationElements.push(settings.locationStreet);
            if (settings.locationZip !== false) locationElements.push(settings.locationZip);
            if (settings.locationCity !== false) locationElements.push(settings.locationCity);
            if (settings.locationCountry !== false) locationElements.push(settings.locationCountry);
            var jqLocationInputs = $();
            if (locationElements.length > 0) {
                jqLocationInputs = $(form).find( locationElements.join(",") );
            }
            // Bind change events for inputs and selects
            $(form).find("input,select").not(jqLocationInputs).on("change", function() {
                form.ebizSearchPrivate.dirtyFields = true;
                ebizSearchChanged(form, settings, this);
            }).filter("input[type=text],input[type=number]").on("keyup", function() {
                form.ebizSearchPrivate.dirtyFields = true;
                ebizSearchChanged(form, settings, this);
            });
            // Bind onchange events for location based fields
            jqLocationInputs.on("change", function() {
                form.ebizSearchPrivate.dirtyLocation = true;
                ebizSearchChanged(form, settings, this);
            }).filter("input[type=text],input[type=number]").on("keyup", function() {
                form.ebizSearchPrivate.dirtyLocation = true;
                ebizSearchChanged(form, settings, this);
            });
            // Bind onchange for bootstrap-select language input
			jqLocationInputs.filter("select[data-bootstrap-select]").on("changed.bs.select", function() {
				form.ebizSearchPrivate.dirtyFields = true;
				form.ebizSearchPrivate.dirtyLocation = true;
                ebizSearchChanged(form, settings, this);
			});
            // Bind onsubmit for search form
            $(form).on("submit", function(event) {
                event.preventDefault();
                ebizSearchSubmit(form, settings, this);
            });
            // Bind click event on search button
            $(form).find(settings.buttonSearch).on("click", function(event) {
                event.preventDefault();
                ebizSearchSubmit(form, settings, this);
            });
        }
        ebizSearchPresearch(form, settings);
    }

    function ebizSearchChanged(form, settings, input) {
        if (form.ebizSearchPrivate.timeoutUpdate !== false) {
            window.clearTimeout(form.ebizSearchPrivate.timeoutUpdate);
            form.ebizSearchPrivate.timeoutUpdate = false;
        }
        form.ebizSearchPrivate.timeoutUpdate = window.setTimeout(function() {
            form.ebizSearchPrivate.timeoutUpdate = false;
            ebizSearchPresearch(form, settings);
        }, settings.timeoutDelay);
        // Input specific events
        if (typeof input != "undefined") {
            var inputName = jQuery(input).attr("name");
            var inputValue = jQuery(input).val();
            if (inputName == "FK_MAN") {
                ebizSearchGetProducts(form, inputValue);
            }
        }
    }

    function ebizSearchGetProducts(form, id_man) {
        if (typeof id_man == "undefined") {
            return false;
        }
        $.ajax({
            url: ebiz_trader_baseurl + "index.php?page=artikel-suche&GET_PRODUCTS_JSON=" + id_man,
            type: 'GET',
            dataType: 'json',
            success: function (result) {
                var productInput = jQuery(form).find("input[name=PRODUKTNAME]");
                var products = new Bloodhound({
                    datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
                    queryTokenizer: Bloodhound.tokenizers.whitespace,
                    // `states` is an array of state names defined in "The Basics"
                    local: $.map(result.list, function(product) { return { value: product }; })
                });
                products.initialize();
                if (productInput.is(".tt-input")) {
                    productInput.typeahead('destroy');
                }
                productInput.typeahead({
                        // Options
                    },
                    {
                        // Data source
                        name: 'products',
                        displayKey: 'value',
                        source: products.ttAdapter()
                    });
            }
        });
        return true;
    }

    function ebizSearchPresearch(form, settings) {
        if (form.ebizSearchPrivate.dirtyLocation) {
            settings.doPresearch = true;
            return ebizSearchPresearchLocation(form, settings);
        } else if (form.ebizSearchPrivate.dirtyFields && (form.ebizSearchPrivate.currentRequest === false)) {
            form.ebizSearchPrivate.dirtyFields = false;
            form.ebizSearchPrivate.currentRequest = $.ajax({
                url: 		$(form)[0].action,
                type: 		'POST',
                beforeSend: function() {
                    var design_page_content = jQuery(".design-page-content");
                    var load = design_page_content.attr("data-load");
                    if ( load != "0" ) {
                        jQuery(".loading").show();
                    }
                },
                complete: function() {
                    jQuery(".loading").hide();
                },
                data:		$(form).serialize(),
                dataType:	'json',
                success: 	function(json) {
                    form.ebizSearchPrivate.currentRequest = false;
                    settings.searchCategory = json.ID_KAT;
                    settings.searchHash = json.HASH;
                    settings.searchResultCount = json.COUNT;
                    settings.searchURL = json.SEARCHURL;
                    if (settings.buttonSearch !== false) {
                        var text = "[[ translation : marketplace : search.results.show : ALL_ADS='" + json.COUNT + "' : ({ALL_ADS}) Treffer anzeigen ]]";
                        var jqButton = $(form).find(settings.buttonSearch);
                        if (jqButton.is("input")) {
                            jqButton.val(text);
                        } else {
                            jqButton.html(text);
                        }
                        if (json.COUNT > 0) {
                            jqButton.removeClass("disabled").prop("disabled", false);
                            if (jqButton.is("a")) {
                                jqButton.attr("href", json.SEARCHURL);
                            }
                        } else {
                            jqButton.addClass("disabled").prop("disabled", true);
                            if (jqButton.is("a")) {
                                jqButton.attr("href", "#no-results");
                            }
                        }
                    }
                    if (settings.buttonAdAgent !== false) {
                        $(form).find(settings.buttonAdAgent).removeClass("disabled").attr("href", json.AGENTURL);
                    }
                    if (settings.doSubmit) {
                        window.location.href = settings.searchURL;
                    }
                    if ( typeof callback_func_for_presearch === "function" ) {
                        var design_page_content = jQuery(".design-page-content");
                        var load = design_page_content.data("load");
                        if (typeof load == "undefined") {
                            load = 0;
                        }
                        if (load == 0) {
                            design_page_content.data("load", 1);
                        } else {
                            callback_func_for_presearch( json );
                        }
                    }
                },
                error: function() {
                    form.ebizSearchPrivate.currentRequest = false;
                }
            });
        }
        return true;
    }

    function ebizSearchPresearchLocation(form, settings) {
        if ((settings.locationRange === false) || (form.ebizSearchPrivate.currentRequest !== false)) {
            return false;
        }
        var defaultCountry = "Deutschland";
        var street = "";
        var zip = "";
        var city = "";
        var country = defaultCountry;
        if (settings.locationStreet !== false) {
            var inputStreet = $(form).find(settings.locationStreet);
            if (inputStreet.length > 0) {
                street = inputStreet.val();
            }
        }
        if (settings.locationZip !== false) {
            var inputZip = $(form).find(settings.locationZip);
            if (inputZip.length > 0) {
                zip = inputZip.val();
            }
        }
        if (settings.locationCity !== false) {
            var inputCity = $(form).find(settings.locationCity);
            if (inputCity.length > 0) {
                city = inputCity.val();
            }
        }
        if (settings.locationCountry !== false) {
            var jqCountry = $(form).find(settings.locationCountry);
            if (jqCountry.length > 0) {
                if (jqCountry.prop("tagName") == "SELECT") {
                    if ($(form).find(settings.locationCountry).children("option:selected").val() > 0) {
                        country = $(form).find(settings.locationCountry).children("option:selected").text();
                    } else {
                        country = "";
                    }
                } else {
                    country = $(form).find(settings.locationCountry).val();
                }
            }
        }
        if ((street == "") && (zip == "") && (city == "") && (country == defaultCountry)) {
            // No address given. Dont search for geolocation
            form.ebizSearchPrivate.dirtyLocation = false;
            // Execute presearch?
            if (settings.doPresearch) {
                settings.doPresearch = false;
                ebizSearchPresearch(form, settings);
            }
        } else {
            form.ebizSearchPrivate.currentRequest = $.ajax({
                url: ebiz_trader_baseurl + 'geolocation.htm',
                data: { STREET: street, ZIP: zip, CITY: city, COUNTRY: country },
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    var lat = 0;
                    var lon = 0;
                    if (response.success) {
                        lat = response.result.LATITUDE;
                        lon = response.result.LONGITUDE;
                        if (typeof settings.showPositionMarker == 'function') {
                            settings.showPositionMarker(lat, lon);
                        }
                    }
                    $(form).find(settings.locationLat).val(lat);
                    $(form).find(settings.locationLon).val(lon);
                    form.ebizSearchPrivate.dirtyFields = true;
                    form.ebizSearchPrivate.dirtyLocation = false;
                    form.ebizSearchPrivate.currentRequest = false;
                    // Execute presearch?
                    if (settings.doPresearch) {
                        settings.doPresearch = false;
                        ebizSearchPresearch(form, settings);
                    }
                },
                error: function() {
                    form.ebizSearchPrivate.currentRequest = false;
                }
            });
        }
        return true;
    }

    function ebizSearchSubmit(form, settings) {
        for (var i = 0; i < settings.onSubmit.length; i++) {
            if (settings.onSubmit[i](form) === false) {
                return;
            }
        }
        if (!form.ebizSearchPrivate.dirtyFields) {
            window.location.href = settings.searchURL;
        } else {
            settings.doSubmit = true;
            ebizSearchPresearch(form, settings);
        }
    }

    $.fn.ebizSearch = function(param1, param2) {
        if (typeof param1 === "string") {
            var searchList = $(this);
            for (var i = 0; i < searchList.length; i++) {
                var searchForm = searchList[i];
                if (typeof searchForm.ebizSearch !== "undefined") {
                    var settings = searchForm.ebizSearch;
                    if (param1 == "changed") {
                        ebizSearchChanged(searchForm, settings, param2);
                    }
                    if (param1 == "dirtyLocation") {
                        searchForm.ebizSearchPrivate.dirtyLocation = (typeof param2 == "undefined" ? true : param2);
                    }
                    if (param1 == "dirtyFields") {
                        searchForm.ebizSearchPrivate.dirtyFields = (typeof param2 == "undefined" ? true : param2);
                    }
                    if (param1 == "presearch") {
                        ebizSearchPresearch(searchForm, settings);
                    }
                    if (param1 == "searchCategory") {
                        return settings.searchCategory;
                    }
                    if (param1 == "searchHash") {
                        return settings.searchHash;
                    }
                    if (param1 == "searchResultCount") {
                        return settings.searchResultCount;
                    }
                    if (param1 == "submit") {
                        if (typeof param2 == "undefined") {
                            document.location.href = settings.searchURL;
                        } else if (typeof param2 == "function") {
                            settings.onSubmit.push(param2);
                        }
                    }
                }
            }
        } else {
            if (typeof param1 == "undefined") {
                param1 = {};
            }
            $(this).each(function() {
                ebizSearchInit(this, param1)
            });
        }
        return this;
    };
}( jQuery ));

/**
 * Executes the given callback after the google map has loaded
 * @param callback
 */
function ebizGoogleMapCallback(callback) {
    if ((typeof google == "undefined") || (typeof google.maps == "undefined")) {
        jQuery(window).load(function() {
            callback();
        });
    } else {
        callback();
    }
}

// Compatibility fixes
if (typeof jQuery.browser == "undefined") {
    jQuery.browser = {
        msie: (window.navigator.userAgent.indexOf("MSIE ") > 0 ? true : false)
    };
}

function statsLoadModal(link) {
    link = jQuery(link);
    if (link.length > 0) {
        var statsWidth = 800;
        var statsHeight = 200;
        var statsTitle = "Statistik";
        var statsUrl = link.attr("data-stats");
        if (link.is("[data-title]")) {
            statsTitle = link.attr("data-title");
        }
        if (link.is("[data-width]")) {
            statsWidth = parseInt(link.attr("data-width"));
        }
        if (link.is("[data-height]")) {
            statsHeight = parseInt(link.attr("data-height"));
        }
        var statsContent = '<iframe style="width: '+statsWidth+'px; height: '+statsHeight+'px;" frameborder="0" src="'+statsUrl+'"></iframe>';
        ShowContentDialog(statsContent, statsTitle, (statsWidth + 30), statsHeight);
    }
    return false;
}

function statsLoadIframe(element) {
    var statsFrame = jQuery(element).closest("[data-stats]");
    if (statsFrame.length > 0) {
        var statsContainer = statsFrame.find(".stats-container");
        if (statsContainer.length == 0) {
            statsContainer = statsFrame;
        }
        var statsWidth = "100%";
        var statsHeight = "200px";
        var statsUrl = statsFrame.attr("data-stats");
        if (statsFrame.is("[data-width]")) {
            statsWidth = statsFrame.attr("data-width");
        }
        if (statsFrame.is("[data-height]")) {
            statsHeight = statsFrame.attr("data-height");
        }
        if (statsContainer.is("[data-loaded]")) {
            statsContainer.toggle();
        } else {
            statsContainer
                .attr("data-loaded", 1)
                .html('<iframe style="width: '+statsWidth+'; height: '+statsHeight+';" frameborder="0" src="'+statsUrl+'"></iframe>');
        }
    }
    return false;
}

function utilParseQueryString(query) {
    if (query == "") return {};
    var result = {};
    for (var i = 0; i < query.length; ++i)
    {
        var parts = query[i].split('=', 2);
        if (parts.length == 1) {
            result[parts[0]] = "";
        } else {
            result[parts[0]] = decodeURIComponent(parts[1].replace(/\+/g, " "));
        }
    }
    return result;
}

function log_statistics_hash() {
    var x = navigator.plugins.length;

    var txt = '';
    for ( var i=0; i<x; i++ ) {
        if ( i+1 != x ) {
            txt += navigator.plugins[i].name + '&';
        }
        else {
            txt += navigator.plugins[i].name;
        }
    }
    var x = navigator.mimeTypes.length;
    var mimeTypes_text = '';
    for ( var i=0; i<x; i++ ) {
        if ( i+1 != x ) {
            mimeTypes_text += navigator.mimeTypes[i].type+'&';
        }
        else {
            mimeTypes_text += navigator.mimeTypes[i].type;
        }
    }
    var screeen_resolution = window.screen.availWidth+'x'+window.screen.availHeight;

    $.ajax({
        type: "POST",
        dataType: "JSON",
        url: ebiz_trader_baseurl + "index.php?page=system&feature=stats",
        data: {
            'log':                'do',
            'plugins':            txt,
            'mimeTypes':          mimeTypes_text,
            'screen_resolution':  screeen_resolution,
            'local_time_in_ms':   Math.floor( Date.now() / 1000 ),
            'link':               window.location.href
        },
        success: function( resp ) {
        },
        error: function( resp ) {
        }
    });
}
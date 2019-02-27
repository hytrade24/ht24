
/* ###VERSIONSBLOCKINLCUDE### */

function adminInitTemplate() {
    jQuery(".ebizAdminToolsLoadtimeDisable").hide();
    jQuery(".ebizAdminToolsTranslationToolDisable").hide();
    jQuery("form.admin-subtpl-config").submit(function(event) {
        "use strict";
        event.preventDefault();
        jQuery.post(jQuery(this).attr("action"), jQuery(this).serialize(), function(result) {
            if (result.success) {
                document.location.reload();
            } else {
                alert("[[ translation : admin : subtpl.config.submit.failed :: Fehler beim aktualisieren der Konfiguration! ]]");
            }
        });
    });
    jQuery("[data-action=close]").click(function(event) {
        "use strict";
        event.preventDefault();
        jQuery(this).closest(".admin-subtpl-config-menu").hide();
    });
    jQuery("[data-toggle=admin-subtpl-config]").each(function() {
        "use strict";
        // Hide config menu by default
        jQuery(this).parent().children(".admin-subtpl-config-menu").hide();
        // Toggle config menu on click
        jQuery(this).click(function(event) {
            var position = jQuery(this).offset();
            position.top -= jQuery("body").scrollTop();
            var windowCenterY = jQuery(window).height() / 2;
            var windowCenterX = jQuery(window).width() / 2;
            if (position.top > windowCenterY) {
                jQuery(this).parent().children(".admin-subtpl-config-menu").css({
                    top: 'auto', bottom: '50%'
                });
            } else {
                jQuery(this).parent().children(".admin-subtpl-config-menu").css({
                    top: '50%', bottom: 'auto'
                });
            }
            if (position.left > windowCenterX) {
                jQuery(this).parent().children(".admin-subtpl-config-menu").css({
                    left: 'auto', right: '50%'
                });
            } else {
                jQuery(this).parent().children(".admin-subtpl-config-menu").css({
                    left: '50%', right: 'auto'
                });
            }
            var configPanel = jQuery(this).parent().children(".admin-subtpl-config-menu");
            configPanel.toggle();
            event.stopImmediatePropagation();
        });
    });
    // Close menus on click
    jQuery(document).on("click", function(event) {
        var clickElement = event.toElement;
        var clickToggle = jQuery(clickElement).closest("[data-toggle]");
        if (clickToggle.length == 0) {
            // Hide subtpl config menus
            jQuery(".admin-subtpl-config-menu:visible").each(function() {
                if (!jQuery(this).is(clickElement) && !jQuery.contains(this, clickElement)) {
                    jQuery(this).hide();
                }
            });
        }
    });
    // Hier Javascript-code zur initialisierung von elementen im Template ausführen
}

function adminToolsHide() {
    // Einschalten
    jQuery.get(ebiz_trader_baseurl+"system/system-admin,general,hide.htm", function(result) {
        if (result === true) {
            alert("Die Admin-Tools stehen nach erneutem einloggen wieder normal zur Verfügung.");
            document.location.reload();
        } else {
            // TODO: Richtige Fehlermeldung
            console.log("Fehler beim aktivieren des Logging zur Analyse der Ladezeiten!", result);
        }
    });
}

function adminAnalyseLoadtime(status) {
    if (status === true) {
        // Einschalten
        jQuery.get(ebiz_trader_baseurl+"system/system-admin,loadtime,enable.htm", function(result) {
            if (result === true) {
                document.location.reload();
            } else {
                // TODO: Richtige Fehlermeldung
                console.log("Fehler beim aktivieren des Logging zur Analyse der Ladezeiten!", result);
            }
        });
    } else {
        // Ausschalten
        jQuery.get(ebiz_trader_baseurl+"system/system-admin,loadtime,disable.htm", function(result) {
            if (result === true) {
                document.location.reload();
            } else {
                // TODO: Richtige Fehlermeldung
                console.log("Fehler beim deaktivieren des Logging zur Analyse der Ladezeiten!", result);
            }
        });
    }
}

function adminAnalyseLoadtimeShow() {
    // Admin-Tools dropdown aktualisieren
    jQuery(".ebizAdminToolsLoadtimeEnable").hide();
    jQuery(".ebizAdminToolsLoadtimeDisable").show();
    // Ladezeiten für aktuelle Seite abrufen
    jQuery.get(ebiz_trader_baseurl+"system/system-admin,loadtime,render.htm", function(result) {
        if (typeof result === "string") {
            var jqContainer = jQuery(".ebizAdminLoadtimeAnalyser");
            if (jqContainer.length === 0) {
                jqContainer = jQuery(".ebizAdminTools").before("<div class='ebizAdminLoadtimeAnalyser'></div>").prev();
            }
            jqContainer.replaceWith(result);
        }
    });
}

var codeMirror = null;

var runSaveChangesForInfoBereiche = true;
function saveChangesForInfoBereiche() {
    var modal = $("#large-modal-for-admin");
    var id_content_info_bereiche = modal.find( "#id_content_info_bereiche" );
    id_content_info_bereiche = id_content_info_bereiche.val();
    console.log( id_content_info_bereiche );

    var resource_type = modal.find("#resource_type").val();
    var T1 = "";

    if ( resource_type == "codemirror" ) {
        T1 = codeMirror.getValue();
    }
    else if ( resource_type == "tinymce" ) {
        T1 = tinyMCE.activeEditor.getContent();
    }

    if ( runSaveChangesForInfoBereiche ) {
        runSaveChangesForInfoBereiche = false;
        $.ajax({
            type: "POST",
            dataType: "JSON",
            url: ebiz_trader_baseurl+"system/system-admin,get-content-info-bereiche.htm",
            data: {
                id_content_info_bereiche: id_content_info_bereiche,
                T1: T1,
                type: "save"
            },
            success: function ( resp ) {
                runSaveChangesForInfoBereiche = true;
                if ( resp.success ) {
                    new PNotify({
                        //title: 'Regular Notice',
                        text: resp.msg
                    });
                    $("[data-content-page='"+id_content_info_bereiche+"']").each(function(index) {
                        $(this).empty();
                        $(this).html( T1 );
                    });
                }
                else {
                    new PNotify({
                        text: resp.msg
                    });
                }
            },
            error: function ( resp ) {
                runSaveChangesForInfoBereiche = true;
                new PNotify({
                    text: "Failed to make the call"
                });
            }
        });
    }
}

function showEditInfoBereicheModal( status, id_content_info_bereiche ) {
    if ( status ) {
        $("#large-modal-for-admin").modal("hide");
        $.ajax({
            type: "POST",
            dataType: "JSON",
            url: ebiz_trader_baseurl+"system/system-admin,get-content-info-bereiche.htm",
            data: {
                id_content_info_bereiche: id_content_info_bereiche,
                type: "get"
            },
            success: function ( resp ) {

                var modal = $("#large-modal-for-admin");

                var modal_header = modal.find('.modal-title');
                modal_header.empty();
                modal_header.html( "Code for {content_page("+resp.data.V1+")}" + ", Language : " + resp.data.V2 );

                var modal_body = modal.find('.modal-body');
                modal_body.empty();

                if ( resp.success ) {
                    $("#large-modal-for-admin").off('shown.bs.modal');

                    if ( resp.data.TXTTYPE == "TXT" ) {
                        var html = '<input type="hidden" name="id_content_info_bereiche" id="id_content_info_bereiche" value="'+id_content_info_bereiche+'" />';
                        html += '<input type="hidden" name="resource_type" id="resource_type" value="codemirror" />';
                        html += '<div class="codeMirrorEditor">';
                        html += '<textarea name="T1" id="T1" cols="90" rows="30">';
                        html += resp.data.T1;
                        html += '</textarea>';
                        html += '</div>';

                        modal_body.html( html );
                        
                        codeMirror = CodeMirror.fromTextArea(document.getElementById("T1"), {
                            mode: "text/html",
                            lineNumbers: true,
                            fixedGutter: true,
                            //onBlur: save_codeMirrorCode(this.getValue())
                        });
                        modal.on('shown.bs.modal', function() {
                            codeMirror.refresh();
                        });
                    }
                    else if ( resp.data.TXTTYPE == "HTML" ) {

                        var html = '<input type="hidden" id="id_content_info_bereiche" name="id_content_info_bereiche" value="'+id_content_info_bereiche+'" />';
                        html += '<input type="hidden" name="resource_type" id="resource_type" value="tinymce" />';
                        html += '<textarea name="T1" id="T1">';
                        html += resp.data.T1;
                        html += '</textarea>';

                        modal_body.html( html );

                        tinyMCE.init({
                            // General options
                            mode: "none",
                            elements: "T1",
                            theme: "advanced",
                            language: "de",
                            width: "780px",
                            height: "335px",
                            object_resizing: false,
                            convert_fonts_to_spans: true,
                            convert_urls: false,
                            document_base_url: "/",
                            relative_urls: "/",
                            remove_script_host : true,
                            forced_root_block: false,
                            verify_html : false,
                            verify_css_classes : false,
                            elements : "ajaxfilemanager",
                            file_browser_callback : "ajaxfilemanager",
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
                            // Example content CSS (should be your site CSS)
                            //content_css : "(/skin/style.css')'?" + new Date().getTime()
                        });
                        tinyMCE.execCommand("mceAddControl", true, "T1");
                    }
                    $("#large-modal-for-admin").modal("show");
                }
            },
            error: function ( resp ) {}
        });
    }
}

function ajaxfilemanager(field_name, url, type, win) {
    var ajaxfilemanagerurl = "/tinymce/ajaxfilemanager/ajaxfilemanager.php?editor=tinymce";
    switch (type) {
        case "image":
            break;
        case "media":
            break;
        case "flash":
            break;
        case "file":
            break;
        default:
            return false;
    }
    tinyMCE.activeEditor.windowManager.open({
        file : ajaxfilemanagerurl,
        title : "My File Browser",
        width : 720,  // Your dimensions may differ - toy around with them!
        height : 500,
        resizable : "yes",
        inline : "yes",  // This parameter only has an effect if you use the inlinepopups plugin!
        close_previous : "no"
    }, {
        window : win,
        input : field_name
    });
    return false;
}

var run_adminSaveFileFromFrontEnd = true;
function adminSaveFileFromFrontEnd( fileName ) {
    if ( run_adminSaveFileFromFrontEnd ) {
        run_adminSaveFileFromFrontEnd = false;

        $.ajax({
            type: "POST",
            dataType: "JSON",
            url: ebiz_trader_baseurl+"system/system-admin,frontend-edit",//",
            data: {
                type: "save",
                T1: codeMirror.getValue(),
                file: fileName
            },
            success: function( resp ) {
                run_adminSaveFileFromFrontEnd = true;

                if ( resp.success ) {
                    new PNotify({
                        text: resp.data.msg
                    });
                    window.location.reload();
                }
                else {
                    new PNotify({
                        text: resp.data.msg
                    });
                }

            },
            error: function ( resp ) {
                run_adminSaveFileFromFrontEnd = true;
                new PNotify({
                    text: "Failed to make the call"
                });
            }
        });
    }
}

var run_adminEnableEdit = true;
function adminEnableEdit( fileName, header, codeType ) {
    if ( run_adminEnableEdit ) {
        run_adminEnableEdit = false;
        var modal = $("#large-modal-for-admin");
        modal.modal("show");

        $.ajax({
            type: "POST",
            dataType: "JSON",
            url: ebiz_trader_baseurl+"system/system-admin,frontend-edit",
            data: {
                type: "get",
                file: fileName
            },
            success: function( resp ) {
                run_adminEnableEdit = true;
                if ( resp.success ) {

                    var modal_header = modal.find('.modal-title');
                    modal_header.empty();
                    modal_header.html( header + ": \""+ resp.data.fileNameRel+"\"" );

                    var modal_body = modal.find('.modal-body');
                    modal_body.empty();

                    var modal_footer = modal.find(".modal-footer");
                    var save_button = modal_footer.find("button.green-btn")
                    save_button.attr("onclick","adminSaveFileFromFrontEnd('"+fileName+"')");

                    var html = '<input type="hidden" name="resource_type" id="resource_type" value="codemirror" />';
                    html += '<div class="codeMirrorEditor">';
                    html += '<textarea name="T1" id="T1" cols="90" rows="30">';
                    html += resp.data.content;
                    html += '</textarea>';
                    html += '</div>';

                    modal_body.html( html );

                    codeMirror = CodeMirror.fromTextArea(document.getElementById("T1"), {
                        mode: codeType,
                        lineNumbers: true,
                        fixedGutter: true
                    });
                    modal.on('shown.bs.modal', function() {
                        codeMirror.refresh();
                    });

                }
            },
            error: function ( resp ) {
                run_adminEnableEdit = true;
            }
        });
    }
}

function adminEnableInfoBereicheEdit( status ) {

    var anchor_element = $(".ebizEditTools .ebizEditToolEnableInfoBereicheEdit a");

    if ( status === true ) {

        anchor_element.attr("href","javascript:adminEnableInfoBereicheEdit(false)");
        anchor_element.html("Disable edit tool for Info Bereiche");

        var element = $('.infobereiche-edit').each(function(index) {
            var id_content_info_bereiche = $(this).attr('data-content-page');
            $(this).addClass("infobereiche-edit-enable");

            var html = '<button type="button" onclick="showEditInfoBereicheModal( true, '+id_content_info_bereiche+' )" ' +
                'class="btn btn-default" onclick="">';
            html += '<span class="glyphicon glyphicon-pencil">';
            html += '</span>';
            html += '</button>';

            $(this).prepend( html );
        });
    }
    else if ( status === false ) {

        anchor_element.attr("href","javascript:adminEnableInfoBereicheEdit(true)");
        anchor_element.html("Enable edit tool for Info Bereiche");

        var element = $('.infobereiche-edit').each(function(index) {
            $(this).removeClass("infobereiche-edit-enable");
            $(this).children()[0].remove();
        });

    }

}

function adminTranslationTool(status) {
    if (status === true) {
        // Einschalten
        jQuery.get(ebiz_trader_baseurl+"system/system-admin,translation,enable.htm", function(result) {
            if (result === true) {
                document.location.reload();
            } else {
                // TODO: Richtige Fehlermeldung
                console.log("Fehler beim aktivieren des Übersetzungs-Tools!", result);
            }
        });
    } else {
        // Ausschalten
        jQuery.get(ebiz_trader_baseurl+"system/system-admin,translation,disable.htm", function(result) {
            if (result === true) {
                document.location.reload();
            } else {
                // TODO: Richtige Fehlermeldung
                console.log("Fehler beim deaktivieren des Übersetzungs-Tools!", result);
            }
        });
    }
}

function adminTranslationToolShow() {
    // Admin-Tools dropdown aktualisieren
    jQuery(".ebizAdminToolsTranslationToolEnable").hide();
    jQuery(".ebizAdminToolsTranslationToolDisable").show();
}

function utilSelectDOMText(domElement) {
    domElement = jQuery(domElement)[0];
    if (document.selection && document.selection.createRange) {
        var textRange = document.selection.createRange();
        textRange.moveToElementText(domElement);
        textRange.select();
    } else if (document.createRange && window.getSelection) {
        var range = document.createRange();
        range.selectNode(domElement);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
}

jQuery(function() {
    adminInitTemplate();

    $('#large-modal-for-admin').on('hidden.bs.modal', function () {
        // do something…
        if ( $('#large-modal-for-admin #resource_type').val() == "tinymce" ) {
            tinyMCE.execCommand('mceRemoveControl',true, "T1");
        }
    })

});
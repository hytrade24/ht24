
/* ###VERSIONSBLOCKINLCUDE### */



function showAd(id_ad) {
	window.open('/index.php?lang=de&page=marktplatz_anzeige&ID_ANZEIGE='+id_ad+'', 	'Anzeige betrachten', 'width=1080,height=880,left=20,top=20,scrollbars=yes');
}


function setBlobText(button, text, visible){
    var div_blob = jQuery('#blob');
    var tbl_blob = jQuery('#blobtable');
    var txt_blob = jQuery('#blobtext');
    
    if (text.length == 0) 
        text = "Keine Hilfe vorhanden.";
    
    if (visible == true) {
        div_blob.show();
        div_blob.click(function() {
            // initially hide all containers for tab content
            setBlobText(button, "", false);
        });
    }
    else {
        div_blob.hide();
    }
    
    txt_blob.html(text.replace("||", "<br />").replace("\n", "<br />"));
    
    var button_pos = jQuery(button).position();
    var height = txt_blob.outerHeight() + 27;
    var width = 300;

    var page_center_h = window.innerWidth / 2;
    var page_center_v = window.innerHeight / 2;
    if ((button_pos.left - window.scrollX) > page_center_h) {
        div_blob.css("left", (button_pos.left - width)+"px");
    } else {
        div_blob.css("left", (button_pos.left + jQuery(button).width())+"px");
    }
    if ((button_pos.top - window.scrollY) < page_center_v) {
        div_blob.css("top", (button_pos.top + jQuery(button).height())+"px");
    } else {
        div_blob.css("top", (button_pos.top - height)+"px");
    }
    //tbl_blob.clonePosition(div_blob);

    tbl_blob.css("height", height + "px");
    div_blob.css("height", height + "px");
    tbl_blob.css("width", width + "px");
    div_blob.css("width", width + "px");
    //tbl_blob.style.height = div_blob.style.height = height + "px";
    //tbl_blob.style.width = div_blob.style.width = width + "px";
}
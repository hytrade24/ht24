
/* ###VERSIONSBLOCKINLCUDE### */

function getLatiLongi(pplz,port,pcountry,pstreet,fields, handler)
{
	//alert('call to getLatiLongi()');
	if(!pcountry) {
		var options = jQuery('#FK_COUNTRY option');
		var c = options.length;
		var country = 'Deutschland';
		if(c) {
			for(i=0; i<c; i++) {
				if(options[i].selected == true) {
					country = options[i].text;
				}
			}
		}
	} else {
		country = pcountry;
	}
    if(country == '---') {
        country = 'Deutschland';
    }
	
	if(!pplz && (jQuery('#ZIP').length > 0)) {
		var plz = jQuery('#ZIP').val();
	} else if(typeof pplz != "undefined") {
		var plz = pplz;
	} else {
        plz = ""
    }

	if(!port && (jQuery('#CITY').length > 0)) {
		var ort = jQuery('#CITY').val();
	} else if(typeof port != "undefined") {
		ort = port;
	} else {
        ort = ""
    }

    if(!pstreet && (jQuery("#STREET").length > 0)) {
        var strasse = jQuery("#STREET").val();
    } else if(typeof pstreet != "undefined") {
        strasse = pstreet;
    } else {
        var strasse = ""
    }

    jQuery.ajax({
        url: ebiz_trader_baseurl + 'geolocation.htm',
        data: { STREET: strasse, ZIP: plz, CITY: ort, COUNTRY: country },
        type: 'POST',
        async: false,
        dataType: 'json',
        success: function(response) {
            if(response.success == true) {
                var lat = response.result.LATITUDE;
                var lon = response.result.LONGITUDE;

                if (jQuery('#ADMINISTRATIVE_AREA_LEVEL_1')) {
                    jQuery('#ADMINISTRATIVE_AREA_LEVEL_1').val(response.result.ADMINISTRATIVE_AREA_LEVEL_1);
                }
                if (jQuery('#FK_GEO_REGION')) {
                    jQuery('#FK_GEO_REGION').val(response.result.FK_GEO_REGION);
                }
                
                if(jQuery('#LONGI')) {
                    jQuery('#LONGI').html(lon);
                    jQuery('#LATI').html(lat);
                }
                if(!fields) {
                    if(jQuery('#LONGITUDE')) {
                        //alert($('LONGITUDE') + ' :: ' + $('LATITUDE'));
                        jQuery('#LONGITUDE').val(lon);
                        jQuery('#LATITUDE').val(lat);
                        //alert($('LONGITUDE').value + ' :: ' + $('LATITUDE').value);
                    }
                } else {
                    jQuery('#'+fields.logitude).val(lon);
                    jQuery('#'+fields.latitude).val(lat);
                }
                if (typeof showPositionMarker == 'function') {
                    showPositionMarker(lat, lon);
                }
                changed = 1;

                if(typeof handler != "undefined") {
                    handler.call(this);
                }
            } else {
                jQuery('#LONGITUDE').val(0);
                jQuery('#LATITUDE').val(0);
            }
        }
    });


}

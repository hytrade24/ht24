
/* ###VERSIONSBLOCKINLCUDE### */

function KatToggle(id_kat, id_ad_user, callback) {
	var childs_container = jQuery('#ad'+id_ad_user+'_div_childs_kat_'+id_kat);
	var kat_icon = jQuery('#ad'+id_ad_user+'_img_toggle_kat_'+id_kat);

	if(typeof id_ad_user == "undefined") {
		id_ad_user = "";
	}
	
	// Check if child nodes are loaded
	if (childs_container.html() == "") {
		// Child nodes not loaded yet
		childs_container.html("Wird geladen...");
		// Load child nodes using ajax
		childs_container.load("index.php", {
			page:	"market_advertisement_orders_edit",
			frame: 	"ajax",
			ajax:	"ajax",
			action:	"read",
			target: id_kat,
			id: id_ad_user
		}, callback);
	}
	
	// Toggle visibility
	childs_container.toggle();
	
	// Update expand/contract icon
	if (childs_container.css('display') != 'none') {
		// Visible
		kat_icon.attr("src", "/bilder/icon_minus.png");
	} else {
		// Not visible
		kat_icon.attr("src", "/bilder/icon_plus.png");
	}
}

function KatGetSelected() {
	var cat_checked = jQuery("#steps input[type=checkbox]:checked"); 
	var cat_count = cat_checked.length;
	var cat_ids = new Array();
	for (var cat_index = 0; cat_index < cat_count; cat_index++) {
		cat_ids.push(cat_checked[cat_index].value);
	}
	return {
		categorys:	cat_ids,
		count:		cat_count
	};
}

function KatChangeRecursive(id_kat, id_ad_user, checked) {
	if (id_kat > 0) {
		jQuery("#steps_loading").show();
		jQuery.ajax({
			url: "index.php",
			data:	{
				page:	"market_advertisement_orders_edit",
				frame: 	"ajax",
				ajax:	"ajax",
				action:	(checked ? "kat_add_recursive" : "kat_rem_recursive"),
				target:	id_kat
			},
			dataType:	"json",
			success: function(response) {
				if (response.success) {
					// Change successful, set categorys checked
					for (var cat_index = 0; cat_index < response.checked.length; cat_index++) {
						var id_cat_checked = response.checked[cat_index];
						if (checked) {
							jQuery("#ad"+id_ad_user+"_btn_uncheck_kat_"+id_cat_checked).show();
							jQuery("#ad"+id_ad_user+"_btn_check_kat_"+id_cat_checked).hide();
							jQuery("#ad"+id_ad_user+"_chk_kat_"+id_cat_checked).attr("checked", true);
						} else {
							jQuery("#ad"+id_ad_user+"_btn_check_kat_"+id_cat_checked).show();
							jQuery("#ad"+id_ad_user+"_btn_uncheck_kat_"+id_cat_checked).hide();
							jQuery("#ad"+id_ad_user+"_chk_kat_"+id_cat_checked).attr("checked", false);
						}
					}
					// Update price
					KatUpdate();
				}
				jQuery("#steps_loading").hide();
			}
		});
	}
}

function KatChange(checkbox) {
	var id_kat = checkbox.value;
	var checked = checkbox.checked;
	if (id_kat > 0) {
		// Prevent further changes until request is done
		jQuery("#steps_loading").show();
		checkbox.disabled = true;
		// Request change
		jQuery.ajax({
			url: "index.php",
			data:	{
				page:	"market_advertisement_orders_edit",
				frame: 	"ajax",
				ajax:	"ajax",
				action:	(checked ? "kat_add" : "kat_rem"),
				target:	id_kat
			},
			dataType:	"json",
			success: function(response) {
				if (!response.success) {
					// Error changing selection state, revert change
					checkbox.checked = !checkbox.checked;
				} else {
					// Change successful, update price
					KatUpdate();
				}
				checkbox.disabled = false;
				jQuery("#steps_loading").hide();
			},
			error: function() {
				// Request failed
				checkbox.checked = !checkbox.checked;
				checkbox.disabled = false;
				jQuery("#steps_loading").hide();
			}
		});
	} else {
		// Category id not valid!
		checkbox.checked = !checkbox.checked;
	}
}

function KatUpdate() {
	var cat_selected = KatGetSelected();
	jQuery.ajax({
		url: "index.php",
		data:		{
			page:	"market_advertisement_orders_edit",
			frame: 	"ajax",
			ajax:	"ajax",
			action:	"update"
		},
		dataType:	"json",
		success: function(response) {
			KatUpdate_Display(response);
		}
	});
}

function KatUpdateReset() {
	KatUpdate_Display({
		price: 0
	});
}

function KatUpdate_Display(info) {
	jQuery('#kat_count').html(info.count);
	jQuery('#kat_price').html(info.price.toFixed(2));
	
	categorys = info;
	if (categorys.count == 1) {
		jQuery('#step2_cur').html(" - Gew&auml;hlt: 1 Kategorie");
	} else {
		jQuery('#step2_cur').html(" - Gew&auml;hlt: "+categorys.count+" Kategorien");
	}
}
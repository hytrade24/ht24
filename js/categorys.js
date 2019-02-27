
/* ###VERSIONSBLOCKINLCUDE### */

function KatToggle(id_kat, id_ad_user, callback) {
	var childs_container = $('#div_childs_kat_'+id_kat);
	var kat_icon = $('#img_toggle_kat_'+id_kat);

	if(typeof id_ad_user == "undefined") {
		id_ad_user = "";
	}
	
	// Check if child nodes are loaded
	if (childs_container.html() == "") {
		// Child nodes not loaded yet
		childs_container.html("Wird geladen...");
		// Load child nodes using ajax
		childs_container.load(ebiz_trader_baseurl + "index.php", {
			page:	"advertisement",
			frame: 	"ajax",
			action:	"read",
			target: id_kat,
			id_ad_user: id_ad_user
		}, callback);
	}
	
	// Toggle visibility
	childs_container.toggle();
	
	// Update expand/contract icon
	if (childs_container.css('display') != 'none') {
		// Visible
		kat_icon.attr("src", ebiz_trader_baseurl+"bilder/icon_minus.png");
	} else {
		// Not visible
		kat_icon.attr("src", ebiz_trader_baseurl+"bilder/icon_plus.png");
	}
}

function KatGetSelected() {
	var cat_checked = $("#steps input[type=checkbox]:checked"); 
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

function KatChangeRecursive(id_kat, checked) {
	if (id_kat > 0) {
		$("#steps_loading").show();
		$.ajax({
			url:		ebiz_trader_baseurl + "index.php",
			data:	{
				page:	"advertisement",
				frame:	"ajax",
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
							$("#btn_uncheck_kat_"+id_cat_checked).show();
							$("#btn_check_kat_"+id_cat_checked).hide();
							$("#chk_kat_"+id_cat_checked).attr("checked", true);
						} else {
							$("#btn_check_kat_"+id_cat_checked).show();
							$("#btn_uncheck_kat_"+id_cat_checked).hide();
							$("#chk_kat_"+id_cat_checked).attr("checked", false);
						}
					}
					// Update price
					KatUpdate();
				}
				$("#steps_loading").hide();
			}
		});
	}
}

function KatChange(checkbox) {
	var id_kat = checkbox.value;
	var checked = checkbox.checked;
	if (id_kat > 0) {
		// Prevent further changes until request is done
		$("#steps_loading").show();
		checkbox.disabled = true;
		// Request change
		$.ajax({
			url:		ebiz_trader_baseurl + "index.php",
			data:	{
				page:	"advertisement",
				frame:	"ajax",
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
				$("#steps_loading").hide();
			},
			error: function() {
				// Request failed
				checkbox.checked = !checkbox.checked;
				checkbox.disabled = false;
				$("#steps_loading").hide();
			}
		});
	} else {
		// Category id not valid!
		checkbox.checked = !checkbox.checked;
	}
}

function KatUpdate() {
	var cat_selected = KatGetSelected();
	$.ajax({
		url:		ebiz_trader_baseurl + "index.php",
		data:		{
			page:	"advertisement",
			frame:	"ajax",
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
	$('#kat_count').html(info.count);
	$('#kat_price').html(info.price.toFixed(2));
	
	categorys = info;
	if (categorys.count == 1) {
		$('#step2_cur').html(" - Gew&auml;hlt: 1 Kategorie");
	} else {
		$('#step2_cur').html(" - Gew&auml;hlt: "+categorys.count+" Kategorien");
	}
	if (typeof CheckInput == "function") {
		CheckInput('kat');
	}
}


var ImportPresetEditor = function (selector, params) {

	var ipeElement = jQuery(selector);

	var ipeObject = this;
	var loadNavigationCallbacks = [];

	this.init = function() {
		this.reloadEditorNavigation();
	}

	this.addNavigationCallback = function(callback) {
		loadNavigationCallbacks.push(callback);
	};
	
	this.saveData = function(data, callback) {

	}

	this.loadAjaxOptions = function(loadOnTabActivation) {
		if ((typeof loadOnTabActivation != "undefined") && loadOnTabActivation) {
			var loadCallback = function() {
				ipeObject.loadAjaxOptions();
				jQuery(window).off("focus", loadCallback);
			};
			jQuery(window).on("focus", loadCallback);
		} else {
			var presetType = ipeElement.find("#importPresetEditorTypeForm [name='PRESET_TYPE']:checked").val();
			ipeElement.find(".TYPE_CONFIG_CONTAINER.TYPE_CONFIG_"+presetType+" .TYPE_CONFIG_AJAX")
				.load(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=LOAD_AJAX_OPTIONS&type='+encodeURIComponent(presetType));
		}
	}

	this.loadCurrentStepContent = function() {
		ipeElement.find("#importPresetEditorContent").load(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=LOAD_CURRENT_STEP');
	}

	this.loadStep = function(step) {
		jQuery.get(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=SET_STEP&step=' + step, function() {
			location.href = ebiz_trader_baseurl + 'my-import-presets-edit.htm';
		});

	}

	this.reloadEditorNavigation = function() {
		var self = this;
		ipeElement.find("#importPresetEditorNavigation").load(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=LOAD_NAVIGATION', function() {
			for (var cbIndex = 0; cbIndex < loadNavigationCallbacks.length; cbIndex++) {
				loadNavigationCallbacks[cbIndex]();
			}
		});
	}


	this.editMappingField = function(fieldName, tableDef) {
		if(jQuery("#FIELD_MAPPING_EDITVIEW_"+tableDef+"_"+fieldName).text().trim() == '') {
			this.reloadTableFieldMappingRow(fieldName, tableDef, 'edit');
		} else {
			jQuery(".fieldMappingEditViewContainer").hide();
			jQuery(".fieldMappingDisplayViewContainer").show();

			jQuery("#FIELD_MAPPING_DISPLAYVIEW_" + tableDef + "_" + fieldName).hide();
			jQuery("#FIELD_MAPPING_EDITVIEW_" + tableDef + "_" + fieldName).show();
		}
	}

	this.reloadTableFieldMappingRow = function(fieldName, tableDef, view) {
		var me = this;

		jQuery.get(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=MAPPING_FIELD_LOAD&FIELD_NAME='+fieldName+'&TABLE_DEF='+tableDef, function(data) {
			jQuery("#TABLE_FIELD_MAPPING_ROW_" + tableDef + '_' + fieldName).replaceWith(data);

			if(view == 'edit') {
				me.editMappingField(fieldName, tableDef);
			}
		});

	}

	this.addMappingFieldValue = function(fieldName, tableDef, pos, type) {
		var me = this;

		jQuery.post(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=MAPPING_FIELD_ADD&FIELD_NAME='+fieldName+'&TABLE_DEF='+tableDef+'&MAPPING_VALUE_TYPE='+type+'&POS='+pos, function(data) {
			me.reloadTableFieldMappingRow(fieldName, tableDef, 'edit');
		});
	}

	this.removeMappingFieldValue = function(fieldName, tableDef, pos) {
		var me = this;

		jQuery.post(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=MAPPING_FIELD_REMOVE&FIELD_NAME='+fieldName+'&TABLE_DEF='+tableDef+'&POS='+pos, function(data) {
			me.reloadTableFieldMappingRow(fieldName, tableDef, 'edit');
		});
	}

	this.saveMappingFieldValue = function(fieldName, tableDef, pos) {
		var me = this;
		var el = jQuery("[name^='MAPPING["+tableDef+"]["+fieldName+"]["+pos+"]']");

		jQuery.post(
			ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=MAPPING_FIELD_SAVE&FIELD_NAME='+fieldName+'&TABLE_DEF='+tableDef+'&POS='+pos,
			el.serialize(),
			function(data) {
				me.reloadTableFieldMappingRow(fieldName, tableDef, 'edit');
			}
		);
	}

	this.saveDefaultFieldValue = function(fieldName, tableDef) {
		var me = this;
		var el = jQuery("[name^='DEFAULTVALUE["+tableDef+"]["+fieldName+"]']");

		jQuery.post(
			ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=FIELD_DEFAULTVALUE_SAVE&FIELD_NAME='+fieldName+'&TABLE_DEF='+tableDef,
			el.serialize(),
			function(data) {
				me.reloadTableFieldMappingRow(fieldName, tableDef, 'edit');
			}
		);
	}

	this.showDataFieldTable = function(markerCol) {
		ShowDialog(ebiz_trader_baseurl + 'index.php?page=my-import-presets-edit&DO=SHOW_DATAFIELD_TABLE&MARKER_COL='+markerCol, 'Import Daten', '1000', '700');
	}

	this.toggleTypeConfig = function() {
		var val = ipeElement.find("#importPresetEditorTypeForm [name='PRESET_TYPE']:checked").val();
		ipeElement.find(".TYPE_CONFIG_CONTAINER").hide().find("input,select,textarea").attr('disabled', true);
		ipeElement.find(".TYPE_CONFIG_CONTAINER.TYPE_CONFIG_"+val).show().find("input,select,textarea").attr('disabled', false);
		ipeObject.loadAjaxOptions();
	}

	this.saveFormAndClose = function() {
		var form = ipeElement.find("form");
		form.attr('action', form.attr('action')+'&AFTER=CLOSE');
		form.submit();

	}

	this._showAjaxLoader = function(selector) {
		var modal = ipeElement.find(selector);
		modal.find(".modal-body").html('<div class="ajax-loader" style="text-align: center;"><img src="'+ ebiz_trader_baseurl +'gfx/ajax-loader.gif"></div>');
		modal.modal({ keyboard: false, show: true });
	}

}
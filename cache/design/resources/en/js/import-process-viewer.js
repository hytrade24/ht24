

var ImportProcessViewer = function (selector, processId, params) {

	var ipvElement = jQuery(selector);
	var importProcessId = processId;
	var pollStateActive = false;
	var pollDelay = 5000;
	var pollDelayImport = 100;
	var pollUpdateTimer = null;
	var logOffset = 0;
	var logTable;
	var markerTable = {1: null, 2: null};


	this.init = function() {

	}

	this.pollCurrentState = function(startPolling) {
		var me = this;

		if(typeof startPolling == "undefined") {
			startPolling = false;
		}

		if(pollStateActive == true || startPolling == true) {
			pollStateActive = true;

			jQuery.ajax({
				url: ebiz_trader_baseurl + 'index.php?page=my-import-run&DO=RUN&ID_IMPORT_PROCESS=' + importProcessId,
				method: 'get',
				dataType: 'json',
				cache: false,
				success: function(response) {
					me.updateProgessStateName(response.PROCESS_STATUS_NAME);
					me.updateProgessStatePercentage(response.PROGRESS_PERCENTAGE);

					if (response.PROCESS_COMPLETE == true) {
						pollStateActive = false;
					}
					// Continue processing
					window.setTimeout(function() {
						 me.pollCurrentState();
					}, pollDelayImport);
					if (pollUpdateTimer == null) {
						// Update log / datasets
						pollUpdateTimer = window.setTimeout(function() {
	                        me.reloadLog(true);
	                        me.reloadMarkerTable(1, true);
	                        me.reloadMarkerTable(2, true);
							pollUpdateTimer = null;
	                    }, pollDelay);
					}
				},
				error: function(response, b, c) {
					pollStateActive = false;
					ipvElement.find("#importProcessContentContainer").prepend(jQuery("<div class='alert alert-danger'>" + response.responseText + "</div>"));
				}
			})
		}
	}

	this.pollLog = function() {
		var me = this;
		var logLevel = ipvElement.find("[name='LOG_LEVEL']").val();
		var o = logOffset;

		logTable = jQuery('#LOG_TABLE').DataTable({
			  "processing": true,
			  "serverSide": true,
			  "scrollY": 600,
			  "scrollX": 600,
			  "ordering": false,
			  searching: false,
			  lengthChange: false,
			  pageLength: 50,
			  "ajax": {
				  url: ebiz_trader_baseurl + 'index.php?page=my-import-run&DO=GET_LOG&ID_IMPORT_PROCESS='+importProcessId+'&LOG_LEVEL='+logLevel,
				  type: "POST"
			  }
		  });

	}
	this.reloadLog = function(resetPaging) {
		var logLevel = ipvElement.find("[name='LOG_LEVEL']").val();
		logTable.ajax.url(ebiz_trader_baseurl + 'index.php?page=my-import-run&DO=GET_LOG&ID_IMPORT_PROCESS='+importProcessId+'&LOG_LEVEL='+logLevel);
		logTable.ajax.reload(null, resetPaging);
	}


	this.pollMarkerTable = function(type) {
		var me = this;
		var logLevel = ipvElement.find("[name='LOG_LEVEL']").val();

		markerTable[type] = jQuery('#MARKER_TABLE_'+type).DataTable({
			"processing": true,
			"serverSide": true,
			"scrollY": 600,
			"scrollX": 600,
			"ordering": false,
			searching: false,
			lengthChange: false,
			pageLength: 50,
			"ajax": {
				url: ebiz_trader_baseurl + 'index.php?page=my-import-run&ID_IMPORT_PROCESS='+importProcessId+'&DO=GET_MARKER_DATASETS&MARKER=' + type,
				type: "POST"
			},
			"autoWidth": false,
			"rowCallback": function( row, data ) {
			   // jQuery('td:eq(2)', row).css('200);

		    }

		});

	}
	this.reloadMarkerTable = function(type, resetPaging) {
		markerTable[type].ajax.reload(null, resetPaging);
	}

	this.reloadErrorDatasetTable = function() {

	}

	this.updateProgessStatePercentage = function(p) {
		ipvElement.find("#importProcessBarContainer .progress-bar").width(p + '%');
		ipvElement.find("#importProcessBarContainer .statusPercent").text(p + '%');

		if(p == 100) {
			ipvElement.find("#importProcessBarContainer .progress-bar").removeClass('progress-bar-striped');
		}
	}

	this.updateProgessStateName = function(name) {
		ipvElement.find("#importProcessBarContainer .statusText").text(name);
	}

	this._showAjaxLoader = function(selector) {
		ipeElement.find(selector).html('<div class="ajax-loader"><img src="'+ ebiz_trader_baseurl +'gfx/ajax-loader.gif"></div>');
	}

}
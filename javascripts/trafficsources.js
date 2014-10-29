/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

var settings = $.extend( {
	rowHeight			: 25,
});
var history = [];

/**
 * jQueryUI widget for Live visitors widget
 */
$(function() {
    var refreshTrafficSourcesWidget = function (element, refreshAfterXSecs) {
        // if the widget has been removed from the DOM, abort
        if ($(element).parent().length == 0) {
            return;
        }
        var lastMinutes = $(element).find('.dynameter').attr('data-last-minutes') || 30;

        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'API',
            method: 'TrafficSources.getTrafficSources',
            format: 'json',
            lastMinutes: lastMinutes
        }, 'get');
        ajaxRequest.setFormat('json');
        ajaxRequest.setCallback(function (data) {
        	data.sort(function(a, b){
        	    return b.value - a.value;
        	});
        	$.each( data, function( index, value ){
              	var pc = value['percentage'];
        		pc = pc > 100 ? 100 : pc;
        		$('#trafficSourcesChart').find("div[id="+value['id']+"]").children('.percent').html(pc+'%');
        		var ww = $('#trafficSourcesChart').find("div[id="+value['id']+"]").width();
        		var len = parseInt(ww, 10) * parseInt(pc, 10) / 100;
        		$('#trafficSourcesChart').find("div[id="+value['id']+"]").children('.bar').animate({ 'width' : len+'px' }, 1500);
        		$('#trafficSourcesChart').find("div[id="+value['id']+"]").attr("index", index);

        	});
			//animation
			var vertical_offset = 0; // Beginning distance of rows from the table body in pixels
			for ( index = 0; index < data.length; index++) {
				$("#trafficSourcesChart").find("div[index="+index+"]").stop().delay(1 * index).animate({ top: vertical_offset}, 1000, 'swing').appendTo("#trafficSourcesChart");
				vertical_offset += settings['rowHeight'];
			}
            // schedule another request
            setTimeout(function () { refreshTrafficSourcesWidget(element, refreshAfterXSecs); }, refreshAfterXSecs * 1000);
        });
        ajaxRequest.send(true);
    };

    var exports = require("piwik/TrafficSources");
    exports.initSimpleRealtimeTrafficSourcesWidget = function (refreshInterval) {
        var ajaxRequest = new ajaxHelper();
        ajaxRequest.addParams({
            module: 'API',
            method: 'TrafficSources.getTrafficSources',
            format: 'json',
            lastMinutes: 30
        }, 'get');
        ajaxRequest.setFormat('json');
        ajaxRequest.setCallback(function (data) {
        	data.sort(function(a, b){
        	    return b.value - a.value;
        	});
            $('#trafficSourcesChart').each(function() {
                // Set table height and width
    			$("#trafficSourcesChart").height((data.length*settings['rowHeight']));

    			for (j=0; j<data.length; j++){
                	$("#trafficSourcesChart").find("div[index="+j+"]").css({ top: (j*settings['rowHeight']) }).appendTo("#trafficSourcesChart");
                }
            });
        	$.each( data, function( index, value ){
               	var pc = value['percentage'];
        		pc = pc > 100 ? 100 : pc;
        		$('#trafficSourcesChart').find("div[index="+index+"]").attr("id", value['id']);
        		$('#trafficSourcesChart').find("div[index="+index+"]").children('.percent').html(pc+'%');
        		$('#trafficSourcesChart').find("div[index="+index+"]").children('.title').text(value['name']);
        		var ww = $('#trafficSourcesChart').find("div[index="+index+"]").width();
        		var len = parseInt(ww, 10) * parseInt(pc, 10) / 100;
        		$('#trafficSourcesChart').find("div[index="+index+"]").children('.bar').animate({ 'width' : len+'px' }, 1500);
        	});
            $('#trafficSourcesChart').each(function() {
    			var $this = $(this),
                   refreshAfterXSecs = refreshInterval;
                setTimeout(function() { refreshTrafficSourcesWidget($this, refreshAfterXSecs ); }, refreshAfterXSecs * 1000);
            });
        });
        ajaxRequest.send(true);
     };
});


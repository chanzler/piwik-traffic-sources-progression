/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(function() {

		var options = {
			lines: {
				show: true,
				zero: false,
				fill: 1 
			},
			points: {
				show: false
			},
			xaxis: {
				tickDecimals: 0,
				tickSize: 0,
				show: true
			},
			yaxis: {
            	autoscaleMargin: 0.2
            },
            legend: {
            	position: "nw"
            }
		}

		var updateTrafficSourcesProgression = function (updateInterval) {
			var alreadyFetched = {};
	        var data = [];
	        var ajaxRequest = new ajaxHelper();
	        ajaxRequest.addParams({
	            module: 'API',
	            method: 'TrafficSourcesProgression.getTrafficSourcesProgression',
	            format: 'original',
	            lastMinutes: 30
	        }, 'get');
	        ajaxRequest.setFormat('json');
	        ajaxRequest.setCallback(function (series) {
				// Push the new data onto our existing data array
	        	$.each( series, function( index, value ){
					if (!alreadyFetched[value.label]) {
						alreadyFetched[value.label] = true;
						data.push(value);
					}
				});
	        	options.xaxis.ticks = [[data[1].data[0][0],"0h"],[data[1].data[18][0],"6h"],[data[1].data[36][0],"12h"],[data[1].data[54][0],"18h"],[data[1].data[71][0],"24h"]];
				$.plot("#tsp-placeholder", data, options);
			});
	        
	        ajaxRequest.send(true);
			setTimeout(function() { updateTrafficSourcesProgression(updateInterval); }, updateInterval * 1000);
		}
		
	    var exports = require("piwik/TrafficSourcesProgression");
	    exports.initTrafficSourcesProgression = function (updateInterval) {
		//function update(updateInterval) {
			var alreadyFetched = {};
	        var data = [];
	        var ajaxRequest = new ajaxHelper();
	        ajaxRequest.addParams({
	            module: 'API',
	            method: 'TrafficSourcesProgression.getTrafficSourcesProgression',
	            format: 'original',
	            lastMinutes: 30
	        }, 'get');
	        ajaxRequest.setFormat('json');
	        ajaxRequest.setCallback(function (series) {
				// Push the new data onto our existing data array
	        	$.each( series, function( index, value ){
					if (!alreadyFetched[value.label]) {
						alreadyFetched[value.label] = true;
						data.push(value);
					}
				});
	        	options.xaxis.ticks = [[data[1].data[0][0],"0h"],[data[1].data[18][0],"6h"],[data[1].data[36][0],"12h"],[data[1].data[54][0],"18h"],[data[1].data[71][0],"24h"]];
	        	$.plot("#tsp-placeholder", data, options);
			});
	        
	        ajaxRequest.send(true);
			setTimeout(function() { updateTrafficSourcesProgression(updateInterval); }, updateInterval * 1000);
		}

	});

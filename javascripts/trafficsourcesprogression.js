/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(function() {

		var plot = $.plot("#tsp-placeholder", [ [[0, 0], [1, 1]] ], {
			lines: {
				show: true,
				fill: 1 
			},
			points: {
				show: false
			},
			xaxis: {
				tickDecimals: 0,
				tickSize: 0,
				show:false
			},
			yaxis: {
            	autoscaleMargin: 0.2
            }
		});

	    var exports = require("piwik/TrafficSourcesProgression");
	    exports.update = function (refreshInterval) {
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
						//data = [ series ];
						data.push(value);
					}
				});
				if (!alreadyFetched[series.label]) {
					alreadyFetched[series.label] = true;
					//data = [ series ];
					data.push(series);
				}
				$.plot("#placeholder", data, options);        
			});
	        
	        ajaxRequest.send(true);
			setTimeout(update(updateInterval), updateInterval);
		}

	});

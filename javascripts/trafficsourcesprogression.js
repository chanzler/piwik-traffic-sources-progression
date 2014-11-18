/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
$(function() {

	var actOptions = {
		lines : {
			show : true,
			zero : false,
			fill : 1
		},
		shadowSize : 0,
		points : {
			show : false
		},
		xaxis : {
			tickDecimals : 0,
			tickSize : 0,
			show : true
		},
		yaxis : {
			autoscaleMargin : 0.2
		},
		grid: {
			hoverable: true,
			autoHighlight: false
		},
		crosshair : {
			mode : "x"
		},
		legend : {
			position : "nw"
		}
	}
	var legends = null;
	var plot = null;
	var updateLegendTimeout = null;
	var latestPosition = null;

	function updateLegend() {
		legends = $("#tsp-placeholder .legendLabel");
		legends.each(function () {
			// fix the widths so they don't jump around
			$(this).css('width', $(this).width()+100);
		});
		updateLegendTimeout = null;

		var pos = latestPosition;
		var axes = plot.getAxes();
		if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max
				|| pos.y < axes.yaxis.min || pos.y > axes.yaxis.max) {
			return;
		}

		var i, j, dataset = plot.getData();
		for (i = 0; i < dataset.length-1; ++i) {

			var series = dataset[i];

			// Find the nearest points, x-wise

			for (j = 0; j < series.data.length; ++j) {
				if (series.data[j][0] > pos.x) {
					break;
				}
			}

			// Now Interpolate

			var y, p1 = series.data[j - 1], p2 = series.data[j];

			if (p1 == null) {
				y = p2[1];
			} else if (p2 == null) {
				y = p1[1];
			} else {
				y = p1[1] + (p2[1] - p1[1]) * (pos.x - p1[0]) / (p2[0] - p1[0]);
			}

			legends.eq(i).text(series.label.replace(/=.*/, "= " + p2[1].toFixed(0)));
		}
	}

	var updateTrafficSourcesProgression = function(updateInterval) {
		var alreadyFetched = {};
		var data = [];
		var ajaxRequest = new ajaxHelper();
		ajaxRequest.addParams({
			module : 'API',
			method : 'TrafficSourcesProgression.getTrafficSourcesProgression',
			format : 'original',
			lastMinutes : 30
		}, 'get');
		ajaxRequest.setFormat('json');
		ajaxRequest.setCallback(function(series) {
			// Push the new data onto our existing data array
			$.each(series, function(index, value) {
				if (!alreadyFetched[value.label]) {
					alreadyFetched[value.label] = true;
					data.push(value);
				}
			});
			actOptions.xaxis.ticks = [ [ data[1].data[0][0], "0h" ],
					[ data[1].data[8][0], "3h" ],
					[ data[1].data[17][0], "6h" ],
					[ data[1].data[26][0], "9h" ],
					[ data[1].data[35][0], "12h" ],
					[ data[1].data[44][0], "15h" ],
					[ data[1].data[53][0], "18h" ],
					[ data[1].data[62][0], "21h" ],
					[ data[1].data[71][0], "24h" ] ];
			plot = $.plot("#tsp-placeholder", data, actOptions);
			$("#tsp-placeholder").bind("plothover", function(event, pos, item) {
				latestPosition = pos;
				if (!updateLegendTimeout) {
					updateLegendTimeout = setTimeout(updateLegend, 50);
				}
			});
		});

		ajaxRequest.send(true);
		setTimeout(function() {
			updateTrafficSourcesProgression(updateInterval);
		}, updateInterval * 1000);
	}

	var exports = require("piwik/TrafficSourcesProgression");
	exports.initTrafficSourcesProgression = function(updateInterval) {
		// function update(updateInterval) {
		var alreadyFetched = {};
		var data = [];
		var ajaxRequest = new ajaxHelper();
		ajaxRequest.addParams({
			module : 'API',
			method : 'TrafficSourcesProgression.getTrafficSourcesProgression',
			format : 'original',
			lastMinutes : 30
		}, 'get');
		ajaxRequest.setFormat('json');
		ajaxRequest.setCallback(function(series) {
			// Push the new data onto our existing data array
			$.each(series, function(index, value) {
				if (!alreadyFetched[value.label]) {
					alreadyFetched[value.label] = true;
					data.push(value);
				}
			});
			actOptions.xaxis.ticks = [ [ data[1].data[0][0], "0h" ],
					[ data[1].data[8][0], "3h" ],
					[ data[1].data[17][0], "6h" ],
					[ data[1].data[26][0], "9h" ],
					[ data[1].data[35][0], "12h" ],
					[ data[1].data[44][0], "15h" ],
					[ data[1].data[53][0], "18h" ],
					[ data[1].data[62][0], "21h" ],
					[ data[1].data[71][0], "24h" ] ];
			plot = $.plot("#tsp-placeholder", data, actOptions);
			$("#tsp-placeholder").bind("plothover", function(event, pos, item) {
				latestPosition = pos;
				if (!updateLegendTimeout) {
					updateLegendTimeout = setTimeout(updateLegend, 50);
				}
			});
		});

		ajaxRequest.send(true);
		setTimeout(function() {
			updateTrafficSourcesProgression(updateInterval);
		}, updateInterval * 1000);
	}

});

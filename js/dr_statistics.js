/**
 * digital ROCK jQuery based Javascript API
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2014 by RalfBecker@digitalROCK.de
 * @version $Id$
 */

/**
 * Display some statistics about a boulder / result
 * @param {DOMElement|string} _parent parent node or jQuery selector of it
 * @param {object} _data with attribute statistics
 */
function dr_statistics(_parent, _data)
{
	// find & empty or create container
	var stats_node = jQuery('.dr_statistics', _parent);
	if (!stats_node.length)
	{
		stats_node = jQuery('<div/>')
			.attr('id', 'dr_statistics')
			.addClass('dr_statistics')
			.appendTo(_parent);
	}
	else
	{
		stats_node.empty();
	}
	var data = {
		top: [], top_min: [], top_max: [], top_avg: [],
		bonus: [], bonus_min: [], bonus_max: [], bonus_avg: []
	};
	if (_data.discipline == 'selfscore')
	{
		var use = _data.selfscore_use || 't';
		data = {};
		if (use.indexOf('b') >= 0) data.bonus = [];
		if (use.indexOf('t') >= 0) data.top = [];
		if (use.indexOf('f') >= 0) data.flash = [];
	}
	for(var b=1; b <= _data.route_num_problems; ++b)// in _data.statistics)
	{
		var boulder = _data.statistics[b] || {};
		for(var n in data)
		{
			data[n].push([parseInt(b), boulder[n] || 0]);
		}
	}
	var show = [], labels = [];
	for (var n in data)
	{
		show.push(data[n]);
		labels.push(n);
	}

	//jQuery.jqplot(_id,  [[[1, 2],[3,5.12],[5,13.1],[7,33.6],[9,85.9],[11,219.9]]]);
	jQuery.jqplot('dr_statistics',  show, {
		// not sure why bar-renderer does not work :(
		//seriesDefaults: {
		//	renderer: jQuery.jqplot.BarRenderer
		//},
		axes: {
			xaxis: {
				label: "Boulder",
				min: 0,
				max: 1+parseInt(_data.route_num_problems),
				tickInterval: _data.route_num_problems <= 20 ? 1 : (_data.route_num_problems <= 50 ? 2 : 5)
			},
			yaxis: {
				min: 0
			}
		},
		/* legend would make sense for more then one data-series, but is always positions behind the plot :(
		legend: {
			labels: labels,
			show: labels.length > 1,
			showSwatches: true,
			showLabels: true,
			placement: 'outsideGrid',//'insideGrid',
			location: 'ne',
			marginTop: '500px'
        },*/
		highlighter: {
			show: true,
			showTooltip: true,
			tooltipAxes: 'both',
			formatString: 'Boulder %s: %s',
			tooltipLocation: 'ne'
		}
	});
}
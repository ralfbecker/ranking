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
	for(var b in _data.statistics)
	{
		var boulder = _data.statistics[b];
		for(var n in data)
		{
			if (typeof boulder[n] != 'undefined') data[n].push([parseInt(b), boulder[n]]);
		}
	}

	//jQuery.jqplot(_id,  [[[1, 2],[3,5.12],[5,13.1],[7,33.6],[9,85.9],[11,219.9]]]);
	jQuery.jqplot('dr_statistics',  [data.top, data.bonus, data.top_avg, data.bonus_avg], {
		axes: {
			xaxis: {
				label: "Boulder",
				min: 0,
				max: 1+parseInt(_data.route_num_problems),
				tickInterval: Math.ceil(_data.route_num_problems/20)	// max 20 numbers
			},
			yaxis: {
				min: 0
			}
		},
		highlighter: {
			show: true,
			showTooltip: true,
			tooltipAxes: 'both',
			formatString: 'Boulder %s: %s',
			tooltipLocation: 'ne'
		}
	});
}
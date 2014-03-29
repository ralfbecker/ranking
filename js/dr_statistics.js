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
 * @param {string} _id
 * @param {object} _data with attribute statistics
 */
function dr_statistics(_id, _data)
{
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
	jQuery.jqplot(_id,  [data.top, data.bonus, data.top_avg, data.bonus_avg], {
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
		}
	});
}
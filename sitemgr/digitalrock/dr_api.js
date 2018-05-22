/**
 * digital ROCK jQuery based Javascript API
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2010-16 by RalfBecker@digitalROCK.de
 * @version $Id: dr_api.js 1250 2015-06-18 06:49:01Z ralfbecker $
 */

/**
 * Widgets defined in this file:
 *
 * - DrWidget: universal widget, which can display all data-types and implement reload-free / in-place navigation
 * - Resultlist: displays results and rankings, inherits from Startlist
 * - Startlist: displays startlists and implementes automatic scrolling and rotation through multiple results
 * - Results: displays top results of multiple categories and allows to change competition
 * - Starters: displays registration data
 * - Competitions: displays calendar, allows to change year and optional filter
 * - Profile: display profile of an athlete based on a html template
 * - ResultTemplate: displays result based on a html template
 * - Aggregated: displays an aggregated ranking: national team ranking, GER sektionenwertung or SUI regionalzentren
 * - DrBaseWidget: virtual base of all widgets implements json(p) loading of data
 * - DrTable: creates and updates a table from data and a column definition, used by most widgets
 *
 * In almost all cases you only need to use DrWidget as shown in following example:
 *
 * <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
 * <script type="text/javascript" src="http://www.digitalrock.de/egroupware/ranking/js/dr_api.js"></script>
 * <link type="text/css" rel="StyleSheet" href="http://www.digitalrock.de/egroupware/ranking/templates/default/dr_list.css" />
 *
 * <div id="container" />
 * <script>
 * 		var widget;
 * 		$(document).ready(function() {
 *			widget = new DrWidget('container', http://www.digitalrock.de/egroupware/ranking/json.php');
 *		}
 * </script>
 *
 * @link http://www.digitalrock.de/egroupware/ranking/README describes available parameters for json url
 * @link https://developers.google.com/webmasters/ajax-crawling/ describes supported ajax crawling scheme
 * @link http://svn.outdoor-training.de/repos/trunk/ranking/inc/class.ranking_widget.inc.php php class implementing ajax crawling
 */

/**
 * Example with multi-result scrolling (use c= and r= for cat and route)
 *
 * http://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?comp=1251&cat=1&route=2&detail=0&rotate=c=1,r=2:c=2,r=2
 *
 * You can also supply an optional parameter w= (think of German "Wettkampf" as "c" was already taken) to rotate though different competitions (the first of which is specified by the "comp" parameter in the original URL.
 *
 * Example https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?comp=1395&beamer=1&cat=1&route=0&rotate=w=1395,c=1,r=0:w=1396,c=1,r=0
 *
 * The interesting part here is rotate=w=1395,c=1,r=0:w=1396,c=1,r=0
 */

/**
 * Baseclass for all widgets
 *
 * We only use jQuery() here (not $() or $j()!) to be able to run as well inside EGroupware as with stock jQuery from googleapis.
 */
var DrBaseWidget = (function() {
	/**
	 * Constructor for all widgets from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 */
	function DrBaseWidget(_container,_json_url)
	{
		this.json_url = _json_url;
		this.container = jQuery(typeof _container == 'string' ? '#'+_container : _container);
		this.container.addClass(this.constructor.name);
	}
	/**
	 * Install update method as popstate or hashchange handler
	 */
	DrBaseWidget.prototype.installPopState = function()
	{
		// add popstate or hashchange (IE8,9) event listener, to use browser back button for navigation
		// some browsers, eg. Chrome, generate a pop on inital page-load
		// to prevent loading page initially twice, we store initial location
		this.prevent_initial_pop = location.href;
		var that = this;
		jQuery(window).bind(window.history.pushState ? "popstate" : "hashchange", function(e) {
			if (!that.prevent_initial_pop || that.prevent_initial_pop != location.href)
			{
				that.update();
			}
			delete that.prevent_initial_pop;
		});
	};
	/**
	 * Update Widget from json_url
	 *
	 * To be able to cache jsonp requests in CDN, we have to use the same callback.
	 * Using same callback leads to problems with concurrent requests: failed: parsererror (jsonp was not called)
	 * To work around that we queue jsonp request, if there's already one running.
	 *
	 * Queue is maintained globally in DrBaseWidget.jsonp_queue, as requests come from different objects!
	 *
	 * @param {boolean} ignore_queue used internally to start next object in queue without requeing it
	 */
	DrBaseWidget.prototype.update = function(ignore_queue)
	{
		// remove our own parameters and current year from json url to improve caching
		var url = this.json_url.replace(/(detail|beamer|rotate|toc)=[^&]*(&|$)/, '')
			.replace(new RegExp('year='+(new Date).getFullYear()+'(&|$)'), '').replace(/&$/, '');

		// do we need a jsonp request
		var jsonp = this.json_url.indexOf('//') != -1 && this.json_url.split('/', 3) != location.href.split('/', 3);
		if (typeof DrBaseWidget.jsonp_queue == 'undefined') DrBaseWidget.jsonp_queue = [];
		if (!ignore_queue && jsonp)
		{
			// add us to the queue
			DrBaseWidget.jsonp_queue.push(this);
		}
		// if there's only one in the queue (or no queueing necessary: no jsonp) --> send ajax request
		if (ignore_queue || !jsonp || DrBaseWidget.jsonp_queue.length == 1)
		{
			jQuery.ajax({
				url: url,
				async: true,
				context: this,
				data: '',
				dataType: jsonp ? 'jsonp' : 'json',
				jsonpCallback: 'jsonp',	// otherwise jQuery generates a random name, not cachable by CDN
				cache: true,
				type: 'GET',
				success: function(_data) {
					// if we are first object in queue, remove us
					if (DrBaseWidget.jsonp_queue[0] === this) DrBaseWidget.jsonp_queue.shift();
					// if someone left in queue, run it's update ignore the queue
					if (DrBaseWidget.jsonp_queue.length) DrBaseWidget.jsonp_queue[0].update(true);
					this.handleResponse(_data);
				},
				error: function(_xmlhttp,_err,_status) {
					// need same handling as success
					if (DrBaseWidget.jsonp_queue[0] === this) DrBaseWidget.jsonp_queue.shift();
					if (DrBaseWidget.jsonp_queue.length) DrBaseWidget.jsonp_queue[0].update(true);
					//if (_err != 'timeout') alert('Ajax request to '+this.json_url+' failed: '+_err+(_status?' ('+_status+')':''));
					// schedule update again after 60sec
					this.update_handle = window.setTimeout(jQuery.proxy(this.update, this, ignore_queue), 60000);
				}
			});
		}
	};
	/**
	 * Callback for loading data via ajax
	 *
	 * Virtual, need to be implemented in inheriting objects!
	 *
	 * @param _data
	 */
	DrBaseWidget.prototype.handleResponse = function(_data)
	{
		throw "No handleResponse implemented in "+this.constructor.name+" inheriting from DrBaseWidget!";
	};
	/**
	 * Add list with see also links, if not beamer or toc disabled
	 *
	 * @param _see_also
	 */
	DrBaseWidget.prototype.seeAlso = function(_see_also)
	{
		this.container.find('ul.seeAlso').remove();

		if (typeof _see_also != 'undefined' && _see_also.length > 0 &&
			!this.json_url.match(/toc=0/) && !this.json_url.match(/beamer=1/))
		{
			var ul = jQuery(document.createElement('ul')).attr('class', 'seeAlso');
			for(var i=0; i < _see_also.length; ++i)
			{
				var tag = jQuery(document.createElement('li'));
				ul.append(tag);
				if (_see_also[i].url)
				{
					var a = jQuery(document.createElement('a')).attr('href', _see_also[i].url);
					tag.append(a);
					if (this.navigateTo)
					{
						a.click(this.navigateTo);
					}
					tag = a;
				}
				tag.text(_see_also[i].name);
			}
			this.container.append(ul);
		}
	};
	/**
	 * Replace attribute named from with one name to and value keeping the order of the attributes
	 *
	 * @param {object} obj
	 * @param {string} from
	 * @param {string} to
	 * @param {*}  value
	 */
	DrBaseWidget.prototype.replace_attribute = function(obj, from, to, value)
	{
		var found = false;
		for(var attr in obj)
		{
			if (!found)
			{
				if (attr == from)
				{
					found = true;
					delete obj[attr];
					obj[to] = value;
				}
			}
			else
			{
				var val = obj[attr];
				delete obj[attr];
				obj[attr] = val;
			}
		}
	};
	/**
	 * Replace "nation" column with what's specified in "display_athlete" on competition
	 *
	 * @param {string} _display_athlete
	 * @param {string} _nation
	 */
	DrBaseWidget.prototype.replace_nation = function(_display_athlete, _nation)
	{
		switch(_display_athlete)
		{
			case 'none':
				delete this.columns.nation;
				break;
			case 'city':
			case 'pc_city':
				this.replace_attribute(this.columns, 'nation', 'city', 'City');
				break;
			case 'federation':
			case 'fed_and_parent':
				var fed_label = 'Federation';
				switch(_nation)
				{
					case 'GER':
						fed_label = 'DAV Sektion';
						break;
					case 'SUI':
						fed_label = 'Sektion';
						break;
				}
				this.replace_attribute(this.columns, 'nation', 'federation', fed_label);
				break;
		}
	};
	/**
	 * Format a date according to browser local format
	 *
	 * @param {string} _ymd yyyy-mm-dd string or everything understood by date constructor
	 * @returns {string}
	 */
	DrBaseWidget.prototype.formatDate = function(_ymd)
	{
		if (!_ymd || typeof _ymd != 'string') return '';

		var date = new Date(_ymd);

		return date.toLocaleDateString().replace(/[0-9]+\./g, function(_match){
			return _match.length <= 2 ? '0'+_match : _match;
		});
	};

	return DrBaseWidget;
})();

/**
 * DrTable helper to construct table from colum-defition and data
 */
var DrTable = (function() {
	/**
	 * Constructor for table with given data and columns
	 *
	 * Table get appended to specified _container
	 *
	 * @param _data array with data for each participant
	 * @param _columns hash with column name => header
	 * @param _sort column name to sort by
	 * @param _ascending
	 * @param _quota quota if quota line should be drawn in result
	 * @param _navigateTo click method for profiles
	 * @param _showUnranked if true AND _sort=='result_rank' show participants without rank, default do NOT show them
	 */
	function DrTable(_data,_columns,_sort,_ascending,_quota,_navigateTo,_showUnranked)
	{
		this.data      = _data;
		this.columns   = _columns;
		if (typeof _sort == 'undefined') for(_sort in _columns) break;
		this.sort      = _sort;
		if (typeof _ascending == 'undefined') _ascending = true;
		this.ascending = _ascending;
		this.quota = parseInt(_quota);
		this.navigateTo = _navigateTo;
		this.showUnranked = _showUnranked ? true : false;
		// hash with PerId => tr containing athlete
		this.athletes = {};

		this.sortData();

		// header
		this.dom = document.createElement('table');
		jQuery(this.dom).addClass('DrTable');
		var thead = document.createElement('thead');
		jQuery(this.dom).append(thead);
		var row = this.createRow(this.columns,'th');
		jQuery(thead).append(row);

		// athletes
		var tbody = jQuery(document.createElement('tbody'));
		jQuery(this.dom).append(tbody);

		for (var i=0; i < this.data.length; ++i)
		{
			var data = this.data[i];

			if (Array.isArray(data.results))	// category with result
			{
				if (typeof this.column_count == 'undefined')
				{
					this.column_count = 0;
					for(var c in this.columns) ++this.column_count;
				}
				if (typeof data.name != 'undefined')
				{
					row = document.createElement('tr');
					var th = jQuery(document.createElement('th'));
					th.attr('colspan', this.column_count);
					th.text(data.name);
					if (typeof data.url != 'undefined')
					{
						var a = jQuery(document.createElement('a'));
						a.attr('href', data.url);
						a.text('complete result');
						if (this.navigateTo || typeof data.click != 'undefined') a.click(this.navigateTo || data.click);
						th.append(a);
					}
					jQuery(row).append(th);
					tbody.append(row);
				}
				for (var j=0; j < data.results.length; ++j)
				{
					tbody.append(this.createRow(data.results[j]));
				}
			}
			else	// single result row
			{
				if (this.sort == 'result_rank' &&
					(typeof data.result_rank == 'undefined' && !this.showUnranked || data.result_rank < 1))
				{
					break;	// no more ranked competitiors
				}
				tbody.append(this.createRow(data));
			}
		}
		//console.log(this.athletes);
	}

	/**
	 * Update table with new data, trying to re-use existing rows
	 *
	 * @param _data array with data for each participant
	 * @param _quota quota if quota line should be drawn in result
	 */
	DrTable.prototype.update = function(_data,_quota)
	{
		this.data = _data;
		if (typeof _quota != 'undefined') this.quota = parseInt(_quota);
		//console.log(this.data);
		this.sortData();

		var tbody = this.dom.firstChild.nextSibling;
		var pos;

		// uncomment to test update: reverses the list on every call
		//if (this.data[0].PerId == tbody.firstChild.id) this.data.reverse();

		var athletes = this.athletes;
		this.athletes = {};

		for(var i=0; i < this.data.length; ++i)
		{
			var data = this.data[i];
			var row;
			if (data.PerId != 'undefined')
			{
				row = athletes[data.PerId];
			}
			else if (data.team_id != 'undefined')
			{
				row = athletes[data.team_id];
			}
			if (this.sort == 'result_rank' &&
				(typeof data.result_rank == 'undefined' || data.result_rank < 1))
			{
				break;	// no more ranked competitiors
			}
			// search athlete in tbody
			if (typeof row != 'undefined')
			{
				//jQuery(row).detach();
				//this.updateRow(row,data);
				jQuery(row).remove();
			}
			//else
			{
				row = this.createRow(data);
			}
			// no child in tbody --> append row
			if (typeof pos == 'undefined')
			{
				jQuery(tbody).prepend(row);
			}
			else
			{
				jQuery(pos).after(row);
			}
			pos = row;
		}
		// remove further rows / athletes not in this.data
		if (typeof pos != 'undefined' && typeof pos.nextSibling != 'undefined')
		{
			jQuery('#'+pos.id+' ~ tr').remove();
		}
	};

	/**
	 * Update given data-row with changed content
	 *
	 * @param {object} _row
	 * @param {object} _data
	 * @todo
	 */
	DrTable.prototype.updateRow = function(_row,_data)
	{

	};

	/**
	 * Create new data-row with all columns from this.columns
	 *
	 * @param {object} _data
	 * @param {string} [_tag=td]
	 */
	DrTable.prototype.createRow = function(_data,_tag)
	{
		//console.log(_data);
		if (typeof _tag == 'undefined') _tag = 'td';
		var row = document.createElement('tr');
		if (typeof _data.PerId != 'undefined' && _data.PerId > 0)
		{
			row.id = _data.PerId;
			this.athletes[_data.PerId] = row;
		}
		else if (typeof _data.team_id != 'undefined' && _data.team_id > 0)
		{
			row.id = _data.team_id;
			this.athletes[_data.team_id] = row;
		}
		var span = 1;
		for(var col in this.columns)
		{
			if (--span > 0) continue;

			var url = _data.url;

			// if object has a special getter func, call it
			var col_data;
			if (typeof this.columns[col] == 'function')
			{
				col_data = this.columns[col].call(this,_data,_tag,col);
			}
			else
			{
				col_data = _data[col];
			}
			// allow /-delemited expressions to index into arrays and objects
			if (typeof col_data == 'undefined' && col.indexOf('/') != -1)
			{
				var parts = col.split('/');
				col_data = _data;
				for(var p in parts)
				{
					col = parts[p];
					if (col == 'lastname' || col == 'firstname')
						url = col_data.url;
					if (typeof col_data != 'undefined')
						col_data = col_data[col];
				}
			}
			else if (col.indexOf('/') != -1)
				col = col.substr(col.lastIndexOf('/')+1);

			var tag = document.createElement(_tag);
			tag.className = col;
			jQuery(row).append(tag);

			// add pstambl link to name & vorname
			if (typeof url != 'undefined' && (col == 'lastname' || col == 'firstname'))
			{
				var a = document.createElement('a');
				a.href = url;
				a.target = 'pstambl';
				if (this.navigateTo && url.indexOf('#') != -1) jQuery(a).click(this.navigateTo);
				jQuery(tag).append(a);
				tag = a;
			}
			if (typeof _data.fed_url != 'undefined' && (col == 'nation' || col == 'federation'))
			{
				var a = document.createElement('a');
				a.href = _data.fed_url;
				a.target = '_blank';
				jQuery(tag).append(a);
				tag = a;
			}
			if (typeof col_data == 'object' && col_data)
			{
				if (typeof col_data.nodeName != 'undefined')
				{
					jQuery(tag).append(col_data);
				}
				else
				{
					if (col_data.colspan > 1) tag.colSpan = span = col_data.colspan;
					if (col_data.className) tag.className = col_data.className;
					if (col_data.title) tag.title = col_data.title;
					if (col_data.url || col_data.click)
					{
						var a = document.createElement('a');
						a.href = col_data.url || '#';
						if (col_data.click || this.navigateTo)
						{
							jQuery(a).click(col_data.click || this.navigateTo);
						}
						jQuery(tag).append(a);
						tag = a;
					}
					if (col_data.nodes)
					{
						jQuery(tag).append(col_data.nodes);
					}
					else
					{
						jQuery(tag).text(col_data.label);
					}
				}
			}
			else
			{
				jQuery(tag).text(typeof col_data != 'undefined' ? col_data : '');
				span = 1;
			}
		}
		// add or remove quota line
		if (this.sort == 'result_rank' && this.quota && _data.result_rank &&
			parseInt(_data.result_rank) >= 1 && parseInt(_data.result_rank) > this.quota)
		{
			row.className = 'quota_line';
			delete this.quota;	// to set quota line only once
		}
		return row;
	};

	/**
	 * Sort data according to sort criteria
	 *
	 * @todo get using this.sortNummeric callback working
	 */
	DrTable.prototype.sortData = function()
	{
		function sortResultRank(_a, _b)
		{
			var rank_a = _a['result_rank'];
			if (typeof rank_a == 'undefined' || rank_a < 1) rank_a = 9999;
			var rank_b = _b['result_rank'];
			if (typeof rank_b == 'undefined' || rank_b < 1) rank_b = 9999;
			var ret = rank_a - rank_b;

			if (!ret) ret = _a['lastname'] > _b['lastname'] ? 1 : -1;
			if (!ret) ret = _a['firstname'] > _b['firstname'] ? 1 : -1;

			return ret;
		}

		switch(this.sort)
		{
			case false:	// dont sort
				break;

			case 'result_rank':
				// not necessary as server returns them sorted this way
				//this.data.sort(sortResultRank);
				break;

			default:
				var sort = this.sort;
				this.data.sort(function(_a,_b){
					var a = sort == 'start_order' ? parseInt(_a[sort]) : _a[sort];
					var b = sort == 'start_order' ? parseInt(_b[sort]) : _b[sort];
					return a == b ? 0 : (a < b ? -1 : 1);
					//return _a[sort] == _b[sort] ? 0 : (_a[sort] < _b[sort] ? -1 : 1);
				});
				break;
		}
		if (!this.ascending) this.data.reverse();
	};
	return DrTable;
})();

/**
 * Startlist widget inheriting from DrBaseWidget
 */
var Startlist = (function() {
	/**
	 * Constructor for startlist from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param {boolean} _no_navigation do NOT display TOC
	 */
	function Startlist(_container, _json_url, _no_navigation)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);
		this.no_navigation = _no_navigation;
		// do not continue, as constructor is called when inheriting from Startlist without parameters!
		if (typeof _container == 'undefined') return;

		// Variables needed for scrolling in upDown
		// scroll speed
		this.scroll_by = 1;
	  	// scroll interval, miliseconds (will be changed on resized windows where scroll_by is increased, so a constant scrolling speed is maintained)
		this.scroll_interval = 20;
		// current scrolling direction. 1: down, -1: up
		this.scroll_dir = 1;
		// sleep on the borders for sleep_for seconds
		this.sleep_for = 4;
	    // margin in which to reverse scrolling
	    // CAUTION: At the beginning, we scroll pixelwise through the margin, one pixel each sleep_for seconds. Do not change the margin unless you know what you do.
		this.margin = 2;

		// helper variable
		var now = new Date();
		this.sleep_until = now.getTime() + 10000;
		this.first_run = true;
		this.do_rotate = false;

		this.update();

		if (this.json_url.match(/rotate=/)) {
			var list = this;
			// 20110716: This doesn't seem to be needed anymore. Comment it for now.
			//window.scrollBy(0, 20);
			this.scroll_interval_handle = window.setInterval(function() { list.upDown(); },20);
		}
	}
	// inherit from DrBaseWidget
	Startlist.prototype = new DrBaseWidget();
	Startlist.prototype.constructor = Startlist;

	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Startlist.prototype.handleResponse = function(_data)
	{
		//console.log(_data);
		var detail = this.json_url.match(/detail=([^&]+)/);
		if (detail) detail = detail[1];

		switch(_data.discipline)
		{
			case 'speedrelay':
				this.startlist_cols = detail === null ? {	// default detail
					'start_order': 'StartNr',
					'team_name': 'Teamname',
					'athletes/0/lastname': 'Athlete #1',
					'athletes/1/lastname': 'Athlete #2',
					'athletes/2/lastname': 'Athlete #3'
				} : (detail ? {	// detail=1
					'start_order': 'StartNr',
					'team_name': 'Teamname',
					//'team_nation': 'Nation',
					'athletes/0/lastname': {'label': 'Athlete #1', 'colspan': 3},
					'athletes/0/firstname': '',
					'athletes/0/result_time': '',
					'athletes/1/lastname': {'label': 'Athlete #2', 'colspan': 3},
					'athletes/1/firstname': '',
					'athletes/1/result_time': '',
					'athletes/2/lastname': {'label': 'Athlete #3', 'colspan': 3},
					'athletes/2/firstname': '',
					'athletes/2/result_time': ''
				} : {	// detail=0
					'start_order': 'StartNr',
					'team_name': 'Teamname',
					'team_nation': 'Nation'
				});
				break;

			case 'combined':
				this.result_cols.final_points = 'Final Points';
				// fall through
			default:
				this.startlist_cols = {
					'start_order': {'label': 'StartNr', 'colspan': 2},
					'start_number': '',
					'lastname' : {'label': 'Name', 'colspan': 2},
					'firstname' : '',
					'birthyear' : 'Birthyear',
					'nation' : 'Nation'
				};
				break;
		}

		// if quali_preselected and heat = 1||2, we have to use a function to get either start_order or text "preselected"
		if (_data.quali_preselected && (_data.route_order == 0 || _data.route_order == 1))
		{
			var quali_preselected = _data.quali_preselected;
			var start_order = this.startlist_cols.start_order;
			this.startlist_cols.start_order = function(_data,_tag,col) {
				if (_tag == 'th') return start_order;
				if (_data.ranking <= quali_preselected) return 'Vorqualifiziert';	//'preselected';
				return _data[col];
			};
		}

		var sort;
		// if we have no result columns or no ranked participant, show a startlist
		if (typeof this.result_cols == 'undefined' || _data.participants[0] && !_data.participants[0].result_rank && _data.discipline != 'ranking')
		{
			this.columns = this.startlist_cols;
			sort = 'start_order';
			this.container.attr('class', 'Startlist');
		}
		// if we are a result showing a startlist AND have now a ranked participant
		// --> switch back to result
		else
		{
			this.columns = this.result_cols;
			sort = 'result_rank';
		}

		this.replace_nation(_data.display_athlete, _data.nation);

		// fix route_names containing only one or two qualifications are send as array because index 0 and 1
		if (Array.isArray(_data.route_names))
		{
			var route_names = _data.route_names;
			delete _data.route_names;
			_data.route_names = {};
			for(var i=0; i < route_names.length; ++i)
			{
				_data.route_names[i] = route_names[i];
			}
		}

		// keep route_names to detect additional routes on updates
		if (typeof this.route_names == 'undefined')
		{
			this.route_names = _data.route_names;
		}
		// remove whole table, if the discipline is speed and the number of route_names changes
		if (_data.discipline == 'speed' && this.json_url.match(/route=-1/) ) // && this.route_names != _data.route_names)
		{
			for (var i=2; i < 10; i++)
			{
				if (typeof _data.route_names[i] != typeof this.route_names[i])
				{
					// there was an update of the route_names array
					this.route_names = _data.route_names;
					jQuery(this.container).empty();
					delete this.table;
					break;
				}
			}
		}
		// remove whole table, if discipline or startlist/resultlist (detemined by sort) changed
		if (this.discipline && this.discipline != _data.discipline ||
			this.sort && this.sort != sort ||
			_data.route_order != this.route_order ||	// switching heats (they can have different columns)
			detail !== this.detail ||		// switching detail on/off
			this.json_url != this.last_json_url)
		{
			jQuery(this.container).empty();
			delete this.table;
		}
		this.discipline = _data.discipline;
		this.sort = sort;
		this.route_order = _data.route_order;
		this.detail = detail;
		this.last_json_url = this.json_url;

		if (typeof this.table == 'undefined')
		{
			// for general result use one column per heat
			if (this.columns.result && _data.route_names && _data.route_order == -1)
			{
				delete this.columns.result;
				// show final first and 2. quali behind 1. quali: eg. 3, 2, 0, 1
				var routes = [];
				if (_data.route_names['1']) routes.push('1');
				for(var id in _data.route_names)
				{
					if (id != '-1' && id != '1') routes.push(id);
				}
				routes.reverse();
				for(var i = 0; i < routes.length; ++i)
				{
					var route = routes[i];
					// for ranking, we add link to results
					if (_data.discipline == 'ranking')
					{
						var id = route.replace(/ $/, '');	// remove space append to force js to keep the order
						var comp_cat = id.split('_');
						this.columns['result'+id] = {
							'label': _data.route_names[route],
							'url': '#!comp='+comp_cat[0]+'&cat='+(comp_cat[1] || _data.cat.GrpId)
						};
					}
					else
					{
						this.columns['result'+route] = _data.route_names[route];
					}
				}
				// evtl. add points column
				if (_data.participants[0] && _data.participants[0].quali_points)
				{
					this.columns['quali_points'] = 'Points';
					// delete single qualification results
					if (this.no_navigation)
					{
						delete this.columns.result0;
						delete this.columns.result1;
						this.columns['quali_points'] = 'Qualification';
					}
				}
				if (_data.discipline == 'combined' && _data.participants[0] && typeof _data.participants[0].final_points == 'undefined') {
					delete this.columns.final_points;
				}
				title_prefix = '';
			}
			if (this.columns.result && _data.participants[0] && _data.participants[0].rank_prev_heat && !this.json_url.match(/detail=0/))
			{
				this.columns['rank_prev_heat'] = 'previous heat';
			}

			// competition
			this.comp_header = jQuery(document.createElement('h1'));
			jQuery(this.container).append(this.comp_header);
			this.comp_header.addClass('compHeader');
			// result date
			this.result_date = jQuery(document.createElement('h3'));
			jQuery(this.container).append(this.result_date);
			this.result_date.addClass('resultDate');
			// route header
			this.header = jQuery(document.createElement('h1'));
			jQuery(this.container).append(this.header);
			this.header.addClass('listHeader');

			// display a toc with all available heats, if not explicitly disabled (toc=0) or beamer
			this.displayToc(_data);

			// create new table
			if (!_data.error && _data.participants.length)
			{
				this.table = new DrTable(_data.participants,this.columns,this.sort,true,
					_data.route_result ? _data.route_quota : null,this.navigateTo,
					_data.discipline == 'ranking' && (detail || !_data.participants[0].result_rank));
				jQuery(this.container).append(this.table.dom);
			}

			this.seeAlso(_data.see_also);
		}
		else
		{
			// update a toc with all available heats, if not explicitly disabled (toc=0) or beamer
			this.displayToc(_data);

			// update existing table
			this.table.update(_data.participants,_data.route_result ? _data.route_quota : null);
		}
		// set/update header line
		this.setHeader(_data);

		// if route is NOT offical, update list every 10 sec, of category not offical update every 5min (to get new heats)
		if (!_data.category_offical && this.discipline != 'ranking')
		{
			var list = this;
			this.update_handle = window.setTimeout(function(){list.update();}, _data.expires*1000);
			//console.log('setting up refresh in '+_data.expires+' seconds');
		}
	};
	/**
	 * Create or update TOC (list of available routes for navigation)
	 *
	 * Can be disabled via "toc=0" or "beamer=1" in json_url. Always disabled for rankings.
	 *
	 * @param _data route data object
	 */
	Startlist.prototype.displayToc = function(_data)
	{
		if (this.json_url.match(/toc=0/) || this.json_url.match(/beamer=1/) || this.discipline == 'ranking' || this.no_navigation)
		{
			return;	// --> no toc
		}
		var toc = this.container.find('ul.listToc');
		var new_toc = !toc.length;
		if (new_toc)
			toc = jQuery(document.createElement('ul')).addClass('listToc');
		else
			toc.empty();

		var href = location.href.replace(/\?(.*)#/, '#');	// prevent query and hash messing up navigation
		for (var r in _data.route_names)
		{
			if (r != this.route_order)
			{
				var li = jQuery(document.createElement('li'));
				var a = jQuery(document.createElement('a'));
				a.text(_data.route_names[r].replace(' - ', '-'));
				var reg_exp = /route=[^&]+/;
				var url = href.replace(reg_exp, 'route='+r);
				if (url.indexOf('route=') == -1) url += '&route='+r;
				a.attr('href', url);
				if (this.navigateTo)
				{
					a.click(this.navigateTo);
				}
				else
				{
					var that = this;
					a.click(function(e){
						that.json_url = that.json_url.replace(reg_exp, this.href.match(reg_exp)[0]);
						if (that.json_url.indexOf('route=') == -1) that.json_url += '&route='+r;
						that.update();
						e.preventDefault();
					});
				}
				li.append(a);
				toc.prepend(li);
			}
		}
		// only add toc, if we have more then one route
		if (!new_toc)
		{
			// already added
		}
		else if (toc.children().length)
		{
			jQuery(this.container).append(toc);
		}
		else
		{
			toc.remove();
		}
		// add category toc
		if (typeof _data.categorys == 'undefined') return;
		var toc = this.container.find('ul.listCatToc');
		var new_toc = !toc.length;
		if (new_toc)
			toc = jQuery(document.createElement('ul')).addClass('listCatToc');
		else
			toc.empty();
		var cats = this.shortenNames(_data.categorys, 'name');
		for(var i=0; i < cats.length; ++i)
		{
			var cat = cats[i];
			if (cat.GrpId != _data.GrpId)
			{
				var li = jQuery(document.createElement('li'));
				var a = jQuery(document.createElement('a'));
				a.text(cat.name);
				var reg_exp = /cat=[^&]+/;
				var url = href.replace(reg_exp, 'cat='+cat.GrpId);
				if (url.indexOf('cat=') == -1) url += '&cat='+cat.GrpId;
				a.attr('href', url);
				if (this.navigateTo)
				{
					a.click(this.navigateTo);
				}
				else
				{
					var that = this;
					a.click(function(e){
						that.json_url = that.json_url.replace(reg_exp, this.href.match(reg_exp)[0]);
						if (that.json_url.indexOf('cat=') == -1) that.json_url += 'cat='+cat.GrpId;
						that.update();
						e.preventDefault();
					});
				}
				li.append(a);
				toc.append(li);
			}
		}
		// only add toc, if we have more then one route
		if (!new_toc)
		{
			// already added
		}
		else if (toc.children().length)
		{
			jQuery(this.container).append(toc);
		}
		else
		{
			toc.remove();
		}
	};
	/**
	 * Shorten several names by removing parts common to all and remove spacing (eg. "W O M E N" --> "WOMEN")
	 *
	 * shortenNames["M E N speed", "W O M E N speed"]) returns ["MEN", "WOMEN"]
	 *
	 * @param {array}  names array of strings or objects with attribute attr
	 * @param {string} attr attribute name to use or undefined
	 * @return {array}
	 */
	Startlist.prototype.shortenNames = function(names, attr)
	{
		if (!jQuery.isArray(names) || !names.length) return names;
		var split_by_regexp = / +/;
		var spacing_regexp = /([A-Z]) ([A-Z])/;
		var strs = [];
		for(var i=0; i < names.length; ++i)
		{
			var name = names[i];
			if (attr) name = name[attr];
			do {
				var n = name;
				name = name.replace(spacing_regexp, '$1$2');
			} while (n != name);
			strs.push(name.split(split_by_regexp));
		}
		var first = [].concat(strs[0]);
		for(var i=0; i < first.length; ++i)
		{
			for(var j=1; j < strs.length; ++j)
			{
				if (jQuery.inArray(first[i], strs[j]) == -1)
				{
					break;
				}
			}
			if (j == strs.length)	// in all strings --> remove first[i] from all strings
			{
				for(var j=0; j < strs.length; ++j)
				{
					strs[j].splice(jQuery.inArray(first[i], strs[j]), 1);
				}
			}
		}
		for(var j=0; j < strs.length; ++j)
		{
			strs[j] = strs[j].join(' ');
			if (attr)
			{
				names[j][attr] = strs[j];
			}
			else
			{
				names[j] = strs[j];
			}
		}
		return names;
	};
	/**
	 * Set header with a (provisional) Result or Startlist prefix
	 *
	 * @param _data
	 * @return
	 */
	Startlist.prototype.setHeader = function(_data)
	{
		var title_prefix = (this.sort == 'start_order' ? 'Startlist' :
			(_data.route_result ? 'Result' : 'provisional Result'))+': ';

		var header = _data.route_name;
		// if NOT detail=0 and not for general result, add prefix before route name
		if (!this.json_url.match(/detail=0/) && _data.route_order != -1)
			header = title_prefix+header;

		document.title = header;

		this.comp_header.empty();
		this.comp_header.text(_data.comp_name);
		this.result_date.empty();
		if (_data.error)
		{
			this.result_date.text(_data.error);
			this.result_date.removeClass('resultDate');
			this.result_date.addClass('error');
		}
		else
		{
			this.result_date.text(_data.route_result);
		}
		this.header.empty();
		this.header.text(header);
	};
	/**
	 * Return the current scrolling position, which is the top of the current view.
	 */
	Startlist.prototype.currentTopPosition = function()
	{
		var y = 0;
		if (window.pageYOffset) {
			// all other browsers
			y = window.pageYOffset;
		} else if (document.body && document.body.scrollTop) {
			// IE
			y = document.body.scrollTop;
		}
		return y;
	};

	Startlist.prototype.upDown = function()
	{
		// check whether to sleep
		var now = new Date();
		var now_ms = now.getTime();
		if (now_ms < this.sleep_until) {
		    // sleep: in this case we do nothing
			return;
		}

		if (this.do_rotate) {
		    // we scheduled a rotation. Do it and then return.
			this.rotateURL();
		    // wait for the page to build
		    this.sleep_until = now.getTime() + 1000;
		    this.first_run = true;
		    this.do_rotate = false;
	            // reset scroll_by and scroll_interval, which might have been changed when the windows was resized.
		    this.scroll_by = 1;
		    this.scroll_interval = 20;
		    //console.log("reset scroll_by to " + this.scroll_by);
		    return;
		}

		// Get current position
		var y = 0;
		var viewHeight = window.innerHeight;
		var pageHeight = document.body.offsetHeight;

		y = this.currentTopPosition();

		// Do the scrolling
		window.scrollBy(0, this.scroll_by * this.scroll_dir);

		// Check, if scrolling worked
		var new_y = 0;
		new_y = this.currentTopPosition();
		if (y == new_y) {
			this.scroll_by += 1;
			//console.log("increased scroll_by to " + this.scroll_by);
			// reconfigure the scroll interval to maintain a constant speed
			this.scroll_interval *=  this.scroll_by;
			this.scroll_interval /= (this.scroll_by - 1);
			//console.log("scroll_interval is now " + this.scroll_interval + " ms");
			window.clearInterval(this.scroll_interval_handle);
			var list = this;
			this.scroll_interval_handle = window.setInterval(function() { list.upDown(); }, this.scroll_interval);
		}

	        // Set scrolling and sleeping parameters accordingly
		var scrollTopPosition = y;
		var scrollBottomPosition = y + viewHeight;
		//alert("pageYOffset(y)="+pageYOffset+", innerHeight(wy)="+innerHeight+", offsetHeight(dy)="+document.body.offsetHeight);
		var do_sleep = 0;
		if (pageHeight <= viewHeight) {
	        // No scrolling at all
			//console.log("Showing whole page");
	        do_sleep = 2;
	        this.do_rotate = true;
		} else if (( this.scroll_dir != -1) && (pageHeight - scrollBottomPosition <= this.margin)) {
			// UP
			this.scroll_dir = -1;
			this.first_run = false;
			do_sleep = 1;
		} else if (( this.scroll_dir != 1) && (scrollTopPosition <= this.margin)) {
			// DOWN
			this.scroll_dir = 1;
			if (! this.first_run ) {
			    do_sleep = 1;
			    this.do_rotate = true;
			}
		}

		// Arm the sleep timer
		//if (do_sleep > 0) { console.log("Sleeping for " + do_sleep * this.sleep_for + " seconds"); }
	    this.sleep_until = now.getTime() + (this.sleep_for * 1000 * do_sleep);

	};

	Startlist.prototype.rotateURL = function() {
		var rotate_url_matches = this.json_url.match(/rotate=([^&]+)/);
		if (rotate_url_matches) {
		    var urls = rotate_url_matches[1];
		    //console.log(urls);

		    var current_comp = this.json_url.match(/comp=([^&]+)/)[1];
		    var current_cat = this.json_url.match(/cat=([^&]+)/)[1];
		    var current_route = this.json_url.match(/route=([^&]+)/)[1];
		    //console.log(current_cat);

		    var next = urls.match("(?:^|:|w=" + current_comp + ",)" + "c=" + current_cat + ",r=" + current_route + ":(?:w=([0-9_a-z]+),)?" +  "c=([0-9_a-z]+),r=(-?[\\d]+)");
		    //console.log(next);
		    if (! next) {
		        // at the end of the list, take the first argument
		        next = urls.match("^(?:w=([0-9_a-z]+),)?" + "c=([0-9_a-z]+),r=(-?[\\d]+)");
			//console.log("starting over");
			//console.log(next);
		    }

		    // We might not find a next competition in the rotate parameter
		    var next_comp = current_comp;
		    if (next[1]) {
		    	next_comp = next[1];
		    }

		    // Extract category and route
		    var next_cat = next[2];
		    var next_route = next[3];
		    //console.log("current_cat = " + current_cat + ", current_route = " + current_route + ", next_cat = " + next_cat + ", next_route = " + next_route);
		    this.json_url = this.json_url.replace(/comp=[0-9_a-z]+/, "comp=" + next_comp);
		    this.json_url = this.json_url.replace(/cat=[0-9_a-z]+/, "cat=" + next_cat);
		    this.json_url = this.json_url.replace(/route=[\d]+/, "route=" + next_route);
		    //console.log(this.json_url);

		    // cancel the currently pending request before starting a new one.
		    window.clearTimeout(this.update_handle);
		    this.update();
		}
	};
	return Startlist;
})();

/**
 * Resultlist widget inheriting from Startlist
 */
var Resultlist = (function() {
	/**
	 * Constructor for result from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param {boolean} _no_navigation
	 */
	function Resultlist(_container, _json_url, _no_navigation)
	{
		Startlist.prototype.constructor.call(this, _container, _json_url, _no_navigation);
	}
	// inherit from Startlist
	Resultlist.prototype = new Startlist();
	Resultlist.prototype.constructor = Resultlist;

	/**
	 * Callback for loading data via ajax
	 *
	 * Reimplemented to use different columns depending on discipline
	 *
	 * @param _data route data object
	 */
	Resultlist.prototype.handleResponse = function(_data)
	{
		var detail = this.json_url.match(/detail=([^&]+)/);

		switch(_data.discipline)
		{
			case 'speedrelay':
				this.result_cols = !detail ? {	// default detail
					'result_rank': 'Rank',
					'team_name': 'Teamname',
					'athletes/0/lastname': 'Athlete #1',
					'athletes/1/lastname': 'Athlete #2',
					'athletes/2/lastname': 'Athlete #3',
					'result': 'Sum'
				} : (detail[1] == '1' ? {	// detail=1
					'result_rank': 'Rank',
					'team_name': 'Teamname',
					//'team_nation': 'Nation',
					'athletes/0/lastname': {'label': 'Athlete #1', 'colspan': 3},
					'athletes/0/firstname': '',
					'athletes/0/result_time': '',
					'athletes/1/lastname': {'label': 'Athlete #2', 'colspan': 3},
					'athletes/1/firstname': '',
					'athletes/1/result_time': '',
					'athletes/2/lastname': {'label': 'Athlete #3', 'colspan': 3},
					'athletes/2/firstname': '',
					'athletes/2/result_time': '',
					'result': 'Sum'
				} : {	// detail=0
					'result_rank': 'Rank',
					'team_name': 'Teamname',
					'team_nation': 'Nation',
					'result': 'Sum'
				});
				break;

			case 'ranking':
				this.result_cols = {
					'result_rank': 'Rank',
					'lastname' : {'label': 'Name', 'colspan': 2},
					'firstname' : '',
					'nation' : 'Nation',
					'points': 'Points',
					'result' : 'Result'
				};
				// default columns for SUI ranking with NO details
				if ((!detail || detail[1] == '0') && _data.nation == 'SUI')
				{
					this.result_cols = {
						'result_rank': 'Rank',
						'lastname' : {'label': 'Name', 'colspan': 2},
						'firstname' : '',
						'birthyear': 'Agegroup',
						'city': 'City',
						'federation' : 'Sektion',
						'rgz': 'Regionalzentrum',
						'points': 'Points',
						'result' : 'Result'
					};
				}
				if ((!detail || detail[1] == '0') && _data.participants[0] && _data.participants[0].result_rank)
				{
					delete this.result_cols.result;
					// allow to click on points to show single results
					this.result_cols.points = {
						label: this.result_cols.points,
						url: location.href+'&detail=1'
					};
					// add calculation to see-also links
					if (typeof _data.see_also == 'undefined') _data.see_also = [];
					_data.see_also.push({
						name: 'calculation of this ranking',
						url: location.href+'&detail=1'
					});
				}
				break;

			default:
				// default columns for SUI ranking with NO details
				if ((!detail || detail[1] == '0') && _data.nation == 'SUI')
				{
					this.result_cols = {
						'result_rank': 'Rank',
						'lastname' : {'label': 'Name', 'colspan': 2},
						'firstname' : '',
						'birthyear': 'Agegroup',
						'city': 'City',
						'federation' : 'Sektion',
						'rgz': 'Regionalzentrum',
						'result' : 'Result'
					};
				}
				else
				{
					this.result_cols = detail && detail[1] == '0' ? {
						'result_rank': 'Rank',
						'lastname' : {'label': 'Name', 'colspan': 2},
						'firstname' : '',
						'nation' : 'Nation',
						'result': 'Result'
					} : {
						'result_rank': 'Rank',
						'lastname' : {'label': 'Name', 'colspan': 2},
						'firstname' : '',
						'nation' : 'Nation',
						'start_number': 'StartNr',
						'result': 'Result'
					};
				}
				// for boulder heats use new display, but not for general result!
				if (_data.discipline.substr(0, 7) == 'boulder' && _data.route_order != -1)
				{
					delete this.result_cols.result;
					var that = this;
					var num_problems = parseInt(_data.route_num_problems);
					this.result_cols.boulder = function(_data,_tag){
						return that.getBoulderResult.call(that, _data, _tag, num_problems);
					};
					//Resultlist.prototype.getBoulderResult;
					if (!detail || detail[1] != 0) this.result_cols.result = 'Sum';
				}
				break;
		}
		// remove start-number column if no start-numbers used (determined on first participant only)
		if (typeof this.result_cols.start_number != 'undefined' && !_data.participants[0].start_number)
		{
			delete this.result_cols.start_number;
		}
		Startlist.prototype.handleResponse.call(this, _data);
		align_td_nbsp('table.DrTable td');

		if (_data.discipline == 'ranking' && !_data.error &&
			(detail && detail[1] == '1' || !_data.participants[0].result_rank) && (_data.max_comp || _data.max_disciplines))
		{
			var tfoot = jQuery(document.createElement('tfoot'));
			jQuery(this.table.dom).append(tfoot);
			var th = jQuery(document.createElement('th'));
			tfoot.append(jQuery(document.createElement('tr')).append(th));
			var cols=0; for(var c in this.result_cols) cols++;
			th.attr('colspan', cols);
			th.attr('class', 'footer');
			var max_disciplines = '';
			if (_data.max_disciplines)
			{
				for(var discipline in _data.max_disciplines)
				{
					max_disciplines += (max_disciplines?', ':'')+discipline[0].toUpperCase()+discipline.slice(1)+': '+_data.max_disciplines[discipline];
				}
			}
			if (_data.nation)
			{
				th.html((_data.max_comp ? 'Für '+(_data.cup ? 'den '+_data.cup.name : 'die Rangliste')+' zählen die '+_data.max_comp+' besten Ergebnisse. ' : '')+
					(max_disciplines ? ' Maximal zählende Ergebnisse pro Disziplin: '+max_disciplines+'. ' : '')+
					'Nicht zählende Ergebnisse sind eingeklammert. '+
					(_data.min_disciplines ? '<br/>Teilnahme an mindestens '+_data.min_disciplines+' Disziplinen ist erforderlich. ' : '')+
					(_data.drop_equally ? 'Streichresultate erfolgen in allen Disziplinen gleichmäßig. ' : ''));
			}
			else
			{
				th.html((_data.max_comp ? _data.max_comp+' best competition results are counting for '+(_data.cup ? _data.cup.name : 'the ranking')+'. ' : '')+
					(max_disciplines ? 'Maximum number of counting results per discipline: '+max_disciplines+'. ' : '')+
					'Not counting points are in brackets. '+
					(_data.min_disciplines ? '<br/>Participation in at least '+_data.min_disciplines+' disciplines is required.' : '')+
					(_data.drop_equally ? 'Not counting results are selected from all disciplines equally.' : ''));
			}
		}
		if (_data.statistics && (_data.discipline == 'selfscore' || this.json_url.match('&stats=')))
		{
			if (!jQuery('#jqplot-css').length)
			{
				var ranking_url = this.json_url.replace(/json.php.*$/, '');
				var jqplot_url = this.json_url.replace(/ranking\/json.php.*$/, '')+'vendor/npm-asset/as-jqplot/dist/';
				jQuery('<link/>', {
					id: 'jqplot-css',
					href: jqplot_url+'jquery.jqplot.min.css',
					type: 'text/css'
				}).appendTo('head');
				var load = [jqplot_url+'jquery.jqplot.min.js',
					// not sure why bar-renderer does not work :(
					//jqplot_url+'plugins/jqplot.barRenderer.min.js',
					jqplot_url+'plugins/jqplot.highlighter.min.js',
					ranking_url+'js/dr_statistics.js?'+_data.dr_statistics];
				for(var i=0; i < load.length; ++i)
				{
					load[i] = jQuery.ajax({
						url: load[i],
						dataType: "script",
						cache: true	// no cache buster!
					});
				}
				var container = this.container;
				jQuery.when.apply(jQuery, load).done(function(){
					dr_statistics(container, _data);
				});
				// dono why, but above done is not always executed, if files are already cached
				window.setTimeout(function(){
					typeof window.dr_statistics != 'undefined' && dr_statistics(container, _data);
				}, 100);
			}
			else
			{
				dr_statistics(this.container, _data);
			}
		}
	};

	/**
	 * Get DOM nodes for display of graphical boulder-result
	 *
	 * @param _data
	 * @param _tag 'th' for header, 'td' for data rows
	 * @param _num_problems
	 * @return DOM node
	 */
	Resultlist.prototype.getBoulderResult = function(_data,_tag,_num_problems)
	{
		if (_tag == 'th') return 'Result';

		var tag = document.createElement('div');

		for(var i=1; i <= _num_problems; ++i)
		{
			var boulder = document.createElement('div');
			var result = _data['boulder'+i];
			if (result && result != 'z0' && result != 'b0')
			{
				var top_tries = result.match(/t([0-9]+)/);
				var bonus_tries = result.match(/(b|z)([0-9]+)/);
				if (top_tries)
				{
					boulder.className = 'boulderTop';
					var top_text = document.createElement('div');
					top_text.className = 'topTries';
					jQuery(top_text).text(top_tries[1]);
					jQuery(boulder).append(top_text);
				}
				else
				{
					boulder.className = 'boulderBonus';
				}
				var bonus_text = document.createElement('div');
				bonus_text.className = 'bonusTries';
				jQuery(bonus_text).text(bonus_tries[2]);
				jQuery(boulder).append(bonus_text);
			}
			else
			{
				boulder.className = result ? 'boulderNone' : 'boulder';
			}
			jQuery(tag).append(boulder);
		}
		return tag;
	};
	return Resultlist;
})();

/**
 * Results widget inheriting from DrBaseWidget
 */
var Results = (function() {
	/**
	 * Constructor for results from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param {boolean} _no_navigation do not show competition chooser
	 */
	function Results(_container,_json_url, _no_navigation)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);
		this.no_navigation = _no_navigation;

		this.update();
	}
	// inherite from DrBaseWidget
	Results.prototype = new DrBaseWidget();
	Results.prototype.constructor = Results;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Results.prototype.handleResponse = function(_data)
	{
		this.columns = {
			'result_rank': 'Rank',
			'lastname' : {'label': 'Name', 'colspan': 2},
			'firstname' : '',
			'nation' : 'Nation'
		};
		this.replace_nation(_data.display_athlete, _data.nation);

		if (typeof this.table == 'undefined')
		{
			// competition chooser
			if (!this.no_navigation)
			{
				this.comp_chooser = jQuery(document.createElement('select'));
				this.comp_chooser.addClass('compChooser');
				this.container.append(this.comp_chooser);
				var that = this;
				this.comp_chooser.change(function(e){
					that.json_url = that.json_url.replace(/comp=[^&]+/, 'comp='+this.value);
					if (that.navigateTo)
						that.navigateTo(that.json_url);
					else
						that.update();
				});
			}
			// competition
			this.comp_header = jQuery(document.createElement('h1'));
			this.comp_header.addClass('compHeader');
			this.container.append(this.comp_header);
			// result date
			this.comp_date = jQuery(document.createElement('h3'));
			this.comp_date.addClass('resultDate');
			this.container.append(this.comp_date);
		}
		else
		{
			jQuery(this.table.dom).remove();
			if (!this.no_navigation) this.comp_chooser.empty();
			this.comp_header.empty();
			this.comp_date.empty();
		}
		// fill competition chooser
		if (!this.no_navigation)
		{
			var option = jQuery(document.createElement('option'));
			option.text('Select another competition ...');
			this.comp_chooser.append(option);
			for(var i=0; i < _data.competitions.length; ++i)
			{
				var competition = _data.competitions[i];
				if (_data.WetId == competition.WetId) continue;	// we dont show current competition
				option = jQuery(document.createElement('option'));
				option.attr({'value':  competition.WetId, 'title': competition.date_span});
				option.text(competition.name);
				this.comp_chooser.append(option);
			}
		}
		this.comp_header.text(_data.name);
		this.comp_date.text(_data.date_span);

		for(var i=0; i < _data.categorys.length; ++i)
		{
			var cat = _data.categorys[i];
			var that = this;
			cat.click = function(e) {
				that.showCompleteResult(e);
			};
		}

		// create new table
		this.table = new DrTable(_data.categorys,this.columns,'result_rank',true,null,this.navigateTo);

		this.container.append(this.table.dom);

		this.seeAlso(_data.see_also);
	};
	/**
	 * Switch from Results (of all categories) to Resultlist (of a single category)
	 *
	 * @param e
	 */
	Results.prototype.showCompleteResult = function(e)
	{
		this.container.empty();
		this.container.removeClass('Results');
		new Resultlist(this.container, this.json_url.replace(/\?.*$/, e.target.href.match(/\?.*$/)[0]));
		e.preventDefault();
	};
	return Results;
})();

/**
 * Starters / registration widget inheriting from DrBaseWidget
 */
var Starters = (function() {
	/**
	 * Constructor for results from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 */
	function Starters(_container,_json_url)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);

		this.update();
	}
	// inherite from DrBaseWidget
	Starters.prototype = new DrBaseWidget();
	Starters.prototype.constructor = Starters;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Starters.prototype.handleResponse = function(_data)
	{
		this.data = _data;
		if (typeof this.table == 'undefined')
		{
			// competition
			this.comp_header = jQuery(document.createElement('h1'));
			this.comp_header.addClass('compHeader');
			this.container.append(this.comp_header);
			// result date
			this.comp_date = jQuery(document.createElement('h3'));
			this.comp_date.addClass('resultDate');
			this.container.append(this.comp_date);
		}
		else
		{
			delete this.table;
			this.comp_header.empty();
			this.comp_date.empty();
		}
		this.comp_header.text(_data.name+' : '+_data.date_span);
		if (_data.deadline) this.comp_date.text('Deadline: '+_data.deadline);

		this.table = jQuery(document.createElement('table')).addClass('DrTable');
		this.container.append(this.table);
		var thead = jQuery(document.createElement('thead'));
		this.table.append(thead);

		// create header row
		var row = jQuery(document.createElement('tr'));
		var th = jQuery(document.createElement('th'));
		th.text(typeof _data.federations != 'undefined' ? 'Federation' : 'Nation');
		if (!this.json_url.match(/no_fed=1/)) row.append(th);
		var cats = {};
		for(var i=0; i < _data.categorys.length; ++i)
		{
			var th = jQuery(document.createElement('th'));
			th.addClass('category');
			th.text(_data.categorys[i].name);
			row.append(th);
			cats[_data.categorys[i].GrpId] = i;
		}
		thead.append(row);

		var tbody = jQuery(document.createElement('tbody'));
		this.table.append(tbody);

		var fed;
		this.fed_rows = [];
		this.fed_rows_pos = [];
		var num_competitors = 0;
		for(var i=0; i < _data.athletes.length; ++i)
		{
			var athlete = _data.athletes[i];
			// evtl. create new row for federation/nation
			if ((typeof fed == 'undefined' || fed != athlete.reg_fed_id) && !this.json_url.match(/no_fed=1/))
			{
				this.fillUpFedRows();
				// reset fed rows to empty
				this.fed_rows = [];
				this.fed_rows_pos = [];
			}
			// find rows with space in column of category
			var cat_col = cats[athlete.cat];
			for(var r=0; r < this.fed_rows.length; ++r)
			{
				if (this.fed_rows_pos[r] <= cat_col) break;
			}
			if (r == this.fed_rows.length)	// create a new fed-row
			{
				row = jQuery(document.createElement('tr'));
				tbody.append(row);
				th = jQuery(document.createElement('th'));
				if (!this.json_url.match(/no_fed=1/)) row.append(th);
				this.fed_rows.push(row);
				this.fed_rows_pos.push(0);
				if (typeof fed == 'undefined' || fed != athlete.reg_fed_id)
				{
					fed = athlete.reg_fed_id;
					th.text(this.federation(athlete.reg_fed_id));
					th.addClass('federation');
				}
			}
			this.fillUpFedRow(r, cat_col);
			// create athlete cell
			var td = jQuery(document.createElement('td'));
			td.addClass('athlete');
			var lastname = jQuery(document.createElement('span')).addClass('lastname').text(athlete.lastname);
			var firstname = jQuery(document.createElement('span')).addClass('firstname').text(athlete.firstname);
			td.append(lastname).append(firstname);
			this.fed_rows[r].append(td);
			this.fed_rows_pos[r]++;
			// do not count
			if (athlete.cat != 120) num_competitors++;
		}
		this.fillUpFedRows();

		var tfoot = jQuery(document.createElement('tfoot'));
		this.table.append(tfoot);
		var th = jQuery(document.createElement('th'));
		tfoot.append(jQuery(document.createElement('tr')).append(th));
		th.attr('colspan', 1+_data.categorys.length);
		th.text('Total of '+num_competitors+' athletes registered in all categories.');
	};
	/**
	 * Fill a single fed-row up to a given position with empty td's
	 *
	 * @param {number} _r row-number
	 * @param {number} _to column-number, default whole row
	 */
	Starters.prototype.fillUpFedRow = function(_r, _to)
	{
		if (typeof _to == 'undefined') _to = this.data.categorys.length;
		while (this.fed_rows_pos[_r] < _to)
		{
			var td = jQuery(document.createElement('td'));
			this.fed_rows[_r].append(td);
			this.fed_rows_pos[_r]++;
		}
	};
	/**
	 * Fill up all fed rows with empty td's
	 */
	Starters.prototype.fillUpFedRows = function()
	{
		for(var r=0; r < this.fed_rows.length; ++r)
		{
			this.fillUpFedRow(r);
		}
	};
	/**
	 * Get name of federation specified by given id
	 *
	 * @param _fed_id
	 * @returns string with name
	 */
	Starters.prototype.federation = function(_fed_id)
	{
		if (typeof this.data.federations == 'undefined')
		{
			return _fed_id;	// nation of int. competition
		}
		for(var i=0; i < this.data.federations.length; ++i)
		{
			var fed = this.data.federations[i];
			if (fed.fed_id == _fed_id) return fed.shortcut || fed.name;
		}
	};
	return Starters;
})();

/**
 * Profile widget inheriting from DrBaseWidget
 */
var Profile = (function() {
	/**
	 * Constructor for profile from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param _template optional string with html-template
	 * @param _remove_leading_slash fix Joomla behavior of adding a slash to <a href="$$something$$"
	 */
	function Profile(_container,_json_url,_template,_remove_leading_slash)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);

		if (_template) this.template = _template;

		if (typeof _remove_leading_slash == 'undefined') _remove_leading_slash = false;
		this.pattern = new RegExp((_remove_leading_slash?'/?':'')+'\\$\\$([^$]+)\\$\\$', 'g');
		this.pattern_results = new RegExp((_remove_leading_slash?'/?':'')+'\\$\\$results\/N\/([^$]+)\\$\\$', 'g');

		this.container.empty();

		this.bestResults = 12;

		this.update();
	}
	// inherite from DrBaseWidget
	Profile.prototype = new DrBaseWidget();
	Profile.prototype.constructor = Profile;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Profile.prototype.handleResponse = function(_data)
	{
		// replace non-result data
		var that = this;
		var html = this.template.replace(this.pattern, function(match, placeholder)
		{
			switch(placeholder)
			{
				case 'categoryChooser':
					var select = '<select class="chooseCategory">\n';
					for(var i=0; i < _data.categorys.length; ++i)
					{
						var cat = _data.categorys[i];
						select += '<option value="'+cat.GrpId+'"'+(cat.GrpId==_data.GrpId?' selected':'')+'>'+cat.name+'</option>\n';
					}
					select += '</select>\n';
					return select;
			}
			var parts = placeholder.split('/');
			var data = _data;
			for(var i=0; i < parts.length; ++i)
			{
				if (typeof data[parts[i]] == 'undefined' || !data[parts[i]])
				{
					return parts[i] === 'N' ? match : '';
				}
				data = data[parts[i]];
			}
			switch(placeholder)
			{
				case 'practice':
					data += ' years, since '+((new Date).getFullYear()-data);
					break;
				case 'height':
					data += ' cm';
					break;
				case 'weight':
					data += ' kg';
					break;
			}
			return data;
		});
		// replace result data
		var bestResults = this.bestResults;
		var that = this;
		html = html.replace(/[\s]*<tr[\s\S]*?<\/tr>\n?/g, function(match)
		{
			if (match.indexOf('$$results/N/') == -1) return match;

			// find and mark N best results
			var year = (new Date).getFullYear();
			var limits = [];
			for(var i=0; i < _data.results.length; ++i)
			{
				var result = _data.results[i];
				result.weight = result.rank/2 + (year-parseInt(result.date)) + 4*!result.nation;
				// maintain array of N best competitions (least weight)
				if (limits.length < bestResults || result.weight < limits[limits.length-1])
				{
					var limit=0;
					for(var l=0; l < limits.length; ++l)
					{
						limit = limits[l];
						if (limit > result.weight) break;
					}
					if (limit < result.weight && l == limits.length-1) l = limits.length;
					limits = limits.slice(0, l).concat([result.weight]).concat(limits.slice(l, bestResults-1-l));
				}
			}
			var weight_limit = limits.pop();

			var rows = '';
			var l = 0;
			for(var i=0; i < _data.results.length; ++i)
			{
				var result = _data.results[i];
				if (match.indexOf('$$results/N/weightClass$$') >= 0 &&
					(result.weight > weight_limit || ++l > bestResults))
				{
					result.weightClass = 'profileResultHidden';
				}
				rows += match.replace(that.pattern_results, function(match, placeholder)
				{
					switch (placeholder)
					{
						case 'cat_name+name':
							return (result.GrpId != _data.GrpId ? result.cat_name+': ' : '')+result.name;
						case 'date':
							return that.formatDate(result.date);
						default:
							return typeof result[placeholder] != 'undefined' ? result[placeholder] : '';
					}
				});
			}
			return rows;
		});
		this.container.html(html);
		// remove links with empty href
		this.container.find('a[href=""]').replaceWith(function(){
			return jQuery(this).contents();
		});
		// remove images with empty src
		this.container.find('img[src=""]').remove();
		// hide rows with profileHideRowIfEmpty, if ALL td.profileHideRowIfEmpty are empty
		this.container.find('tr.profileHideRowIfEmpty').each(function(index, row){
			var tds = jQuery(row).children('td.profileHideRowIfEmpty');
			if (tds.length == tds.filter(':empty').length)
			{
				jQuery(row).hide();
			}
		});
		// install click handler from DrWidget
		if (this.navigateTo) this.container.find('.profileData a:not(a[href^="javascript:"])').click(this.navigateTo);
		// bind chooseCategory handler (works with multiple templates)
		var that = this;
		this.container.find('select.chooseCategory').change(function(e)
		{
			that.chooseCategory.call(that, this.value);
			e.stopImmediatePropagation();
			return false;
		});
	};
	/**
	 * toggle between best results and all results
	 */
	Profile.prototype.toggleResults = function()
	{
		var hidden_rows = this.container.find('tr.profileResultHidden');
		var display = hidden_rows.length ? jQuery(hidden_rows[0]).css('display') : 'none';
		hidden_rows.css('display', display == 'none' ? 'table-row' : 'none');
	};
	/**
	 * choose a given category for rankings
	 *
	 * @param {string} GrpId
	 */
	Profile.prototype.chooseCategory = function(GrpId)
	{
		var cat_regexp = /([#&])cat=([^&]+)/;
		function replace_cat(str, GrpId)
		{
			if (str.match(cat_regexp))
			{
				return str.replace(cat_regexp, '$1cat='+GrpId);
			}
			return str+'&cat='+GrpId;
		}
		location.hash = replace_cat(location.hash, GrpId);
		this.json_url = replace_cat(this.json_url, GrpId);
		this.update();
	};
	/**
	 * Default template for Profile widget
	 */
	Profile.prototype.template =
		'<div>\n'+
		'<table class="profileHeader">\n'+
		' <thead>\n'+
		'  <tr>\n'+
		'   <td class="profilePhoto"><img src="$$photo$$" border="0"></td>\n'+
		'   <td>\n'+
		'  	<h1><a href="$$homepage$$" target="_blank">\n'+
		'	  <span class="firstname">$$firstname$$</span>\n'+
		'	  <span class="lastname">$$lastname$$</span>\n'+
		'  	</a></h1>\n'+
		'    <h2 class="profileNation">$$nation$$</h1>\n'+
		'    <h3 class="profileFederation"><a href="$$fed_url$$" target="_blank">$$federation$$</a></h1>\n'+
		'   </td>\n'+
		'   <td class="profileLogo"><a href="http://www.digitalROCK.de" target=_blank><img src="http://www.digitalrock.de/dig_rock-155x100.png" title="digital ROCK\'s Homepage" /></a></td>\n'+
		'  </tr>\n'+
		' </thead>\n'+
		'</table>\n'+
		'<table cols="6" class="profileData">\n'+
		'  <thead>\n'+
		'	<tr>\n'+
		'		<td>age:</td>\n'+
		'		<td class="profileAge">$$age$$</td>\n'+
		'		<td>year of birth:</td>\n'+
		'		<td colspan="3" class="profileBirthdate">$$birthdate$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileHideRowIfEmpty">\n'+
		'		<td colspan="2"></td>\n'+
		'		<td>place of birth:</td>\n'+
		'		<td colspan="3" class="profileBirthplace profileHideRowIfEmpty">$$birthplace$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileHideRowIfEmpty">\n'+
		'		<td>height:</td>\n'+
		'		<td class="profileHeight profileHideRowIfEmpty">$$height$$</td>\n'+
		'		<td>weight:</td>\n'+
		'		<td colspan="3" class="profileWeight profileHideRowIfEmpty">$$weight$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileMarginTop profileHideRowIfEmpty">\n'+
		'		<td>address:</td>\n'+
		'		<td colspan="2" class="profileCity profileHideRowIfEmpty">$$postcode$$ $$city$$</td>\n'+
		'		<td colspan="3" class="profileStreet profileHideRowIfEmpty">$$street$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileMarginTop profileHideRowIfEmpty">\n'+
		'		<td colspan="2">practicing climbing for:</td>\n'+
		'		<td colspan="4" class="profilePractice profileHideRowIfEmpty">$$practice$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileHideRowIfEmpty">\n'+
		'		<td colspan="2">professional climber (if not, profession):</td>\n'+
		'		<td colspan="4" class="profileProfessional profileHideRowIfEmpty">$$professional$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileHideRowIfEmpty">\n'+
		'		<td colspan="2">other sports practiced:</td>\n'+
		'		<td colspan="4" class="profileOtherSports profileHideRowIfEmpty">$$other_sports$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileMarginTop profileHideRowIfEmpty">\n'+
		'		<td colspan="6" class="profileFreetext profileHideRowIfEmpty">$$freetext$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileMarginTop">\n'+
		'		<td colspan="6">Category: $$categoryChooser$$</td>\n'+
		'	</tr>\n'+
		'	<tr>\n'+
		'		<td colspan="2" class="profileRanglist"><a href="$$rankings/0/url$$">$$rankings/0/name$$</a>:</td>\n'+
		'		<td class="profileRank">$$rankings/0/rank$$</td>\n'+
		'		<td colspan="2" class="profileRanglist"><a href="$$rankings/1/url$$">$$rankings/1/name$$</a>:</td>\n'+
		'		<td class="profileRank">$$rankings/1/rank$$</td>\n'+
		'	</tr>\n'+
		'	<tr>\n'+
		'		<td colspan="2" class="profileRanglist"><a href="$$rankings/2/url$$">$$rankings/2/name$$</a>:</td>\n'+
		'		<td class="profileRank">$$rankings/2/rank$$</td>\n'+
		'		<td colspan="2" class="profileRanglist"><a href="$$rankings/3/url$$">$$rankings/3/name$$</a>:</td>\n'+
		'		<td class="profileRank">$$rankings/3/rank$$</td>\n'+
		'	</tr>\n'+
		'	<tr class="profileResultHeader profileMarginTop">\n'+
		'		<td colspan="6"><a href="javascript:widget.widget.toggleResults()" title="click to toggle between best results and all results">best results / all results:</a></td>\n'+
		'	</tr>\n'+
		'   </thead>\n'+
		'   <tbody>\n'+
		'	<tr class="profileResult $$results/N/weightClass$$">\n'+
		'		<td class="profileResultRank">$$results/N/rank$$</td>\n'+
		'		<td colspan="4" class="profileResultName"><a href="$$results/N/url$$">$$results/N/cat_name+name$$</a></td>\n'+
		'		<td class="profileResultDate">$$results/N/date$$</td>\n'+
		'	</tr>\n'+
		'  </tbody>\n'+
		'</table>\n'+
		'</div>\n';
	return Profile;
})();


/**
 * ResultTemplate widget inheriting from DrBaseWidget
 */
var ResultTemplate = (function() {
	/**
	 * Constructor for ResultTemplate from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param _template optional string with html-template
	 */
	function ResultTemplate(_container,_json_url,_template)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);

		if (_template)
			this.template = _template;
		else
			this.template = this.container.html();
		this.container.empty();

		this.update();
	}
	// inherite from DrBaseWidget
	ResultTemplate.prototype = new DrBaseWidget();
	ResultTemplate.prototype.constructor = ResultTemplate;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	ResultTemplate.prototype.handleResponse = function(_data)
	{
		// if route is NOT offical, update list every 10 sec
		if (!_data.route_result && typeof this.update_handle == 'undefined')
		{
			var list = this;
			this.update_handle = window.setInterval(function(){
				list.update();
			},10000);
		}
		// if route is offical stop reload
		else if (_data.route_result && this.update_handle)
		{
			window.clearInterval(this.update_handle);
			delete this.update_handle;
		}

		// replace non-result data
		var pattern = /\$\$([^$]+)\$\$/g;
		var html = this.template.replace(pattern, function(match, placeholder)
		{
			var parts = placeholder.split('/');
			var data = _data;
			for(var i=0; i < parts.length; ++i)
			{
				if (typeof data[parts[i]] == 'undefined' || !data[parts[i]])
				{
					return parts[i] === 'N' ? match : '';
				}
				data = data[parts[i]];
			}
			switch(placeholder)
			{
			}
			return data;
		});
		// replace result data
		pattern = /\$\$participants\/N\/([^$]+)\$\$/g;
		html = html.replace(/[\s]*<tr[\s\S]*?<\/tr>\n?/g, function(match)
		{
			if (match.indexOf('$$participants/N/') == -1) return match;

			var rows = '';
			for(var i=0; i < _data.participants.length; ++i)
			{
				var result = _data.participants[i];
				rows += match.replace(pattern, function(match, placeholder)
				{
					switch (placeholder)
					{
						default:
							return typeof result[placeholder] != 'undefined' ? result[placeholder] : '';
					}
				});
			}
			return rows;
		});

		// replace container
		this.container.html(html);
	};
	return ResultTemplate;
})();

/**
 * Competitions / calendar widget inheriting from DrBaseWidget
 */
var Competitions = (function() {
	/**
	 * Constructor for ompetitions / calendar from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param _filters object with filters and optional _comp_url
	 *		{string} _filters._comp_url url to use as link with added WetId for competition name
	 */
	function Competitions(_container,_json_url,_filters)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);
		if (typeof _filters != 'undefined')
		{
			this.filters = _filters;
			if (typeof _filters._comp_url != 'undefined')
			{
				this.comp_url = _filters._comp_url;
				delete this.filters._comp_url;
			}
			if (typeof _filters._comp_url_label != 'undefined')
			{
				this.comp_url_label = _filters._comp_url_label;
				delete this.filters._comp_url_label;
			}
		}
		this.year_regexp = /([&?])year=(\d+)/;

		this.update();
	}
	// inherite from DrBaseWidget
	Competitions.prototype = new DrBaseWidget();
	Competitions.prototype.constructor = Competitions;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Competitions.prototype.handleResponse = function(_data)
	{
		this.container.empty();

		var year = this.json_url.match(this.year_regexp);
		year = year ? parseInt(year[2]) : (new Date).getFullYear();
		var h1 = jQuery(document.createElement('h1')).text('Calendar '+year);
		this.container.append(h1);

		var filter = jQuery(document.createElement('div')).addClass('filter');
		var select = jQuery(document.createElement('select')).attr('name', 'year');
		var years = _data.years || [year+1, year, year-1];
		for(var i=0; i < years.length; ++i)
		{
			var y = years[i];
			var option = jQuery(document.createElement('option')).attr('value', y);
			option.text(y);
			if (year == y) option.attr('selected', 'selected');
			select.append(option);
		}
		var that=this;
		select.change(function(e) {
			that.changeYear(this.value);
		});
		select.attr('style', 'margin-right: 5px');
		filter.append(select);

		if (typeof this.filters != 'undefied' && !jQuery.isEmptyObject(this.filters))
		{
			select = jQuery(document.createElement('select')).attr('name', 'filter');
			for(var f in this.filters)
			{
				var option = jQuery(document.createElement('option')).attr('value', this.filters[f]);
				option.text(f);
				if (decodeURI(this.json_url).indexOf(this.filters[f]) != -1) option.attr('selected', 'selected');
				select.append(option);
			}
			select.change(function(e) {
				that.changeFilter(this.value);
			});
			filter.append(select);
		}
		this.container.append(filter);
		var competitions = jQuery(document.createElement('div')).addClass('competitions');
		this.container.append(competitions);
		var now = new Date();
		// until incl. Wednesday (=3) we display competitions from last week first, after that from this week
		var week_to_display = now.getWeek() - (now.getDay() <= 3 ? 1 : 0);
		var closest, closest_dist;

		for(var i=0; i < _data.competitions.length; ++i)
		{
			var competition = _data.competitions[i];

			var comp_div = jQuery(document.createElement('div')).addClass('competition');
			var title = jQuery(document.createElement('div')).addClass('title').text(competition.name);
			if (this.comp_url)
			{
				title = jQuery(document.createElement('a')).attr({href: this.comp_url+competition.WetId}).append(title);
			}
			comp_div.append(title);
			comp_div.append(jQuery(document.createElement('div')).addClass('date').text(competition.date_span));
			var cats_ul = jQuery(document.createElement('ul')).addClass('cats');
			var have_cats = false;
			var links = { 'homepage': 'Event Website', 'info': 'Regulation', 'info2': 'Info Sheet', 'startlist': 'Startlist', 'result': 'Result' };
			// add comp_url as first link with given label
			if (this.comp_url && this.comp_url_label)
			{
				links = jQuery.extend({comp_url: this.comp_url_label}, links);
				competition.comp_url = this.comp_url+competition.WetId;
			}
			if (typeof competition.cats == 'undefined') competition.cats = [];
			for(var c=0; c < competition.cats.length; ++c)
			{
				var cat = competition.cats[c];
				var url = '';
				if (typeof cat.status != 'undefined')
				{
					switch(cat.status)
					{
						case 4:	// registration
							links.starters = 'Starters';
							competition.starters = '#!type=starters&comp='+competition.WetId;
							break;
						case 2:	// startlist in result-service
						case 1:	// result in result-service
						case 0:	// result in ranking (ToDo: need extra export, as it might not be in result-service)
							url = '#!comp='+competition.WetId+'&cat='+cat.GrpId;
							break;
					}
				}
				var cat_li = jQuery(document.createElement('li'));
				if (url != '')
				{
					var a = jQuery(document.createElement('a')).attr('href', url);
					a.text(cat.name);
					if (this.navigateTo) a.click(this.navigateTo);
					cat_li.append(a);
				}
				else
				{
					cat_li.text(cat.name);
				}
				cats_ul.append(cat_li);
				have_cats = true;
			}
			var links_ul = jQuery(document.createElement('ul')).addClass('links');
			var have_links = false;
			for(var l in links)
			{
				if (typeof competition[l] == 'undefined' || competition[l] === null) continue;
				var a = jQuery(document.createElement('a'));
				a.attr('href', competition[l]);
				if (l == 'comp_url' && this.comp_url[0] == '/')
					;
				else if (l != 'starters')
					a.attr('target', '_blank');
				else if (this.navigateTo)
					a.click(this.navigateTo);
				a.text(links[l]);
				links_ul.append(jQuery(document.createElement('li')).addClass(l+'Link').append(a));
				have_links = true;
			}
			if (have_links) comp_div.append(links_ul);
			if (have_cats) comp_div.append(cats_ul);
			competitions.append(comp_div);

			var dist = Math.abs((new Date(competition.date)).getWeek() - week_to_display);
			if (typeof closest_dist == 'undefined' || dist < closest_dist)
			{
				closest_dist = dist;
				closest = comp_div[0];
			}
		}
		if (closest && year == (new Date()).getFullYear())
		{
			// need to delay scrolling a bit, layout seems to need some time
			window.setTimeout(function() {
				closest.scrollIntoView();		// scrolls competition div AND whole document to show closest
				window.scrollTo(0,0);			// scrolls whole document back up
			}, 100);
		}
	};
	Competitions.prototype.changeYear = function(year)
	{
		if (this.json_url.match(this.year_regexp))
		{
			this.json_url = this.json_url.replace(this.year_regexp, '$1year='+year);
		}
		else
		{
			if (this.json_url.substr(-1) != '?')
				this.json_url += this.json_url.indexOf('?') == -1 ? '?' : '&';
			this.json_url += 'year='+year;
		}
		if (this.navigateTo)
		{
			this.navigateTo(this.json_url);
		}
		else
		{
			this.update();
		}
	};
	Competitions.prototype.changeFilter = function(filter)
	{
		if (this.json_url.indexOf('?') == -1)
		{
			this.json_url += '?'+filter;
		}
		else
		{
			var year = this.json_url.match(this.year_regexp);
			this.json_url = this.json_url.replace(/\?.*$/, '?'+(year && year[2] ? 'year='+year[2]+'&' : '')+filter);
		}
		if (this.navigateTo)
		{
			this.navigateTo(this.json_url);
		}
		else
		{
			this.update();
		}
	};
	return Competitions;
})();

/**
 * Aggregated rankings widget inheriting from DrBaseWidget
 */
var Aggregated = (function() {
	/**
	 * Constructor for aggregated rankings (nat. team ranking, sektionenwertung, ...) from given json url
	 *
	 * Table get appended to specified _container
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 */
	function Aggregated(_container,_json_url)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);

		this.update();
	}
	// inherite from DrBaseWidget
	Aggregated.prototype = new DrBaseWidget();
	Aggregated.prototype.constructor = Aggregated;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	Aggregated.prototype.handleResponse = function(_data)
	{
		var that = this;

		// if we are not controlled by DrWidget, install our own navigation
		if (!this.navigateTo)
		{
			this.navigateTo = function(e)
			{
				document.location.hash = this.href.replace(/^.*#!/, '');
				e.preventDefault();
			};
			this.update = function()
			{
				that.json_url = that.json_url.replace(/&(cup|comp|cat)=[^&]+/, '')+'&'+
					document.location.hash.substr(1);
				DrBaseWidget.prototype.update.call(that);
			};
			this.installPopState();
		}
		this.columns = {
			'rank': 'Rank',
			'nation': { 'label': _data.aggregated_name, 'colspan': 2},
			'name': _data.aggregated_name,
			'points': {'label': 'Points'}
		};
		if (location.hash.indexOf('detail=1') == -1)
		{
			this.columns.points.click = function(e) {
				var hidden_cols = that.container.find('.result,.calculationHidden');
				var display = hidden_cols.length ? jQuery(hidden_cols[0]).css('display') : 'none';
				hidden_cols.css('display', display == 'none' ? 'table-cell' : 'none');
				location.hash += '&detail=1';
				e.preventDefault();
			};
			// add calculation to see-also links
			if (typeof _data.see_also == 'undefined') _data.see_also = [];
			_data.see_also.push({
				name: 'calculation of this ranking',
				url: location.href+'&detail=1'
			});
		}
		if (_data.aggregate_by != 'nation') delete this.columns.nation;

		if (typeof this.table == 'undefined')
		{
			this.ranking_name = jQuery(document.createElement('h1')).addClass('rankingName');
			this.container.append(this.ranking_name);

			// cup or competition
			this.header = jQuery(document.createElement('h2')).addClass('rankingHeader');
			this.container.append(this.header);

			// category names
			this.header2 = jQuery(document.createElement('h3')).addClass('rankingHeader2');
			this.container.append(this.header2);
		}
		else
		{
			jQuery(this.table.dom).remove();
			this.header.empty();
			this.header2.empty();
		}
		this.ranking_name.text(_data.name);
		this.header.text(_data.cup_name || _data.comp_name);
		// if we filter by cat display categories as 2. header
		if (_data.cat_filter)
		{
			if (_data.cat_name)	// category name given, use it but upcase and space it
			{
				this.header2.text(_data.cat_name.toUpperCase().split('').join(' '));
			}
			else
			{
				var names = '';
				for(var i in _data.categorys)
				{
					names += (names ? ', ' : '')+_data.categorys[i].name;
				}
				this.header2.text(names);
			}
		}

		// make _data available to other methods
		this.data = _data;

		var comps = [];
		for(var c in _data.competitions) comps.push(_data.competitions[c]);
		// use competition columns for more then one comp. and international or SUI
		if (comps.length > 1 && (!_data.nation || _data.nation == 'SUI'))
		{
			comps.sort(function(a,b){
				return a.date < b.date ? 1 : -1;
			});
			for(var c=0; c < comps.length; ++c)
			{
				this.columns['result'+comps[c].WetId] = function(_data, _tag, _name) {
					return that.comp_column.call(that, _data, _tag, _name);
				};
			}
		}
		else	// otherwise use category header
		{
			var cats = [];
			for(var c in _data.categorys) cats.push(_data.categorys[c]);
			cats.sort(function(a,b){
				return a.name < b.name ? -1 : 1;
			});
			for(var c=0; c < cats.length; ++c)
			{
				this.columns['result'+cats[c].GrpId] = function(_data, _tag, _name) {
					return that.cat_column.call(that, _data, _tag, _name);
				};
			}
		}
		if (!_data.use_cup_points)	// display all ranking points with 2 digits
		{
			for(var f=0; f < _data.federations.length; ++f)
			{
				_data.federations[f].points = _data.federations[f].points.toFixed(2);
			}
		}
		// create new table
		this.table = new DrTable(_data.federations, this.columns, false, true, null, this.navigateTo);

		// add table footer with note about how many results are counting
		var tfoot = jQuery(document.createElement('tfoot'));
		jQuery(this.table.dom).append(tfoot);
		var th = jQuery(document.createElement('th')).addClass('result');
		if (this.json_url.indexOf('detail=1') == -1) th.addClass('calculationHidden');
		tfoot.append(jQuery(document.createElement('tr')).append(th));
		var cols=0; for(var c in this.columns) cols++;
		th.attr('colspan', cols);
		th.text('For '+_data.name+' '+_data.best_results+' best results per competition and category are counting. '+
				'Not counting results are in brackets.');

		this.container.append(this.table.dom);

		this.seeAlso(_data.see_also);
	};
	/**
	 * Display a competition column
	 *
	 * @param _data
	 * @param _tag tag 'th' for header or 'td' for data row
	 * @param _name column-name
	 */
	Aggregated.prototype.comp_column = function(_data, _tag, _name)
	{
		var id = _name.substr(6);
		var ret = {'className': 'result'};
		if (this.json_url.indexOf('detail=1') == -1) ret.className += ' calculationHidden';
		if (_tag == 'th')
		{
			// use comp. shortcut plus date as column header
			ret.label = (this.data.competitions[id].short || this.data.competitions[id].name.replace(/^.* - /, ''))+"\n"+
				this.formatDate(this.data.competitions[id].date);
			// add comp to url evtl. replacing cup
			ret.url = location.href.indexOf('cup=') == -1 ? location.href+
				(location.href.indexOf('#!') == -1 ? '#!' : '&')+'comp='+id :
				location.href.replace(/(cup)=[^&]+/, 'comp='+id);
			// nat. team ranking selects a cat, if none given, need to add it to not get a different selected
			if (this.data.cat_filter && ret.url.indexOf('cat=') == -1) ret.url += '&cat='+this.data.cat_filter;
			// keep in detailed view
			if (ret.url.indexOf('detail=1') == -1) ret.url += '&detail=1';
			ret.title = this.data.competitions[id].name;
		}
		else if (this.data.cat_filter && this.data.cat_filter.indexOf(',') == -1)
		{
			ret.label = '';
			ret.nodes = this.results(id, 'WetId', _data.counting);
		}
		else
		{
			ret.label = _data.comps[id];
		}
		return ret;
	};
	/**
	 * Display a category column
	 *
	 * @param _data
	 * @param _tag tag 'th' for header or 'td' for data row
	 * @param _name column-name
	 */
	Aggregated.prototype.cat_column = function(_data, _tag, _name)
	{
		var id = _name.substr(6);
		var ret = {'className': 'result'};
		if (this.json_url.indexOf('detail=1') == -1) ret.className += ' calculationHidden';
		if (_tag == 'th')
		{
			ret.label = this.data.categorys[id].name;
			// get less wide headers by inserting a newline
			ret.label = ret.label.replace(/(lead|speed|boulder)/, "\n$1")
				.replace(/(männliche|weibliche|male|female) */, "$1\n");
			if (!this.data.comp_filter && !this.data.cat_filter)
			{
				ret.url = location.href+'&cat='+id;
				// keep in detailed view
				if (ret.url.indexOf('detail=1') == -1) ret.url += '&detail=1';
			}
		}
		else if (!this.data.comp_filter && !this.data.cat_filter)	// sektionenwertung
		{
			var points = 0.0;
			for(var r=0; r < _data.counting.length; ++r)
			{
				var result = _data.counting[r];
				if (result.GrpId == id)
				{
					points += result.points;
				}
			}
			ret.label = points.toFixed(2);
		}
		else
		{
			ret.label = '';
			ret.nodes = this.results(id, 'GrpId', _data.counting);
		}
		return ret;
	};
	Aggregated.prototype.results = function(_id, _attr, _results)
	{
		var cols = ['rank','lastname','firstname','points'];
		var nodes;
		for(var r=0; r < _results.length; ++r)
		{
			var result = _results[r];
			if (result[_attr] == _id)
			{
				if (typeof nodes == 'undefined')
				{
					nodes = jQuery(document.createElement('div')).addClass('resultRows');
				}
				var div = jQuery(document.createElement('div')).addClass('resultRow');
				for(var c=0; c < cols.length; ++c)
				{
					var col = cols[c];
					var span = jQuery(document.createElement('span')).addClass(col);
					span.text(col != 'points' || this.data.use_cup_points ? result[col] : result[col].toFixed(2));
					div.append(span);
				}
				nodes.append(div);
			}
		}
		return nodes;
	};
	return Aggregated;
})();

/**
 * Universal widget to display all data specified by _json_url or location
 */
var DrWidget = (function() {
	/**
	 * call appropriate widget to display data specified by _json_url or location
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param _arg3 object with widget specific 3. argument, eg. { Competitions: {filters}, Profile: 'template-id' }
	 */
	function DrWidget(_container,_json_url,_arg3)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url);

		this.arg3 = _arg3 || {};

		var matches = this.json_url.match(/\?.*$/);
		this.update(matches ? matches[0] : null);

		// install this.update as PopState handler
		this.installPopState();
	}
	// inherit from DrBaseWidget
	DrWidget.prototype = new DrBaseWidget();
	DrWidget.prototype.constructor = DrWidget;
	/**
	 * Navigate to a certain result-page
	 *
	 * @param _params default if not specified first location.hash then location.search
	 * @param {DrBaseWidget} _widget to clear evtl. pending update timer
	 */
	DrWidget.prototype.navigateTo = function(_params, _widget)
	{
		// clear pending update timer of widget, to stay on new widget we navigate to now
		if (_widget && _widget.update_handle)
		{
			window.clearTimeout(_widget.update_handle);
			delete _widget.update_handle;
		}
		delete this.prevent_initial_pop;
		var params = '!'+_params.replace(/^.*(#!|#|\?)/, '');

		// update location hash, to reflect current page-content
		if (document.location.hash != '#'+params) document.location.hash = params;
	};
	DrWidget.prototype.update = function(_params)
	{
		var params = _params || location.hash || location.search;
		params = params.replace(/^.*(#!|#|\?)/, '');

		this.json_url = this.json_url.replace(/\?.*$/, '')+'?'+params;

		// check which widget is needed to render requested content
		function hasParam(_param, _value)
		{
			if (typeof _value == 'undefined') _value = '';
			return params.indexOf(_param+'='+_value) != -1;
		}
		var widget;
		if (hasParam('type', 'nat_team_ranking') || hasParam('type', 'sektionenwertung') || hasParam('type', 'regionalzentren'))
		{
			widget = 'Aggregated';
		}
		else if (hasParam('person'))
		{
			widget = 'Profile';
		}
		else if (hasParam('cat') && !hasParam('comp'))
		{
			widget = 'Resultlist';	// ranking uses Resultlist!
		}
		else if (hasParam('nation') || !hasParam('comp'))
		{
			widget = 'Competitions';
		}
		else if (hasParam('comp') && hasParam('type', 'starters'))
		{
			widget = 'Starters';
		}
		else if (hasParam('comp') && !hasParam('cat') || hasParam('filter'))
		{
			widget = 'Results';
		}
		else if (hasParam('type', 'startlist'))
		{
			widget = 'Startlist';
		}
		else
		{
			widget = 'Resultlist';
		}
		// check if widget is currently instancated and only need to update or need to be instancated
		if (typeof this.widget == 'undefined' || this.widget.constructor != window[widget])
		{
			this.container.html('');	// following .empty() does NOT work in IE8 Grrrr
			this.container.empty();
			// do a new of objects whos name is stored in widget
			this.widget = Object.create(window[widget].prototype);
			// Object.create, does NOT call constructor, so do it now manually
			window[widget].call(this.widget, this.container, this.json_url, this.arg3[widget]);
			var that = this;
			this.widget.navigateTo = function(e) {
				if (typeof e == 'string')
				{
					that.navigateTo(e, that.widget);
				}
				else
				{
					that.navigateTo(this.href, that.widget);
					e.preventDefault();
				}
			};
		}
		else
		{
			this.widget.json_url = this.json_url;
			this.widget.update();
		}
	};
	return DrWidget;
})();

/**
 * Widget to let user choose a competition and category to show its result
 *
 * Chooser selectboxes stay visible, when user navigates in result eg. to show a profile
 */
var ResultChooser = (function() {
	/**
	 * call appropriate widget to display data specified by _json_url or location
	 *
	 * @param _container
	 * @param _json_url url for data to load
	 * @param {object} _arg3 object with 3. parameter to pass to widget used to display results
	 */
	function ResultChooser(_container, _json_url, _arg3)
	{
		DrBaseWidget.prototype.constructor.call(this, _container, _json_url.replace(/&cat=[^&]+/, ''));

		this.arg3 = _arg3 || {
			Results: true,	// show NO own navigation
			Startlist: true,
			Resultlist: true
		};

		var matches = this.json_url.match(/\?.*$/);
		this.update(matches ? matches[0] : null);

		this.comp_chooser = this.cat_chooser = undefined;
		var matches = _json_url.match(/&cat=([^&]+)/);
		this.cat = matches ? matches[1] : undefined;

		this.widget = undefined;

		// install this.update as PopState handler
		//this.installPopState();
	}
	// inherit from DrBaseWidget
	ResultChooser.prototype = new DrBaseWidget();
	ResultChooser.prototype.constructor = ResultChooser;
	/**
	 * Callback for loading data via ajax
	 *
	 * @param _data route data object
	 */
	ResultChooser.prototype.handleResponse = function(_data)
	{
		if (!this.comp_chooser)
		{
			// competition chooser
			this.comp_chooser = jQuery(document.createElement('select'));
			this.comp_chooser.addClass('compChooser');
			this.container.append(this.comp_chooser);
			var that = this;
			this.comp_chooser.change(function(e){
				that.json_url = that.json_url.replace(/comp=[^&]+/, 'comp='+this.value).replace(/&cat=[^&]+/, '');
				that.update();
			});
			this.cat_chooser = jQuery(document.createElement('select'));
			this.cat_chooser.addClass('catChooser');
			this.container.append(this.cat_chooser);
			this.cat_chooser.change(function(e){
				that.cat = this.value;
				that.json_url = that.json_url.replace(/comp=[^&]+/, 'comp='+that.comp_chooser.val());
				if (!that.cat)
					that.json_url = that.json_url.replace(/&cat=[^&]+/, '');
				else if (that.json_url.search('cat=') == -1)
					that.json_url += '&cat='+this.value;
				else
					that.json_url = that.json_url.replace(/cat=[^&]+/, 'cat='+this.value);
				that.widget.navigateTo(that.json_url);
			});
		}
		else
		{
			this.comp_chooser.empty();
			this.cat_chooser.empty();
		}
		// fill competition chooser
		for(var i=0; i < _data.competitions.length; ++i)
		{
			var competition = _data.competitions[i];
			var option = jQuery(document.createElement('option'));
			option.attr({value:  competition.WetId, title: competition.date_span});
			option.text(competition.name);
			if (competition.WetId == _data.WetId) option.attr('selected', true);
			this.comp_chooser.append(option);
		}
		// fill category chooser
		var option = jQuery(document.createElement('option'));
		option.attr('value', '');
		option.text('Select a single category to show ...');
		this.cat_chooser.append(option);
		var cat_found = false;
		for(var i=0; i < _data.categorys.length; ++i)
		{
			var cat = _data.categorys[i];
			option = jQuery(document.createElement('option'));
			option.attr('value', cat.GrpId);
			option.text(cat.name);
			if (cat.GrpId == this.cat)
			{
				option.attr('selected', true);
				cat_found = true;
			}
			this.cat_chooser.append(option);
		}
		if (!cat_found) this.cat = undefined;

		if (!this.widget)
		{
			var widget_container = jQuery(document.createElement('div')).appendTo(this.container);
			this.widget = new DrWidget(widget_container, this.json_url+(this.cat?'&cat='+this.cat:''), this.arg3);
		}
		else
		{
			this.widget.navigateTo(this.json_url+(this.cat ? '&cat='+this.cat : ''));
		}
	};
	return ResultChooser;
})();

/**
 * Dynamically load a css file
 *
 * @param href url to css file
 */
function load_css(href)
{
	//Get the head node and append a new link node with the stylesheet url to it
	var headID = document.getElementsByTagName('head')[0];
	var cssnode = document.createElement('link');
	cssnode.type = "text/css";
	cssnode.rel = "stylesheet";
	cssnode.href = href;
	headID.appendChild(cssnode);
}

/**
 * Align non-breaking-space separated parts of text in a td by wrapping them in equally sized spans
 *
 * @param {string|jQuery} elems
 */
function align_td_nbsp(elems)
{
	jQuery(elems).replaceWith(function()
	{
		var parts = jQuery(this).contents().text().split(/\u00A0+/);
		if (parts.length == 1) return jQuery(this).clone();
		var prefix = '<div class="tdAlign'+parts.length+'">';
		var postfix = '</div>';
		return jQuery('<td>'+prefix+parts.join(postfix+prefix)+postfix+'</td>');
	});
}

/**
 * Some compatibilty functions to cope with older javascript implementations
 */
if(!Array.isArray)
{
	Array.isArray = function (vArg) {
		return Object.prototype.toString.call(vArg) === "[object Array]";
	};
}

if(!Object.create)
{
    Object.create = function(o) {
        function F(){}
        F.prototype=o;
        return new F();
    };
}

if (!Date.getWeek)
{
	Date.prototype.getWeek = function() {
	    var onejan = new Date(this.getFullYear(),0,1);
	    return Math.ceil((((this - onejan) / 86400000) + onejan.getDay()+1)/7);
	};
}

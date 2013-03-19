/**
 * digital ROCK jQuery based Javascript API
 * 
 * We only use jQuery() here (not $() or $j()!) to be able to run as well inside EGroupware as with stock jQuery from googleapis.
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2010-13 by RalfBecker@digitalROCK.de
 * @version $Id$
 */
 
/**
 * Example with multi-result scrolling (use c= and r= for cat and route)
 *
 * http://www.ifsc-climbing.org/egroupware/ranking/sitemgr/digitalrock/eliste.html?comp=1251&cat=1&route=2&detail=0&rotate=c=1,r=2:c=2,r=2
 * 
 * You can also supply an optional parameter w= (think of German "Wettkampf" as "c" was already taken) to rotate though different competitions (the first of which is specified by the "comp" parameter in the original URL.
 *
 * Example https://rb66.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?comp=1395&beamer=1&cat=1&route=0&rotate=w=1395,c=1,r=0:w=1396,c=1,r=0
 * 
 * The interesting part here is rotate=w=1395,c=1,r=0:w=1396,c=1,r=0
 * 
 * @link https://developers.google.com/webmasters/ajax-crawling/
 */
 
/**
 * Constructor for result from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Resultlist(_container,_json_url)
{
	Resultlist.prototype.update = Startlist.prototype.update;
	Resultlist.prototype.setHeader = Startlist.prototype.setHeader;
	Resultlist.prototype.currentTopPosition = Startlist.prototype.currentTopPosition;
	Resultlist.prototype.upDown = Startlist.prototype.upDown;
	Resultlist.prototype.rotateURL = Startlist.prototype.rotateURL;

	Startlist.apply(this, [_container,_json_url]);
	this.container.attr('class', 'Resultlist');
}

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
			if (!detail || detail[1] == '0') 
			{
				delete this.result_cols.result;
				// allow to click on points to show single results
				this.result_cols.points = {
					label: this.result_cols.points,
					url: location.href+'&detail=1'
				};
			}
			break;

		default:
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
			// route defaults to -1 if not set or empty
			var route = this.json_url.match(/route=([0-9]+)/);
			route = route && route[1] !== '' ? route[1] : -1;
			// for boulder heats use new display, but not for general result!
			if (_data.discipline == 'boulder' && (!detail || detail[1] == 2) && route != -1)
			{
				delete this.result_cols.result;
				var that = this;
				var num_problems = parseInt(_data.route_num_problems);
				this.result_cols.boulder = function(_data,_tag){
					return that.getBoulderResult.call(that, _data, _tag, num_problems);
				};
				//Resultlist.prototype.getBoulderResult;
				if (detail && detail[1] == 2) this.result_cols.result = 'Sum';
			}
			break;
	}
	Startlist.prototype.handleResponse.call(this, _data);
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
		if (result && result != 'b0')
		{
			var top_tries = result.match(/t([0-9]+)/);
			var bonus_tries = result.match(/b([0-9]+)/);
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
			jQuery(bonus_text).text(bonus_tries[1]);
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

/**
 * Constructor for startlist from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Startlist(_container,_json_url)
{
	this.json_url = _json_url;
	this.container = jQuery(typeof _container == 'string' ? '#'+_container : _container);
	this.container.attr('class', 'Startlist');
	
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

/**
 * Update Startlist from json_url
 */
Startlist.prototype.update = function()
{
	// remove our own parameters and current year from json url to improve caching
	var url = this.json_url.replace(/(detail|beamer|rotate|toc)=[^&]*(&|$)/, '')
		.replace(new RegExp('year='+(new Date).getFullYear()+'(&|$)'), '').replace(/&$/, '');
	
	jQuery.ajax({
		url: url,
		async: true,
		context: this,
		data: '',
		dataType: this.json_url.indexOf('//') == -1 ? 'json' : 'jsonp',
		jsonpCallback: 'jsonp',	// otherwise jQuery generates a random name, not cachable by CDN
		cache: true,
		type: 'GET', 
		success: this.handleResponse,
		error: function(_xmlhttp,_err,_status) { 
			if (_err != 'timeout') alert('Ajax request to '+this.json_url+' failed: '+_err+(_status?' ('+_status+')':''));		}
	});
};

/**
 * Callback for loading data via ajax
 * 
 * @param _data route data object
 */            
Startlist.prototype.handleResponse = function(_data)
{
	//console.log(_data);
	var detail = this.json_url.match(/detail=([^&]+)/);

	switch(_data.discipline)
	{
		case 'speedrelay':
			this.startlist_cols = !detail ? {	// default detail
				'start_order': 'StartNr',
				'team_name': 'Teamname',
				'athletes/0/lastname': 'Athlete #1',
				'athletes/1/lastname': 'Athlete #2',
				'athletes/2/lastname': 'Athlete #3'
			} : (detail[1] ? {	// detail=1
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
	var sort;
	// if we have no result columns or no ranked participant, show a startlist
	if (typeof this.result_cols == 'undefined' || !_data.participants[0].result_rank)
	{
		this.columns = this.startlist_cols;
		sort = 'start_order';
	}
	// if we are a result showing a startlist AND have now a ranked participant
	// --> switch back to result
	else
	{
		this.columns = this.result_cols;
		sort = 'result_rank';
	}
	
	// for SUI and GER competitions replace nation
	switch (_data.nation)
	{
		case 'GER':
			replace_attribute(this.columns, 'nation', 'federation', 'DAV Sektion');
			break;
		case 'SUI':
			replace_attribute(this.columns, 'nation', 'city', 'City');
			break;			
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
		_data.route_order != this.route_order)	// switching heats (they can have different columns)
	{
		jQuery(this.container).empty();
		delete this.table;		
	}
	this.discipline = _data.discipline;
	this.sort = sort;
	this.route_order = _data.route_order;

	if (typeof this.table == 'undefined')
	{
		// for general result use one column per heat
		if (this.columns.result && _data.route_names && _data.route_order == -1)
		{
			delete this.columns.result;
			var routes = [];
			for(var id in _data.route_names)
			{
				routes.push(id);
			}
			routes.reverse();
			// show final first and 2. quali behind 1. quali: eg. 3, 2, 0, 1
			//for(var route=10; route >= -1; --route)
			for(var i = 0; i < routes.length; ++i)
			{
				var route = routes[i];
				if (route != 1 && typeof _data.route_names[Math.abs(route)] != 'undefined')
					this.columns['result'+Math.abs(route)] = _data.route_names[Math.abs(route)];
			}
			// evtl. add points column
			if (_data.participants[0].quali_points)
				this.columns['quali_points'] = 'Points';
			
			title_prefix = '';
		}
		if (this.columns.result && _data.participants[0].rank_prev_heat && !this.json_url.match(/detail=0/))
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
		
		if (!this.json_url.match(/toc=0/) && !this.json_url.match(/beamer=1/) && this.discipline != 'ranking')
		{
			var toc = jQuery(document.createElement('ul'));
			jQuery(this.container).append(toc);
			toc.addClass('listToc');
			for (var r in _data.route_names)
			{
				var li = jQuery(document.createElement('li'));
				if (r != this.route_order)
				{
					var a = jQuery(document.createElement('a'));
					a.text(_data.route_names[r]);
					var reg_exp = /route=[^&]+/;
					var url = location.href.replace(reg_exp, 'route='+r);
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
				}
				else
				{
					li.text(_data.route_names[r]);
				}
				toc.prepend(li);
			}
		}
		// create new table
		this.table = new DrTable(_data.participants,this.columns,this.sort,true,_data.route_result ? _data.route_quota : null,this.navigateTo);
	
		jQuery(this.container).append(this.table.dom);
	}
	else
	{
		// update existing table
		this.table.update(_data.participants,_data.route_result ? _data.route_quota : null);
	}
	// set/update header line
	this.setHeader(_data);

	// if route is NOT offical, update list every 10 sec
	if (!_data.route_result) 
	{
		var list = this;
		this.update_handle = window.setTimeout(function(){list.update();},10000);
	}
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
	this.result_date.text(_data.route_result);
	this.header.empty();
	this.header.text(header);
};

/**
 * Constructor for results from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Results(_container,_json_url)
{
	this.json_url = _json_url;
	this.container = jQuery(typeof _container == 'string' ? '#'+_container : _container);
	this.container.attr('class', 'Results');
	
	this.update();
}
Results.prototype.update = Startlist.prototype.update;
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
	// for SUI and GER competitions replace nation
	switch (_data.nation)
	{
		case 'GER':
			replace_attribute(this.columns, 'nation', 'federation', 'DAV Sektion');
			break;
		case 'SUI':
			replace_attribute(this.columns, 'nation', 'city', 'City');
			break;			
	}

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
		delete this.table.dom;
		this.comp_header.empty();
		this.comp_date.empty();
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

/**
 * Constructor for starters / registration from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Starters(_container,_json_url)
{
	this.json_url = _json_url;
	this.container = jQuery(typeof _container == 'string' ? '#'+_container : _container);
	this.container.attr('class', 'Starters');
	
	this.update();
}
Starters.prototype.update = Startlist.prototype.update;
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
	row.append(th);
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
	for(var i=0; i < _data.athletes.length; ++i)
	{
		var athlete = _data.athletes[i];
		// evtl. create new row for federation/nation
		if (typeof fed == 'undefined' || fed != athlete.reg_fed_id)
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
			row.append(th);
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
	}
	this.fillUpFedRows();

	var tfoot = jQuery(document.createElement('tfoot'));
	this.table.append(tfoot);
	var th = jQuery(document.createElement('th'));
	tfoot.append(jQuery(document.createElement('tr')).append(th));
	th.attr('colspan', 1+_data.categorys.length);
	th.text('Total of '+_data.athletes.length+' athlets registered in all categories.');
};
/**
 * Fill a single fed-row up to a given position with empty td's
 * 
 * @param int _r row-number
 * @param int _to column-number, default whole row
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

/**
 * Constructor for profile from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container id or dom node
 * @param _json_url url for data to load
 * @param _template optional string with html-template
 */
function Profile(_container,_json_url,_template)
{
	this.json_url = _json_url;
	this.container = jQuery(typeof _container == 'string' ? '#'+_container : _container);
	this.container.attr('class', 'Profile');
	if (_template) this.template = _template;
	this.container.empty();
	
	this.bestResults = 12;
	
	this.update();
}
Profile.prototype.update = Startlist.prototype.update;
/**
 * Callback for loading data via ajax
 * 
 * @param _data route data object
 */            
Profile.prototype.handleResponse = function(_data)
{
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
	var n = 0;
	var bestResults = this.bestResults;
	pattern = /\$\$results\/N\/([^$]+)\$\$/g;
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
			rows += match.replace(pattern, function(match, placeholder)
			{
				switch (placeholder)
				{
					case 'cat_name+name':
						return (result.GrpId != _data.GrpId ? result.cat_name+': ' : '')+result.name;
					case 'date':
						return result.date.split('-').reverse().join('.');
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
 * Constructor calendar from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 * @param _filters object 
 */
function Competitions(_container,_json_url,_filters)
{
	this.json_url = _json_url;
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = jQuery(_container);
	this.container.attr('class', 'Calendar');
	if (typeof _filters != 'undefined') this.filters = _filters;
	this.year_regexp = /([&?])year=(\d+)/;
	
	this.update();
}
Competitions.prototype.update = Startlist.prototype.update;
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

	if (typeof this.filters != 'undefied')
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
	
	for(var i=0; i < _data.competitions.length; ++i)
	{
		var competition = _data.competitions[i];
		
		var comp_div = jQuery(document.createElement('div')).addClass('competition');
		comp_div.append(jQuery(document.createElement('div')).addClass('title').text(competition.name));
		comp_div.append(jQuery(document.createElement('div')).addClass('date').text(competition.date_span));
		var cats_ul = jQuery(document.createElement('ul')).addClass('cats');
		var have_cats = false;
		var links = { 'homepage': 'Event Website', 'info': 'Information', 'startlist': 'Startlist', 'result': 'Result' };
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
			if (l != 'starters') 
				a.attr('target', '_blank');
			else if (this.navigateTo)
				a.click(this.navigateTo);
			a.text(links[l]);
			links_ul.append(jQuery(document.createElement('li')).append(a));
			have_links = true;
		}
		if (have_links) comp_div.append(links_ul);
		if (have_cats) comp_div.append(cats_ul);
		this.container.append(comp_div);
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

/**
 * call appropriate widget to display data specified by _json_url or location
 * 
 * @param _container
 * @param _json_url url for data to load
 * @param _arg3 object with widget specific 3. argument, eg. { Competitions: {filters}, Profile: 'template-id' }
 */
function DrWidget(_container,_json_url,_arg3)
{
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = jQuery(_container);
	this.json_url = _json_url;
	this.arg3 = _arg3 || {};
	
	var matches = this.json_url.match(/\?.*$/);
	this.update(matches ? matches[0] : null);

	// add popstate or hashchange (IE8,9) event listener, to use browser back button for navigation
	// some browsers, eg. Chrome, generate a pop on inital page-load
	// to prevent loading page initially twice, we store initial location
	this.prevent_initial_pop = location.href;
	var that = this;
	jQuery(window).bind(window.history.pushState ? "popstate" : "hashchange", function(e) {
		if (!that.prevent_initial_pop || that.prevent_initial_pop != location.href)
		{
			that.update(location.hash || location.search);
		}
		delete that.prevent_initial_pop;
	});
	}
/**
 * Navigate to a certain result-page
 * 
 * @param _params default if not specified first location.hash then location.search
 */
DrWidget.prototype.navigateTo = function(_params)
{
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
	if (hasParam('person'))
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
				that.navigateTo(e);
			}
			else
			{
				that.navigateTo(this.href);
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
 */
function DrTable(_data,_columns,_sort,_ascending,_quota,_navigateTo)
{
	this.data      = _data;
	this.columns   = _columns;
	if (typeof _sort == 'undefined') for(_sort in _columns) break;
	this.sort      = _sort;
	if (typeof _ascending == 'undefined') _ascending = true;
	this.ascending = _ascending;
	this.quota = parseInt(_quota);
	this.navigateTo = _navigateTo;
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
	
	// athlets
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
				(typeof data.result_rank == 'undefined' || data.result_rank < 1))
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
	
	athletes = this.athletes;
	this.athletes = {};

	for(i in this.data)
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
//			jQuery(row).detach();
//			this.updateRow(row,data);
			jQuery(row).remove();
		}
//		else
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
 * @todo
 */
DrTable.prototype.updateRow = function(_row,_data)
{
	
};

/**
 * Create new data-row with all columns from this.columns
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
		if (typeof this.columns[col] == 'function')
		{
			var col_data = this.columns[col].call(this,_data,_tag);
		}
		else
		{
			var col_data = _data[col];
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
			if (this.navigateTo) jQuery(a).click(this.navigateTo);
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
				if (col_data.url)
				{
					var a = document.createElement('a');
					a.href = col_data.url;
					if (this.navigateTo) jQuery(a).click(this.navigateTo);
					jQuery(tag).append(a);
					tag = a;
				}
				jQuery(tag).text(col_data.label);
			}
		}
		else
		{
			jQuery(tag).text(col_data ? col_data : '');
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
 * Replace attribute named from with one name to and value keeping the order of the attributes
 * 
 * @param object obj
 * @param string from
 * @param string to
 * @param mixed value
 */
function replace_attribute(obj, from, to, value)
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

/**
 * Default tempalte for Profile widget
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
'		<td>date of birth:</td>\n'+
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
'		<td colspan="6"><a href="javascript:widget.widget.toggleResults()" title="show all results">best results:</a></td>\n'+
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

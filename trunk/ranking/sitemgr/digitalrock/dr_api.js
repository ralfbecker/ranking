/**
 * digital ROCK jQuery based Javascript API
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2010 by RalfBecker@digitalROCK.de
 * @version $Id$
 */

/**
 * Constructor for relay startlist from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Relaystartlist(_container,_json_url)
{
	Relaystartlist.prototype.update = Startlist.prototype.update;
	Relaystartlist.prototype.handleResponse = Startlist.prototype.handleResponse;
 	Relaystartlist.prototype.setHeader = Startlist.prototype.setHeader;

	this.startlist_cols = {
		'start_order': 'StartNr',
		'team_name': 'Teamname',
		'team_nation': 'Nation',
		'athletes/0/lastname': {'label': 'Athlete #1', 'colspan': 3}, 
		'athletes/0/firstname': '', 
		'athletes/0/start_number': '', 
		'athletes/1/lastname': {'label': 'Athlete #2', 'colspan': 3}, 
		'athletes/1/firstname': '', 
		'athletes/1/start_number': '', 
		'athletes/2/lastname': {'label': 'Athlete #3', 'colspan': 3}, 
		'athletes/2/firstname': '', 
		'athletes/2/start_number': ''
	};
	Startlist.apply(this, [_container,_json_url]);
}

/**
 * Constructor for relay result from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
*/
function Relayresultlist(_container,_json_url)
{
	Relayresultlist.prototype.update = Startlist.prototype.update;
	Relayresultlist.prototype.handleResponse = Startlist.prototype.handleResponse;
 	Relayresultlist.prototype.setHeader = Startlist.prototype.setHeader;

	Startlist.apply(this, [_container,_json_url,_json_url.match(/detail=0/) ? {
		'result_rank': 'Rank',
		'team_name': 'Teamname',
		'team_nation': 'Nation',
		'result': 'Sum'
	} : {
		'result_rank': 'Rank',
		'team_name': 'Teamname',
		'team_nation': 'Nation',
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
	}]);
}

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
	Resultlist.prototype.handleResponse = Startlist.prototype.handleResponse;
	Resultlist.prototype.setHeader = Startlist.prototype.setHeader;

	Startlist.apply(this, [_container,_json_url,_json_url.match(/detail=0/) ? {
		'result_rank': 'Rank',
		'lastname' : {'label': 'Name', 'colspan': 2},
		'firstname' : '',
		'nation' : 'Nation',
		'result': 'Result'
	} : {
		'result_rank': 'Rank',
		'lastname' : {'label': 'Name', 'colspan': 2},
		'firstname' : '',
		'birthyear' : 'Birthyear',
		'nation' : 'Nation',
//		'startnummer': 'StartNr',
		'result': 'Result'
	}]);
}

/**
 * Constructor for startlist from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 * @param _columns hash with key => label pairs, defaulting to this.startlist_cols
 * @param _sort name of column to sort by, defaults to first column
 */
function Startlist(_container,_json_url,_columns,_sort)
{
	this.json_url = _json_url;
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = _container;
	// set startlist columns, if not set already
	if (typeof this.startlist_cols == 'undefined') this.startlist_cols = {
		'start_order': {'label': 'StartNr', 'colspan': 2},
		'start_number': '',
		'lastname' : {'label': 'Name', 'colspan': 2},
		'firstname' : '',
		'birthyear' : 'Birthyear',
		'nation' : 'Nation'
	};
	// use starlist columns, if no columns given
	this.columns = typeof _columns == 'undefined' ? this.startlist_cols : _columns;
	// if not sort given, use first column
	if (typeof _sort == 'undefined') for(_sort in this.columns) break;
	this.sort = _sort;

	this.update();

	if (_json_url.match(/rotate=/)) {
		window.setInterval("up_down();",20);
	}
}

/**
 * Update Startlist from json_url
 */
Startlist.prototype.update = function()
{
	$.ajax({
		url: this.json_url,
		async: true,
		context: this,
		data: '',
		dataType: 'json',
		type: 'GET', 
		success: this.handleResponse,
		error: function(_xmlhttp,_err) { alert('Ajax request to '+this.json_url+' failed: '+_err); }
	});
};

/**
 * Callback for loading data via ajax
 * 
 * @param _data route data object
 */            
Startlist.prototype.handleResponse = function(_data)
{
	// if we have no ranked participant, show a startlist
	if (!_data.participants[0].result_rank && this.sort == 'result_rank')
	{
		this.result_cols = this.columns;
		this.columns = {
			'start_order': {'label': 'StartNr', 'colspan': 2},
			'start_number': '',
			'lastname' : {'label': 'Name', 'colspan': 2},
			'firstname' : '',
			'birthyear' : 'Birthyear',
			'nation' : 'Nation'
		};
		this.sort = 'start_order';
	}
	// if we are a result showing a startlist AND have now a ranked participant
	// --> switch back to result
	else if(_data.participants[0].result_rank && this.result_cols)
	{
		this.columns = this.result_cols;
		delete this.result_cols;
		this.sort = 'result_rank';
		// remove whole table
		$(this.container).empty();
		delete this.table;
	}
	if (typeof this.table == 'undefined')
	{
		// for general result use one column per heat
		if (this.columns.result && _data.route_names)
		{
			delete this.columns.result;
			// show final first and 2. quali behind 1. quali: eg. 3, 2, 0, 1
			for(var route=10; route >= -1; --route)
			{
				if (route != 1 && typeof _data.route_names[Math.abs(route)] != 'undefined')
					this.columns['result'+Math.abs(route)] = _data.route_names[Math.abs(route)];
			}
			// evtl. add points column
			if (_data.participants[0].quali_points)
				this.columns['quali_points'] = 'Points';
			
			title_prefix = '';
		}
		if (this.columns.result && _data.participants[0].rank_prev_heat)
		{
			this.columns['rank_prev_heat'] = 'previous heat';
		}
		
		// header line
		this.header = $(document.createElement('h1'));
		$(this.container).append(this.header);
		this.header.className = 'listHeader';
		
		// create new table
		this.table = new Table(_data.participants,this.columns,this.sort,true,_data.route_result ? _data.route_quota : null);
	
		$(this.container).append(this.table.dom);
	}
	else
	{
		// update existing table
		this.table.update(_data.participants,_data.route_result ? _data.route_quota : null);
	}
	// set/update header line
	this.setHeader(_data);

	if (!_data.route_result) 
	{
		var list = this;
		window.setTimeout(function(){list.update();},10000);
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

	var header = title_prefix+_data.route_name;
	
	document.title = header;
	this.header.empty();
	this.header.text(header);
}

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
 */
function Table(_data,_columns,_sort,_ascending,_quota)
{
	this.data      = _data;
	this.columns   = _columns;
	if (typeof _sort == 'undefined') for(_sort in _columns) break;
	this.sort      = _sort;
	if (typeof _ascending == 'undefined') _ascending = true;
	this.ascending = _ascending;
	this.quota = _quota;
	// hash with PerId => tr containing athlete
	this.athletes = {};
	
	this.sortData();
	
	// header
	this.dom = document.createElement('table');
	var thead = document.createElement('thead');
	$(this.dom).append(thead);
	var row = this.createRow(this.columns,'th');
	$(thead).append(row);
	
	// athlets
	var tbody = document.createElement('tbody');
	$(this.dom).append(tbody);
	for(i in this.data)
	{
		if (this.sort == 'result_rank' && 
			(typeof this.data[i].result_rank == 'undefined' || this.data[i].result_rank < 1))
		{
			break;	// no more ranked competitiors
		}
		var row = this.createRow(this.data[i]);
		$(tbody).append(row);
	}
	//console.log(this.athletes);
}

/**
 * Update table with new data, trying to re-use existing rows
 * 
 * @param _data array with data for each participant
 * @param _quota quota if quota line should be drawn in result
 */
Table.prototype.update = function(_data,_quota)
{
	this.data = _data;
	if (typeof _quota != 'undefined') this.quota = _quota;
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
		var row = athletes[data.PerId];
		
		if (this.sort == 'result_rank' && 
			(typeof data.result_rank == 'undefined' || data.result_rank < 1))
		{
			break;	// no more ranked competitiors
		}
		// search athlete in tbody
		if (typeof row != 'undefined')
		{
//			$(row).detach();
//			this.updateRow(row,data);
			$(row).remove();
		}
//		else
		{
			row = this.createRow(data);
		}
		// no child in tbody --> append row
		if (typeof pos == 'undefined')
		{
			$(tbody).prepend(row);
		}
		else
		{
			$(pos).after(row);
		}
		pos = row;
	}
	// remove further rows / athletes not in this.data
	if (typeof pos.nextSibling != 'undefined')
	{
		$('#'+pos.id+' ~ tr').remove();
	}
};

/**
 * Update given data-row with changed content
 * @todo
 */
Table.prototype.updateRow = function(_row,_data)
{
	
};

/**
 * Create new data-row with all columns from this.columns
 */
Table.prototype.createRow = function(_data,_tag)
{
	//console.log(_data);
	if (typeof _tag == 'undefined') _tag = 'td';
	var row = document.createElement('tr');
	if (typeof _data.PerId != 'undefined' && _data.PerId > 0)
	{
		row.id = _data.PerId;
		this.athletes[_data.PerId] = row;
	}
	var span = 1;
	for(var col in this.columns)
	{
		if (--span > 0) continue;
		
		var col_data = _data[col];
		var url = _data.url;
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
		$(row).append(tag);
		
		// add pstambl link to name & vorname
		if (typeof url != 'undefined' && (col == 'lastname' || col == 'firstname'))
		{
			var a = document.createElement('a');
			a.href = url;
			a.target = 'pstambl';
			$(tag).append(a);
			tag = a;
		}
		if (typeof col_data == 'object' && col_data)
		{
			if (col_data['colspan'] > 1) tag.colSpan = span = col_data['colspan'];
			$(tag).text(col_data['label']);
		}
		else
		{
			$(tag).text(col_data ? col_data : '');
			span = 1;
		}
	}
	// add or remove quota line
	if (this.sort == 'result_rank' && this.quota && _data.result_rank && 
		_data.result_rank >= 1 && _data.result_rank > this.quota)
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
Table.prototype.sortData = function()
{
	switch(this.sort)
	{
		case 'start_order':
			this.data.sort(sortStartOrder);
			break;

		case 'result_rank':
			this.data.sort(sortResultRank);
			break;

		default:
			throw("Table.sortData: Not yet implemented sort by '"+this.sort+"'!");
			break;
	}
	if (!this.ascending) this.data.reverse();
};

/**
 * Callback for sort by 'startfolgenr' attribute
 * 
 * @param _a first object to compare
 * @param _b second object to compare
 * @return int
 */
function sortStartOrder(_a, _b)
{
	return _a['start_order'] - _b['start_order'];
}

/**
 * Callback for sort by 'platz' attribute and then 'name' and 'vorname'
 * 
 * @param _a first object to compare
 * @param _b second object to compare
 * @return int
 */
function sortResultRank(_a, _b)
{
	var rank_a = _a['result_rank'];
	if (typeof rank_a == 'undefined' || rank_a < 1) rank_a = 9999;
	var rank_b = _b['result_rank'];
	if (typeof rank_b == 'undefined' || rank_b < 1) rank_b = 9999;
	var ret = rank_a - rank_b;
	
	if (!ret) ret = _a['lastname'] > _b['lastname'];
	if (!ret) ret = _a['firstname'] > _b['firstname'];
	
	return ret;
}

/**
 * Callback for numerical sort
 * 
 * @param _a first object to compare
 * @param _b second object to compare
 * @todo get this working
 * @return int
 */
Table.prototype.sortNummeric = function(_a,_b)
{
	//console.log('_a[PerId]='+_a['PerId']+': _a['+this.sort+']='+_a[this.sort]+', _b[PerId]='+_b['PerId']+': _b['+this.sort+']='+_b[this.sort]+' returning '+((_b[this.sort] - _a[this.sort]) * (this.ascending ? 1 : -1)));
	return (_a[this.sort] - _b[this.sort]) * (this.ascending ? 1 : -1);
};

// scroll speed
var scroll_by=1;
// sleep on the borders for $sleep_for seconds
var sleep_for = 4;

// helper variable
var sleep_until = 0;
function up_down()
{
	// check whether to sleep
	var now = new Date();
	if (now.getTime() < sleep_until) {
		return;
	}
	// margin in which to reverse scrolling
	var margin = 2;
	var y = 0;
	var viewHeight = window.innerHeight;
	var pageHeight = document.body.offsetHeight;
	window.scrollBy(0, scroll_by);

	if (window.pageYOffset) {
		// all other browsers
		y = window.pageYOffset;
	} else if (document.body && document.body.scrollTop) {
		// IE
		y = document.body.scrollTop;
	}

	var scrollTopPosition = y;
	var scrollBottomPosition = y + viewHeight;
	//alert("pageYOffset(y)="+pageYOffset+", innerHeight(wy)="+innerHeight+", offsetHeight(dy)="+document.body.offsetHeight);
	var do_sleep = false;
	if (pageHeight - scrollBottomPosition <= margin) {
		scroll_by = -1;
		do_sleep = true;
	} else if(scrollTopPosition <= margin) {
		scroll_by = 1;
		do_sleep = true;
	}
	if (do_sleep) {
		sleep_until = now.getTime() + (sleep_for * 1000);
	}
}

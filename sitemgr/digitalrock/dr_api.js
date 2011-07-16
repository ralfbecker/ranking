/**
 * digital ROCK jQuery based Javascript API Lars
 * 
 * We only use jQuery() here (not $() or $j()!) to be able to run as well inside EGroupware as with stock jQuery from googleapis.
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2010 by RalfBecker@digitalROCK.de
 * @version $Id$
 */
 
/**
 * Example with multi-result scrolling (use c= and r= for cat and route)
 *
 * http://www.ifsc-climbing.org/egroupware/ranking/sitemgr/digitalrock/eliste.html?comp=1251&cat=1&route=2&detail=0&rotate=c=1,r=2:c=2,r=2
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
	Resultlist.prototype.upDown = Startlist.prototype.upDown;
	Resultlist.prototype.rotateURL = Startlist.prototype.rotateURL;

	Startlist.apply(this, [_container,_json_url]);
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
			this.result_cols = detail && detail[1] == 0 ? {
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
			};
			break;

		default:
			this.result_cols = {
				'result_rank': 'Rank',
				'lastname' : {'label': 'Name', 'colspan': 2},
				'firstname' : '',
				'nation' : 'Nation',
				'result': 'Result'
			};
			// route defaults to -1 if not set or empty
			var route = this.json_url.match(/route=([0-9]+)/);
			route = route && route[1] !== '' ? route[1] : -1;
			// for boulder heats use new display, but not for general result!
			if (_data.discipline == 'boulder' && (!detail || detail[1] == 2) && route != -1)
			{
				delete this.result_cols.result;
				this.result_cols.boulder = Resultlist.prototype.getBoulderResult;
				if (detail && detail[1] == 2) this.result_cols.result = 'Sum';
			}
			break;
	}
	Startlist.prototype.handleResponse.apply(this, [_data]);
};

/**
 * Get DOM nodes for display of graphical boulder-result
 * 
 * @param _data
 * @param _tag 'th' for header, 'td' for data rows
 * @return DOM node
 */
Resultlist.prototype.getBoulderResult = function(_data,_tag)
{
	if (_tag == 'th') return 'Result';

	var tag = document.createElement('div');
	
	for(var i=1; typeof _data['boulder'+i] != 'undefined'; ++i)
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
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = _container;
	
	// Variables needed for scrolling in upDown
	// scroll speed
	this.scroll_by=1;
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
		window.scrollBy(0, 20);
		window.setInterval(function() { list.upDown(); },20);
	}
}

/**
 * Update Startlist from json_url
 */
Startlist.prototype.update = function()
{
	jQuery.ajax({
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
	//console.log(_data);

	switch(_data.discipline)
	{
		case 'speedrelay':
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

	// remove whole table, if discipline or startlist/resultlist (detemined by sort) changed
	if (this.discipline && this.discipline != _data.discipline ||
		this.sort && this.sort != sort)
	{
		jQuery(this.container).empty();
		delete this.table;		
	}
	this.discipline = _data.discipline;
	this.sort = sort;

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
		if (this.columns.result && _data.participants[0].rank_prev_heat && ! this.json_url.match(/detail=0/) )
		{
			this.columns['rank_prev_heat'] = 'previous heat';
		}
		
		// header line
		this.header = jQuery(document.createElement('h1'));
		jQuery(this.container).append(this.header);
		this.header.className = 'listHeader';
		
		// create new table
		this.table = new Table(_data.participants,this.columns,this.sort,true,_data.route_result ? _data.route_quota : null);
	
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
	// if NOT detail=0, add prefix before route name
	if (!this.json_url.match(/detail=0/))
		header = title_prefix+header;
	
	document.title = header;
	this.header.empty();
	this.header.text(header);
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
	jQuery(this.dom).append(thead);
	var row = this.createRow(this.columns,'th');
	jQuery(thead).append(row);
	
	// athlets
	var tbody = document.createElement('tbody');
	jQuery(this.dom).append(tbody);
	for(i in this.data)
	{
		if (this.sort == 'result_rank' && 
			(typeof this.data[i].result_rank == 'undefined' || this.data[i].result_rank < 1))
		{
			break;	// no more ranked competitiors
		}
		var row = this.createRow(this.data[i]);
		jQuery(tbody).append(row);
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
				if (col_data['colspan'] > 1) tag.colSpan = span = col_data['colspan'];
				jQuery(tag).text(col_data['label']);
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
			// not necessary as server returns them sorted this way
			//this.data.sort(sortResultRank);
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
 * Currently not used, as server returns data already sorted this way
 * AND our implementation does NOT work in webkit browsers, because
 * wie return boolean for sort by alphabet
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

Startlist.prototype.upDown = function()
{
	// check whether to sleep
	var now = new Date();
	var now_ms = now.getTime();
	if (now_ms < this.sleep_until) {
	    // sleep
		return;
	}
	
	if (this.do_rotate) {
		this.rotateURL();
	    // wait for the page to build
	    this.sleep_until = now.getTime() + 1000;
	    this.first_run = true;
	    this.do_rotate = false;
	    return;
	}
	
	// Do the scrolling
	window.scrollBy(0, this.scroll_by);

    // Set scrolling and sleeping parameters accordingly
	var y = 0;
	var viewHeight = window.innerHeight;
	var pageHeight = document.body.offsetHeight;

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
	var do_sleep = 0;
	if (pageHeight <= viewHeight) {
        // No scrolling at all
		//console.log("Showing whole page");
        do_sleep = 2;
        this.do_rotate = true;
	} else if (pageHeight - scrollBottomPosition <= this.margin) {
		// UP
		this.scroll_by = -1;
		this.first_run = false;
		do_sleep = 1;
	} else if(scrollTopPosition <= this.margin) {
		// DOWN
		this.scroll_by = 1;
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

	    var current_cat = this.json_url.match(/cat=([^&]+)/)[1];
	    var current_route = this.json_url.match(/route=([^&]+)/)[1];
	    //console.log(current_cat);
	    var next = urls.match("c=" + current_cat + ",r=" + current_route + ":c=([0-9_a-z]+),r=([\\d]+)");
	    if (! next) {
	        // take the first argument
	        next = urls.match("c=([0-9_a-z]+),r=([\\d]+)");
	    }
	    //console.log(next);
	
	    var next_cat = next[1];
	    var next_route = next[2];
	    //console.log("current_cat = " + current_cat + ", current_route = " + current_route + ", next_cat = " + next_cat + ", next_route = " + next_route);
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

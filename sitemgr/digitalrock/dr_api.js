/**
 * digital ROCK jQuery based Javascript API
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2010 by RalfBecker@digitalROCK.de
 * @version $Id$
 */

/**
 * Constructor for startlist from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 */
function Resultlist(_container,_json_url)
{
	Startlist.apply(this, [_container,_json_url,{
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
 * Update Startlist from json_url
 */
Resultlist.prototype.update = function()
{
	Startlist.prototype.update.apply(this, []);
};

/**
 * Callback for loading data via ajax
 * 
 * @param _data route data object
 */            
Resultlist.prototype.handleResponse = function(_data)
{
	Startlist.prototype.handleResponse.apply(this, [_data]);
};

/**
 * Constructor for startlist from given json url
 * 
 * Table get appended to specified _container
 * 
 * @param _container
 * @param _json_url url for data to load
 * @param _columns hash with key => label pairs
 * @param _sort name of column to sort by
 */
function Startlist(_container,_json_url,_columns,_sort)
{
	this.json_url = _json_url;
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = _container;
	if (typeof _columns == 'undefined') _columns = {
		'start_order': {'label': 'StartNr', 'colspan': 2},
		'start_number': '',
		'lastname' : {'label': 'Name', 'colspan': 2},
		'firstname' : '',
		'birthyear' : 'Birthyear',
		'nation' : 'Nation'
	};
	this.columns = _columns;
	if (typeof _sort == 'undefined') for(_sort in _columns) break;
	this.sort = _sort;

	this.update();
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
	if (typeof this.table == 'undefined')
	{
		var title_prefix = (this.sort == 'start_order' ? 'Startlist' : 'Result')+': ';

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
		document.title = title_prefix+_data.route_name;
		
		// header line
		var header = document.createElement('h1');
		$(this.container).append(header);
		header.className = 'listHeader';
		$(header).text(title_prefix+_data.route_name);
		
		// create new table
		this.table = new Table(_data.participants,this.columns,this.sort);
	
		$(this.container).append(this.table.dom);
	}
	else
	{
		// update existing table
		this.table.update(_data.participants);
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
 */
function Table(_data,_columns,_sort,_ascending)
{
	this.data      = _data;
	this.columns   = _columns;
	if (typeof _sort == 'undefined') for(_sort in _columns) break;
	this.sort      = _sort;
	if (typeof _ascending == 'undefined') _ascending = true;
	this.ascending = _ascending;
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
		var row = this.createRow(this.data[i]);
		$(tbody).append(row);
	}
	//console.log(this.athletes);
}

/**
 * Update table with new data, trying to re-use existing rows
 * 
 * @param _data array with data for each participant
 */
Table.prototype.update = function(_data)
{
	this.data = _data;
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

		var tag = document.createElement(_tag);
		tag.className = col;
		$(row).append(tag);
		
		// add pstambl link to name & vorname
		if (typeof _data.url != 'undefined' && (col == 'lastname' || col == 'firstname'))
		{
			var a = document.createElement('a');
			a.href = _data.url;
			a.target = 'pstambl';
			$(tag).append(a);
			tag = a;
		}
		if (typeof _data[col] == 'object')
		{
			if (_data[col]['colspan'] > 1) tag.colSpan = span = _data[col]['colspan'];
			$(tag).text(_data[col]['label']);
		}
		else
		{
			$(tag).text(_data[col]);
			span = 1;
		}
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
	var ret = _a['result_rank'] - _b['result_rank'];
	
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
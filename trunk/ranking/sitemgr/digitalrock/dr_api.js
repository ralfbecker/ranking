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
 * @param _profile_url url for athlete profiles
 */
function Resultlist(_container,_json_url,_profile_url)
{
	Startlist.apply(this, [_container,_json_url,_profile_url,{
		'platz': 'Rank',
		'name' : {'label': 'Name', 'colspan': 2},
		'vorname' : '',
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
 * @param _profile_url url for athlete profiles
 * @param _columns hash with key => label pairs
 * @param _sort name of column to sort by
 */
function Startlist(_container,_json_url,_profile_url,_columns,_sort)
{
	if (typeof _profile_url == 'undefined') _profile_url = '/pstambl.php?';
	this.profile_url = _profile_url;
	this.json_url = _json_url;
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = _container;
	if (typeof _columns == 'undefined') _columns = {
		'startfolgenr': {'label': 'StartNr', 'colspan': 2},
		'startnummer': '',
		'name' : {'label': 'Name', 'colspan': 2},
		'vorname' : '',
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
		document.title = _data.bezeichnung;
		
		// header line
		var header = document.createElement('h1');
		$(this.container).append(header);
		header.className = 'listHeader';
		$(header).text((this.sort == 'startfolgenr' ? 'Startlist' : 'Result')+': '+_data.bezeichnung);
		
		// create new table
		this.table = new Table(_data.teilnehmer,this.columns,
			this.profile_url+'cat='+_data.GrpId+'&person=',this.sort);
	
		$(this.container).append(this.table.dom);
	}
	else
	{
		// update existing table
		this.table.update(_data.teilnehmer);
	}
};

/**
 * Constructor for table with given data and columns
 * 
 * Table get appended to specified _container
 * 
 * @param _data array with data for each participant
 * @param _columns hash with column name => header
 * @param _profile_url profile url (to which PerId of data-row gets appended)
 * @param _sort column name to sort by
 * @param _ascending
 */
function Table(_data,_columns,_profile_url,_sort,_ascending)
{
	this.profile_url = _profile_url;
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
		var profile_url = this.profile_url+_data.PerId;
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
		if (typeof profile_url != 'undefined' && (col == 'name' || col == 'vorname'))
		{
			var a = document.createElement('a');
			a.href = profile_url;
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
		case 'startfolgenr':
			this.data.sort(sortStartfolge);
			break;

		case 'platz':
			this.data.sort(sortPlatz);
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
function sortStartfolge(_a, _b)
{
	return _a['startfolgenr'] - _b['startfolgenr'];
}

/**
 * Callback for sort by 'platz' attribute and then 'name' and 'vorname'
 * 
 * @param _a first object to compare
 * @param _b second object to compare
 * @return int
 */
function sortPlatz(_a, _b)
{
	var ret = _a['platz'] - _b['platz'];
	
	if (!ret) ret = _a['name'] > _b['name'];
	if (!ret) ret = _a['vorname'] > _b['vorname'];
	
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
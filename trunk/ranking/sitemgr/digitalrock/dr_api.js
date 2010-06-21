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
function Startlist(_container,_json_url,_profile_url)
{
	if (typeof _profile_url == 'undefined') _profile_url = '/pstambl.php?';
	this.profile_url = _profile_url;
	this.json_url = _json_url;
	if (typeof _container == "string") _container = document.getElementById(_container);
	this.container = _container;
	
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
		$(header).text(_data.bezeichnung);
		
		// create new table
		this.table = new Table(_data.teilnehmer,{
			'startfolgenr': {'label': 'Start', 'colspan': 2},
			'startnummer': null,
			'name' : {'label': 'Name', 'colspan': 2},
			'vorname' : null,
//			'birthyear' : 'Birthyear',
			'nation' : 'Nation',
//			'result': 'Result',
		},this.profile_url+'cat='+_data.GrpId+'&person=','startfolgenr',true);
	
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
	this.sort      = _sort;
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
	console.log(this.athletes);
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

if (this.data[0].PerId == tbody.firstChild.id) this.data.reverse();
	
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
	for(var col in this.columns)
	{
		if (_data[col] == null) continue;

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
		if (typeof _data[col] == 'string')
		{
			$(tag).text(_data[col]);
		}
		else if (typeof _data[col] == 'object')
		{
			if (_data[col]['colspan'] > 1) tag.colSpan = _data[col]['colspan'];
			$(tag).text(_data[col]['label']);
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
//	console.log(this.data);

//	this.data.sort(this.sortNummeric);
//	this.data.sort(function(_a,_b) { this.sortNummeric.call(this,_a,_b);});
	
	var that = this;
//	this.data.sort(function(_a,_b) { that.sortNummeric.call(that,_a,_b); })
//	this.data.sort(function(_a,_b) { that.sortNummeric(_a,_b); })
	this.data.sort(sortStartfolge);
	
//	console.log(this.data);
	
/*	for(i in this.data)
	{
		console.log(this.data[i].startfolgenr+': '+this.data[i].name+', '+this.data[i].vorname);
		if (i > 3) break;
	}*/
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
 * Callback for numerical sort
 * 
 * @param _a first object to compare
 * @param _b second object to compare
 * @return int
 */
Table.prototype.sortNummeric = function(_a,_b)
{
	//console.log('_a[PerId]='+_a['PerId']+': _a['+this.sort+']='+_a[this.sort]+', _b[PerId]='+_b['PerId']+': _b['+this.sort+']='+_b[this.sort]+' returning '+((_b[this.sort] - _a[this.sort]) * (this.ascending ? 1 : -1)));
	return (_a[this.sort] - _b[this.sort]) * (this.ascending ? 1 : -1);
};
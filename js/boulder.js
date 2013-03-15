/**
 * digital ROCK protocol in browser local storage for boulder measurement
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2013 by RalfBecker@digitalROCK.de
 * @version $Id$
 */

/**
 * Object to record and display boulder measurement
 * 
 * Protocol ist stored in localStorage and therefore presistent between requests and browser restarts.
 * 
 * Protocol entries are send to server immediatly after recording and additionally periodically if transmissions fail.
 */
function boulderProtocol()
{
	this.columns = {
		'boulder': '#',
		'athlete': 'Athlete',
		'history': 'Protocol',
		'result':   'Result',
		'updated': 'Updated',
		'stored':  'Stored'
	};
}

/**
 * Add or update a protocol entry
 * 
 * @param r.WetId
 * @param r.GrpId
 * @param r.route
 * @param r.PerId
 * @param r.boulder
 * @param r.try
 * @param r.bonus
 * @param r.top
 * @param r.clicked 'try', 'bonus', 'top'
 */
boulderProtocol.prototype.record = function(r)
{
	var store = this.get(r, true) || {	// true = remove from store
		'WetId': r.WetId,
		'GrpId': r.GrpId,
		'route': r.route,
		'PerId': r.PerId,
		'athlete': this.athlete(r.PerId),
		'boulder': r.boulder,
		'history': ''
	};
	// update history, check if try already recored
	var trys = store.history.match(/[0-9]+[^0-9 ]*/g);
	var last_try = trys ? trys.pop() : null;
	if (!last_try || !r['try'] || parseInt(last_try) != r['try'])
	{
		last_try = ''+(!r['try'] ? Math.max(r.top, r.bonus) : r['try']);
		store.history += (store.history ? ' ' : '')+last_try;
	}
	if (!r.clicked || (r.clicked == 'bonus' || r.clicked == 'top') &&
		last_try.indexOf(r.clicked[0]) == -1)
	{
		store.history += r.clicked ? r.clicked[0] : (r.bonus ? 'b'+(r.top ? 't' : '') : '');
	}
	store.top = r.top;
	store.bonus = r.bonus;
	store.updated = new Date;
	store.stored = false;
	
	// store data
	this.set(store);
	
	// display data
	this.update(store);
};

/**
 * Key for boulder result in localStorage
 * @param r with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder
 * @returns {String}
 */
boulderProtocol.prototype.key = function(r)
{
	return 'boulder:'+r.WetId+':'+r.GrpId+':'+r.route+':'+r.PerId+':'+r.boulder;
};

/**
 * Read record from localstore
 * 
 * @param int|string|object r object with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder or integer index (0-based)
 * @param remove true remove item from storage
 * @returns false if not localstore supported, null if not found or object with stored data
 */
boulderProtocol.prototype.get = function(r, remove)
{
	if (!window.localStorage) return false;
	
	var key = typeof r == 'number' ? window.localStorage.key(r) : 
		typeof r == 'string' ? r : this.key(r);

	if (!key || typeof window.localStorage[key] == 'undefined')
	{
		return null;
	}
	var ret = JSON.parse(window.localStorage[key]);
	ret.updated = new Date(ret.updated);
	ret.stored = ret.stored == 'true';

	if (remove) delete window.localStorage[key];

	return ret;
};

/**
 * Read record from localstore
 * 
 * @param r with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder
 * @returns false if not localstore supported, true if successful stored
 */
boulderProtocol.prototype.set = function(r)
{
	if (!window.localStorage) return false;
	
	var key = this.key(r);
	window.localStorage[key] = JSON.stringify(r);
	
	return true;
};

/**
 * Return list of all records sorted by updated timestamp
 * 
 * @param boolean _newest_first default oldest first
 * @return Array with all records
 */
boulderProtocol.prototype.listUpdated = function(_newest_first)
{
	if (!window.localStorage) return [];
	
	var all = [];
	for(var i=0; i < window.localStorage.length; ++i)
	{
		var key = window.localStorage.key(i);
		if (key.substr(0, 8) == 'boulder:')
		{
			all.push(this.get(key));
		}
	}
	
	// sort array by updated timestamp
	var sign = _newest_first ? -1 : 1;
	all.sort(function(a,b){
		return sign*(a.updated.valueOf()-b.updated.valueOf());
	});

	return all;
};

/**
 * Display protocol list: UI for protocoll
 */
boulderProtocol.prototype.display = function()
{
	if (typeof this.protocol_div == 'undefined')
	{
		this.create();
	}
	this.resize();
	this.protocol_div.show();
};

/**
 * Resize protocol div
 */
boulderProtocol.prototype.resize = function()
{
	if (typeof this.protocol_div != 'undefined')
	{
		this.protocol_div.height(jQuery(window).height()-18);
		this.protocol_div.width(jQuery(window).width()-18);
	}
};

/**
 * Create protocol div
 */
boulderProtocol.prototype.create = function()
{
	// overlay div
	this.protocol_div = jQuery(document.createElement('div'));
	this.protocol_div.attr('id', 'protocolDiv').css('display', 'none');
	jQuery('body').append(this.protocol_div);
	this.protocol_div.height(jQuery(window).height()-18);
	this.protocol_div.width(jQuery(window).width()-18);
	
	// close button
	var buttons = jQuery(document.createElement('div')).attr('id', 'protocolButtons');
	this.protocol_div.append(buttons);
	var button = jQuery(document.createElement('input'));
	button.attr({'type': 'button', 'value': 'Close', 'id': 'closeButton'});
	buttons.append(button);
	button.click(function(e){
		jQuery('#protocolDiv').hide();
	});
	// delete button
	button = jQuery(document.createElement('input'));
	button.attr({'type': 'button', 'value': 'Delete', 'id': 'deleteButton'});
	buttons.append(button);
	var that = this;
	button.click(function(e){
		if (confirm('Delete all recorded results?'))
		{
			window.localStorage.clear();
			that.tbody.empty();
		}
	});
	
	// protocol table
	this.protocol_table = jQuery(document.createElement('table')).attr('id', 'protocolTable').addClass('DrTable');
	var thead = jQuery(document.createElement('thead'));
	var row = jQuery(document.createElement('tr'));
	for(var name in this.columns)
	{
		row.append(jQuery(document.createElement('th')).text(this.columns[name]));
	}
	thead.append(row);
	this.protocol_table.append(thead);
	this.tbody = jQuery(document.createElement('tbody'));
	this.protocol_table.append(this.tbody);
	
	// display all rows in localStore with last updated first
	var all = this.listUpdated();
	for(var i=0; i < all.length; ++i)
	{
		this.update(all[i]);
	}

	this.protocol_div.append(this.protocol_table);
	
	// bind our resize handler to window resize and orientationchange event
	jQuery(window).resize(function(){
		that.resize();
	});
	if (window.DeviceOrientationEvent)
	{
		jQuery(window).bind('orientationchange', function(){
			that.resize();
		});
	}
};

/**
 * Update or add data row
 */
boulderProtocol.prototype.update = function(_data)
{
	if (typeof this.protocol_div == 'undefined')
	{
		this.create();
	}
	else
	{
		var key = this.key(_data);
		var old_row = document.getElementById(key);
		if (old_row)
		{
			jQuery(old_row).remove();
		}
		var row = jQuery(document.createElement('tr')).attr('id', key);
		for(var name in this.columns)
		{
			var td = jQuery(document.createElement('td'));
			td.addClass(name);
			switch(name)
			{
				case 'updated':	// replace under iOS appended timezone
					td.text(_data.updated.toLocaleTimeString().replace(/[ a-zA-Z]+$/,''));
					break;
				case 'result':
					td.text((_data.top ? 't'+_data.top+' ' : '')+(_data.bonus ? 'b'+_data.bonus : ''));
					break;
				default:
					td.text(_data[name]);
					break;
			}
			row.append(td);
		}
		this.tbody.prepend(row);
	}
};

/**
 * Get athlete name from athlete selecttion
 * 
 * @param int PerId
 * @return string
 */
boulderProtocol.prototype.athlete = function(PerId)
{
	var select = document.getElementById('exec[nm][PerId]');
	
	if (select && select.options)
	{
		for(var i=1; i < select.options.length; ++i)
		{
			var option = select.options.item(i);
			if (option.value == PerId)
			{
				return jQuery(option).text();
			}
		}
	}
	return PerId;
};

var protocol;
jQuery(document).ready(function(){
	protocol = new boulderProtocol();
});
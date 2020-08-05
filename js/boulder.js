/**
 * digital ROCK protocol in browser local storage for boulder measurement
 *
 * @link http://www.digitalrock.de
 * @author Ralf Becker <RalfBecker@digitalROCK.de>
 * @copyright 2013-16 by RalfBecker@digitalROCK.de
 * @version $Id$
 */

/**
 * Object to record and display boulder measurement
 *
 * Protocol ist stored in localStorage and therefore presistent between requests and browser restarts.
 *
 * Protocol entries are send to server immediatly after recording and additionally periodically if transmissions fail.
 *
 * @param {string} _webserverUrl EGroupware url
 * @param {function(_msg)} _set_msg function to set message
 * @param {function(_PerId)} _get_athlete function to get name of athlete for a given PerId
 * @constructor
 */
function boulderProtocol(_webserverUrl, _set_msg, _get_athlete)
{
	this.columns = {
		'boulder': '#',
		'athlete': 'Athlete',
		'history': 'Protocol',
		'result':   'Result',
		'updated': 'Updated',
		'stored':  'Stored'
	};
	this.resend = null;
	this.resend_timeout = 1;
	this.webserverUrl = _webserverUrl;
	this.set_msg(_set_msg);
	this.get_athlete(_get_athlete);

}

/**
 * Set function to display a message
 *
 * @param {function(_msg)} _set_msg function to set message
 */
boulderProtocol.prototype.set_msg = function(_set_msg)
{
	this.msg = _set_msg || jQuery('#ranking-result-index_msg').text.bind(jQuery('#ranking-result-index_msg'));
};

/**
 * Set function to query athlete name by PerId
 *
 * @param {function(_PerId)} _get_athlete function to get name of athlete for a given PerId
 */
boulderProtocol.prototype.get_athlete = function(_get_athlete)
{
	this.athlete = _get_athlete || function(_PerId) { return '#'+_PerId; };
};

/**
 * Add or update a protocol entry
 *
 * @param {object} r with attributes WetId, GrpId, route, PerId, boulder, try, bonus, top, clicked: 'try', 'bonus', 'top'
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
	if (!r.clicked || r.clicked == 'apply')
	{
		store.history += (store.history?' ':'')+(r.top?'t'+r.top:'')+(r.bonus?'z'+r.bonus:'0');
	}
	else
	{
		var trys = store.history.match(/[0-9]+[^0-9 ]*/g);
		var last_try = trys ? trys.pop() : null;
		if (!last_try || !r['try'] || parseInt(last_try) != r['try'])
		{
			last_try = ''+(!r['try'] ? Math.max(r.top, r.bonus) : r['try']);
			store.history += (store.history ? ' ' : '')+last_try;
		}
		if ((r.clicked === 'bonus' || r.clicked === 'top') &&
			last_try.indexOf(r.clicked === 'bonus' ? 'z' : r.clicked[0]) == -1)
		{
			store.history += r.clicked ? (r.clicked === 'bonus' ? 'z' : r.clicked[0]) : (r.bonus ? 'z'+(r.top ? 't' : '') : '');
		}
	}
	store.try = r.try;
	store.top = r.top;
	store.bonus = r.bonus;
	store.updated = new Date;
	store.stored = false;

	// store data
	this.set(store);

	// display data
	this.update(store);

	// sending pending results to server
	if (!r.state || r.top != r.state.top || r.bonus != r.state.bonus || r.try != r.state.try)
	{
		this.send({
			'WetId': r.WetId,
			'GrpId': r.GrpId,
			'route': r.route
		});
	}
};

/**
 * Sending (all) pending results to server
 *
 * If sending them fails, show number of untransmitted records to user and install
 * timeout to try sending them again.
 *
 * If sending succeeds mark records as transmitted and reset eventualy existing
 * number of untransmitted records.
 *
 * @param {object} _filter what records to send
 * @param {boolean} _clear_resend
 */
boulderProtocol.prototype.send = function(_filter, _clear_resend)
{
	// if we got an error from server, wait for timeout to expire (called with _clear_resend=true)
	// before trying new send, but updating number of pending records
	if (this.resend && !_clear_resend) return;
	this.resend = null;

	var filter = _filter || {};
	filter.stored = false;
	//Assemble the actual request object containing the json data string
	var to_send = this.listUpdated(false, filter);
	if (!to_send.length) return;	// nothing to send
	for(var i=0; i < to_send.length; ++i)
	{
		delete to_send[i].history;
		delete to_send[i].stored;
		delete to_send[i].athlete;
	}

	jQuery.ajax({
		url: this.webserverUrl+'/json.php?menuaction=ranking.ranking_boulder_measurement.ajax_protocol_update',
		context: this,
		data: {
			json_data: JSON.stringify({
				request: {
					parameters: to_send
				}
			})
		},
		dataType: 'json',
		type: 'POST',
		success: function(_data)
		{
			for(var i=0; i < _data.length; i++)
			{
				var data = _data[i];
				// get current record
				var record = this.get(this.key(data));
				// mark record as stored, if it has NOT been updated in meantime
				if (this.key(record) == this.key(to_send[i]) &&
					record.top == to_send[i].top && record.bonus == to_send[i].bonus)
				{
					record.stored = typeof data.stored == 'undefined' ? true : data.stored;
					this.set(record);
					this.update(record);
					this.msg(data.msg+(record.stored!==true?' ('+record.stored+')':''));
				}
			}
			// reset timeout to default of 1
			this.resend_timeout = 1;
		},
		error: function(_jqXHR, _type, _ex)
		{
			this.msg('communication with server failed: '+_type+' '+_ex);
			if (!this.resend)
			{
				this.resend = window.setTimeout(jQuery.proxy(this.send, this, _filter, true), 1000*this.resend_timeout);
				if (this.resend_timeout < 64) this.resend_timeout *= 2;
			}
		}
	});
	this.msg(to_send.length+' pending');
};

/**
 * Key for boulder result in localStorage
 *
 * @param {object} r with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder
 * @returns {string}
 */
boulderProtocol.prototype.key = function(r)
{
	return 'boulder:'+r.WetId+':'+r.GrpId+':'+r.route+':'+r.PerId+':'+r.boulder;
};

/**
 * Read record from localstore
 *
 * @param {int|string|object} r object with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder or integer index (0-based)
 * @param {boolean} remove true remove item from storage
 * @returns {boolean} false if not localstore supported, null if not found or object with stored data
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

	if (remove) delete window.localStorage[key];

	return ret;
};

/**
 * Read record from localstore
 *
 * @param {object} r with attributes r.WetId, r.GrpId, r.route, r.PerId and r.boulder
 * @returns {boolean} false if not localstore supported, true if successful stored
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
 * @param {boolean} [_newest_first=false] default oldest first
 * @param {object} [_filter=] only return records matching given filter
 * @return {array} with all or filtered records
 * @memberOf boulderProtocol.prototype
 */
boulderProtocol.prototype.listUpdated = function(_newest_first, _filter)
{
	if (!window.localStorage) return [];

	var all = [];
	for(var i=0; i < window.localStorage.length; ++i)
	{
		var key = window.localStorage.key(i);
		if (key.substr(0, 8) == 'boulder:')
		{
			var record = this.get(key);
			var match = true;
			if (_filter)
			{
				for (var attr in _filter)
				{
					if (_filter[attr] != record[attr])
					{
						match = false;
						break;
					}
				}
			}
			if (match) all.push(record);
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
 *
 * @memberOf boulderProtocol.prototype
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
 *
 * @param {object} _data
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
					td.text((_data.top ? 't'+_data.top+' ' : '')+(_data.bonus ? 'z'+_data.bonus : ''));
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

var protocol;
jQuery(document).ready(function(){
	protocol = new boulderProtocol(egw_webserverUrl);
});

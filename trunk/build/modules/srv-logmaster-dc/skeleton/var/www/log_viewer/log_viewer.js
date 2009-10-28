function adjust_date(date, adjustment)
{
	adjusted = new Date(date);
	timestamp = adjusted.getTime();
	timestamp = timestamp + adjustment;
	adjusted.setTime(timestamp);
	return adjusted;
}


function add_filter()
{
	var filter_list = document.getElementById('filter_list');
	var filter_template = document.getElementById('filter_template');

	if (filter_list && filter_template)
	{
		var new_filter = filter_template.cloneNode(true);
		new_filter.id = '';
		filter_list.appendChild(new_filter);
		return 1;
	}
	return 0;
}


function clear_selection(id)
{
	var select = document.getElementById(id);
	
	if (! select)
	{
		return 0;
	}
	for (var i = 0; i < select.options.length; i++)
	{
		select.options[i].selected = false;
	}
}


function highlight_named_anchor()
{
	if (window.location.hash.match(/^#\d+.\d+$/))
	{
		var element = document.getElementById(window.location.hash.substr(1));
		if (element && element.parentNode)
		{
			element.parentNode.style.fontWeight = "bold";
			element.parentNode.style.background = "#222222";
			element.parentNode.style.border = "2px dotted white";
		}
	}
}


function invert_selection(id)
{
	var select = document.getElementById(id);
	
	if (! select)
	{
		return 0;
	}
	for (var i = 0; i < select.options.length; i++)
	{
		if (select.options[i].selected)
		{
			select.options[i].selected = false;
		}
		else
		{
			select.options[i].selected = true;
		}
	}	
}


function is_visible(id)
{
	var element = document.getElementById(id);

	if (! element)
	{
		return -1;
	}
	if (element.style.display == 'none')
	{
		return 0;
	}
	return 1;
}

	
function parse_token(token)
{
	if (token == null)
	{
		token = prompt("Please enter a log token.\nLog tokens are expected to look like this example: '10:48:53:2009-03-08:req:19525:636'.\n", "HH:MM:SS:YYYY-MM-DD:<STRING>");
	}
	token = token.replace(/^\s+|\s+$/g, '');

	if (! token.length)
	{
		return false;
	}

	// Token Regular Expression Map:
	//   10:48:53:2009-03-08:<STRING>
	//   HH:MM:SS:YYYY-MM-DD:<STRING>
	if (! token.match(/^\d{2}:\d{2}:\d{2}:\d{4}-\d{2}-\d{2}:/))
	{
		alert("The log token '" + token + "' is not valid.");
		return false;
	}

	var hour = parseInt(token.substr(0,2), 10);
	var minute = parseInt(token.substr(3,2), 10);
	var second = parseInt(token.substr(6,2), 10);
	var year = parseInt(token.substr(9,4), 10);
	var month = parseInt(token.substr(14,2), 10);
	var day = parseInt(token.substr(17,2), 10);
	var string = token.substr(20);

	var timestamp = new Date(year, month - 1, day, hour, minute, second);
	if (! set_start_time_selections(adjust_date(timestamp, -1000)))
	{
		alert("The log token '" + token + "' is outside of the allowed timeframe.");
		return false;
	}
	if (! set_end_time_selections(adjust_date(timestamp, 1000)))
	{
		alert("The log token '" + token + "' is outside of the allowed timeframe.");
		return false;
	}

	// Remove all filters.
	var filter_list = document.getElementById('filter_list');
	if (filter_list)
	{
		while (filter_list.hasChildNodes())
		{
			filter_list.removeChild(filter_list.firstChild);
		}
	}

	// Add a single filter that consists of the token string that we are
	// looking for.
	add_filter();
	document.getElementsByName('filter[]')[1].value = string;
	
	var form = document.getElementById('control_form');
	if (form)
	{
		form.onsubmit();
		form.submit();
	}
}


function set_end_time_selections(timestamp)
{
	var r = true;

	r = r && set_selection(document.getElementsByName('end_year')[0], timestamp.getFullYear());
	r = r && set_selection(document.getElementsByName('end_month')[0], timestamp.getMonth() + 1);
	r = r && set_selection(document.getElementsByName('end_day')[0], timestamp.getDate());
	r = r && set_selection(document.getElementsByName('end_hour')[0], timestamp.getHours());
	r = r && set_selection(document.getElementsByName('end_minute')[0], timestamp.getMinutes());
	r = r && set_selection(document.getElementsByName('end_second')[0], timestamp.getSeconds());

	return r;
}


function set_selection(select, value)
{
	for (var i = 0; i < select.options.length; i++)
	{
		if (typeof(value) == 'number')
		{
			if (parseInt(select.options[i].value, 10) == value)
			{
				select.selectedIndex = i;
				return true;
			}
		}
		else
		{
			if (select.options[i].value == value)
			{
				select.selectedIndex = i;
				return true;
			}
		}
	}
	return false;
}


function set_start_time_selections(timestamp)
{
	var r = true;

	r = r && set_selection(document.getElementsByName('start_year')[0], timestamp.getFullYear());
	r = r && set_selection(document.getElementsByName('start_month')[0], timestamp.getMonth() + 1);
	r = r && set_selection(document.getElementsByName('start_day')[0], timestamp.getDate());
	r = r && set_selection(document.getElementsByName('start_hour')[0], timestamp.getHours());
	r = r && set_selection(document.getElementsByName('start_minute')[0], timestamp.getMinutes());
	r = r && set_selection(document.getElementsByName('start_second')[0], timestamp.getSeconds());

	return r;
}


function tail()
{
	var form = document.getElementById('control_form');

	var timestamp = new Date();
	set_start_time_selections(adjust_date(timestamp, -60000));
	set_end_time_selections(timestamp);

	if (form)
	{
		form.action = form.action + '#tail';
		form.onsubmit();
		form.submit();
	}
}


function toggle_display(id)
{
	var element = document.getElementById(id);

	if (! element)
	{
		return -1;
	}
	if (element.style.display == 'none')
	{
		element.style.display = '';
		return 1;
	}
	element.style.display = 'none';
	return 0;
}

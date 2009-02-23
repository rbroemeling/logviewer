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


function highlight_named_anchor()
{
	if (window.location.hash.match(/^#\d+$/))
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


function reset_form()
{
	var inputs = document.getElementsByTagName('input');

	for (var i = 0; i < inputs.length; i++)
	{
		if (inputs[i].type == 'text' || inputs[i].type == 'hidden')
		{
			inputs[i].value = '';
		}
		else if (inputs[i].type == 'checkbox')
		{
			inputs[i].checked = false;
		}
		else if (inputs[i].type == 'button' || inputs[i].type == 'submit')
		{
			/* Skip this input, it doesn't need to be reset. */
		}
		else
		{
			alert("reset_form(): do not know how to clear an input of type " + inputs[i].type)
		}
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
}


function submit_form()
{
	var log_file_form = document.getElementById('log_file_form');
	var timestamp_input = document.getElementById('timestamp_input');

	if (timestamp_input)
	{
		var timestamp = new Date();
		timestamp_input.value = timestamp.getTime();
	}

	// Submit the form.
	if (log_file_form)
	{
		log_file_form.submit();
	}
}


function tail()
{
	var length_input = document.getElementById('length_input');
	var log_file_form = document.getElementById('log_file_form');
	var offset_input = document.getElementById('offset_input');

	if (length_input)
	{
		var length = parseInt(length_input.value);
		if (isNaN(length))
		{
			length = '';
		}
		else if (length == 0)
		{
			length = '';
		}
		else
		{
			length = Math.abs(length) * -1;
		}
		length_input.value = length.toString();
	}
	if (offset_input)
	{
		offset_input.value = '';
	}
	if (log_file_form)
	{
		log_file_form.action = log_file_form.action + '#tail';
	}
	submit_form();
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
		element.style.display = 'block';
		return 1;
	}
	element.style.display = 'none';
	return 0;
}

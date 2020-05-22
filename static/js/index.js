function datetime_to_obj(d) {
	// according to http://stackoverflow.com/questions/24703698/html-input-type-datetime-local-setting-the-wrong-time-zone
	d = d.replace(/-/g, "/");
	d = d.replace("T", " ");
	if (d.split(":").length < 3)
		d += ":59";
	var now = d.split(".");
	if (now.length > 1)
		d = now[0];
	return Date.parse(d);
}

$(document).ready(function() {
	updateViewsDropdown();

	$('#view-modal-form').submit(function(e) {
		if ($('#store-value').val()) {
			var storage = getStoredViews();
			var val = $('#store-value').val();

			var id = storage.length + 1;
			var name = '#' + id;
			if ($('#view-modal-name').val()) {
				name = $('#view-modal-name').val();
				$('#view-modal-name').val('');
			} else {
				try {
					name = name + ' ' + Object.keys(JSON.parse(val)).join('-');
				} catch (err) {}
			}
			var duplicateIndex = storage.findIndex(i => i.name == name);
			if (duplicateIndex != -1)
				storage.splice(duplicateIndex, 1);
			localStorage.setItem('msg-views', JSON.stringify(storage.concat([{ id: id, name: name, value: val }])));
			updateViewsDropdown();
		}
		$('#view-modal').modal('toggle');
		e.preventDefault();
	});

	$('#btn-delete-view').on('click', function() {
		if ($(this).data('id')) {
			var storage = getStoredViews();
			var index = storage.findIndex(i => i.id == $(this).data('id'));
			if (index != -1) {
				storage.splice(index, 1);
				localStorage.setItem('msg-views', JSON.stringify(storage.map(function(i, index) {
					i.id = index + 1;
					return i;
				})));
				$(this).data('id', '').addClass('disabled');
				updateViewsDropdown();
			}
		}
	});
});

function updateViewsDropdown() {
	var storage = getStoredViews();
	if (storage.length > 0) {
		$('#storage-views').find('.view-item').remove();
		var currentId = $('#btn-delete-view').data('id');

		storage.forEach((item, i) => {
			var itemElement = $('<a class="dropdown-item text-truncate view-item" href="#"></a>').data('id', item.id).html(item.name).on('click', function() {
				try {
					var storage = getStoredViews();
					var item = storage.find(i => i.id == $(this).data('id'));
					if (item) {
						$('#multifilter-form').find('.multifilter-input').remove();
						var filters = JSON.parse(item.value);
						var i = 0;
						$('<input type="hidden" name="multifilter[id]" class="multifilter-input">').val(item.id).appendTo('#multifilter-form');
						Object.keys(filters).forEach(function(key) {
							$('<input type="hidden" name="multifilter[items][' + i + '][field]" class="multifilter-input">').val(key).appendTo('#multifilter-form');
							$('<input type="hidden" name="multifilter[items][' + i + '][operator]" class="multifilter-input">').val(filters[key][0].operator).appendTo('#multifilter-form');
							$('<input type="hidden" name="multifilter[items][' + i + '][value]" class="multifilter-input">').val(filters[key][0].value).appendTo('#multifilter-form');
							i++;
						});
						$('#multifilter-form').submit();
					}
				} catch (err) {}
			}).appendTo('#storage-views');

			if (currentId && currentId == item.id)
				itemElement.addClass('active');
		});
	}
}

function getStoredViews() {
	try {
		var raw = localStorage.getItem('msg-views');
		var storage = JSON.parse(raw);
		return Array.isArray(storage) ? storage : [];
	} catch (err) {
		return [];
	}
}

function getSearchDate(ts = null) {
	if (ts == null) {
		var currentDate = new Date();
		var hours = '00';
		var minutes = '00';
		var seconds = '00';
	} else {
		var currentDate = new Date(ts);
		var hours = currentDate.getHours();
		hours = (hours < 10) ? '0' + hours : hours;
		var minutes = currentDate.getMinutes();
		minutes = (minutes < 10) ? '0' + minutes : minutes;
		var seconds = currentDate.getSeconds();
		seconds = (seconds < 10) ? '0' + seconds : seconds;
	}
	var year = currentDate.getFullYear();
	var month = currentDate.getMonth()+1;
	month = (month < 10) ? '0' + month : month;
	var day = currentDate.getDate();
	day = (day < 10) ? '0' + day : day;
	return year + '/' + month + '/' + day + ' ' + hours + ':' + minutes + ':' + seconds;
}

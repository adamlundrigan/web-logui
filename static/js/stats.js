$(document).ready(function() {
	var chartId = 0;
	var chartList = [];
	var chartListData = [];
	var intervalHandler = null;

	var localStore = localStorage.getItem('charts-view-' + containerName);
	if (typeof localStore === 'string') {
		var charts = JSON.parse(localStore);
		charts.map(function (i) {
			addChart(++chartId, i.chart, i.type, i.target, i.width);
		});
	}

	if (chartId == 0 && typeof defaultView != 'undefined' && Array.isArray(defaultView)) {
		defaultView.map(function (i) {
			addChart(++chartId, i.chart, i.type);
		});
	}

	$('#mode-recent').on('change', function() {
		if ($(this).prop('checked') == true) {
			$('#fa-recent').addClass('fa-spin');

			$('#datepicker').find('input, button').each(function () {
				$(this).prop('disabled', true);
			});

			updateCharts();
			intervalHandler = setInterval(updateCharts, 10000);
		}
	});

	$('#mode-interval').on('change', function() {
		if ($(this).prop('checked') == true) {
			$('#fa-recent').removeClass('fa-spin');

			$('#datepicker').find('input, button').each(function () {
				$(this).prop('disabled', false);
			});

			updateCharts();
			clearInterval(intervalHandler);
		}
	});

	$('#card-container').sortable({
		handle: '.card-header',
		change: function (event, ui) {
			$('#save-changes').attr('hidden', false);
		}
	});

	$('a.chart-add').on('click', function() {
		var chart = $(this).parent().data('chart');
		var type = $(this).data('type');

		if (addChart(++chartId, chart, type, ''))
			$('#save-changes').attr('hidden', false);
	});

	$('#save-changes').on('click', function() {
		var store = [];
		$('#card-container').children('[id^=chart-]').map(function (i, card) {
			store.push({
				chart: $(card).data('chart'),
				type: $(card).data('type'),
				target: $(card).data('target') ? $(card).data('target') : undefined,
				width: $(card).data('width') ? $(card).data('width') : undefined
			});
		});
		localStorage.setItem('charts-view-' + containerName, JSON.stringify(store));
		$(this).attr('hidden', true);
	});
});

function addChart(id, chart, type, target = '', width = '') {
	// card & header
	var chartElement = $('<div class="float-lg-left ' + (width == 'full' ? 'col-12' : 'col-lg-6') + ' pb-3" id="chart-' + id + '" data-chart="' + chart + '" data-id="' + id + '" data-type="' + type + '" data-target="' + target + '" data-width="' + width + '"></div>').appendTo("#card-container");
	var card = $('<div class="card"></div>').appendTo(chartElement);
	var cardHeader = $('<div class="card-header" draggable="true"></div>').appendTo(card);
	var cardHeaderRow = $('<div class="row"></div>').appendTo(cardHeader);

	var cardTitle = $('<div class="col-9 chart-title text-truncate"></div>').appendTo(cardHeaderRow);
	var cardToolbar = $('<div class="col-3 chart-toolbar"></div>').appendTo(cardHeaderRow);

	cardInputGroup = $('<div class="input-group chart-edit" style="position: absolute; top: -4px; right: 4px;" hidden></div>').appendTo(cardToolbar);
	cardInputGroupAppend = $('<div class="input-group-append"></div>').appendTo(cardInputGroup);
	$('<button type="button" class="btn btn-outline-secondary btn-sm" data-id="' + id + '"><i class="fa fa-check"></i></button>').on('click', function () {
		var value = $('#chart-' + $(this).data('id')).find('.filter-value').val();
		setChartData(
			$(this).data('id'),
			$('#chart-' + $(this).data('id')).data('chart'),
			$('#chart-' + $(this).data('id')).data('type'),
			value
		);
		$(chartElement).data('target', value);

		$('#save-changes').attr('hidden', false);

		$(cardTitle).removeClass('col-6').addClass('col-9');
		$(cardToolbar).removeClass('col-6').addClass('col-3');
		$('#chart-' + $(this).data('id')).find('.chart-edit').prop('hidden', true);
		$('#chart-' + $(this).data('id')).find('.btn-card-edit').prop('hidden', false);
		$('#chart-' + $(this).data('id')).find('.btn-card-close').prop('hidden', false);
		$('#chart-' + $(this).data('id')).find('.btn-card-expand').prop('hidden', false);
		$('#chart-' + $(this).data('id')).find('.btn-card-export').prop('hidden', false);
	}).appendTo(cardInputGroupAppend);

	if (typeof inputFilterOptions != 'undefined' && Array.isArray(inputFilterOptions))
		$('<select class="custom-select custom-select-sm filter-value"><option></option>' + inputFilterOptions.map(function (option) {
			return '<option value="' + option + '">' + option + '</option>';
		}) + '</select>').prependTo(cardInputGroup);
	else
		$('<input type="text" class="form-control form-control-sm filter-value" placeholder="' + inputFilterLabel + '">').prependTo(cardInputGroup);

	// chart
	var cardBody = $('<div class="card-body p-3"></div>').appendTo(card);
	$('<p class="text-center text-muted"><i class="fas fa-spinner fa-2x fa-spin loading"></i></p>').appendTo(cardBody);

	// domain button
	$('<button type="button" class="btn btn-link p-0 float-right text-secondary mr-2 btn-card-edit" title="Filter" data-id="' + id + '"><i class="fas fa-filter"></i></a>').on('click', function () {
		$(cardTitle).removeClass('col-9').addClass('col-6');
		$(cardToolbar).removeClass('col-3').addClass('col-6');
		$('#chart-' + $(this).data('id')).find('.btn-card-edit').prop('hidden', true);
		$('#chart-' + $(this).data('id')).find('.btn-card-close').prop('hidden', true);
		$('#chart-' + $(this).data('id')).find('.btn-card-expand').prop('hidden', true);
		$('#chart-' + $(this).data('id')).find('.btn-card-export').prop('hidden', true);
		$('#chart-' + $(this).data('id')).find('.chart-edit').prop('hidden', false);
		$('#chart-' + $(this).data('id')).find('.chart-input').focus();
	}).prependTo(cardToolbar);
	// export button
	$('<button type="button" class="btn btn-link p-0 float-right text-secondary mr-2 btn-card-export" title="Export to CSV"><i class="fa fa-file-download"></i></button>').on('click', function () {
		var data = chartListData[id];
		if (data) {
			var csvExport = 'data:text/csv;charset=utf-8,';
			csvExport += '"' + data.label + ' - ' + data.group + ' (' + chartRange.start + ' - ' + chartRange.stop + ')' + '"' + "\n";
			var labels = '';
			var rows = [];
			data.datasets.map((set, index) => {
				if (chart == 'line')
					labels += (index == 0 ? ',' : '') + '"' + set.label + '"' + ',';
				else
					labels += '"' + set.label + '"' + ',' + 'hits' + ',';
				set.data.map((hit, row) => {
					if (chart == 'line') {
						if (typeof rows[row] == 'undefined')
							rows[row] = { t: 0, y: '' };
						var day = moment(hit.t);
						rows[row] = { t: day.format('"MMM D, YYYY, hh:mm:SS A"'), y: rows[row].y + hit.y + ',' };
					} else {
						if (typeof rows[row] == 'undefined')
							rows[row] = '';
						rows[row] += ['"' + data.labels[row] + '"', hit].join(',') + ',';
					}
				});
			});
			csvExport += labels.replace(/\,$/, '') + "\n";
			csvExport += rows.map(row => {
				var val = '';
				if (chart == 'line')
					val = row.t + ',' + row.y;
				else
					val = row;
				return val.replace(/\,$/, '');
			}).join("\n");
			var download = document.createElement('a');
			download.setAttribute('href', encodeURI(csvExport));
			download.setAttribute('download', 'export.csv');
			document.body.appendChild(download);
			download.click();
			download.remove();
		}
	}).prependTo(cardToolbar);
	// expand button
	$('<button type="button" class="btn btn-link p-0 float-right text-secondary mr-2 btn-card-expand" title="Expand"><i class="fa fa-expand"></button>').on('click', function () {
		$(chartElement).data('width', $(chartElement).data('width') == 'full' ? '' : 'full');
		if ($(chartElement).data('width') == 'full')
			$(chartElement).removeClass('col-lg-6').addClass('col-12');
		else
			$(chartElement).removeClass('col-12').addClass('col-lg-6');
		$('#save-changes').attr('hidden', false);
	}).prependTo(cardToolbar);
	// close button
	$('<button type="button" class="btn btn-link p-0 float-right text-secondary btn-card-close" data-id="' + id + '"><i class="fa fa-times"></i></a>').on("click", function () {
		$('#chart-' + $(this).data('id')).fadeOut('slow', function () {
			$(this).remove();
			$('#save-changes').attr('hidden', false);
		});
	}).prependTo(cardToolbar);

	$('<canvas id="chart-canvas-' + id + '"></canvas>').appendTo(cardBody);

	setChartData(id, chart, type, target);

	return true;
}

function updateCharts() {
	$('#card-container').children('[id^=chart-]').map(function (i, card) {
		setChartData($(card).data('id'), $(card).data('chart'), $(card).data('type'), $(card).data('target'), true);
	});
}

function setChartData(id, chart, type, target, dataOnly = false) {
	$.post('?xhr', {
		'page': 'stats',
		'chart': chart,
		'type': type,
		'start': chartRange.start,
		'stop': chartRange.stop,
		'target': target,
		'mode': $('#mode-recent').prop('checked') == true ? 'fixed_interval' : undefined,
		'interval': $('#mode-recent').prop('checked') == true ? 'fixed_interval' : undefined
	}).done((data) => {
		if (data.error)
			return;
		if (typeof data.datasets === 'undefined')
			return;

		if (!dataOnly) {
			$('#chart-' + id).find('.loading').parent().remove();
			if (data.label)
				$('#chart-' + id).find('.chart-title').html(data.label + ' - ' + data.group);
			if (target)
				$('#chart-' + id).find('.chart-title').append(' (' + target + ')');
		}

		if (typeof this.chartList == 'undefined')
			this.chartList = [];

		if (typeof this.chartListData == 'undefined')
			this.chartListData = [];

		this.chartListData[id] = data;

		if (chart === 'line') {
			if (typeof this.chartList[id] == 'undefined')
				this.chartList[id] = time_chart($('#chart-canvas-' + id), data.datasets, data.interval);
			else
				time_chart_update(this.chartList[id], data.datasets, data.interval, false);
		}	else if (chart === 'bar') {
			if (typeof this.chartList[id] == 'undefined')
				this.chartList[id] = bar_chart($('#chart-canvas-' + id), data.labels, data.datasets);
			else
				chart_update(this.chartList[id], data.labels, data.datasets, false);
		} else if (chart === 'pie') {
			if (typeof this.chartList[id] == 'undefined')
				this.chartList[id] = pie_chart($('#chart-canvas-' + id), data.labels, data.datasets);
			else
				chart_update(this.chartList[id], data.labels, data.datasets, false);
		}
	});
}

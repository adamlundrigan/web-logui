$(document).ready(function() {
	var chartId = 0;
	var chartList = [];
	var intervalHandler = null;

	var localStore = localStorage.getItem('charts-view-' + containerName);
	if (typeof localStore === 'string') {
		var charts = JSON.parse(localStore);
		charts.map(function (i) {
			addChart(++chartId, i.chart, i.type, i.target);
		});
	}

	if (chartId == 0 && typeof defaultView != 'undefined' && Array.isArray(defaultView)) {
		defaultView.map(function (i) {
			addChart(++chartId, i.chart, i.type);
		});
	}

	$('#realtime-interval').on('click', function() {
		$(this).toggleClass('active');
		updateCharts();
		if ($(this).hasClass('active'))
			intervalHandler = setInterval(updateCharts, 5000);
		else
			clearInterval(intervalHandler);
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
		var target = targetDomain;

		if (addChart(++chartId, chart, type, target))
			$('#save-changes').attr('hidden', false);
	});

	$('#save-changes').on('click', function() {
		var store = [];
		$('#card-container').children('[id^=chart-]').map(function (i, card) {
			store.push({
				chart: $(card).data('chart'),
				type: $(card).data('type'),
				target: $(card).data('target') ? $(card).data('target') : undefined
			});
		});
		localStorage.setItem('charts-view-' + containerName, JSON.stringify(store));
		$(this).attr('hidden', true);
	});
});

function addChart(id, chart, type, target = '') {
	var duplicate = false;
	$('#card-container').children('[id^=chart-]').map(function (i, card) {
		if (chart == $(card).data('chart') && type == $(card).data('type') && target == $(card).data('target'))
			duplicate = true;
	});

	if (duplicate)
		return false;

	var chartElement = $('<div class="float-lg-left col-lg-6 pb-3" id="chart-'+ id +'" data-chart="'+ chart +'" data-id="'+ id +'" data-type="'+ type +'" data-target="' + target + '"></div>').appendTo("#card-container");
	var cardElement = $('<div class="card"></div>').appendTo(chartElement);
	var cardHeader = $('<div class="card-header" draggable="true"></div>').appendTo(cardElement);
	var cardBodyElement = $('<div class="card-body p-3"></div>').appendTo(cardElement);
	$('<p class="text-center text-muted"><i class="fas fa-spinner fa-2x fa-spin loading"></i></p>').appendTo(cardBodyElement);

	$('<a class="float-right text-secondary chart-close" href="#" data-id="'+ id +'"><i class="fa fa-times"></i></a>').on("click", function () {
		$('#chart-' + $(this).data('id')).fadeOut('slow', function () {
			$(this).remove();
			$('#save-changes').attr('hidden', false);
		});
	}).prependTo(cardHeader);
	$('<canvas id="chart-canvas-'+ id +'"></canvas>').appendTo(cardBodyElement);

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
		'interval': $('#realtime-interval').hasClass('active') ? 'fixed_interval' : undefined
	}).done((data) => {
		if (data.error)
			return;
		if (typeof data.datasets === 'undefined')
			return;

		if (!dataOnly) {
			$('#chart-' + id).find('.loading').parent().remove();
			if (data.label)
				$('#chart-' + id).find('.card-header').append(data.label + ' - ' + data.group);
			if (target)
				$('#chart-' + id).find('.card-header').append(' (' + target + ')');
		}

		if (typeof this.chartList == 'undefined')
			this.chartList = [];

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

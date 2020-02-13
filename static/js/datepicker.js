$(document).ready(function() {
	$('#datepicker').datepicker({
		format: 'yyyy-mm-dd',
		todayHighlight: true,
		autoclose: true,
		weekStart: 1
	});

	$('#shortcut_range a').on('click', function(event) {
    event.preventDefault();
		var range = $(this).data('range');
		var today = new Date();
		var oneday = 60 * 60 * 24 * 1000;
		var set_start = today.getTime();
    var set_end = today.getTime();

		switch (range) {
			case '1d':
				set_start = today.getTime();
				set_end = today.getTime();
				break;
			case '1w':
				set_start = today.getTime() - oneday * (today.getDay() - 1);
				set_end = today.getTime();
				break;
			case '1m':
				set_start = today.getTime() - oneday * (today.getDate() - 1);
				set_end = today.getTime();
				break;
			case '1y':
				var start = new Date(today.getFullYear(), 0, 1);
				var diff = today - start;
				set_start = today.getTime() - diff;
				set_end = today.getTime();
				break;
			case '24h':
					set_start = today.getTime() - oneday;
					set_end = today.getTime();
					break;
			case '30d':
				set_start = today.getTime() - oneday * 30;
				set_end = today.getTime();
				break;
			case '60d':
				set_start = today.getTime() - oneday * 60;
				set_end = today.getTime();
				break;
			case '6m':
				set_start = today.getTime() - oneday * 180;
				set_end = today.getTime();
				break;
		}

		$('#indexstart').datepicker('setDate', getFormatDate(set_start));
		$('#indexend').datepicker('setDate', getFormatDate(set_end));
	});
});

function getFormatDate(ts) {
	var currentDate = new Date(ts);
	var year = currentDate.getFullYear();
	var month = currentDate.getMonth() + 1;
	month = (month < 10) ? '0' + month : month;
	var day = currentDate.getDate();
	day = (day < 10) ? '0' + day : day;
	return year + '-' + month + '-' + day;
}
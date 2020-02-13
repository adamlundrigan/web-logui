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
	
});

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

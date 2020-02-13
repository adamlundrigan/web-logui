$(document).ready(function() {
	$('[data-action]').click(function(e) {
		var action = $(this).data('action');

		if (confirm("Are you sure you want to " + action + " this message?")) {
			$('#action').val(action);
			$('#actionform').submit();
		}

	});

	// Hide back button if page was opened in a new tab
	if (window.history.length == 1)
		$('#history_back').hide();
});

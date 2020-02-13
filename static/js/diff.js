diff_match_patch.prototype.diff_prettyHalonHtml = function(diffs, splitlines)
{
	var html = [];
	var i = 0;
	var pattern_amp = /&/g;
	var pattern_lt = /</g;
	var pattern_gt = />/g;
	for (var x = 0; x < diffs.length; x++) {
		var op = diffs[x][0];	// Operation (insert, delete, equal)
		var data = diffs[x][1];	// Text of change.
		var text = data.replace(pattern_amp, '&amp;').replace(pattern_lt, '&lt;')
			.replace(pattern_gt, '&gt;');
		switch (op) {
			case DIFF_INSERT:
				if (splitlines)
					html[x] = '<ins>' + $.trim(text).split("\n").join('</ins><ins>') + '</ins>';
				else
					html[x] = '<ins>' + text + '</ins>';
				break;
			case DIFF_DELETE:
				if (splitlines)
					html[x] = '<del>' + $.trim(text).split("\n").join('</del><del>') + '</del>';
				else
					html[x] = '<del>' + text + '</del>';
				break;
			case DIFF_EQUAL:
				if (splitlines)
					html[x] = '<span>' + $.trim(text).split("\n").join('</span><span>') + '</span>';
				else
					html[x] = '<span>' + text + '</span>';
				break;
		}
		if (op !== DIFF_DELETE) {
			i += data.length;
		}
	}
	return html.join('');
};

function diff_lineMode(text1, text2, splitlines)
{
	if (typeof(splitlines) === 'undefined') splitlines = false; 
	var dmp = new diff_match_patch();
	var a = dmp.diff_linesToChars_(text1, text2);
	var lineText1 = a.chars1;
	var lineText2 = a.chars2;
	var lineArray = a.lineArray;

	var diffs = dmp.diff_main(lineText1, lineText2, false);
	dmp.diff_cleanupSemanticLossless(diffs);
	dmp.diff_charsToLines_(diffs, lineArray);
	return dmp.diff_prettyHalonHtml(diffs, splitlines);
}

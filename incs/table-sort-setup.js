/* Stupid jQuery Table Sort
http://joequery.github.io/Stupid-Table-Plugin/  [StupidTable Plugin docs.webarchive]
requires stupidtable.min.js and html5shiv.js

Bare bones setup guide:

	* load jquery, stupidtable.min.js (and html5shiv.js for production)
	* load css-matrix.css for styling of sort arrows and much more
	* <table id="matrix">, must have <thead>
	* <th data-sort="int|float|string"> the columns to sort cell contents
	* <td data-sort-value="?"> to sort with invisible data instead of cell contents
	* first sorted column is default; add this to the TH to get an arrow on it on first run
			<span class="char_sort_arrow">▼</span><!-- default sort -->

Template for bare bones setup:

	<table id="matrix">
	<thead><tr>
	<th data-sort="int">integer column<span class="char_sort_arrow">▼</span><!-- default sort --></th>
	<th data-sort="string" >string column</th>
	etc...
	</tr></thead>

	<tr>
	<td>365</td>
	<td data-sort-value="data actually used for sort">user-facing data</td>
	</tr>

Th-th-that's all, folks!

*/

$(function(){
		
	$("#matrix").stupidtable(); // apply stupidtable intelligence to tables of id=matrix (large data tables)

	console.log('Matrix table initialized for sorting');

/* $("#matrix").on("beforetablesort", function (event, data) {
// Apply a "disabled" look to the table while sorting.
// Using addClass for "testing" as it takes slightly longer to render.
// $("#msg").text("Sorting...");
// $("#matrix").addClass("disabled"); // sort may be too fast for this to be needed, but it's nice if the sort is slow enough
}); */

	$("#matrix").bind('aftertablesort', function (event, data) {
		// data.column - the index of the column sorted after a click
		// data.direction - the sorting direction (either asc or desc)
		// $("#msg").html("&nbsp;");
		// $("#matrix").removeClass("disabled");
		var th = $(this).find("th");
		th.find(".char_sort_arrow").remove();
		var arrow = data.direction === "asc" ? "&#9650;" : "&#9660;"; // 9650=up-triangle, 9660=down-triangle
		th.eq(data.column).append('<span class="char_sort_arrow">' + arrow +'</span>');
		// the sort_arrow class needs to be styled with a special font_stack to ensure rendering on most devices
		document.getElementById("sort_help").style.display = 'none'; // if there’s id=sort_help, make it go away after the first sort
		});

	});


$(document).ready(function(){
	$(".index").click(function(){
		document.location.href = '/';
	});

	$(".exit").click(function(){
		$.cookie("user", null);
		document.location.reload();
	});
});

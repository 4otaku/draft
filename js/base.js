$(".disabled").live('click', function(e){
	e.preventDefault();
});

$(document).ready(function(){
	$(".faq").click(function(){
		document.location.href = '/faq/';
	});

	$(".index").click(function(){
		document.location.href = '/';
	});

	$(".exit").click(function(){
		$.cookie("user", null);
		document.location.reload();
	});
});

function setSizes() {
   var containerHeight = $(window).height();
   $(".content").height(containerHeight - 40);
}

$(window).resize(function() { setSizes();});

$(document).ready(function(){
	setSizes();

	$(".faq").click(function(){
		document.location.href = '/faq/';
	});

	$(".exit").click(function(){
		$.cookie("user", null);
		document.location.reload();
	});
});

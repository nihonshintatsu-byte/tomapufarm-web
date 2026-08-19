//SPメニューボタン
$(function(){
	$('.menu-trigger').click(function(){

		if($(this).hasClass('active')){
      $(this).removeClass('active');
      $('.global_navi').removeClass('active');
    } else {
			$(this).addClass('active');
			$('.global_navi').addClass('active');
		}
	});
});
$(function(){
	$('#Navi ul li a').click(function(){

		if($('.menu-trigger').hasClass('active')){
			$('.global_navi').slideUp();
      $('.menu-trigger').removeClass('active');
    } else {
			$('.global_navi').slideDown();
			$('.menu-trigger').addClass('active');
		}
	});
});


// ------------ スムーススクロール ------------
$(function(){
	$('a[href^=#]').click(function(){
		var speed = 500;
		var href= $(this).attr("href");
		var target = $(href == "#" || href == "" ? 'html' : href);
		var position = target.offset().top - 80 ;
		$("html, body").animate({scrollTop:position}, speed, "swing");
		return false;
	});
});


$(function(){

	$('.mv_slide').slick({

    arrows: false,
    autoplay: true,
    autoplaySpeed: 4000,
    speed: 800,

	});

});

/* common for JQUERY AND ZEPTO */


$(document).ready(function(){
    
	var baseurl = $("meta[name='BEdita.base']").attr('content');

	/*
	function unichar() {
		var randomNumber = (Math.floor((Math.random() * 100)) % 94) + 33;
		mystring = String.fromCharCode(randomNumber);
		return mystring;
		//http://www.babelstone.co.uk/Unicode/unicode.html
	}
	*/

	$("section.column.inside:first",$(".didascalicon")).addClass("double");

	// function sortAlphaSurname(a,b){
	//     return $(".surname",a).text().toLowerCase() > $(".surname",b).text().toLowerCase() ? 1 : -1;
	// };
/*
	$("header .logo A, footer A.logo").bind('click', function(e){
		e.preventDefault();
		window.location.href = baseurl;
	});
*/
	//placeholders
	function placeMyHolders() {

		$("A.placeholder[href]",$(".current")).each(function(i){

			var mitem = $(this).attr("href");
			var refobject = $("figure#"+mitem,$(".attach"));
			if (refobject.length != 0) {
				$(this).unwrap().hide().after(refobject);
			}
		});
		$(".attach article:empty").parent("section").remove();
	}

	placeMyHolders();

	//ajax for getMedia
	$("figure A").click(function(e){
		var name = $(this).attr("name");
		e.preventDefault();
		var url = $(this).attr("href");
		/*$(".column").not(":first-child").hide();
        (".column").not(":first").hide();*/
        $(".column").hide();
		$(".column:first-child.double").hide();
		$(".column.media").remove();
		$("html").scrollTop(0);
		$("body").scrollTop(0);
		$("<section class='column media' id='"+name+"' style='min-height:600px; width:610px'>")
		.appendTo("BODY")
		.css({
			background: "transparent url('/img/loadingAnimation.gif') center 150px no-repeat"
		})
		.load(url, function() {
		 	$(this).css("background-image","none");
		});

	});

	var u = $(".slideshow article").size();
	var numRand = Math.floor(Math.random()*u);
	$(".slideshow article").eq(numRand).show();


	$(".media figure img,.close").live('click', function(){
		var name= $(this).closest(".column.media").remove().find("figure[ref]").attr("ref");
		$(".column").show();
		window.location.hash = name;
	});

	//$("P.unichar").text(unichar());

	 // $('#illustrators article').sort(sortAlphaSurname).appendTo('#illustrators');
	 // $('#more_illustrators LI').sort(sortAlphaSurname).appendTo('#more_illustrators');


	$("figure.partial A").unbind('click');

	$("figure.partial").css("cursor","pointer").toggle(
	  function () {
		var hh = $("img",$(this)).height();
	     $("A",$(this)).animate({
		 	height:hh+"px"
		},200);
	  },
	  function () {
	     $("A",$(this)).animate({
			height:"200px"
		},200);
	  }
	);

	$(".share").click(function(){
		$("#shareform").fadeToggle("fast");
	});

	$(".differenti-positivi-1").removeAttr("href");

	// promo page
	try {
        $('.flexslider').flexslider({
            animation: 'slide',
            animationLoop: true,
            slideshow: false,
            itemWidth: 768,
            useCSS: false,
        });
    } catch (err) {}

	$(".promo_menu li a, .top").click(function(event){
		event.preventDefault();
		var alto = $(".promo_nav").height();
		$('html, body').animate({scrollTop:$(this.hash).offset().top-(alto+40)}, 500);
		return false;
	});

	/*$(".menu-toggle").click(function(event){
		 $(".promo_menu").slideToggle("fast");
	});*/

	$("body.promo").parent().addClass('overflowMob');

    $('table').delegate('input[type=button].delete, button.delete', 'click', function (evt) {
        $(this).closest('tr').remove();
    });

    $('iframe[src^="https://player.vimeo.com"]').css({ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', }).wrap($('<div>').css({ position: 'relative', width: '100%', height: 0, overflow: 'hidden', paddingBottom: '56.25%' }));

});

$(document).keydown(function(e) {

	// variabili definite in get_media.tpl
	if (e.which == 37) { // freccia indietro
		if (previd == undefined) {
			$("#"+prevcol+"").click();
		} else {
			$("#"+previd+"").click();
		}
	}

	if (e.which == 39) { // freccia avanti
		$("#"+nextid).click();
	}

	if (e.which == 27) { // esc
		var name= $(".column.media").remove().find("figure[ref]").attr("ref");
		$(".column").show();
		window.location.hash = name;
	}

});

// promo page

//Initial load of page
//$(document).ready(sizeContent);

//Every resize of window
//$(window).resize(sizeContent);

//Dynamically assign height
/*function sizeContent() {
    var newHeight = $("html").height() - $(".promo_nav").height() - 40 + "px";
    $(".main_promo").css("height", newHeight);
}*/

$(function(){
        // Check the initial Poistion of the Sticky Header
        var stickyHeader = $('#stickyheader').offset() || {top: null};

        $(window).scroll(function(){
                if( $(window).scrollTop() > stickyHeader.top ) {
                        $('#stickyheader').css({position: 'fixed', top: '105px'});
                        $('#stickyalias').css('display', 'block');
                        $('.promo_nav').addClass('shadow');
                } else {
                        $('#stickyheader').css({position: 'static', top: '0px'});
                        $('#stickyalias').css('display', 'none');
                        $('.promo_nav').removeClass('shadow');
                }
        });
  });

//http://www.babelstone.co.uk/Unicode/unicode.html

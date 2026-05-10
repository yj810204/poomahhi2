// <![CDATA[
jQuery(function($){
	"use strict";

    $(".search_btn .search").on('click', function(){
        $(this).closest('.search_btn').addClass('on');
        $(".search_wg").addClass('on');
        $(".bh_layer.bh_layer_right").removeClass('on');
    });

    $(".search_btn .close, .search_wg .close").on('click', function(){ 
        $(".search_btn").closest('.search_btn').removeClass('on');
        $(".search_wg").removeClass('on');
    });


    $("footer .business_info").on('click', function(){ 
        $('footer .copyright').toggleClass('on');
    });
});
// ]]>
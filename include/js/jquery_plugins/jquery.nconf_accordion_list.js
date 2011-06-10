//
// accordion enhanced
//
(function($){
    $.fn.nconf_accordion_list = function(event) {
        return $(this).each( function() {

            // accordion style enhanced to handle all items
            if (event.type == 'click'){
                var clicked_element = $(this);
                clicked_element.toggleClass("ui-state-active ui-corner-bottom").next(".accordion_content").toggle("blind", function(){
                   clicked_element.children("span").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s");
                });
                return false;
            } else {
                $(this).toggleClass("ui-state-hover")
            }

        });
    };
})(jQuery);

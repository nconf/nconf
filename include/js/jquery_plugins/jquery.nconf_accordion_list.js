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

    // NConf's clever accordion
    // it saves the state as cookie and can so know the state of an element after refresh or revisiting the page
    $.fn.nconf_accordion_clever = function(event) {
        return $(this).each( function() {

            var accordion_id = $(this).attr("id");
            var cookie_status = readCookie(accordion_id);
            if (cookie_status && cookie_status == "closed") {
                $(this).toggleClass("ui-corner-bottom closed").next().hide();
            }else{
            }

            // Add a click event to open and close menu parts
            $(this).click(function() {
                $(this).toggleClass("ui-corner-bottom closed").next().slideToggle('slow');
                // Save the state as cookie to have a persistent state
                if ($(this).hasClass("closed") === false){
                    createCookie(accordion_id, "open", 365);
                }else{
                    createCookie(accordion_id, "closed", 365);
                }
                return false;
            });
        });
    };
})(jQuery);

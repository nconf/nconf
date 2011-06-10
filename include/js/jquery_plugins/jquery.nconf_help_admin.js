//
// HELP TEXT
//
//jQuery.nconf_help_admin = function() {
jQuery.nconf_help_admin = function(type) {

    $("#help_text > div").each(function(index) {
        var clicked_id = $(this).attr("id");

        // check if item exists, otherwise continue 
        var exists = $("#page_content").length;
        if (exists == 0){
            return true;
        }


        // create position for help
        var offset = $("#page_content").offset();
        var position_left = $("#page_content").outerWidth();
        position_left += offset.left;
        position_left += 20;
        var position_top = offset.top + 7;

        var help_text = $(this);
        $(this).addClass('help_box');
        $(this).dialog({
            autoOpen: false,
            position: [position_left, position_top]
        });


        if (type == null){
            var help_parent = $(":input[name='"+$(this).attr("id")+"']").parent().prev();
            // div instead of button fixes the button bug when clicked
            help_parent.append("<div></div>");
            var help_button = help_parent.children("div");
            
        }else if(type == "direct"){
            var help_button = $("div[name='"+$(this).attr("id")+"']");
        }


        help_button.button({
            icons: {
                primary: 'ui-icon-help'
            },
            text: false
        }).addClass("fg-button fg-button-icon-only");


        help_button.click(function() {
            var previouse_help_id = $(".help_box:visible").attr("id");
            if ($(".help_box:visible").dialog("isOpen") ){
                $(".help_box:visible").dialog('close');
            }
            // if the same help button was clicked, just close the box
            // if the clicked help is an other, display the new content
            if (clicked_id != previouse_help_id ){
                help_text.dialog('open');
            }

            return false;
        });

    });

};




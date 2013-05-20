// jquery document ready for all pages with active jQuery
$(document).ready(function(){

    // addClass to fieldset
    $('fieldset').not( $('#footer fieldset') ).addClass('ui-widget-content');
    $('#footer fieldset').not( $('fieldset fieldset') ).addClass('ui-state-highlight');

    // button style
    $( "#buttons > input, input:submit, input:button, :button" )
        .add('a > img', '#ui-nconf-icon-bar')
        .add('input[type="image"]', '#ui-nconf-icon-bar')
        .add('input.ui-button')
        .add('img.ui-button')
        .button();
        
    // buttons for images
    //$('.icon_buttons > a').button().children("span").removeClass("ui-button-text");


    // Back button
    $('#ui-nconf-icon-bar .button_back').button({
        icons: {
            primary: "ui-icon-arrowreturnthick-1-w"
        },
        text: false
    });
    $('#ui-nconf-icon-bar .button_overview').button({
        icons: {
            primary: "ui-icon-home"
        },
        text: false
    });

    // tooltip
    $.nconf_tooltip();

    // link hovers (a little bit complex because no simple theme css available)
    $( "a" ).live('hover', function () {
        $(this).not(".ui-button").not("[role=button]").toggleClass("ui-nconf-link");
    });

    // all image links should contain lighten class, for mouseover effect, expect the new toolbar icons
    $( "a > img").not('a > img', '#ui-nconf-icon-bar').addClass("lighten");
    
    // remove the current lighten class, which we do not want on our new buttons (which is still configured in image definitions)
    $('a > img', '#ui-nconf-icon-bar').removeClass("lighten");
        

    // give input fields a focus effect
    $("input[type=text],input[type=password], textarea, select")
        .addClass("ui-state-default ui-nconf-input")
        .bind('focus blur', function() {
            $(this).toggleClass("ui-state-focus");
        });




    /*  possible navigation for next GUI release
        needs also cookie settings to save current open/closed...

        // accordion style for each config host
        $("> div", $("#navigation > div")).children("h2").addClass("ui-nconf-header ui-corner-top ui-state-active").click( function() {
            var clicked_element = $(this);
            clicked_element.toggleClass("ui-state-active ui-corner-bottom").next("div.ui-widget-content.box_content").toggle("blind", function(){
                clicked_element.children("span").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s");
            });
            return false;
        });
    */

    /* for the moment we save this navigation handling here
    * perhaps we have to move this to an other place, and improve it further with cookie saving !
    */
   /*
    var cookie_status = readCookie('advanced_box');
    if (cookie_status && cookie_status == "open") {
        cookie_status = 0;
    }else{
        cookie_status = false;
    }
    $('.accordion h2').click(function() {
        $(this).toggleClass("ui-corner-bottom closed").next().slideToggle('slow');
        createCookie($(this).attr("id"), "closed", 365);
        return false;
    });
    
    */
    
    
    $('.accordion h2').each(function() {
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
    


});

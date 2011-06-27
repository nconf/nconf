// jquery document ready for all pages with active jQuery
$(document).ready(function(){

    // addClass to fieldset
    $('fieldset').not( $('#footer fieldset') ).addClass('ui-widget-content');
    $('#footer fieldset').not( $('fieldset fieldset') ).addClass('ui-state-highlight');

    // button style
    $( "#buttons > input, input:submit, input:button, :button" )
        .add('a > img', '#ui-nconf-icon-bar')
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

    // link hovers (a litle bit complex because no simple theme css available)
    $( "a" ).live('hover', function () {
        $(this).not(".ui-button").not("[role=button]").toggleClass("ui-state-hover ui-nconf-link");
    });

    // all image links should contain lighten class, for mouseover effect
    $( "a > img").addClass("lighten");

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



});

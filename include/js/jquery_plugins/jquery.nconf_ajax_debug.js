// this function gets the debug information of a file called over "ajax" (otherwise you are missing the informations on the remote file)
// files called over "call_file" can so be debugged and also receive errors
// debug must be enabled : debug=yes
jQuery.fn.nconf_ajax_debug = function() {
    return this.each(function(){
        // Handle ERROR information
        //$(this).filter("#jquery_error").append( $("#ajax_error") );
        
        // Handle DEBUG information
        $(this).filter("#ajax_error").appendTo( $("#jquery_error") );
        $("#ajax_error").parent("#jquery_error").show();
        //$("#jquery_console_parent").show();

        // Handle DEBUG information
        $(this).filter("#ajax_debug").prependTo( $("#jquery_console") );
        $("#jquery_console_parent").show();

    });

};

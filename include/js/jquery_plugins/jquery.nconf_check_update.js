//
// UPDATE CHECKER
// Try to detect newest version number and compares it with installed version
//
jQuery.nconf_check_update = function(installed_version) {
    $.getScript('http://update.nconf.org/check.js', function() {
        if (installed_version < check_update_version ){
            //newer version available
            $("#check_update_title").html( check_update_title );
            $("#check_update_content").html(check_update_content);
            $("#check_update").show("slow");
        }
    });

};

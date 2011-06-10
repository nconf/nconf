jQuery.nconf_help_get_nagios_documentation = function(class_name, attr_name) {
    //alert ("searching help for: " + class_name + " -> " + attr_name);

    // remove "-template" to match correct class
    class_name = class_name.replace("-template", "");
    // remove "-" in the class names
    class_name = class_name.replace("-", "");
    // nagios url
    var nagios_dir = "http://nagios.sourceforge.net/docs/3_0/";
    var nagios_objects = "objectdefinitions.html";

    var status = false;
    $.get('include/help/nagios_documentation/objectdefinitions.html', function(data) {

        $(data).find("a[name='"+class_name+"']").parent().nextAll("p.SectionTitle:contains('Directive Description')").eq(0).next("table").find("tr").each(function(){
            if ( $(this).find("td:first-child > strong").find("a").remove().end().html() == attr_name ){
                $("#nagios_documentation").html('<strong>' + attr_name + '</strong><br>' + $(this).children("td:first-child").next().html()).wrapInner('<p></p>');
                status = true;
                return false;
            }
        });

        if (status === false){
            $("#nagios_documentation").html('<i>nothing found in documentation</i>');
            $("#nagios_documentation_row:visible").hide("blind", "slow");
        }else{
            // manipulate link href
            $("#nagios_documentation").find("a:not(.nagio_docu_link)").each(function(index){
                var newHREF = nagios_dir;

                // add objects page if no html target is given
                if ( $( this ).is(':not([href*=".html"])') ){
                    newHREF += nagios_objects;
                }
                // add href content to generated link location
                newHREF += $( this ).attr('href');
                $( this ).attr('href', newHREF).attr('target', "_blank").addClass("nagios_docu_link");
            });
            // show row with help
            $("#nagios_documentation_row:hidden").show("blind", "slow");
        }

    });


};


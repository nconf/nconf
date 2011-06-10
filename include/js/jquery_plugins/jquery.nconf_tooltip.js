//
// TOOLTIP
//
jQuery.nconf_tooltip = function() {

    var flyingObj = null;
    var title_tmp = null;
    function CreateFlyingObj () {
        flyingObj = document.createElement ("div");
        flyingObj.style.position = "fixed";
        flyingObj.className = "tooltip";
        flyingObj.innerHTML = "";
        //flyingObj.style.width = "150px";
        //flyingObj.style.height = "20px";
        document.body.appendChild (flyingObj);
    }

    $('.jQ_tooltip').live("mouseenter", function(e) {
        if (!flyingObj) {
            CreateFlyingObj ();
        }
        flyingObj.innerHTML = $(this).attr("title");
        title_tmp = $(this).attr("title");
        if ( $(this).hasClass('forbidden') ){
            flyingObj.innerHTML = '<div><img src="../img/failed.png"><b>Forbidden:</b> </div> '+$(this).attr("title");
        }
        $(this).attr("title", "");
        flyingObj.style.display = "block";

    });


    $('.jQ_tooltip').live("mousemove", function(e) {
        if (!flyingObj) {
            CreateFlyingObj ();
        }
        flyingObj.style.left = e.clientX + "px";
        flyingObj.style.top  = e.clientY + "px";

    });

    $('.jQ_tooltip').live("mouseleave", function(e) {
        if (flyingObj != null) {
            flyingObj.style.display = "none";
            $(this).attr("title", title_tmp );
            flyingObj.innerHTML = "";
        }

    });


};




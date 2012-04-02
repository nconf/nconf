function swap_visible (ID) {
        if (document.getElementById(ID).style.display == "none"){
                document.getElementById(ID).style.display = "";
                document.getElementById("swap_icon_" + ID).src = "img/icon_collapse.gif";
        }else{
                document.getElementById(ID).style.display = "none";
                document.getElementById("swap_icon_" + ID).src = "img/icon_expand.gif";
        }
}

function swap_tree (ID, OPEN_IMG, CLOSE_IMG) {
        if (document.getElementById(ID).style.display == "none"){
                document.getElementById(ID).style.display = "";
                document.getElementById("swap_icon_" + ID).src = CLOSE_IMG;
        }else{
                document.getElementById(ID).style.display = "none";
                document.getElementById("swap_icon_" + ID).src = OPEN_IMG;
        }
}

function swap_advanced (ID) {
    var items = document.getElementsByName(ID);
    for (var i=0; i<items.length; i++) {
        if (document.getElementsByName(ID)[i].style.display == "none"){
                document.getElementsByName(ID)[i].style.display = "";
        }else{
                document.getElementsByName(ID)[i].style.display = "none";
        }
    }
}





function swap_checkboxes (ID) {
    var Inputs = document.getElementsByTagName("input");
    for (var i=0; i<Inputs.length; i++) {
        if(Inputs[i].type == 'checkbox'){
            if(Inputs[i].checked == false){
                Inputs[i].checked = true
            }else{
                Inputs[i].checked = false
            }
        }
    }
}

function submitform (FORMNAME, VALUE){
    var FormObj = document.forms[FORMNAME];
    FormObj.action = FormObj.action + "&goto=" + VALUE;
    FormObj.submit();
}


function disable (ID) {
    var items = document.getElementsByName(ID);
    for (var i=0; i<items.length; i++) {
        document.getElementsByName(ID)[i].disabled = true;
    }
}


/******************************************
// stays one release, will then be removed
/* AJAX helpers 

function attachInfo(s, TYPE) {
    if (s.attachEvent) {
        // IE
        s.attachEvent('onmouseover',function (){
            getText4IE(s, TYPE)
        });
        s.attachEvent('onpropertychange',function () {
            getText4IE(s, TYPE)
        });
    }
}

function getText4IE(ctrl, TYPE) {
    if (window.attachEvent) {
        ajax_loadContent('info_ajax','call_file.php?ajax_file=info.php&type='+TYPE+'&id='+ ctrl.value, '', showHideContent('', 'dhtmlgoodies_question1', 'show') );
    }
}

function getText(ctrl, TYPE) {
    ajax_loadContent('info_ajax','call_file.php?ajax_file=info.php&type='+TYPE+'&id='+ ctrl.value, '', showHideContent('', 'dhtmlgoodies_question1', 'show') );
}
**********************************************/



/************************************************************************************************************
!! special assign_many field !!

Following script parts are also written by fabian gander
the originaly idea of handling was from script @ www.dhtmlgoodies.com
The script is nearly totaly rewritten, so i do not go into detail of the copyright...

************************************************************************************************************/

    
var fromBoxArray = new Array();
var toBoxArray = new Array();
var selectBoxIndex = 0;
var arrayOfItemsToSelect = new Array();
var livesearchfromBox = new Array();
var livesearchBASE = new Array();
var livesearchfromBox_object = new Array();
var livesearchBASE_object = new Array();

function moveElementsUpDown(BoxIndex,move)
{
    tmpToBox = toBoxArray[BoxIndex];
    var tmp_move;
    if (move == "up"){
        for(var no=1;no<(tmpToBox.options.length);no++){
            var destination_no = no-1;
            if(tmpToBox.options[no].selected && !tmpToBox.options[destination_no].selected){
                tmpToBox.options[no].selected = false;
                // save upper entry
                tmp_move = tmpToBox.options[destination_no];
                // move selected entry one up and select it again
                tmpToBox.options[destination_no] = new Option(tmpToBox.options[no].text,tmpToBox.options[no].value);
                tmpToBox.options[destination_no].selected = true;
                // move upper entry one down
                tmpToBox.options[no] = new Option(tmp_move.text,tmp_move.value);
            }           
        }
    }else if(move == "down"){
        for(var no=(tmpToBox.options.length-2);no>=0;no--){
            var destination_no = no+1;
            if(tmpToBox.options[no].selected && !tmpToBox.options[destination_no].selected){
                tmpToBox.options[no].selected = false;
                // save upper entry
                tmp_move = tmpToBox.options[destination_no];
                // move selected entry one up and select it again
                tmpToBox.options[destination_no] = new Option(tmpToBox.options[no].text,tmpToBox.options[no].value);
                tmpToBox.options[destination_no].selected = true;
                // move upper entry one down
                tmpToBox.options[no] = new Option(tmp_move.text,tmp_move.value);
            }           
        }
    }else if(move == "top"){
        var tmpBoxArray = new Array();
        var select_count = 0;
        // First take all selected elements and create new option set
        for(var no=0;no<tmpToBox.options.length;no++){
            if(tmpToBox.options[no].selected){
                select_count++;
                tmpBoxArray.push(tmpToBox.options[no].text + '!!!' + tmpToBox.options[no].value);
                // remove element from box
                tmpToBox.remove(no);
                // because the element is removed, we need to set the index number 1 down for next 
                no = no -1;
            }
        }
        // Add all remaining (not selected) entries to option set
        for(var no=0;no<tmpToBox.options.length;no++){
            tmpBoxArray.push(tmpToBox.options[no].text + '!!!' + tmpToBox.options[no].value);
        }

        // Clean ToBox and takeover of new option set
        tmpToBox.options.length=0; 
        for(var no=0;no<tmpBoxArray.length;no++){
            var items = tmpBoxArray[no].split('!!!');
            tmpToBox.options[no] = new Option(items[0],items[1]);
            // select the elements
            if (no < select_count){
                tmpToBox[no].selected = true;
            }
        }       
    }else if(move == "bottom"){
        var tmpBoxArray = new Array();
        var select_count = 0;
        // First take all selected elements and create new option set
        for(var no=0;no<tmpToBox.options.length;no++){
            // NOT selected first: !
            if(!tmpToBox.options[no].selected){
                select_count++;
                tmpBoxArray.push(tmpToBox.options[no].text + '!!!' + tmpToBox.options[no].value);
                tmpToBox.remove(no);
                no = no -1;
            }
        }
        // Add all remaining (not selected) entries to option set
        for(var no=0;no<tmpToBox.options.length;no++){
            tmpBoxArray.push(tmpToBox.options[no].text + '!!!' + tmpToBox.options[no].value);
        }

        // Clean ToBox and takeover of new option set
        tmpToBox.options.length=0; 
        for(var no=0;no<tmpBoxArray.length;no++){
            var items = tmpBoxArray[no].split('!!!');
            tmpToBox.options[no] = new Option(items[0],items[1]);
            // select the elements higher than amount of not selected entries
            if (no >= select_count){
                tmpToBox[no].selected = true;
            }
        }       

    }
}

function moveElementsLeftRight(BoxIndex,move_direction,cust_order,element)
{
    var tmpFromBox;
    var tmpToBox;
    var move_all = new Boolean(false);
    // handle moving "all" elements
    if ( move_direction.match("_all") ){
        move_direction = move_direction.replace("_all", "");
        move_all = true;
    }
    if(BoxIndex !== false){
        // button movements
        if (move_direction == 'right'){
            tmpFromBox  = fromBoxArray[BoxIndex];
            tmpToBox    = toBoxArray[BoxIndex];
        }else if(move_direction == 'left'){
            tmpFromBox  = toBoxArray[BoxIndex];
            tmpToBox    = fromBoxArray[BoxIndex];
        }
    }else if (element.tagName.toLowerCase()=='select'){
        // doubleclicks on select
        tmpFromBox = element;
        var BoxIndex = element.parentNode.parentNode.id.replace(/[^\d]/g,'');
        if(tmpFromBox==fromBoxArray[BoxIndex]){
            tmpToBox = toBoxArray[BoxIndex];
        }else{
            tmpToBox = fromBoxArray[BoxIndex];
        }
    }
    
    // new jQuery functionality for movement
    if(move_all === true){
        $('#' + tmpFromBox.id + ' option').remove().appendTo('#' + tmpToBox.id);
    }else{
        $('#' + tmpFromBox.id + ' option:selected').remove().appendTo('#' + tmpToBox.id);
    }

    // Update the live search
    livesearch_update(BoxIndex);

    // Sort the entries in the destination box (only left one) when moving an item to left one
    if ( (cust_order == 0) || (cust_order == 1 && (move_direction == "left") ) ){
        // new ordering function
        $('#' + tmpToBox.id + ' option').sort(sortAlpha).appendTo('#' + tmpToBox.id);
    }
    
    // remove classes on left move
    if ( move_direction == "left" ){
        $('#' + tmpToBox.id + ' option').removeClass();
    }

}


// new Ordering function
// found @ http://www.wrichards.com/blog/2009/02/jquery-sorting-elements/
// including also the case insensitive ordering from the comment (.toLowerCase)
function sortAlpha(a,b){  
    return a.innerHTML.toLowerCase() > b.innerHTML.toLowerCase() ? 1 : -1;
};  


// update the livesearch values regarding the "tobox" and selectboxindex
function livesearch_update(selectBoxIndex)
{
    var ToBox = toBoxArray[selectBoxIndex];
    var tmp_livesearchBASE_object = new cloneObject(livesearchBASE_object[selectBoxIndex]);
    for(var i=0;i<ToBox.options.length;i++){
        delete tmp_livesearchBASE_object[ToBox.options[i].text];
    }
    delete livesearchfromBox_object[selectBoxIndex];
    livesearchfromBox_object[selectBoxIndex] = tmp_livesearchBASE_object;
}

// clones a object
function cloneObject(what) {
    for (i in what) {
        if (typeof what[i] == 'object') {
            this[i] = new cloneObject(what[i]);
        }
        else
            this[i] = what[i];
    }
}


// escape livesearch string for regex
function livesearch_encode(string)
{
    string = string.replace(/(\/|\.|\+|\?|\^|\$|\||\\|\(|\)|\[|\]|\{|\})/g, "\\$1");
    string = string.replace(/\*/g, ".*");
    return string;
}


// this section handles the livesearch input and limits the frombox
// fromBox: name of select field to manipulate
// element: the livesearch input element
function livesearch(fromBox, element)
{
    var boxRef = document.getElementById(fromBox);
    var selectBoxIndex = element.id.replace(/[^\d]/g,'');
    // value in livesearch field
    var query = element.value;
    if ( query != ""){
        query = livesearch_encode(query);
    }

    var tmpTextArray2 = new Array();
    tmpTextArray2 = livesearchfromBox_object[selectBoxIndex];
    boxRef.options.length=0;

    var i = 0;
    for (var key in tmpTextArray2){
        var items = tmpTextArray2[key].split('!!!');
        var myregexp = new RegExp("^" + query, "i")
        var Pos = items[0].match(myregexp);
        if (Pos){
            boxRef.options[i] = new Option(items[0],items[1]);         
            i++;
        }
    }       
    
}




/* This function highlights options in the "to-boxes".
    It is needed if the values should be remembered after submit.
    Call this function onsubmit for your form
*/
function multipleSelectOnSubmit()
{
    for(var no=0;no<arrayOfItemsToSelect.length;no++){
        var obj = arrayOfItemsToSelect[no];
        if (obj.length == 0){
            obj.options[0] = new Option("","");
        }
        for(var no2=0;no2<obj.options.length;no2++){
            obj.options[no2].selected = true;
        }
    }
    
}

function createMovableOptions(fromBox,toBox,totalWidth,totalHeight,labelLeft,labelRight,feature,cust_order,replace_mode)
{
    // set assign_cust_order(global), 0 if not given
    cust_order   = (cust_order == null) ? 0 : cust_order;
    replace_mode = (replace_mode == null) ? 0 : replace_mode;
    var livesearch_input_height = 19;
    fromObj = document.getElementById(fromBox);
    toObj = document.getElementById(toBox);
    livesearchBASE_object[selectBoxIndex] = new Object();
    livesearchfromBox_object[selectBoxIndex] = new Object();

    if (feature == "livesearch"){
        // creates the livesearch BASE values
        var tmpTextArray2 = new Array();
        for(var no=0;no<fromObj.options.length;no++){
            livesearchBASE_object[selectBoxIndex][fromObj.options[no].text] = (fromObj.options[no].text + '!!!' + fromObj.options[no].value);
        }
        // when there are modifies or pre defined values, they are already in the toBox, we must add theme also into the BASE
        for(var no=0;no<toObj.options.length;no++){
            livesearchBASE_object[selectBoxIndex][toObj.options[no].text] = (toObj.options[no].text + '!!!' + toObj.options[no].value);
        }
    }
    
    livesearchfromBox_object[selectBoxIndex] = livesearchBASE_object[selectBoxIndex];
    
    arrayOfItemsToSelect[arrayOfItemsToSelect.length] = toObj;

    // add doubleclick function
    fromObj.ondblclick = function () { moveElementsLeftRight(false,"right",cust_order,this) };
    toObj.ondblclick   = function () { moveElementsLeftRight(false,"left",cust_order,this) };
    
    fromBoxArray.push(fromObj);
    toBoxArray.push(toObj);
    var parentEl = fromObj.parentNode;
    
    var parentDiv = document.createElement('DIV');
    parentDiv.className='multipleSelectBoxControl ui-nconf-content ui-widget-content ui-corner-all';
    parentDiv.id = 'selectBoxGroup' + selectBoxIndex;
    if (cust_order == 1){
        parentDiv.style.width = (totalWidth + 30) + 'px';
    }else{
        parentDiv.style.width = totalWidth + 'px';
    }

    if (feature == "livesearch"){
        parentDiv.style.height = (totalHeight + livesearch_input_height) + 'px';
    }else{
        parentDiv.style.height = totalHeight + 'px';
    }
    parentEl.insertBefore(parentDiv,fromObj);
    
    
    var subDiv = document.createElement('DIV');
    //subDiv.style.width = (Math.floor(totalWidth/2) - 15) + 'px';
    subDiv.style.width = "44%";
    //fromObj.style.width = (Math.floor(totalWidth/2) - 17) + 'px';
    fromObj.style.width = "100%";
    var livesearch_input_width = (Math.floor(totalWidth/2) - 17);

    var label = document.createElement('SPAN');
    label.innerHTML = labelLeft;

    // add livesearch field
    if (feature == "livesearch"){
        label.innerHTML = label.innerHTML + '<input id="livesearch_input_' + selectBoxIndex + '" class="livesearch_input" type="text" onkeyup="livesearch(\'' + fromObj.id + '\', this)">';
    }
    subDiv.appendChild(label);
    
    subDiv.appendChild(fromObj);
    subDiv.className = 'multipleSelectBoxDiv';
    parentDiv.appendChild(subDiv);
    
    
    var buttonDiv = document.createElement('DIV');
    buttonDiv.style.verticalAlign = 'middle';
    // move buttons margintop for layout
    if (feature == "livesearch"){
        buttonDiv.style.paddingTop = (totalHeight/2) - 32 + 'px';
    }else{
        buttonDiv.style.paddingTop = (totalHeight/2) - 40 + 'px';
    }
    buttonDiv.style.width = '30px';
    buttonDiv.style.textAlign = 'center';
    parentDiv.appendChild(buttonDiv);

    // Move images
    var code = document.createElement('SPAN');
    code.id = "moveable_horizontal";
    code.className = "icon_buttons";

    code.innerHTML  = '<img class="pointer lighten" src="img/icon_right.gif" \
                        onclick="moveElementsLeftRight(\'' + selectBoxIndex + '\', \'right\', ' + cust_order + ')">';
    //                            onmouseover="this.src=\'img/icon_right_over.gif\'" \
    //                            onmouseout="this.src = \'img/icon_right.gif\'"> \

    code.innerHTML += '<img class="pointer lighten" src="img/icon_right2.gif" \
                        onclick="moveElementsLeftRight(\'' + selectBoxIndex + '\', \'right_all\', ' + cust_order + ')">';
    //                      onmouseover="this.src=\'img/icon_right2_over.gif\'" \
    //                      onmouseout="this.src = \'img/icon_right2.gif\'"> \


    code.innerHTML += '<img class="pointer lighten" src="img/icon_left.gif" \
                        onclick="moveElementsLeftRight(\'' + selectBoxIndex + '\', \'left\', ' + cust_order + ')">';
    //                        onmouseover="this.src=\'img/icon_left_over.gif\'" \
    //                        onmouseout="this.src = \'img/icon_left.gif\'"> \

    code.innerHTML += '<img class="pointer lighten" src="img/icon_left2.gif" \
                        onclick="moveElementsLeftRight(\'' + selectBoxIndex + '\', \'left_all\', ' + cust_order + ')">';
    //                        onmouseover="this.src=\'img/icon_left2_over.gif\'" \
    //                        onmouseout="this.src = \'img/icon_left2.gif\'"> \
    buttonDiv.appendChild(code);
    
    var subDiv = document.createElement('DIV');
    //subDiv.style.width = (Math.floor(totalWidth/2) - 15) + 'px';
    subDiv.style.width = "44%";
    //toObj.style.width = (Math.floor(totalWidth/2) - 17) + 'px';
    toObj.style.width = "100%";
    var label = document.createElement('SPAN');
    label.innerHTML = labelRight;
    subDiv.appendChild(label);
    subDiv.appendChild(toObj);

    // show add / replace mode
    if (replace_mode){
        var replaceDiv = document.createElement('DIV');
        replaceDiv.className = "replaceDiv";
        // define which element is checked
        if (replace_mode === 1){
        	var replacemode_checked_replace = 'checked="checked"';
        	var replacemode_checked_add		= '';
        }else if (replace_mode === 2){
        	var replacemode_checked_replace = '';
        	var replacemode_checked_add		= 'checked="checked"';
        }
        replaceDiv.innerHTML += '<input type="radio" name="replace_mode" id="replace_mode_replace" value="replace" ' + replacemode_checked_replace + '>';
        replaceDiv.innerHTML += '<label for="replace_mode_replace">overwrite existing</label>';
        replaceDiv.innerHTML += '<input type="radio" name="replace_mode" id="replace_mode_add" value="add" ' + replacemode_checked_add + '>';
        replaceDiv.innerHTML += '<label for="replace_mode_add">add additional</label>';
        subDiv.appendChild( replaceDiv);
        
    }

    parentDiv.appendChild(subDiv);      
    
    toObj.style.height = (totalHeight - label.offsetHeight) + 'px';
    fromObj.style.height = (totalHeight - label.offsetHeight) + 'px';

    //special setup for feature livesearch
    if (feature == "livesearch"){
        var livesearch_input = document.getElementById('livesearch_input_' + selectBoxIndex);
        // margin bottom defined in CSS
        var livesearch_input_marginBottom = 4;
        var toObjHeight = (totalHeight - label.offsetHeight + livesearch_input_height + livesearch_input_marginBottom);
        toObj.style.height = (toObjHeight) + "px";
        //livesearch_input.style.width = ( (livesearch_input_width) + "px");
        livesearch_input.style.width = "100%";
    }

    // CUST ORDER
    // possibility to move elements up and down
    if (cust_order){
        var buttonDiv = document.createElement('DIV');
        buttonDiv.style.verticalAlign = 'middle';
        // move buttons margintop for layout
        if (feature == "livesearch"){
            buttonDiv.style.paddingTop = (totalHeight/2) - 27 + 'px';
        }else{
            buttonDiv.style.paddingTop = (totalHeight/2) - 40 + 'px';
        }
        buttonDiv.style.width = '30px';
        buttonDiv.style.textAlign = 'center';
        parentDiv.appendChild(buttonDiv);

        var code = document.createElement('SPAN');
        code.id = "moveable_vertical";
        code.className = "buttons";

        code.innerHTML  = '<img class="pointer lighten" src="img/icon_top_24.gif" \
                        onclick="moveElementsUpDown(\'' + selectBoxIndex + '\', \'top\')">';
        //                onmouseover="this.src=\'img/icon_top_24_over.gif\'" \
        //                onmouseout="this.src = \'img/icon_top_24.gif\'" \

        code.innerHTML += '<img class="pointer lighten" src="img/icon_up_24.gif" \
                        onclick="moveElementsUpDown(\'' + selectBoxIndex + '\', \'up\')">';
        //                onmouseover="this.src=\'img/icon_up_24_over.gif\'" \
        //                onmouseout="this.src = \'img/icon_up_24.gif\'" \

        code.innerHTML += '<img class="pointer lighten" src="img/icon_down_24.gif" \
                        onclick="moveElementsUpDown(\'' + selectBoxIndex + '\', \'down\')">';
        //                onmouseover="this.src=\'img/icon_down_24_over.gif\'" \
        //                onmouseout="this.src = \'img/icon_down_24.gif\'" \

        code.innerHTML += '<img class="pointer lighten" src="img/icon_bottom_24.gif" \
                        onclick="moveElementsUpDown(\'' + selectBoxIndex + '\', \'bottom\')">';
        //                onmouseover="this.src=\'img/icon_bottom_24_over.gif\'" \
        //                onmouseout="this.src = \'img/icon_bottom_24.gif\'" \
        buttonDiv.appendChild(code);

    }


    livesearch_update(selectBoxIndex);
    selectBoxIndex++;
    
}





/* Cookie functions from http://www.quirksmode.org/js/cookies.html  */

function createCookie(name,value,days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime()+(days*24*60*60*1000));
        var expires = "; expires="+date.toGMTString();
    }
    else var expires = "";
    document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

function eraseCookie(name) {
    createCookie(name,"",-1);
}


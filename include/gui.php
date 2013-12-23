<?php
##
## GUI settings
##

#
# History Tab in detail view
# How much entries listed
#
define("HISTORY_TAB_LIMIT",          "10");


#
# Admin only fields (DISABLED for ordinary users)
#
$ADMIN_ONLY = array ("host_is_collector", "nc_permission");


# Labels & text
define("SELECT_NAME_NAGIOSSERVER",  "Nagiosserver");
define("FRIENDLY_NAME_NAGIOSSERVER","monitored by");
define("FRIENDLY_NAME_IPADDRESS",   "address");
define("FRIENDLY_NAME_OS",          "OS");
define("FRIENDLY_NAME_DETAILS",     "details");
define("FRIENDLY_NAME_EDIT",        "edit");
define("FRIENDLY_NAME_DELETE",      "delete");
define("FRIENDLY_NAME_SERVICES",    "services");
define("FRIENDLY_NAME_CLONE",       "clone");
define("FRIENDLY_NAME_ACTIONS",     "[ actions ]");
define("FRIENDLY_NAME_HOSTGROUP",   "hostgroup");
define("OVERVIEW_DETAILS",          "details");



# what to display in an empty field of a select box
define("SELECT_EMPTY_FIELD",        "&nbsp;");

# Navigation: Name of standard user and admin part
define("TXT_MENU_BASIC",            "Basic Items");             # USER MENU
define("TXT_MENU_ADDITIONAL",       "Additional Items");        # ADDITIONAL MENU for ADMINS
define("TXT_MENU_ADMINISTRATION",       "Administration");        # ADMINISTRATION MENU



##
## ICONS
##

#
# OS icons
#
define("OS_LOGO_SIZE", "width=18 height=18");
define("FRIENDLY_NAME_OS_LOGO", "");    // Title above icons in overview

#
# Title
#
define("TITLE_SEPARATOR", " :: ");

#
# overview icons
#

# generell icons
define("ICON_EDIT",                 '<img src="img/icon_edit_16.png" class="jQ_tooltip lighten" title="Modify">');
define("ICON_DELETE",               '<img src="img/icon_delete_16.gif" class="jQ_tooltip lighten" title="Delete">');
define("ICON_SERVICES",             '<img src="img/icon_service.gif" class="jQ_tooltip lighten" title="Show services">');
# other icons
define("ICON_HISTORY",              '<img src="img/icon_history.gif" class="jQ_tooltip lighten" title="Show history">');
define("ICON_CLONE",                '<img src="img/icon_clone_16.gif" class="jQ_tooltip lighten" title="Clone">');
define("ICON_PARENT_CHILD",         '<img src="img/icon_parent_child_16.png" class="jQ_tooltip lighten" title="Show host parent / child view">');
define("ICON_SERVICE",              '<img src="img/icon_service.gif" class="lighten">');
define("ICON_SERVICES_DISABLED",    '<img src="img/icon_service_disabled.gif" class="jQ_tooltip lighten" title="one or more services disabled">');
define("ICON_SERVICE_DISABLED",     '<img src="img/icon_service_disabled.gif" class="jQ_tooltip lighten" title="service is disabled">');
define("ICON_SERVICE_ALERT",        '<img src="img/icon_service_alert.gif" class="jQ_tooltip lighten" title="service conflict">');

define("ICON_WARNING",              '<img width=24 height=24 src="img/icon_warning.png" alt="warn">');

define("ICON_LEFT",                 'img/icon_left.gif');

define("ICON_LEFT2",                'img/icon_left2.gif');

define("ICON_LEFT_FIRST",           'img/icon_left_first.gif');

define("ICON_RIGHT",                'img/icon_right.gif');

define("ICON_RIGHT2",               'img/icon_right2.gif');

define("ICON_RIGHT_LAST",           'img/icon_right_last.gif');

# Animated icons
define("ICON_LEFT_ANIMATED",        '<img src="'.ICON_LEFT.'" class="pointer lighten">');
define("ICON_LEFT_FIRST_ANIMATED",  '<img src="'.ICON_LEFT_FIRST.'" class="pointer lighten">');
define("ICON_RIGHT_ANIMATED",       '<img src="'.ICON_RIGHT.'" class="pointer lighten">');
define("ICON_RIGHT_LAST_ANIMATED",  '<img src="'.ICON_RIGHT_LAST.'" class="pointer lighten">');


# advanced tab submit icons
define("ADVANCED_ICON_CLONE",       'img/icon_clone_16.gif');
define("ADVANCED_ICON_MULTIMODIFY", 'img/icon_multi_modify.gif');
define("ADVANCED_ICON_DELETE",      'img/icon_delete_16.gif');
define("ADVANCED_ICON_SELECT",      'img/icon_check_box.gif');

# status icons
define("ICON_TRUE",             	'<img width=24 height=24 src="img/icon_true.png">');
define("ICON_FALSE",              	'<img width=24 height=24 src="img/icon_false.png">');
define("ICON_TRUE_SMALL",           '<img width=24 height=24 src="img/icon_true_16.png">');
define("ICON_FALSE_SMALL",          '<img width=16 height=16 src="img/icon_false_16.png">');
define("ICON_TRUE_RED",             '<img width=24 height=24 src="img/icon_true_red.png">');

# move icons
define("ICON_UP_BOX_BLUE",          '<img src="img/icon_up.png">');
define("ICON_DOWN_BOX_BLUE",        '<img src="img/icon_down.png">');

#
# overview list
#
# selectable quantity on overview
define('QUANTITY_SMALL',  '25');
define('QUANTITY_MEDIUM', '50');
define('QUANTITY_LARGE',  '100');


#
# show attribute icons
#

define("SHOW_ATTR_TEXT",            '<img src="img/icon_text.png" alt="text">');
define("SHOW_ATTR_PASSWORD",        '<img src="img/password.gif" alt="password">');
define("SHOW_ATTR_SELECT",          '<img width=24 height=24 src="img/icon_select.png" alt="select">');
define("SHOW_ATTR_ASSIGN_ONE",      '<img width=24 height=24 src="img/icon_assign_one.png" alt="assign one">');
define("SHOW_ATTR_ASSIGN_MANY",     '<img width=24 height=24 src="img/icon_assign_many.png" alt="assign may">');
define("SHOW_ATTR_ASSIGN_CUST_ORDER",		'<img width=24 height=24 src="img/icon_assign_cust_order.png" alt="assign cust order">');
define("SHOW_ATTR_NAMING_ATTR",     '<img width=24 height=24 src="img/icon_naming_attr.png" alt="naming attr">');
define("SHOW_ATTR_NAMING_ATTR_CONFLICT",    '<img width=24 height=24 src="img/icon_warning.png" alt="warn">');


# size of multi-select box
define("CSS_SELECT_MULTI",          "height:155px");


# Tree view
define("TREE_PLUS",         'img/tree_plus.gif');
define("TREE_PLUS_LAST",    'img/tree_plus_last.gif');
define("TREE_MINUS",        'img/tree_minus.gif');
define("TREE_MINUS_LAST",   'img/tree_minus_last.gif');
define("TREE_ITEM",         'img/tree_item.gif');
define("TREE_ITEM_LAST",    'img/tree_item_last.gif');
define("TREE_SPACE",        'img/tree_space.gif');
define("TREE_LINE",         'img/tree_line.gif');
define("TREE_FOLDER",       'img/tree_folder.gif');
define("TREE_PARENT",       'img/tree_parent.gif');
define("TREE_SERVICE",      'img/tree_service.gif');
define("TREE_INFO",         'img/tree_info.gif');
define("TREE_WARNING",      'img/icon_warning.png');

?>
    <h2 id="nav-menu-additional" class="ui-nconf-header ui-widget-header ui-corner-top pointer"><span><?php echo TXT_MENU_ADDITIONAL;?></span></h2>
    <div class="ui-widget-content box_content">
        <?php
        // FIX menu admin begin
        include('include/menu/static_content/menu_admin_begin.html');
        ?>

<!-- ###################### -->

        <?php
        // Select ConfigClasses
        $query = 'SELECT * FROM ConfigClasses WHERE nav_privs = "admin" AND nav_visible = "yes" ORDER BY UPPER(grouping), ordering ASC, config_class';
        $result = db_handler($query, "array", "Select admin Navigation classes");

        // Creates admin menu dynamic
        create_menu($result);
        ?>

    </div>

    <?php
    // FIX menu user end
    include('include/menu/static_content/menu_admin_end.html');

    // administration menu (editor, attributes and classes)
    include('include/menu/menu_administration.php');
    ?>

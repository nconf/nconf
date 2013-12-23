<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>



<div id="ui-nconf-icon-bar">
    <fieldset>
      <legend>advanced actions</legend>
      <div>
        <?php
          // TODO: General permission check. Could be an option to allow some users to modify on class level.
        
          // tool bar of details view
          $output = '';
          
          // Multi modify
          $output .= '<input type="image" id="submit_multimodify" src="'.ADVANCED_ICON_MULTIMODIFY.'" value="multimodify" name="multimodify" class="ui-button jQ_tooltip" title="Multimodify">';
          // Multi delete
          $output .= '<input type="image" id="submit_multidelete" src="'.ADVANCED_ICON_DELETE.'" value="multidelete" name="multidelete" class="ui-button jQ_tooltip" title="Delete">';
          
          // TODO: move parent/child view also into the table ?
          // $output .= ( $item_class == "host" ) ? '<a href="dependency.php?id='.$_GET["id"].'">'.ICON_PARENT_CHILD.'</a>' : '';
          
          echo $output;
      
        ?>
        </div>
    </fieldset>
</div>
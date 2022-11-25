<?php
/*
Plugin Name:  Colour Match
Description:  Colour Matching Tool
Version:      1.0.0
Author:       Roy Wenman
Author URI:   https://roywenman.co.uk
*/

/*
 * Setup
 */
if (!function_exists('request_filesystem_credentials')) {
    require (ABSPATH . '/wp-admin/includes/file.php');
}

function cm_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cm_deactivation' );

/*
 * Settings
 */
function cm_settings_init()
{
    // register the json setting
    register_setting('colourmatch_json', 'cm_colourmatch');

    // register a new section
    add_settings_section(
        'cm_json_section',
        'Colour Match Colours',
        'cm_settings_section_cb',
        'cm_colourmatch'
    );

    // register a new field
    add_settings_field(
        'cm_json',
        'Colour Match Setting',
        'cm_settings_field_cb',
        'cm_colourmatch'
    );
}

/**
 * register cm_settings_init to the admin_init action hook
 */
add_action('admin_init', 'cm_settings_init');

/**
 * callback functions
 */

// section content cb
function cm_settings_section_cb()
{
    echo '';
}

// field content cb
function cm_settings_field_cb()
{
    // get the value of the setting we've registered with register_setting()
    $setting = get_option('cm_json');
    // output the field
    ?>
    <input type="text" name="cm_json" value="<?php echo isset( $setting ) ? esc_attr( $setting ) : ''; ?>">
    <?php
}

/*
 * Because of the size of the JSON, we can't use get_option() to store it in a database
 * because it is simply too big, so instead we must read and write from a file
 */
function connect_fs($url, $method, $context, $fields = null)
{
    global $wp_filesystem;
    if(false === ($credentials = request_filesystem_credentials($url, $method, false, $context, $fields))) {
        return false;
    }
    
    //check if credentials are correct or not.
    if(!WP_Filesystem($credentials)) {
        request_filesystem_credentials($url, $method, true, $context);
        return false;
    }
    
    return true;
}

function write_file($json) {
    global $wp_filesystem;
    
    $url = wp_nonce_url("admin.php?page=aps_colour_match%2Faps_colour_match.php", "cm-nonce");
    //$url = "admin.php?page=colour_match/colour_match.php";
    
    if (connect_fs($url, "", WP_PLUGIN_DIR . "/colour_match/data", $form_fields)) {
        $dir = $wp_filesystem->find_folder(WP_PLUGIN_DIR . "/colour_match/data");
        $file = trailingslashit($dir) . "colours.json";
        $wp_filesystem->put_contents($file, $json, FS_CHMOD_FILE);
        
        return $text;
    } else {
        return new WP_Error("filesystem_error", "Cannot initialize filesystem");
    }
}

function read_file() {
    global $wp_filesystem;
    
    $url = wp_nonce_url("admin.php?page=colour_match%2Fcolour_match.php", "cm-nonce");
    
    if(connect_fs($url, "", WP_PLUGIN_DIR . "/colour_match/data")) {
        $dir = $wp_filesystem->find_folder(WP_PLUGIN_DIR . "/colour_match/data");
        $file = trailingslashit($dir) . "colours.json";
    
        if($wp_filesystem->exists($file)) {
            $text = $wp_filesystem->get_contents($file);
            
            if(!$text) {
                return "";
            } else {
                return $text;
            }
        } else {
            return new WP_Error("filesystem_error", "File doesn't exist");      
        } 
    } else {
        return new WP_Error("filesystem_error", "Cannot initialize filesystem");
    }
}

/*
 * Update settings
 */
if ($_POST) {
    $newColoursArray = array();

    if (!empty($_POST['colourName'] > 0)) {
        for($i = 0; $i < count($_POST['colourName']); $i++) {
            $newColoursArray[] = array(
                'name'    => $_POST['colourName'][$i],
                'desc'    => $_POST['colourDesc'][$i],
                'rating'  => $_POST['colourRating'][$i]
            );
        }

        /*
        * Append CSV file if present to list
        */ 
        if ($_FILES['csvfile']) {
            $csvArray = array_map('str_getcsv', file($_FILES['csvfile']['tmp_name']));

            foreach($csvArray as $line) {
                $newColoursArray[] = array(
                    'name'    => $line[0],
                    'desc'    => $line[1],
                    'rating'  => $line[2],
                );
            }
        }
        
        $jsonColoursArray = json_encode($newColoursArray);

        if ($jsonColoursArray) {
            write_file($jsonColoursArray);  
        }
    } else if ($_POST['deleteAll'] && $_POST['deleteAll'] == "on") {
        $newColoursArray = json_encode(array());
        write_file($newColoursArray);
    } else if (empty($_POST['colourName']) && $_FILES['csvfile']) {
        /*
         * If the list is empty
         */
        $csvArray = array_map('str_getcsv', file($_FILES['csvfile']['tmp_name']));

        foreach($csvArray as $line) {
            $newColoursArray[] = array(
                'name'    => $line[0],
                'desc'    => $line[1],
                'rating'  => $line[2],
            );
        }

        $jsonColoursArray = json_encode($newColoursArray);

        if ($jsonColoursArray) {
            write_file($jsonColoursArray);  
        }
    }

    
}

/*
 * Admin panel
 */
add_action( 'admin_menu', 'my_admin_menu' );

function my_admin_menu() {
	add_menu_page( 'Colour Match', 'Colour Match', 'manage_options', 'colour_match/colour_match.php', 'colourmatch_admin_page', 'dashicons-art', 20  );
}

function colourmatch_admin_page() {
	?>
    <style>
    .deleteAll {
        display: inline-block;
        font-weight: 400;
        line-height: 1.5;
        text-align: center;
        text-decoration: none;
        vertical-align: middle;
        cursor: pointer;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
        background-color: transparent;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        border-radius: 0.25rem;
        transition: 
        color .15s ease-in-out,
            background-color .15s ease-in-out,
            border-color .15s ease-in-out,
            box-shadow .15s ease-in-out;
        color: #dc3545;
        border-color: #dc3545;
    }

    .deleteAll:hover {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }

    .advancedOptions {
        display: none;
        margin-bottom: 20px;
        background-color: white;
        padding: 20px;
    }

    .showAdvanced {
        cursor: not-allowed;
        color: grey;
    }

    .loader {
        color: black;
        font-size: 15px;
        text-indent: -9999em;
        overflow: hidden;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        /*margin: 72px auto;*/
        position: relative;
        display: inline-block;
        -webkit-transform: translateZ(0);
        -ms-transform: translateZ(0);
        transform: translateZ(0);
        -webkit-animation: load6 1.7s infinite ease, round 1.7s infinite ease;
        animation: load6 1.7s infinite ease, round 1.7s infinite ease;
        }
        @-webkit-keyframes load6 {
        0% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        5%,
        95% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        10%,
        59% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.087em -0.825em 0 -0.42em, -0.173em -0.812em 0 -0.44em, -0.256em -0.789em 0 -0.46em, -0.297em -0.775em 0 -0.477em;
        }
        20% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.338em -0.758em 0 -0.42em, -0.555em -0.617em 0 -0.44em, -0.671em -0.488em 0 -0.46em, -0.749em -0.34em 0 -0.477em;
        }
        38% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.377em -0.74em 0 -0.42em, -0.645em -0.522em 0 -0.44em, -0.775em -0.297em 0 -0.46em, -0.82em -0.09em 0 -0.477em;
        }
        100% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        }
        @keyframes load6 {
        0% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        5%,
        95% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        10%,
        59% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.087em -0.825em 0 -0.42em, -0.173em -0.812em 0 -0.44em, -0.256em -0.789em 0 -0.46em, -0.297em -0.775em 0 -0.477em;
        }
        20% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.338em -0.758em 0 -0.42em, -0.555em -0.617em 0 -0.44em, -0.671em -0.488em 0 -0.46em, -0.749em -0.34em 0 -0.477em;
        }
        38% {
            box-shadow: 0 -0.83em 0 -0.4em, -0.377em -0.74em 0 -0.42em, -0.645em -0.522em 0 -0.44em, -0.775em -0.297em 0 -0.46em, -0.82em -0.09em 0 -0.477em;
        }
        100% {
            box-shadow: 0 -0.83em 0 -0.4em, 0 -0.83em 0 -0.42em, 0 -0.83em 0 -0.44em, 0 -0.83em 0 -0.46em, 0 -0.83em 0 -0.477em;
        }
        }
        @-webkit-keyframes round {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
        }
        @keyframes round {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    </style>
	<script>
	jQuery(document).ready(function($) {
        $('.loader').hide();
        $('.showAdvanced').css('cursor', 'pointer');
        $('.showAdvanced').css('color', 'black');

	    $('#addRow').on('click', function() {
	        var tr = $(
			    '<tr>'
			    + '<td class="cat"><input type="text" id="colourName[]" name="colourName[]" value="" size="25"></td>'
			    + '<td class="sel"><input type="text" id="colourDesc[]" name="colourDesc[]" value="" size="75"></td>'
			    + '<td id="textArea_cell"><input type="number" id="colourRating[]" name="colourRating[]" value="" size="10" min="1" max="4"></td>'
			    + '<td><button class="colourDelete" name="colourDelete">Delete</button></td>'
			    + '</tr>');
	        $('.colourTable > tbody:last').one().append(tr);
	    });

	    $('body').on('click', '.colourDelete', function() {
	        $(this).closest('tr').remove();
	        $('#saveWarningMessage').show();
	    });

        $('body').on('click', '#deleteAll', function() {
	        $('.colourTable > tbody').empty();
	        $('#saveWarningMessage').show();
	    });

        $('body').on('click', '.showAdvanced', function() {
            $('.advancedOptions').slideToggle();
	    });
	});
	</script>
	<div class="wrap">
		<h2>
            Colour Matcher
        </h2>
	</div>
	<p class="cm_description">
	    The Colour Matcher allows you to add as many colours as you would like, along with a description and a colour match rating for each colour.
	</p>
	<?php
    $dbColoursFull = json_decode(read_file(), true);
    ?>
      <form name="addcolours" method="post" action="" enctype="multipart/form-data">
            <h2 class="showAdvanced">
                <div class="loader">Loading...</div>
                Show Additional Options
            </h2>
            <div class="advancedOptions">
                <h3>Delete all rows</h3>
                <p> 
                    This will clear all rows in the table. You must make sure the checkbox is selected. You will still be required
                    to save the form to actually delete the rows. If you clicked it and want to undo, simply refresh the page or navigate away without saving.
                </p>
                <p>
                    <label for="deleteAll" class="deleteAll">
                        <input type="checkbox" id="deleteAll" name="deleteAll">
                        Delete all rows
                    </label>
                </p>

                <h3>Upload CSV</h3>
                <p>
                    Use this to upload a CSV file and add new entries to the end of the table. Click <a href="<?php echo plugins_url('', __FILE__ ); ?>/data/example.csv" target="_blank">here</a> for an example CSV template
                    of the expected file structure.
                </p>
                <p>
                    <input 
                        type="file"
                        id="csvfile"
                        name="csvfile"
                        accept=".csv"
                    >
                    <input type="submit" value="Save and upload"> <span id='saveWarningMessage' style='display: none; color: red; font-weight: bold;'>< Don't forget to save after deleting!</span>
                </p>
            </div>
          <table class="colourTable">
              <thead>
                  <tr>
                      <th>
                          Name
                      </th>
                      <th>
                          Description
                      </th>
                      <th>
                          Rating
                      </th>
                      <th>
                          Delete
                      </th>
                  </tr>
              </thead>
              <tbody>
              <?php
              $i = 1;
              if (!empty($dbColoursFull)) {
                foreach($dbColoursFull as $colour) {
                    ?>
                    <tr>
                        <td>
                            <input type="text" id="colourName[]" name="colourName[]" value="<?php echo $colour['name']; ?>" size="25">
                        </td>
                        <td>
                            <input type="text" id="colourDesc[]" name="colourDesc[]" value="<?php echo $colour['desc']; ?>" size="75">
                        </td>
                        <td>
                            <input type="number" id="colourRating[]" name="colourRating[]" value="<?php echo $colour['rating']; ?>" size="10" min="1" max="4">
                        </td>
                        <td>
                            <button class="colourDelete" name="colourDelete">Delete</button>
                        </td>
                    </tr>
                    <?php
                    $i++;
                }
            }
            ?>
            </tbody>
          </table>
          <?php wp_nonce_field("aps-nonce"); ?>
          <input type="button" id="addRow" name="addRow" value="Add Row">
          <input type="submit" value="Save"> <span id='saveWarningMessage' style='display: none; color: red; font-weight: bold;'>< Don't forget to save after deleting!</span>
      </form>


      <?php

	?>

	<?php
}

/*
 * Front end facing shortcode
 */
function colour_match( $atts ) {
    wp_enqueue_script('colour_picker');
    $dbColours = read_file();
    if ($dbColours == null) { return null; }

    $dbColoursFull = json_decode($dbColours, true);

    $output = "<select id='colourPicker'>";
    $output .= "<option>Please select a colour</option>";
    foreach($dbColoursFull as $row) {
        $output .= "<option value='".$row['name']."' data-desc='".$row['desc']."' data-rating='".$row['rating']."'>".$row['name']."</option>";
    }
    $output .= "</select>";

    $output .= "<div id='colourPickerInfo'>
                <div id='colourPickerName'>Name: <span id='colourName'></span></div>
                <div id='colourPickerDesc'>Description: <span id='colourDesc'></span></div>
                <div id='colourPickerRating'>Rating: <span id='colourRating'></span></div>
                </div>";

    return $output;

}
add_shortcode( 'colour_matcher', 'colour_match' );

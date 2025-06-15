#!/usr/bin/php
<?php
/**
 * @package admin
 * @copyright Copyright 2003-2019 Zen Cart Development Team
 * @copyright Copyright 2019 mc12345678: https://mc12345678.com
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Author: mc12345678  Tue Feb 6 2019 New in v4.0.37 $
 */
// uncomment the following line to disable this script execution in the case of an emergency malfunction when you can't access the server cron settings to kill the scheduled cron job:
// exit(1);
// This is intended to prevent unauthorized execution via a browser
$is_browser = (isset($_SERVER['HTTP_HOST']) || PHP_SAPI != 'cli');
if ($is_browser && isset($_SERVER["REMOTE_ADDR"]) && ($_SERVER["REMOTE_ADDR"] != $_SERVER["SERVER_ADDR"])){
  echo ' ERROR: Permission denied.';
  exit(1);
}
// Some servers' PHP configuration doesn't know where to find the mysql socket correctly (evidenced by getting errors about mysqli and mysql.sock, esp when running cron or command-line scripts, such as this one)
// uncomment the following line ONLY if your server's configuration requires it and you don't already have this in your configure.php file
// define('DB_SOCKET', '/tmp/mysql.sock');
// define('STRICT_ERROR_REPORTING', TRUE); // commented out for normal use
// define('DEBUG_AUTOLOAD', TRUE);         // commented out for normal use
define('IS_CLI', 'VERBOSE'); // options: VERBOSE will cause it to output informational messages. NONE or anything else will suppress status messages other than caught errors.
// Set timezone if passed as "TZ=Continent/City" (since often the PHP CLI doesn't know the same timezone as an apache vhost, and thus may not honor the vhost-specific date.timezone setting) (Yes, PHP 5.4+ ignores the TZ environment variable, but this uses it and takes it a step further for forward compatibility)
if (isset($_SERVER["argc"]) && $_SERVER["argc"] > 1) {
  for($i=1;$i<$_SERVER["argc"];$i++) {
    list($key, $val) = explode('=', $_SERVER["argv"][$i]);
    if ($key == 'TZ') {
      putenv($_SERVER["argv"][$i]);
      date_default_timezone_set($val);
    }
    if ($key == 'import') {
      $ep4_import_file = $val;
    }
    if (in_array($_SERVER["argv"][$i], array('help', '?', '-help', '--help', '-?', '-usage', 'usage'))) {
      echo 'Zen Cart(tm) Easy Populate V4 cron script.' . "\n\n";
      echo 'Usage: Create a cron job on your server, and give it the following command line:' . "\n";
      echo '       php /full/path/to/ep4_cron.php import=filename.csv' . "\n";
      echo '       php /full/path/to/ep4_cron.php TZ=America/Chicago import=filename.csv' . "\n";
      echo '       php /full/path/to/ep4_cron.php import=filename.csv TZ=America/Chicago' . "\n";
      echo '       php /full/path/to/ep4_cron.php -help' . "\n\n";
      echo "- May optionally add TZ=Continent/City to specify a PHP-recognized timezone \n  if your store/domain is set to a timezone other than the server default.\n";
      echo "- The filename.csv file is the file to be imported and stored in the file location used to import/export data files in Easy Populate V4.\n";
      echo "- NOTE: Script expects to be found in your store's (renamed) admin folder. \n  Moving it to another folder will break it.\n";
      echo "- Recommend running *infrequently*, as running too often is usually unnecessary.\n  Suggest once or twice per week, or maybe once or twice per day.\n  Hourly is fine, but is rarely necessary.\n";
      echo "\n\n";
      exit(0);
    }
  }
}
if (empty($ep4_import_file)) {
  die("Error: an import file was not identified.\nTo see the list of options execute this file with a parameter of -help.\n\n");
}

// setup
chdir( dirname(__FILE__) );
$loaderPrefix = 'epfour_cron'; // Can not use 'ep4_cron' as ZC filters for letters only
$_SERVER['REMOTE_ADDR'] = 'cron';
$_SERVER['REQUEST_URI'] = 'cron';
// Define any necessary overriding defines here before loading the system.
$result = require('includes/application_top.php');
if ($result == FALSE) {
  die("Error: application_top not found.\nMake sure you have placed the ep4_cron.php file in your (renamed) Admin folder.\n\n");
}
$_SERVER['HTTP_USER_AGENT'] = 'Zen Cart update';
// $_SERVER['REMOTE_ADDR'] = DB_SERVER;
// echo 'PHP_SAPI = ' . PHP_SAPI . "\n";

// Report inability to execute and exit operation with appropriate response.
if (!(defined('FILENAME_EASYPOPULATE_4') && file_exists((!strstr(FILENAME_EASYPOPULATE_4, '.php') ? FILENAME_EASYPOPULATE_4 . '.php' : FILENAME_EASYPOPULATE_4)))) {
  echo "Error: File not found: " . (!strstr(FILENAME_EASYPOPULATE_4, '.php') ? FILENAME_EASYPOPULATE_4 . '.php' : FILENAME_EASYPOPULATE_4) . ".\nMake sure you have placed the ep4_cron.php file in your (renamed) Admin folder and that Easy Populate V4 is properly installed.\n\n";
  exit(1);
}

// main execution area identifying if EP4 is installed
  if (IS_CLI == 'VERBOSE' && $is_browser) {
    echo '<br><pre>' . "\n";
  }
  if (IS_CLI == 'VERBOSE') {
    echo 'Importing file: ' . $ep4_import_file . '... ' . "\n";
  }
if (!isset($_SESSION['securityToken'])) {
  $_SESSION['securityToken'] = md5(uniqid(rand(), true));
}


  // assign cleansed $_POST values here instead of before loading the application_top.  If the security token is not set when application_top is executed, then $_POST values will be removed/fully cleansed.
  // $_POST['key'] = cleaning operation.

  $_POST['import'] = urlencode($ep4_import_file);
  if (class_exists('AdminRequestSanitizer')) {
    $sanitizer = AdminRequestSanitizer::getInstance();
    $sanitizer->runSanitizers();
    unset($sanitizer);
  }

  if (class_exists('queryFactoryEP4')) {
    $ep4['db'] = new queryFactoryEP4($db);
    $ep4['link'] = $ep4['db']->getLink();
  } else {
    $ep4['db'] = $db;
    $ep4['link'] = $ep4['db']->link;
  }
  $ep4['path'] = realpath(dirname(__FILE__));
  // Ensure ends with a directory divider to simplify further handling
  if ($ep4['path'] !== '/') {
    $ep4['path'] .= '/';
  }

  $csv_delimiter = ","; // "\t" = tab AND "," = COMMA
  $csv_enclosure = '"';
  $category_delimiter = "\x5e";
if (!function_exists('zen_define_default')) {
  if (!defined('EP4_DB_FILTER_KEY')) {
    define('EP4_DB_FILTER_KEY', 'products_model');
  }
  if (!defined('EP4_ADMIN_TEMP_DIRECTORY')) {
    define('EP4_ADMIN_TEMP_DIRECTORY', 'true');
  }
  if (!defined('EP4_SHOW_ALL_FILETYPES')) {
    // Intention is to force display of all file types and files for someone
    //  that hasn't done an update on the database as part of installing this
    //  software.  Perhaps could/need to create a default(s) file to
    //  assist with installation/operation.  mc12345678 12/30/15
    define('EP4_SHOW_ALL_FILETYPES', 'true');
  }
  if (!defined('EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE')) {
    define('EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE', 'language_id');
  }
} else {
  
  // Need to define this to support use of primary key for import/export
  //   Instead of adding an additional switch, have incorporated the conversion
  //   of a blank product_id field to a new product in here.  Currently
  //   expecting three choices: products_model, products_id, and blank_new
  //   with model as default
  zen_define_default('EP4_DB_FILTER_KEY', 'products_model'); // This could/should apply to both
  //  import and export files, so here is a good location for it.

  // Intention is to identify which file path to reference throughout instead of
  //  storing the path in the database. If the individual wishes to use the
  //  admin path, then this switch will direct the files to use the admin path
  //  instead of storing the path in the database.
  zen_define_default('EP4_ADMIN_TEMP_DIRECTORY', 'true'); // Valid Values considered (false, true)

  // Intention is to force display of all file types and files for someone
  //  that hasn't done an update on the database as part of installing this
  //  software.  Perhaps could/need to create a default(s) file to
  //  assist with installation/operation.  mc12345678 12/30/15
  zen_define_default('EP4_SHOW_ALL_FILETYPES', 'true');

  zen_define_default('EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE', 'language_id');

}
  $tempdir = defined('EASYPOPULATE_4_CONFIG_TEMP_DIR') ? EASYPOPULATE_4_CONFIG_TEMP_DIR : 'EASYPOPULATE_4_CONFIG_TEMP_DIR'; // This ideally should not actually include the Admin Directory in the variable.
  $ep_date_format = defined('EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT') ? EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT : 'm-d-y';
  $ep_raw_time = defined('EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME') ? EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME : '09:00:00';
  $ep_debug_logging = ($tempdir !== 'EASYPOPULATE_4_CONFIG_TEMP_DIR' && (!defined('EASYPOPULATE_4_CONFIG_DEBUG_LOGGING') || EASYPOPULATE_4_CONFIG_DEBUG_LOGGING !== 'false') ? true : false);
  $ep_debug_logging_all = ($tempdir !== 'EASYPOPULATE_4_CONFIG_TEMP_DIR' && (!defined('EASYPOPULATE_4_CONFIG_DEBUG_LOGGING') || EASYPOPULATE_4_CONFIG_DEBUG_LOGGING === 'all') ? true : false);

  $ep_split_records = defined('EASYPOPULATE_4_CONFIG_SPLIT_RECORDS') ? (int) EASYPOPULATE_4_CONFIG_SPLIT_RECORDS : 2000;
  $price_with_tax = ((defined('EASYPOPULATE_4_CONFIG_PRICE_INC_TAX') && EASYPOPULATE_4_CONFIG_PRICE_INC_TAX === 'true') ? 1 : 0);
  $strip_smart_tags = ((!defined('EASYPOPULATE_4_CONFIG_SMART_TAGS') || EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
  $max_qty_discounts = defined('EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS') ? EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS : '0';
  $ep_feedback = ((!defined('EASYPOPULATE_4_CONFIG_VERBOSE') || EASYPOPULATE_4_CONFIG_VERBOSE == 'true') ? true : false);
  $ep_execution = defined('EASYPOPULATE_4_CONFIG_EXECUTION_TIME') ? (int) EASYPOPULATE_4_CONFIG_EXECUTION_TIME : 60;
  $ep_curly_quotes = defined('EASYPOPULATE_4_CONFIG_CURLY_QUOTES') ? (int) EASYPOPULATE_4_CONFIG_CURLY_QUOTES : 0;
  $ep_char_92 = defined('EASYPOPULATE_4_CONFIG_CHAR_92') ? (int) EASYPOPULATE_4_CONFIG_CHAR_92 : 1;
  $ep_metatags = defined('EASYPOPULATE_4_CONFIG_META_DATA') ? (int) EASYPOPULATE_4_CONFIG_META_DATA : 1; // 0-Disable, 1-Enable
  $ep_music = defined('EASYPOPULATE_4_CONFIG_MUSIC_DATA') ? (int) EASYPOPULATE_4_CONFIG_MUSIC_DATA : 0; // 0-Disable, 1-Enable
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3' ? true : false);

  $ep_4_fetch_array = $ep_fetch_array = 'mysqli_fetch_array';
  $ep_4_num_rows = 'mysqli_num_rows';
  $ep_4_fetch_assoc = 'mysqli_fetch_assoc';
  $ep_4_insert_id = 'mysqli_insert_id'; // @TODO: Verify this works as expected.
  $ep_4_error = 'mysqli_error'; // @TODO: verify this works as expected.
  if (!$ep_uses_mysqli) {
    $ep_4_fetch_array = $ep_fetch_array = 'mysql_fetch_array';
    $ep_4_num_rows = 'mysql_num_rows';
    $ep_4_fetch_assoc = 'mysql_fetch_assoc';
    $ep_4_insert_id = 'mysql_insert_id'; // @TODO: Verify this works as expected.
    $ep_4_error = 'mysql_error';
  }

  $ep_4_strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
  $ep_4_split = function_exists('mb_split') ? 'mb_split' : 'explode';
  $ep_4_split_conv = function_exists('mb_split') ? 'preg_quote' : '';

@set_time_limit($ep_execution);  // execution limit in seconds. 300 = 5 minutes before timeout, 0 means no timelimit
  if (!isset($error) || !$error) {
    $upload_max_filesize = ini_get("upload_max_filesize");
    if (preg_match("/([0-9]+)K/i", $upload_max_filesize, $tempregs)) {
      $upload_max_filesize = $tempregs[1] * 1024;
    }
    if (preg_match("/([0-9]+)M/i", $upload_max_filesize, $tempregs)) {
      $upload_max_filesize = $tempregs[1] * 1024 * 1024;
    }
    if (preg_match("/([0-9]+)G/i", $upload_max_filesize, $tempregs)) {
      $upload_max_filesize = $tempregs[1] * 1024 * 1024 * 1024;
    }
  }

  //$ep_debug_logging_all = false;

  // Try to load early enough to not cause notices about missing values.

  $filename = 'easypopulate_4.php';
  $folder = '/';  // end with slash
  $langs = array();
  $langs[] = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
  $langs[] = 'english';

  $old_langfile = '';
  foreach ($langs as $lang) {
    $old_langfile = $ep4['path'] . DIR_WS_LANGUAGES . $lang . $folder . $filename;
    if (file_exists($old_langfile)) {
      break;
    }
  }

  if (class_exists('LanguageLoader')) {
    foreach ($langs as $lang) {
      $new_langfile = $ep4['path'] . DIR_WS_LANGUAGES . $lang . $folder . 'lang.' . $filename;
      if (file_exists($new_langfile)) {
        break;
      }
    }
  }

  if (class_exists('LanguageLoader') && file_exists($new_langfile)) {
    global $languageLoader;
    $languageLoader->loadExtraLanguageFiles($ep4['path'] . DIR_WS_LANGUAGES, $_SESSION['language'], $filename, $folder);
  } elseif (file_exists($old_langfile)) {
    $tpl_old_langfile = $ep4['path'] . DIR_WS_LANGUAGES . $_SESSION['language'] . $folder .  $template_dir . '/' . $filename;
    if (file_exists($tpl_old_langfile)) {
      $old_langfile = $tpl_old_langfile;
    }
    require $old_langfile;
  }

  $curver_detail = '4.0.39.ZC';
  if (file_exists(DIR_WS_MODULES . 'easypopulate_4_version.php')) {
    require DIR_WS_MODULES . 'easypopulate_4_version.php';
  }
  $display_output = ''; // results of import displayed after script run
  $ep_dltype = NULL;
  $ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error
  $specials_print = EASYPOPULATE_4_SPECIALS_HEADING;
  $has_specials = false;
  $ep_supported_mods = array();
  $smart_tags = array("\r\n|\r|\n" => '<br />',);
  if (substr($tempdir, -1) != '/') {
    $tempdir .= '/';
  }
  if (substr($tempdir, 0, 1) == '/') {
    $tempdir = substr($tempdir, 1);
  }
  //$ep_debug_log_path = DIR_FS_CATALOG . $tempdir;
  $ep_debug_log_path = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir;

  if (EP4_ADMIN_TEMP_DIRECTORY !== 'true') {
    if (strpos($ep_debug_log_path, DIR_FS_ADMIN) !== false) {
      $temp_rem = substr($ep_debug_log_path, strlen(DIR_FS_ADMIN));
      $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'true\' where configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', 1, false, 0, true);
      $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'' . $temp_rem . '\' WHERE configuration_key = \'EASYPOPULATE_4_CONFIG_TEMP_DIR\'', 1, false, 0, true);
      // @TODO need a message to  be displayed...
      // Reload the page with the path now reset. No parameters are passed.
//      zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
      die("File path had to be updated, import has not occurred.  Please attempt to import again.\n\n");
    }
  }
  if ($ep_debug_logging_all == true && is_dir($ep_debug_log_path)) {
    $fp = fopen($ep_debug_log_path . 'ep_debug_log.txt', 'w'); // new blank log file on each page impression for full testing log (too big otherwise!!)
    fclose($fp);
  }
  $chmod_check = ep_4_chmod_check($tempdir);
  $ep_4_SBAEnabled = ep_4_SBA1Exists();
  $ep4CEONURIDoesExist = false;
  if (ep_4_CEONURIExists() == true) {
    $ep4CEONURIDoesExist = true;
    if (empty($languages) || !is_array($languages)) {
      $languages = zen_get_languages();
    }
  }

  $ep_supported_mods['psd'] = ep_4_check_table_column(TABLE_PRODUCTS_DESCRIPTION, 'products_short_desc');
  $ep_supported_mods['uom'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_price_uom'); // uom = unit of measure, added by Chadd
  $ep_supported_mods['upc'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_upc');       // upc = UPC Code, added by Chadd
  $ep_supported_mods['gpc'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_gpc'); // gpc = google product category for Google Merchant Center, added by Chadd 10-1-2011
  $ep_supported_mods['msrp'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_msrp'); // msrp = manufacturer's suggested retail price, added by Chadd 1-9-2012
  $ep_supported_mods['map'] = ep_4_check_table_column(TABLE_PRODUCTS, 'map_enabled');
  $ep_supported_mods['map'] = ($ep_supported_mods['map'] && ep_4_check_table_column(TABLE_PRODUCTS, 'map_price'));
  $ep_supported_mods['gppi'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_group_a_price'); // gppi = group pricing per item, added by Chadd 4-24-2012
  $ep_supported_mods['excl'] = ep_4_check_table_column(TABLE_PRODUCTS, 'products_exclusive'); // exclu = Custom Mod for Exclusive Products: 04-24-2012
  $ep_supported_mods['dual'] = ep_4_check_table_column(TABLE_PRODUCTS_ATTRIBUTES, 'options_values_price_w');
  // END: check for existance of various mods
  $ep_mods_supported = array(
                        'psd'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SHORT_DESC,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS_DESCRIPTION, 'products_short_desc')
                        ),
                        'uom'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UNIT_MEAS,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_price_uom') // uom = unit of measure, added by Chadd
                        ),
                        'upc'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UPC,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_upc')       // upc = UPC Code, added by Chadd
                        ),
                        'gpc'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GOOGLE_CAT,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_gpc') // gpc = google product category for Google Merchant Center, added by Chadd 10-1-2011
                        ),
                        'msrp'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MSRP,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_msrp') // msrp = manufacturer's suggested retail price, added by Chadd 1-9-2012
                        ),
                        'map'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MAP,
                          'support' => (ep_4_check_table_column(TABLE_PRODUCTS, 'map_enabled') && ep_4_check_table_column(TABLE_PRODUCTS, 'map_price'))
                        ),
                        'gppi'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GP,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_group_a_price') // gppi = group pricing per item, added by Chadd 4-24-2012
                        ),
                        'excl'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_EXCLUSIVE,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS, 'products_exclusive') // exclu = Custom Mod for Exclusive Products: 04-24-2012
                        ),
                        'dual'=>array(
                          'title'=> EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_DPM,
                          'support' => ep_4_check_table_column(TABLE_PRODUCTS_ATTRIBUTES, 'options_values_price_w')
                        ),
                        'sba' => array(
                          'title' => EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SBA,
                          'support' => ($ep_4_SBAEnabled != false)
                        ),
                        'ceonuri' => array(
                          'title' => EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_CEON,
                          'support' => ($ep4CEONURIDoesExist == true)
                        )
                      );
  // custom products fields check
  $custom_field_names = array();
  $custom_field_check = array();
  $custom_fields = array();
  if (defined('EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS') && strlen(EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS) > 0) {
    $custom_field_names = explode(',', EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS);
    foreach ($custom_field_names as $field) {
      if (ep_4_check_table_column(TABLE_PRODUCTS, trim($field))) {
        $custom_field_check[] = TRUE;
        $custom_fields[] = trim($field);
      } else {
        $custom_further = explode(':', $field);
//        $custom_further[0] // expected to be table;
//        $custom_further[1] // expected to be field;
        if (count($custom_further) > 2) {
          //error message about field entry
        } else {
          // Test if table exists
          $temp_table = /*constant(*/'TABLE_' . strtoupper(trim($custom_further[0]));//);
          if (defined(/*strtoupper(*/$temp_table))/*)*/ {
            // then if table exists, test if field exists in table.
            if(ep_4_check_table_column(constant($temp_table), trim($custom_further[1]))) {
  // mc12345678 commented out for now until can fully address how to handle this.
//              $custom_field_check[] = TRUE;
//              $custom_fields[] = trim($custom_further[1]);
//              continue;
            }
          }
        }
        $custom_field_check[] = FALSE;
//        $custom_fields[] = NULL;
      }
    }
    unset($field);
  }
  // maximum length for a category in this database
  //$category_strlen_max = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name'); // mc12345678 don't see where this is used and therefore being removed.
  // maximum length for important fields
  $categories_name_max_len = zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name');
  $manufacturers_name_max_len = zen_field_length(TABLE_MANUFACTURERS, 'manufacturers_name');
  $products_model_max_len = zen_field_length(TABLE_PRODUCTS, 'products_model');
  $products_name_max_len = zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_name');
  $products_url_max_len = zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_url');
  $artists_name_max_len = zen_field_length(TABLE_RECORD_ARTISTS, 'artists_name');
  $record_company_name_max_len = zen_field_length(TABLE_RECORD_COMPANY, 'record_company_name');
  $music_genre_name_max_len = zen_field_length(TABLE_MUSIC_GENRE, 'music_genre_name');
  $max_len = array(
              'categories_name'     => zen_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name'),
              'manufacturers_name'  => zen_field_length(TABLE_MANUFACTURERS, 'manufacturers_name'),
              'products_model'      => zen_field_length(TABLE_PRODUCTS, 'products_model'),
              'products_name'       => zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_name'),
              'products_url'        => zen_field_length(TABLE_PRODUCTS_DESCRIPTION, 'products_url'),
              'artists_name'        => zen_field_length(TABLE_RECORD_ARTISTS, 'artists_name'),
              'record_company_name' => zen_field_length(TABLE_RECORD_COMPANY, 'record_company_name'),
              'music_genre_name'    => zen_field_length(TABLE_MUSIC_GENRE, 'music_genre_name')
           );
  $project = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
  if ($ep_uses_mysqli) {
    $collation = mysqli_character_set_name($ep4['link']); // should be either latin1 or utf8
  } else {
    $collation = mysql_client_encoding(); // should be either latin1 or utf8
  }
  if (substr($collation, 0, 4) == 'utf8') {
    if (function_exists('mb_internal_encoding')) {
      mb_internal_encoding("UTF-8");
    }
  }
  if (($collation == 'utf8') && ((substr($project, 0, 5) == "1.3.8") || (substr($project, 0, 5) == "1.3.9"))) {
    //mb_internal_encoding("UTF-8");
    foreach ($max_len as $key => $value) {
      $max_len[$key] = $value / 3;
    }
    unset ($key);
    unset ($value);
    $category_strlen_max = $category_strlen_max / 3;
    $categories_name_max_len = $categories_name_max_len / 3;
    $manufacturers_name_max_len = $manufacturers_name_max_len / 3;
    $products_model_max_len = $products_model_max_len / 3;
    $products_name_max_len = $products_name_max_len / 3;
    $products_url_max_len = $products_url_max_len / 3;
    $artists_name_max_len = $artists_name_max_len / 3;
    $record_company_name_max_len = $record_company_name_max_len / 3;
    $music_genre_name_max_len = $music_genre_name_max_len / 3;
    $zco_notifier->notify('EP4_COLLATION_UTF8_ZC13X');
  }
// test for Ajeh
//$ajeh_sql = 'SELECT * FROM '. TABLE_PRODUCTS .' WHERE '.TABLE_PRODUCTS.'.products_id NOT IN (SELECT '. TABLE_PRODUCTS_TO_CATEGORIES.'.products_id FROM '. TABLE_PRODUCTS_TO_CATEGORIES.')';
//$ajeh_result = ep_4_query($ajeh_sql);
// Pre-flight checks finish here
// check default language_id from configuration table DEFAULT_LANGUAGE
// chadd - really shouldn't do this! $epdlanguage_id is better replaced with $langcode[]
// $epdlanguage_id is the only value used here ( I assume for default language)
// default langauage should not be important since all installed languages are used $langcode[]
// and we should iterate through that array (even if only 1 stored value)
// $epdlanguage_id is used only in categories generation code since the products import code doesn't support multi-language categories
/* @var $epdlanguage_query array */
//$epdlanguage_query = $db->Execute("SELECT languages_id, name FROM ".TABLE_LANGUAGES." WHERE code = '".DEFAULT_LANGUAGE."'");
  if (!defined('DEFAULT_LANGUAGE')) {
    $epdlanguage_query = ep_4_query("SELECT languages_id, code, name FROM " . TABLE_LANGUAGES . " ORDER BY languages_id LIMIT 1");
    $epdlanguage = $ep_4_fetch_array($epdlanguage_query);
    define('DEFAULT_LANGUAGE', $epdlanguage['code']);
  }
  $epdlanguage_query = ep_4_query("SELECT languages_id, code, name FROM " . TABLE_LANGUAGES . " WHERE code = '" . DEFAULT_LANGUAGE . "'");
  if ($ep_4_num_rows($epdlanguage_query) == 0) {
    exit("EP4 FATAL ERROR: No default language set."); // this should never happen
  }
  $epdlanguage = $ep_4_fetch_array($epdlanguage_query);
  $epdlanguage_id = $epdlanguage['languages_id'];
  $epdlanguage_name = $epdlanguage['name'];
  $epdlanguage_code = $epdlanguage['code'];
  $langcode = ep_4_get_languages(); // array of currently used language codes ( 1, 2, 3, ...)

  require 'easypopulate_4_import.php';
 // zen_update_currencies(IS_CLI == 'VERBOSE');
  if (IS_CLI == 'VERBOSE') {
    echo 'Done.' . "\n\n";
  }
  exit(0); // returns 0 status code, which means successful

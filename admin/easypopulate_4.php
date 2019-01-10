<?php
// $Id: easypopulate_4.php, v4.0.37 02-08-2017 mc12345678 $

// START INITIALIZATION
require_once ('includes/application_top.php');
// CSV VARIABLES - need to make this configurable in the ADMIN
// $csv_delimiter = "\t"; // "\t" = tab AND "," = COMMA
$csv_delimiter = ","; // "\t" = tab AND "," = COMMA
if (defined('EASYPOPULATE_4_CONFIG_CSV_DELIMITER')) {
  $csv_delimiter = EASYPOPULATE_4_CONFIG_CSV_DELIMITER;
  $csv_list = getDBDelimiterList();
  $csv_delimiter = $csv_list[(int)$csv_delimiter];
}
$csv_enclosure = '"'; // chadd - i think you should always use the '"' for best compatibility
//$category_delimiter = "^"; //Need to move this to the admin panel
$category_delimiter = "\x5e"; //Need to move this to the admin panel
// See https://en.wikipedia.org/wiki/UTF-8 for UTF-8 character encodings


$excel_safe_output = true; // this forces enclosure in quotes

if (!defined('EP4_DB_FILTER_KEY')) {
  // Need to define this to support use of primary key for import/export
  //   Instead of adding an additional switch, have incorporated the conversion
  //   of a blank product_id field to a new product in here.  Currently
  //   expecting three choices: products_model, products_id, and blank_new
  //   with model as default
  define(EP4_DB_FILTER_KEY, 'products_model'); // This could/should apply to both
  //  import and export files, so here is a good location for it.
}
if (!defined('EP4_ADMIN_TEMP_DIRECTORY')) {
  // Intention is to identify which file path to reference throughout instead of
  //  storing the path in the database. If the individual wishes to use the
  //  admin path, then this switch will direct the files to use the admin path
  //  instead of storing the path in the database.
  define('EP4_ADMIN_TEMP_DIRECTORY', 'true'); // Valid Values considered (false, true)
}
if (!defined('EP4_SHOW_ALL_FILETYPES')) {
  // Intention is to force display of all file types and files for someone
  //  that hasn't done an update on the database as part of installing this
  //  software.  Perhaps could/need to create a default(s) file to
  //  assist with installation/operation.  mc12345678 12/30/15
  define('EP4_SHOW_ALL_FILETYPES', 'true');
}
/* Configuration Variables from Admin Interface  */
$tempdir = EASYPOPULATE_4_CONFIG_TEMP_DIR; // This ideally should not actually include the Admin Directory in the variable.
$ep_date_format = EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT;
$ep_raw_time = EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME;
$ep_debug_logging = ((EASYPOPULATE_4_CONFIG_DEBUG_LOGGING == 'true') ? true : false);
$ep_split_records = (int) EASYPOPULATE_4_CONFIG_SPLIT_RECORDS;
$price_with_tax = ((EASYPOPULATE_4_CONFIG_PRICE_INC_TAX == 'true') ? 1 : 0);
$strip_smart_tags = ((EASYPOPULATE_4_CONFIG_SMART_TAGS == 'true') ? true : false);
$max_qty_discounts = EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS;
$ep_feedback = ((EASYPOPULATE_4_CONFIG_VERBOSE == 'true') ? true : false);
$ep_execution = (int) EASYPOPULATE_4_CONFIG_EXECUTION_TIME;
$ep_curly_quotes = (int) EASYPOPULATE_4_CONFIG_CURLY_QUOTES;
$ep_char_92 = (int) EASYPOPULATE_4_CONFIG_CHAR_92;
$ep_metatags = (int) EASYPOPULATE_4_CONFIG_META_DATA; // 0-Disable, 1-Enable
$ep_music = (int) EASYPOPULATE_4_CONFIG_MUSIC_DATA; // 0-Disable, 1-Enable
$ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3' ? true : false);

@set_time_limit($ep_execution);  // executin limit in seconds. 300 = 5 minutes before timeout, 0 means no timelimit

if ((isset($error) && !$error) || !isset($error)) {
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

/* Test area start */
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);//test purposes only
// register_globals_vars_check();
// $maxrecs = 4;
// usefull stuff: mysql_affected_rows(), mysql_num_rows().
$ep_debug_logging_all = false; // do not comment out.. make false instead
//$sql_fail_test == true; // used to cause an sql error on new product upload - tests error handling & logs
/* Test area end */

/* populate the variable $curver_detail using independent file to identify the version of this plugin. */
require DIR_WS_MODULES . 'easypopulate_4_version.php'; //$curver_detail = '4.0.37.6';
$message = '';
if (IS_ADMIN_FLAG) {
  $new_version_details = plugin_version_check_for_updates(2069, $curver_detail);
  if ($new_version_details !== FALSE) {
    $message = '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>' . '</span>';
  }
}

// Current EP Version - Modded by mc12345678 after Chadd had done so much
$curver              = $curver_detail . ' - 02-08-2017' . $message;
$display_output = ''; // results of import displayed after script run
$ep_dltype = NULL;
$ep_stack_sql_error = false; // function returns true on any 1 error, and notifies user of an error
$specials_print = EASYPOPULATE_4_SPECIALS_HEADING;
$has_specials = false;
$zco_notifier->notify('EP4_START');

// Load language file(s) for main screen menu(s).
if(file_exists(DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/easypopulate_4_menus.php'))
{
  require(DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/easypopulate_4_menus.php');
} else {
  require(DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english' . '/easypopulate_4_menus.php');
}

// all mods go in this array as 'name' => 'true' if exist. eg $ep_supported_mods['psd'] => true means it exists.
$ep_supported_mods = array();

// default smart-tags setting when enabled. This can be added to.
$smart_tags = array("\r\n|\r|\n" => '<br />',); // need to check into this more

if (substr($tempdir, -1) != '/') {
  $tempdir .= '/';
}
if (substr($tempdir, 0, 1) == '/') {
  $tempdir = substr($tempdir, 1);
}

//$ep_debug_log_path = DIR_FS_CATALOG . $tempdir;
$ep_debug_log_path = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir;

// Check the current path of the above directory, if the selection is the
//  store directory, but the path leads into the admin directory, then
//  reset the selection to be the admin directory and modify the path so
//  that the admin directory is no longer typed into the path.  This same
//  action occurs in the configuration window now, but this is in case
//  operation of the program has allowed some other modification to occur
//  and the database for EP4 has the admin path in it.
if (EP4_ADMIN_TEMP_DIRECTORY !== 'true') {
  if (strpos($ep_debug_log_path, DIR_FS_ADMIN) !== false) {
    $temp_rem = substr($ep_debug_log_path, strlen(DIR_FS_ADMIN));
    $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'true\' where configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', false, false, 0, true);

    $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'' . $temp_rem . '\' WHERE configuration_key = \'EASYPOPULATE_4_CONFIG_TEMP_DIR\'', false, false, 0, true);

    // @TODO need a message to  be displayed...

    // Reload the page with the path now reset. No parameters are passed.
    zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  }
}

if ($ep_debug_logging_all == true) {
  $fp = fopen($ep_debug_log_path . 'ep_debug_log.txt', 'w'); // new blank log file on each page impression for full testing log (too big otherwise!!)
  fclose($fp);
}

// Pre-flight checks start here
$chmod_check = ep_4_chmod_check($tempdir);
//if ($chmod_check == false) { // test for temporary folder and that it is writable
  // $messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_FAIL, 'caution');
//}

// /temp is the default folder - check if it exists & has writeable permissions
if (EASYPOPULATE_4_CONFIG_TEMP_DIR === 'EASYPOPULATE_4_CONFIG_TEMP_DIR' && (is_null($_GET['epinstaller']) && !isset($_GET['epinstaller']) && $_GET['epinstaller'] != 'install')) { // admin area config not installed
  $messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_INSTALL_KEYS_FAIL, '<a href="' . zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=install') . '">', '</a>'), 'warning');
}

// installation start
if (isset($_GET['epinstaller']) && ($_GET['epinstaller'] == 'install' || $_GET['epinstaller'] == 'update')) {
  install_easypopulate_4(); // install new configuration keys
  //$messageStack->add(EASYPOPULATE_4_MSGSTACK_INSTALL_CHMOD_SUCCESS, 'success');
  zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
}

if (isset($_GET['epinstaller']) && $_GET['epinstaller'] == 'remove') { // remove easy populate configuration variables
  remove_easypopulate_4();
  zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
} // end installation/removal

$ep_4_SBAEnabled = ep_4_SBA1Exists();

$ep4CEONURIDoesExist = false;
if (ep_4_CEONURIExists() == true) {
  $ep4CEONURIDoesExist = true;
  if (empty($languages) || !is_array($languages)) {
    $languages = zen_get_languages();
  }
}

// START: check for existance of various mods
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
if (strlen(EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS) > 0) {
  $custom_field_names = explode(',', EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS);
  foreach ($custom_field_names as $field) {
    if (ep_4_check_table_column(TABLE_PRODUCTS, trim($field))) {
      $custom_field_check[] = TRUE;
      $custom_fields[] = trim($field);
    } else {
      $custom_further = explode(':', $field);
//      $custom_further[0] // expected to be table;
//      $custom_further[1] // expected to be field;
      if (count($custom_further) > 2) {
        //error message about field entry
      } else {
        // Test if table exists
        $temp_table = /*constant(*/'TABLE_' . strtoupper(trim($custom_further[0]));//);
        if (defined(/*strtoupper(*/$temp_table))/*)*/ {
          // then if table exists, test if field exists in table.
          if(ep_4_check_table_column(constant($temp_table), trim($custom_further[1]))) {
// mc12345678 commented out for now until can fully address how to handle this.
//            $custom_field_check[] = TRUE;
//            $custom_fields[] = trim($custom_further[1]);
//            continue;
          }
        }
      }
      $custom_field_check[] = FALSE;
//      $custom_fields[] = NULL;
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
  $collation = mysqli_character_set_name($db->link); // should be either latin1 or utf8
} else {
  $collation = mysql_client_encoding(); // should be either latin1 or utf8
}
if ($collation == 'utf8') {
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
  $epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
  define('DEFAULT_LANGUAGE', $epdlanguage['code']);
}
$epdlanguage_query = ep_4_query("SELECT languages_id, code, name FROM " . TABLE_LANGUAGES . " WHERE code = '" . DEFAULT_LANGUAGE . "'");
if (($ep_uses_mysqli ? mysqli_num_rows($epdlanguage_query) : mysql_num_rows($epdlanguage_query))) {
  $epdlanguage = ($ep_uses_mysqli ? mysqli_fetch_array($epdlanguage_query) : mysql_fetch_array($epdlanguage_query));
  $epdlanguage_id = $epdlanguage['languages_id'];
  $epdlanguage_name = $epdlanguage['name'];
  $epdlanguage_code = $epdlanguage['code'];
} else {
  exit("EP4 FATAL ERROR: No default language set."); // this should never happen
}

$langcode = ep_4_get_languages(); // array of currently used language codes ( 1, 2, 3, ...)

/*
  if ( isset($_GET['export2']) ) { // working on attributes export
  include_once('easypopulate_4_export2.php'); // this file contains all data import code
  } */

function getDBDelimiterList() {
  global $db;
  
  $sql = "SELECT set_function FROM " . TABLE_CONFIGURATION . " where configuration_key = 'EASYPOPULATE_4_CONFIG_CSV_DELIMITER'";
  $set_function = $db->Execute($sql);

  if ($set_function->EOF) {
    return NULL;
  }

  $output_array = array();
  $output_array2 = array();

  $set_function_val = $set_function->fields['set_function'];

  $delimiters = array(','); // Default to using a comma.  Specifically necessary to support those that have this function but not the defining key.

  preg_match_all("/\(([^\)(]+)\)/", $set_function_val, $output_array); // Pull the inner part of the set_function to determine what delimiter(s) are available

  if (!empty($output_array) && !empty($output_array[0])) {
    $delimiters = array();
    foreach ($output_array[1] as $out_array) {
      $output_delimiters = preg_replace('/&quot;/','"', $out_array);
      preg_match_all('/\'([^\']+)\'/', $output_delimiters, $output_array2); // Pull just the array of values within the function

      if (!empty($output_array2) && !empty($output_array2[1])) {
        $delimiters[] = $output_array2[1][3];
      }
    }
    
  }

  return $delimiters;
}

function getFileDelimiter($file, $checkLines = 2) {
  //global $db;

  $tempdir = EASYPOPULATE_4_CONFIG_TEMP_DIR;
  if (substr($tempdir, -1) != '/') {
    $tempdir .= '/';
  }
  while (substr($tempdir, 0, 1) == '/') {
    $tempdir = substr($tempdir, 1);
  }

  $basepath = "";
  $realBase = realpath($basepath);
  $userpath = $basepath . $file;
  $realUserPath = realpath($userpath);
  if ($realUserPath === false || strpos($realUserPath, $realBase) !== 0) {
      return NULL; // return back to the function with a non-result?
  }

  $file = new SplFileObject($file);

  $delimiters = getDBDelimiterList();

  if (is_null($delimiters)) {
    return NULL;
  }
  // Test the file for $checkLines quantity  each of the delimiters

  $results = array();
  foreach ($delimiters as $key => $delimiter) {
    $i = 0;
    $file->rewind();

    while($file->valid() && $i < $checkLines) {
      $line[$delimiter][$i] = $file->fgetcsv($delimiter); // obtain fields based on attempted delimiter.
      if (count($line[$delimiter][$i]) > 1) { // There are at least two fields which would be needed to perform any work.
        if (!empty($results[$delimiter])) {
          if (!empty($results[$delimiter][$i])) {
            $results[$delimiter][$i]++;
          } else {
            $results[$delimiter][$i] = 1;
          }
        } else {
          $results[$delimiter] = array();
          $results[$delimiter][$i] = 1;
        }
      }
      $i++;
    }
  }

  $size_results = count($results); // Identifies the number of delimiters that were potentially obtained.
  
  if ($size_results == 1) {
    // found the one delimiter that works
    $results = array_keys($results, max($results));
    return $results[0]; // The one and only delimiter.
  } else if ($size_results > 1) {
    // Options available, need to let know.
    // Have opportunity to evaluate the results of the row(s).
    // Ideally can evaluate each line for number of fields.
    // The number of fields should align between each line, otherwise there could be a problem with the file.

    foreach ($delimiters as $delimiter) {
      if (empty($line[$delimiter])) {
        continue;
      }

      $i = 0;
      $base = count($line[$delimiter][$i]); // Establish the number of fields in the first line.
      $i++;
      // Check each line to see how the number of fields compares to the first.
      while ($i <= $checkLines) {
        if (!empty($line[$delimiter][$i]) && count($line[$delimiter][$i]) <> $base) { // If any line has a different number of fields from the first, then columns do not match and/or delimiter is not properly used.
          unset($results[$delimiter]); // Remove the data associated with a delimiter that doesn't match.
          break; // stop processing any remaining rows.
        }
        $i++;
      }
    }
    $returndelimiters = array();
    if (!empty($results)) {
      foreach ($delimiters as $delimiter) {
        if ((isset($results[$delimiter]) || array_key_exists($delimiter, $results)) && is_array($results[$delimiter]) && count($results[$delimiter]) == $checkLines) {
          // Delimiter is used in every line and resulted in a satisfactory split of the data.
          $returndelimiters[] = $delimiter;
        }
      }
    }

    return $returndelimiters; // Will return an array, but question is if it is empty or has 1 or more values.   
  } else {
    // None found and need user to do something else.
    return NULL;
  }
}

function ep_4_display_CSV_Delimiter($filename) {
  $file_delimiter = getFileDelimiter($filename);

  if (!isset($file_delimiter)) {
    // File does not have a matching delimiter to what is in the database, bad filename, or delimiter is not set in database, need user to take some sort of action.
    return NULL;
  } else if (is_array($file_delimiter)) {
    // Could have none (empty array), 1 delimiter, or multiple come back.
    // if empty array then the delimiter was inconsistently used in each row.
    // if 1 delimiter, then its the one to use. Return user friendly choice.
    // if 2 or more delimiters, need to offer choices/advise of potential issue.
    if (empty($file_delimiter)) {
      return NULL;
    } else if (count($file_delimiter) == 1) {
      if ($file_delimiter[0] == "\t") {
        $file_delimiter[0] = 'tab';
      } elseif ($file_delimiter[0] == " " || $file_delimiter[0] == "&nbsp;") {
        $file_delimiter[0] = 'space';
      }
      return $file_delimiter[0];
    } else {
      return array('delims'=>$file_delimiter);
    }
  } else {
    // Single delimiter used and was "immediately" found return user friendly choice.
    if ($file_delimiter == "\t") {
      $file_delimiter = 'tab';
    } elseif ($file_delimiter == " " || $file_delimiter == "&nbsp;") {
      $file_delimiter = 'space';
    }
    return $file_delimiter;
  }
}

if (isset($_POST['export']) || isset($_GET['export']) || isset($_POST['exportorder'])) {
  include_once('easypopulate_4_export.php'); // this file contains all data export code
}
if (isset($_POST['import'])) {
  include_once('easypopulate_4_import.php'); // this file contains all data import code
}
if (isset($_POST['split'])) {
  include_once('easypopulate_4_split.php'); // this file has split code
}

// if we had an SQL error anywhere, let's tell the user - maybe they can sort out why
if ($ep_stack_sql_error == true) {
  $messageStack->add(EASYPOPULATE_4_MSGSTACK_ERROR_SQL, 'caution');
}

$upload_dir = (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir;

//  UPLOAD FILE isset($_FILES['usrfl'])
if (isset($_FILES['uploadfile'])) {
  $file = ep_4_get_uploaded_file('uploadfile');
  if (!strlen($file['tmp_name']) || !$file['size']){
    $messageStack->add(EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_EMPTY, 'warning');
  } else {
    if (is_uploaded_file($file['tmp_name'])) {
      ep_4_copy_uploaded_file($file, (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir);
    }
    $messageStack->add(sprintf(EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_COMPLETE, $file['name'], /*"<td align=center>" .*/ (strtolower(end(explode('.', $file['name']))) == 'csv' ? zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), '', 'post', '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($file['name']), '') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT, '', false, 'submit') . EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_IMPORT . "</form>\n" /*</td>\n"*/ : EASYPOPULATE_4_DISPLAY_RESULT_UPLOAD_NO_CSV)), 'success');
  }
}

// Handle file deletion (delete only in the current directory for security reasons)
if (((isset($error) && !$error) || !isset($error)) && (isset($_POST["delete"])) && !is_null($_SERVER["SCRIPT_FILENAME"]) && $_POST["delete"] != basename($_SERVER["SCRIPT_FILENAME"])) {
  if (preg_match("/(\.(sql|gz|csv|txt|log))$/i", $_POST["delete"]) && @unlink($upload_dir . basename($_POST["delete"]))) {
    // $messageStack->add(sprintf($_POST["delete"]." was deleted successfully"), 'success');
    zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  } else {
    $messageStack->add(sprintf("Cannot delete file: " . $_POST["delete"]), 'caution');
    // zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));
  }
}
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <?php $zco_notifier->notify('EP4_EASYPOPULATE_4_LINK'); ?>
    <script language="javascript" type="text/javascript" src="includes/menu.js"></script>
    <script language="javascript" type="text/javascript" src="includes/general.js"></script>
    <!-- <script language="javascript" src="includes/ep4ajax.js"></script> -->
    <script type="text/javascript">
<!--
   function init()
   {
     cssjsmenu('navbar');
     if (document.getElementById)
     {
       var kill = document.getElementById('hoverJS');
       kill.disabled = true;
     }
   }
// -->
    </script>
    <style type="text/css">
      #epfiles tbody tr:hover { background:#CCCCCC; }
    </style>
  </head>
  <body onLoad="init()">
      <?php require(DIR_WS_INCLUDES . 'header.php');
      $update = false;
      $zco_notifier->notify('EP4_ZC155_AFTER_HEADER');
       ?>

    <!-- body -->
    <div style="padding:5px">
        <?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>
      <div class="pageHeading"><?php echo "Easy Populate $curver"; ?></div>


      <div style="text-align:right; float:right; width:25%"><?php
      if (!defined('TOOLS_EASYPOPULATE_4_VERSION')) { // database does not have key
        $group_check = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Easy Populate 4'");

        // But EP4 is already installed
        if (!$group_check->EOF && $group_check->RecordCount() > 0 && $group_check->fields['configuration_group_id'] > 0) {
          $update = true;
        }
        unset($group_check);
      } else {
        $check_database_installed = plugin_version_check_for_updates(2069, TOOLS_EASYPOPULATE_4_VERSION);
        // If EP4 is installed (because of above), database version installed is greater than or equal to the version available on ZC, current database version is not the same as the software version (allows an upgrade or a downgrade, but downgrade won't remove admin settings).
        if ($check_database_installed === FALSE && $curver_detail !== TOOLS_EASYPOPULATE_4_VERSION) {
          $update = true;
        }
        // If EP4 is installed (because of above), files installed are greater than or equal to the version available on ZC, installed version is behind the version available on ZC.
        if (!$update && plugin_version_check_for_updates(2069, $curver_detail) === FALSE && $check_database_installed !== FALSE) {
          $update = true;
        }
      }
      if ($update) { ?>
      <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=update') ?>"><?php echo EASYPOPULATE_4_UPDATE_SETTINGS; ?></a><br /><br />
<?php }
      unset($update);
      ?><a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'epinstaller=remove') ?>"><?php echo EASYPOPULATE_4_REMOVE_SETTINGS; ?></a>
           <?php
           echo '<br /><b><u>' . EASYPOPULATE_4_CONFIG_SETTINGS . '</u></b><br />';
           echo EASYPOPULATE_4_CONFIG_UPLOAD . '<b>' . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ 'catalog/' : /* Admin side */ 'admin/') . $tempdir . '</b><br />';
           echo 'Verbose Feedback: ' . (($ep_feedback) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_SPLIT_SHORT . $ep_split_records . '<br />';
           echo EASYPOPULATE_4_DISPLAY_EXEC_TIME . $ep_execution . '<br />';
           switch ($ep_curly_quotes) {
             case 0:
               $ep_curly_text = "No Change";
               break;
             case 1:
               $ep_curly_text = "Basic";
               break;
             case 2:
               $ep_curly_text = "HTML";
               break;
           }
           switch ($ep_char_92) {
             case 0:
               $ep_char92_text = "No Change";
               break;
             case 1:
               $ep_char92_text = "Basic";
               break;
             case 2:
               $ep_char92_text = "HTML";
               break;
           }
           echo 'Convert Curly Quotes: ' . $ep_curly_text . '<br />';
           echo 'Convert Char 0x92: ' . $ep_char92_text . '<br />';
           echo EASYPOPULATE_4_DISPLAY_ENABLE_META . $ep_metatags . '<br />';
           echo EASYPOPULATE_4_DISPLAY_ENABLE_MUSIC . $ep_music . '<br />';

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_CUSTOM_PRODUCT_FIELDS . '</u></b><br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SHORT_DESC . (($ep_supported_mods['psd']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UNIT_MEAS . (($ep_supported_mods['uom']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_UPC . (($ep_supported_mods['upc']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           // Google Product Category for Google Merchant Center
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GOOGLE_CAT . (($ep_supported_mods['gpc']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MSRP . (($ep_supported_mods['msrp']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_MAP . (($ep_supported_mods['map']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_GP . (($ep_supported_mods['gppi']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_EXCLUSIVE . (($ep_supported_mods['excl']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_SBA . (($ep_4_SBAEnabled != false) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_CEON . (($ep4CEONURIDoesExist == true) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
           echo EASYPOPULATE_4_DISPLAY_STATUS_PRODUCT_DPM . (($ep_supported_mods['dual']) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';

           $zco_notifier->notify('EP4_DISPLAY_STATUS');

           echo "<br /><b><u>" . EASYPOPULATE_4_DISPLAY_USER_DEF_FIELDS . "</u></b><br />";
           $i = 0;
           foreach ($custom_field_names as $field) {
             echo $field . ': ' . (($custom_field_check[$i]) ? '<font color="green">TRUE</font>' : "FALSE") . '<br />';
             $i++;
           }
           unset($field);

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_INSTALLED_LANG . '</u></b><br />';
           foreach ($langcode as $key => $lang) {
             echo $lang['id'] . '-' . $lang['code'] . ': ' . $lang['name'] . '<br />';
           }
           unset($key);
           unset($lang);
           echo EASYPOPULATE_4_DISPLAY_INSTALLED_LANG_DEF . $epdlanguage_id . '-' . $epdlanguage_name . '<br />';
           echo EASYPOPULATE_4_DISPLAY_INT_CHAR_ENC . (function_exists('mb_internal_encoding') ? mb_internal_encoding() : 'mb_internal_encoding not available') . '<br />';
           echo EASYPOPULATE_4_DISPLAY_DB_COLL . $collation . '<br />';

           echo '<br /><b><u>' . EASYPOPULATE_4_DISPLAY_DB_FLD_LGTH . '</u></b><br />';
           foreach ($max_len as $key => $value) {
             echo $key . ':' . $value . '<br />';
           }
           unset($key);
           unset($value);
/*           echo 'categories_name:' . $max_len['categories_name'] . '<br />';
           echo 'manufacturers_name:' . $max_len['manufacturers_name'] . '<br />';
           echo 'products_model:' . $max_len['products_model'] . '<br />';
           echo 'products_name:' . $max_len['products_name'] . '<br />';*/

           $zco_notifier->notify('EP4_MAX_LEN');
           /*  // some error checking
             echo '<br /><br />Problem Data: '. mysql_num_rows($ajeh_result);
             echo '<br />Memory Usage: '.memory_get_usage();
             echo '<br />Memory Peak: '.memory_get_peak_usage();
             echo '<br /><br />';
             print_r($langcode);
             echo '<br /><br />code: '.$langcode[1]['id'];
            */
           //register_globals_vars_check_4(); // testing
           ?></div>

      <?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?>

      <div style="text-align:left">

        <?php echo zen_draw_form('ep4 upload', FILENAME_EASYPOPULATE_4, '', 'post', 'enctype="multipart/form-data"'); ?>
          <div align = "left"><br />
            <b><?php echo EASYPOPULATE_4_DISPLAY_TITLE_UPLOAD; ?></b><br />
            <?php echo sprintf(EASYPOPULATE_4_DISPLAY_MAX_UP_SIZE, $upload_max_filesize, round($upload_max_filesize / 1024 / 1024)) . '<br />'; ?>
            <?php echo zen_draw_hidden_field('MAX_FILE_SIZE', $upload_max_filesize, $parameters = ''); ?>
            <?php echo zen_draw_file_field('uploadfile', false); ?>
            <?php echo zen_draw_input_field("buttoninsert", EASYPOPULATE_4_DISPLAY_UPLOAD_BUTTON_TEXT, '', false, 'submit', true); ?>
            <br /><br /><br />
          </div>
        </form>

        <?php
// echo zen_draw_form('custom', 'easypopulate_4.php', 'id="custom"', 'get');
        echo zen_draw_form('custom', FILENAME_EASYPOPULATE_4, '', 'post', 'id="custom"');
        ?>

        <div align = "left">
             <?php
             $export_type_array = array();
             $export_type_array = array(array("id" => '0', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_DEFAULT),
               array("id" => '0', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_COMPLETE),
               array("id" => '1', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_QUANTITY),
               array("id" => '2', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_BREAKS),
               array("id" => '3', 'text' => EASYPOPULATE_4_DD_DOWNLOAD_COMPLETE_SINGLE));

             $category_filter_array = array();
             $category_filter_array = array_merge(array(0 => array("id" => '', 'text' => EASYPOPULATE_4_DD_FILTER_CATEGORIES)), zen_get_category_tree());

             $manufacturers_array = array();
             $manufacturers_array[] = array("id" => '', 'text' => EASYPOPULATE_4_DISPLAY_MANUFACTURERS);
             if ($ep_uses_mysqli) {
               $manufacturers_query = mysqli_query($db->link, "SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
               while ($manufacturers = mysqli_fetch_array($manufacturers_query)) {
                 $manufacturers_array[] = array("id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);
               }

             } else {
               $manufacturers_query = mysql_query("SELECT manufacturers_id, manufacturers_name FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name");
               while ($manufacturers = mysql_fetch_array($manufacturers_query)) {
                 $manufacturers_array[] = array("id" => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']);
               }
             }
             $status_array = array(array("id" => '1', 'text' => EASYPOPULATE_4_DD_STATUS_DEFAULT ), array("id" => '1', 'text' => EASYPOPULATE_4_DD_STATUS_ACTIVE), array("id" => '0', 'text' => EASYPOPULATE_4_DD_STATUS_INACTIVE), array("id" => '3', 'text' => EASYPOPULATE_4_DD_STATUS_ALL));

             echo "<b>" . EASYPOPULATE_4_DISPLAY_FILTERABLE_EXPORTS . "</b><br />";

             echo zen_draw_pull_down_menu('ep_export_type', $export_type_array) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_category_filter', $category_filter_array) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_manufacturer_filter', $manufacturers_array) . ' ';
             echo ' ' . zen_draw_pull_down_menu('ep_status_filter', $status_array) . ' ';
             echo zen_draw_input_field('export', EASYPOPULATE_4_DD_FILTER_EXPORT, ' style="padding: 0px"', false, 'submit');
             ?>
          <br /><br />
        </div></form>
  <?php
  echo zen_draw_form('custom2', FILENAME_EASYPOPULATE_4, '', 'post', 'id="custom2"');
  ?>

    <div align = "left">
    <?php
    $order_export_type_array  = array(array( "id" => '0', 'text' => EASYPOPULATE_4_ORDERS_DROPDOWN_FIRST ),
      array( "id" => '1', 'text' => EASYPOPULATE_4_ORDERS_FULL ),
      array( "id" => '2', 'text' => EASYPOPULATE_4_ORDERS_NEWFULL ),
      array( "id" => '3', 'text' => EASYPOPULATE_4_ORDERS_NO_ATTRIBS ),
      array( "id" => '4', 'text' => EASYPOPULATE_4_ORDERS_ATTRIBS ));
    $order_status_export_array = array ();
    echo EASYPOPULATE_4_ORDERS_DROPDOWN_TITLE;

    echo zen_draw_pull_down_menu('ep_order_export_type', $order_export_type_array) . ' ';
    echo zen_cfg_pull_down_order_statuses(NULL, 'order_status');
    echo zen_draw_input_field('exportorder', EASYPOPULATE_4_ORDERS_DROPDOWN_EXPORT, ' style="padding: 0px"', false, 'submit');
    ?>
    <br /><br />
    </div></form>


        <b><?php echo EASYPOPULATE_4_DISPLAY_PRODUCTS_PRICE_EXPORT_OPTION; ?></b><br />
        <!-- Download file links -->
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=full', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_COMPLETE_PRODUCTS; ?></a><br/>
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=fullsingle', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_COMPLETE_PRODUCTS_SINGLE; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=priceqty', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_PRICE_QTY; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=pricebreaks', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_PRICE_BREAKS; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=featured', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_FEATURED; ?></a><br />

        <br /><b><?php echo EASYPOPULATE_4_DISPLAY_TITLE_CATEGORY; ?></b><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=category', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_CATEGORY; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=categorymeta', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_CATEGORYMETA; ?></a><br/>

        <br /><?php echo EASYPOPULATE_4_DISPLAY_TITLE_ATTRIBUTE; ?><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=attrib_basic', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_ATTRIBUTE_BASIC; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=attrib_detailed', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_ATTRIBUTE_DETAILED; ?></a><br />
        <?php
        /* Begin SBA1 addition */
        if ($ep_4_SBAEnabled != false) {
          ?>
          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBA_detailed', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_DETAILED_SBA; ?></a><br />
          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBAStock', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_SBA_STOCK; ?></a><br />

          <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=SBAStockProdFilter, $request_type'); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_SBA_STOCK_ASC; ?></a><br />

        <?php } /* End SBA1 Addition */
    $zco_notifier->notify('EP4_LINK_SELECTION_END');
    ?>
        <br><?php echo EASYPOPULATE_4_DISPLAY_TITLE_EXPORT_ONLY; ?><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=options', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_NAMES; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=values', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_VALUES; ?></a><br />
        <a href="<?php echo zen_href_link(FILENAME_EASYPOPULATE_4, 'export=optionvalues', $request_type); ?>"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_OPTION_NAMES_TO_VALUES; ?></a><br />
        <?php
// List uploaded files in multifile mode
// Table header
        echo '<br /><br />';
//  echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
// $upload_dir = DIR_FS_CATALOG.$tempdir; // defined above
        if ($dirhandle = opendir($upload_dir)) {
          $files = array();
          while (false !== ($files[] = readdir($dirhandle))); // get directory contents
          closedir($dirhandle);
          $file_count = 0;
          sort($files);
          /*
           * Have a list of files... Now need to work through them to identify what they can do.
           * After identified what they can do, need to generate the screen view of their option(s).  Ideally, every file will have the same capability, but will be grouped with a title and associated action.
           * So need to identify the "#" of groups, and loop through the files for those groups.  Probably a case statement
           */

          $filenames = array(
            "attrib-basic-ep" => ATTRIB_BASIC_EP,
            "attrib-detailed-ep" => ATTRIB_DETAILED_EP_DESC,
            "category-ep" => CATEGORY_EP_DESC,
            "categorymeta-ep" => CATEGORYMETA_EP_DESC,
            "featured-ep" => FEATURED_EP_DESC,
            "pricebreaks-ep" => PRICEBREAKS_EP_DESC,
            "priceqty-ep" => PRICEQTY_EP_DESC,
            "sba-detailed-ep" => SBA_DETAILED_EP_DESC,
            "sba-stock-ep" => SBA_STOCK_EP_DESC,
            "orders-full-ep"=>ORDERSEXPORT_LINK_SAVE1,
            "orders-fullb-ep"=>ORDERSEXPORT_LINK_SAVE1B,
            "orders-noattribs-ep"=>ORDERSEXPORT_LINK_SAVE2,
            "orders-onlyAttribs-ep"=>ORDERSEXPORT_LINK_SAVE3
          );
          $zco_notifier->notify('EP4_FILENAMES');

          $filetypes = array();

          for ($i = 0; $i < count($files); $i++) {
            if (($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i", $files[$i])) {
              $found = false;

              foreach ($filenames as $key => $val) {
                if (strtolower(substr($files[$i], 0, strlen($key))) == strtolower($key)) {
                  $filetypes[$key][] = $i;
                  $found = true;
                  break;
                }
              }
              unset($key);
              unset($val);

              if ($found == false) {
                // Treat as an everything else file.
                $filetypes['zzzzzzzz'][] = $i;
              }
            } // End of if file is one to manage here.
          } // End For $i of $files
          ksort($filetypes);

          $filenames_merged = array();
          $filenames_merged = array_merge($filenames, array("zzzzzzzz" => CATCHALL_EP_DESC));

          echo "\n";
          echo "\n";
          echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
          if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
            echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
          }

          foreach ((EP4_SHOW_ALL_FILETYPES != 'false' ? $filenames_merged : $filetypes) as $key => $val) {
            (EP4_SHOW_ALL_FILETYPES != 'false' ? $val = ((isset($filetypes[$key]) || array_key_exists($key, $filetypes)) ? $filetypes[$key] : NULL) : '');
            if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
              $val = array();
              for ($i = 0; $i < count($files); $i++) {
                $val[$i] = $i;
              }
            }

            $file_count = 0;
            //Display the information needed to start use of a filetype.
            $plural_state = "<strong>" . (count($val) > 1 ? EP_DESC_PLURAL : EP_DESC_SING) . "</strong>";
            if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
              echo "<tr><td colspan=\"8\">" . sprintf($filenames_merged[$key], "<strong>" . $key . "</strong>", $plural_state) . "</td></tr>";
              echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
            }

            for ($i = 0; $i < count($val); $i++) {
              $file_delimiter_error = false;
              if (EP4_SHOW_ALL_FILETYPES != 'Hidden' || (EP4_SHOW_ALL_FILETYPES == 'Hidden' && ($files[$i] != ".") && ($files[$i] != "..") && preg_match("/\.(sql|gz|csv|txt|log)$/i", $files[$i]) )) {
                $file_count++;
                $file_delimiter = ep_4_display_CSV_Delimiter($upload_dir . $files[$val[$i]]);

                $file_text = '';
                if (is_array($file_delimiter)) {
                  $file_text = '"';
                  foreach($file_delimiter as $file_delim) {
                    switch ($file_delim) {
                      case "\t":
                        $file_text .= 'tab';
                        break;
                      case " ":
                        $file_text .= 'space';
                        break;
                      default:
                        $file_text .= $file_delim;
                        break;
                    }
                    $file_text .= '", "';
                  }
                  $file_text = substr($file_text, 0, -3);
                }

                if (isset($file_delimiter) && is_array($file_delimiter) || !isset($file_delimiter)) {
                  $file_delimiter = $file_text . EASYPOPULATE_4_DELIMITER_UNKNOWN;
                  $file_delimiter_error = true;
                }

                echo '<tr><td>' . $files[$val[$i]] . '</td>
          <td align="right">' . filesize($upload_dir . $files[$val[$i]]) . '</td>
          <td align="center">' . date("Y-m-d H:i:s", filemtime($upload_dir . $files[$val[$i]])) . '</td>';
                $elem_list = explode('.', $files[$val[$i]]);
                $end_elem = end($elem_list);
                $ext = strtolower($end_elem);
                // file type
                switch ($ext) {
                  case 'sql':
                    echo '<td align=center>SQL</td>';
                    break;
                  case 'gz':
                    echo '<td align=center>GZip</td>';
                    break;
                  case 'csv':
                    echo '<td align=center>CSV ' . $file_delimiter . ' </td>';
                    break;
                  case 'txt':
                    echo '<td align=center>TXT</td>';
                    break;
                  case 'log':
                    echo '<td align=center>LOG</td>';
                    break;
                  default:
                }
                // file management
                if ($ext == 'csv') {
                  // $_SERVER["PHP_SELF"] vs $_SERVER['SCRIPT_NAME']
//                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?split=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT . "</a></td>\n";
                  echo "<td align=center>" . zen_draw_form('split_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('split', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('split_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; //. "<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?split=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_SPLIT . "</a></td>\n";
                  if (strtolower(substr($files[$val[$i]], 0, 12)) == "sba-stock-ep") {
//                    echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a><br /><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "&amp;sync=1\"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC; </a></td>\n";
                    echo "<td align=center>" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form><br />" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_hidden_field('sync', '1', /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; //"<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a><br /><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "&amp;sync=1\"><?php echo EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT_SYNC; //</a></td>\n";
                  } else if(!$file_delimiter_error) {
//                    echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a></td>\n";
                    echo "<td align=center>" . zen_draw_form('import_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('import', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('import_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>\n"; // <a href=\"" . $_SERVER['SCRIPT_NAME'] . "?import=" . $files[$val[$i]] . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_IMPORT . "</a></td>\n";
                  } else {
                    echo "<td align=center>Import error</td>\n";
                  }
//        echo zen_draw_form('custom', 'easypopulate_4.php', '', 'post', 'id="custom"');

                  echo "<td align=center>" . zen_draw_form('delete_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('delete', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('delete_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>";
/*                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?delete=" . urlencode($files[$val[$i]]) . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE . "</a></td>";*/
                  echo "<td align=center><a href=\"" . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* BOF Storeside */ defined('ENABLE_SSL_CATALOG') && ENABLE_SSL_CATALOG === 'true' ? HTTPS_CATALOG_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_CATALOG_SERVER . DIR_WS_CATALOG /* EOF Storeside */ : /* BOF Adminside */ (defined('ENABLE_SSL_ADMIN') && ENABLE_SSL_ADMIN === 'true' ? (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . (defined('DIR_WS_HTTPS_ADMIN') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) : HTTP_SERVER . DIR_WS_ADMIN) /* EOF Adminside */) . $tempdir . $files[$val[$i]] . "\" target=_blank>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DOWNLOAD . "</a></td></tr>\n";
                } else {
                  echo "<td>&nbsp;</td>\n";
                  echo "<td>&nbsp;</td>\n";
//                  echo "<td align=center><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?delete=" . urlencode($files[$val[$i]]) . "\">" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE . "</a></td>";
                  echo "<td align=center>" . zen_draw_form('delete_form', basename($_SERVER['SCRIPT_NAME']), /*$parameters = */'', 'post', /*$params =*/ '', $request_type == 'SSL') . zen_draw_hidden_field('delete', urlencode($files[$val[$i]]), /*$parameters = */'') . zen_draw_input_field('delete_button', EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DELETE, /*$parameters = */'', /*$required = */false, /*$type = */'submit') . "</form></td>";
                  echo "<td align=center><a href=\"" . (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* BOF Storeside */ defined('ENABLE_SSL_CATALOG') && ENABLE_SSL_CATALOG === 'true' ? HTTPS_CATALOG_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_CATALOG_SERVER . DIR_WS_CATALOG /* EOF Storeside */ : /* BOF Adminside */ (defined('ENABLE_SSL_ADMIN') && ENABLE_SSL_ADMIN === 'true' ? (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . (defined('DIR_WS_HTTPS_ADMIN') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) : HTTP_SERVER . DIR_WS_ADMIN) /* EOF Adminside */) . $tempdir . $files[$val[$i]] . "\" target=_blank>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_DOWNLOAD . "</a></td></tr>\n";
                }
              }
            } // End loop within a filetype
            if ($file_count == 0 && EP4_SHOW_ALL_FILETYPES != 'Hidden') {
              echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
            } // if (count($files)>0)
            if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
              break;
            }
          } // End foreach filetype
          unset($key);
          unset($val);
          if (EP4_SHOW_ALL_FILETYPES != 'Hidden') {
            echo "</table>\n";
            if (count($filetypes) == 0 && EP4_SHOW_ALL_FILETYPES == 'false') {
              echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
              echo "<tr><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_FILENAME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SIZE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DATE_TIME . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_TYPE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_SPLIT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_IMPORT . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DELETE . "</th><th>" . EASYPOPULATE_4_DISPLAY_EXPORT_TABLE_TITLE_DOWNLOAD . "</th>\n";
              echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
              echo "</table>\n";
            }
          }
        } else { // can't open directory
          echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_ERROR_FOLDER_OPEN . "</font> " . $tempdir . "</td></tr>\n";
          $error = true;
        } // opendir()
        if (EP4_SHOW_ALL_FILETYPES == 'Hidden') {
          echo "</table>\n";
          if (count($filetypes) == 0) {
            echo "<table id=\"epfiles\"    width=\"80%\" border=1 cellspacing=\"2\" cellpadding=\"2\">\n";
            echo "<tr><td COLSPAN=8><font color='red'>" . EASYPOPULATE_4_DISPLAY_EXPORT_FILE_NONE_SUPPORTED . "</font></td></tr>\n";
            echo "</table>\n";
          }
        }




        /*
          echo "<div>";
          $test_string = "Πλαστικά^Εξαρτήματα";
          $test_array1 = explode("^", $test_string);
          echo "<br />Using explode() with: ".$test_string."<br />";
          print_r($test_array1);

          $test_array2 = mb_split('\x5e', $test_string);
          echo "<br /><br />Using mb_split() with: ".$test_string."<br />";
          print_r($test_array2);
          echo "</div>";
         */
        ?>
      </div>
      <div id='results'></div>
      <?php
      echo $display_output; // upload results
      if (strlen($specials_print) > strlen(EASYPOPULATE_4_SPECIALS_HEADING)) {
        echo '<br />' . $specials_print . EASYPOPULATE_4_SPECIALS_FOOTER; // specials summary
      }
      ?>
    </div>
    <br />
    <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
  </body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>

<?php
// $Id: easypopulate_4_functions.php, v4.0.38.ZC 05-15-2021 mc12345678 $

  if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
  }

function ep_4_curly_quotes($curly_text) {
  // Address PHP 8.x deprecation of str_replace on a null value.
  if (is_null($curly_text)) {
    return null;
  }
  $ep_curly_quotes = (int)EASYPOPULATE_4_CONFIG_CURLY_QUOTES;
  $ep_char_92 = (int)EASYPOPULATE_4_CONFIG_CHAR_92;
  if ($ep_curly_quotes == 1) { // standard characters
    $clean_text = str_replace(array("\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"),
            array("'", "'", '"', '"', '-', '--', '...'), $curly_text);
  } elseif ($ep_curly_quotes == 2) { // html
    $clean_text = str_replace(array("\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"),
      array("&lsquo;", "&rsquo;", '&ldquo;', '&rdquo;', '&ndash;', '&mdash;', '&hellip;'), $curly_text);
  } else { // do nothing
    $clean_text = $curly_text;
  }
  // deal with 0x92 ... a funky right-single-quote
  if ($ep_char_92 == 1) { // standard single quote
    $clean_text = str_replace("\x92", "'", $clean_text);
  } elseif ($ep_char_92 == 2) { // html right-single quote
    $clean_text = str_replace("\x92", "&rsquo;", $clean_text);
    //echo '<br>option 2 - html <br>';
  }

  unset($ep_curly_quotes);
  unset($ep_char_92);

  return $clean_text;
}

// function to return field length
// uses $tbl = table name, $fld = field name
function ep4_zen_field_length($tbl, $fld) {
  global $db;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');

  $meta = array();
  if ($ep_uses_mysqli) {
    global $ep4;
  }
  $result = ($ep_uses_mysqli ? mysqli_query($ep4['link'], "SELECT $fld FROM $tbl") : mysql_query("SELECT $fld FROM $tbl"));
  if (!$result) {
      echo 'Could not run query: ' . ($ep_uses_mysqli ? mysqli_error($ep4['link']) : mysql_error());
      unset($project);
      unset($ep_uses_mysqli);
      unset($meta);
      unset($result);

      exit;
  }
  $length = ($ep_uses_mysqli ? mysqli_fetch_field_direct($result, 0)->length : mysql_field_len($result, 0));
  unset($project);
  unset($ep_uses_mysqli);
  unset($meta);
  unset($result);

  return $length;
}

function ep_4_get_languages() {
    $ep_languages_array = array();
    $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  $langcode = array();
  $languages_query = ep_4_query("SELECT languages_id, name, code FROM ".TABLE_LANGUAGES." ORDER BY sort_order");
  $i = 1;

  while ($ep_languages = ($ep_uses_mysqli ? mysqli_fetch_array($languages_query): mysql_fetch_array($languages_query))) {
    $ep_languages_array[$i++] = array(
      'id' => $ep_languages['languages_id'],
      'name' => $ep_languages['name'],
      'code' => $ep_languages['code']
      );
  }
  unset($project);
  unset($ep_uses_mysqli);
  unset($langcode);
  unset($languages_query);
  unset($i);
  unset($ep_languages);

  return $ep_languages_array;
}

function ep_4_SBA1Exists () {
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  // The current thought is to have one of these Exists files for each version of SBA to consider; however, they also all could fall under one SBA_Exists check provided some return is made and a comparison done on the other end about what was returned.
  //Check to see if any version of Stock with attributes is installed (If so, and properly programmed, there should be a define for the table associated with the stock.  There may be more than one, and if so, they should all be verified for the particular SBA.
  if (!defined('TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK')) {
    unset($project);
    unset($ep_uses_mysqli);

    return false;
  }

  $tablePresent = ep_4_query('SELECT *
        FROM information_schema.tables
        WHERE table_schema = \'' . DB_DATABASE . '\'
        AND table_name = \'' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK . '\'
        LIMIT 1;');
  // Check if database table is present in the database before attempting to access it.  If not present, then no need to
  //  continue processing.

  if (($ep_uses_mysqli ? ($tablePresent === false ? 0 : mysqli_num_rows($tablePresent)) : mysql_num_rows($tablePresent)) == 0)/*$tablePresent == false || (is_object($tablePresent) && $tablePresent->num_rows == 0))*/ {
    unset($project);
    unset($ep_uses_mysqli);
    unset($tablePresent);
    return false;
  }
  //Now that have identified that the table (applicable to mc12345678's store, has been identified as in existence, now need to look at the setup of the table (Number of columns and if each column identified below is in the table, or conversely if the table's column matches the list below.
  //Columns in table: stock_id, products_id, stock_attributes, quantity, and sort.
  $colsarray = ep_4_query('SHOW COLUMNS FROM ' . TABLE_PRODUCTS_WITH_ATTRIBUTES_STOCK);
//  echo 'After execute<br />';
  $numCols = ($ep_uses_mysqli ? ($colsarray === false ? 0 : mysqli_num_rows($colsarray)) : mysql_num_rows($colsarray));

  if ($numCols < 5) {
    unset($project);
    unset($ep_uses_mysqli);
    unset($tablePresent);
    unset($colsarray);
    unset($numCols);
    unset($row);
    return false;
  }

  if ($numCols == 5) {
    while ($row = ($ep_uses_mysqli ? mysqli_fetch_array($colsarray) : mysql_fetch_array($colsarray))){
      switch ($row['Field']) {
        case 'stock_id':
        case 'products_id':
        case 'stock_attributes':
        case 'quantity':
        case 'sort':
          break;
        default:
          unset($project);
          unset($ep_uses_mysqli);
          unset($tablePresent);
          unset($colsarray);
          unset($numCols);
          unset($row);
          return false;
          break;

      }
    }
    unset($project);
    unset($ep_uses_mysqli);
    unset($tablePresent);
    unset($colsarray);
    unset($numCols);
    unset($row);
    return '1';
  }

  $desired = 0;
//  $addToList = array();
  while ($row = ($ep_uses_mysqli ? mysqli_fetch_array($colsarray) : mysql_fetch_array($colsarray))){
    switch ($row['Field']) {
      case 'stock_id':
      case 'products_id':
      case 'stock_attributes':
      case 'quantity':
      case 'sort':
      case 'customid';
        $desired++;
        break;
      default:
//        $addToList = $row['Field'];
        break;
    }
  }
  unset($project);
  unset($ep_uses_mysqli);
  unset($tablePresent);
  unset($colsarray);
  unset($numCols);
  unset($row);
  if ($desired >= 6) {
    unset($desired);
    return '2';
  }
  unset($desired);
  return false;
}

function ep_4_CEONURIExists () {
  //$project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  // The current thought is to have one of these Exists files for each version of SBA to consider; however, they also all could fall under one SBA_Exists check provided some return is made and a comparison done on the other end about what was returned.
  //Check to see if any version of Stock with attributes is installed (If so, and properly programmed, there should be a define for the table associated with the stock.  There may be more than one, and if so, they should all be verified for the particular SBA.
  if (!defined('TABLE_CEON_URI_MAPPINGS')) {
    //unset($project);
    unset($ep_uses_mysqli);
    return false;
  }
  //Now that have identified that the table (applicable to mc12345678's store, has been identified as in existence, now need to look at the setup of the table (Number of columns and if each column identified below is in the table, or conversely if the table's column matches the list below.
  //Columns in table: stock_id, products_id, stock_attributes, quantity, and sort.
  $colsarray = ep_4_query('SHOW COLUMNS FROM ' . TABLE_CEON_URI_MAPPINGS);
//    echo 'After execute<br />';
  $numCols = ($ep_uses_mysqli ? ($colsarray === false ? 0 : mysqli_num_rows($colsarray)) : mysql_num_rows($colsarray));
  if ($numCols != 9) {
    //unset($project);
    unset($ep_uses_mysqli);
    unset($colsarray);
    unset($numCols);
    return false;
  }
  while ($row = ($ep_uses_mysqli ? mysqli_fetch_array($colsarray) : mysql_fetch_array($colsarray))){
    switch ($row['Field']) {
      case 'uri':
      case 'language_id':
      case 'current_uri':
      case 'main_page':
      case 'query_string_parameters':
      case 'associated_db_id':
      case 'alternate_uri':
      case 'redirection_type_code':
      case 'date_added':
        break;
      default:
        //unset($project);
        unset($ep_uses_mysqli);
        unset($colsarray);
        unset($numCols);
        unset($row);
        return false;
//        break;

    }
  }

  //unset($project);
  unset($ep_uses_mysqli);
  unset($colsarray);
  unset($numCols);
  unset($row);

  return file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.CeonURIMappingAdmin.php');
}

if (!function_exists('zen_get_sub_categories')) {
  function zen_get_sub_categories(&$categories, $categories_id) {
    //global $db;
    $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
    $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
    if ($ep_uses_mysqli) {
      global $ep4;
    }
    $cat_query = "SELECT categories_id FROM " . TABLE_CATEGORIES .
      " WHERE parent_id = ".(int)$categories_id;

    $sub_categories_query = ($ep_uses_mysqli ? mysqli_query($ep4['link'], $cat_query) 
      : mysql_query($cat_query));
    while ($sub_categories = ($ep_uses_mysqli ? mysqli_fetch_array($sub_categories_query) : mysql_fetch_array($sub_categories_query))) {
      if ($sub_categories['categories_id'] == 0) {
        return true;
      }
      $categories[count($categories)] = $sub_categories['categories_id'];
      if ($sub_categories['categories_id'] != $categories_id) {
        zen_get_sub_categories($categories, $sub_categories['categories_id']);
      }
    }
    unset($project);
    unset($ep_uses_mysqli);
    unset($sub_categories_query);
    unset($sub_categories);

    return null;
  }
}

// Removed from extra_functions because ZC v1.5.8 made it such that as a zc_plugins program causes issues.
if (false && !function_exists('plugin_version_check_for_updates')) {
/**
 * plugin_support.php
 *
 * @package functions
 * @copyright Copyright 2003-2015 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Modified in v1.5.5 $
 */
/**
 * Functions to support plugin usage
 */
    /**
     * Check for updated version of a plugin
     * Arguments:
     *   $plugin_file_id = the fileid number for the plugin as hosted on the zen-cart.com plugins library
     *   $version_string_to_compare = the version that I have now on my own server (will be checked against the one on the ZC server)
     * If the "version string" passed to this function evaluates (see strcmp) to a value less-then-or-equal-to the one on the ZC server, FALSE will be returned.
     * If the "version string" on the ZC server is greater than the version string passed to this function, this function will return an array with up-to-date information. The [link] value is the plugin page at zen-cart.com
     * If no plugin_file_id is passed, or if no result is found, then FALSE will be returned.
     *
     * USAGE:
     *   if (IS_ADMIN_FLAG) {
     *     $new_version_details = plugin_version_check_for_updates(999999999, 'some_string');
     *     if ($new_version_details !== FALSE) {
     *       $message = '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>' . '</span>';
     *     }
     *   }
     *
     * @param int    $plugin_file_id
     * @param string $version_string_to_compare
     *
     * @return bool|mixed
     */
  function plugin_version_check_for_updates($plugin_file_id = 0, $version_string_to_compare = '')
  {
    if ($plugin_file_id == 0) return FALSE;
    $new_version_available = FALSE;
    $lookup_index = 0;
    $url = 'https://www.zen-cart.com/downloads.php?do=versioncheck' . '&id='.(int)$plugin_file_id;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Plugin Version Check [' . (int)$plugin_file_id . '] ' . HTTP_SERVER);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    if ($error > 0) {
      curl_setopt($ch, CURLOPT_URL, str_replace('tps:', 'tp:', $url));
      $response = curl_exec($ch);
      $error = curl_error($ch);
    }
    curl_close($ch);
    if ($error > 0 || $response == '') {
      $response = file_get_contents($url);
    }
    if ($response === false) {
      $response = file_get_contents(str_replace('tps:', 'tp:', $url));
    }
    if ($response === false) return false;
    $data = json_decode($response, true);
    if (!$data || !is_array($data)) return false;
    // compare versions
    if (strcmp($data[$lookup_index]['latest_plugin_version'], $version_string_to_compare) > 0) $new_version_available = TRUE;
    // check whether present ZC version is compatible with the latest available plugin version
    if (!in_array('v'. PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR, $data[$lookup_index]['zcversions'])) $new_version_available = FALSE;
    return ($new_version_available) ? $data[$lookup_index] : FALSE;
  }

}

function ep_4_get_uploaded_file($filename) {
  if (isset($_FILES[$filename])) {
    //global $_FILES;
    $uploaded_file = array('name' => $_FILES[$filename]['name'],
    'type' => $_FILES[$filename]['type'],
    'size' => $_FILES[$filename]['size'],
    'tmp_name' => $_FILES[$filename]['tmp_name']);
  } elseif (isset($_POST[$filename])) {
    $uploaded_file = array('name' => $_POST[$filename],
    );
  } elseif (isset($GLOBALS['HTTP_POST_FILES'][$filename])) {
    global $HTTP_POST_FILES;
    $uploaded_file = array('name' => $HTTP_POST_FILES[$filename]['name'],
    'type' => $HTTP_POST_FILES[$filename]['type'],
    'size' => $HTTP_POST_FILES[$filename]['size'],
    'tmp_name' => $HTTP_POST_FILES[$filename]['tmp_name']);
  } elseif (isset($GLOBALS['HTTP_POST_VARS'][$filename])) {
    global $HTTP_POST_VARS;
    $uploaded_file = array('name' => $HTTP_POST_VARS[$filename],
    );
  } else {
    $uploaded_file = array('name' => $GLOBALS[$filename . '_name'],
    'type' => $GLOBALS[$filename . '_type'],
    'size' => $GLOBALS[$filename . '_size'],
    'tmp_name' => $GLOBALS[$filename]);
  }
return $uploaded_file;
}

// the $filename parameter is an array with the following elements: name, type, size, tmp_name
function ep_4_copy_uploaded_file($filename, $target) {
  if (substr($target, -1) != '/') $target .= '/';
  $target .= $filename['name'];
  move_uploaded_file($filename['tmp_name'], $target);
}

function ep_4_get_tax_class_rate($tax_class_id) {
  //global $db;
  $tax_multiplier = 0;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  if ($ep_uses_mysqli) {
    global $ep4;
  }
  
  $tax_rate_query = "SELECT SUM(tax_rate) AS tax_rate FROM ".TABLE_TAX_RATES.
    " WHERE tax_class_id = " . (int)$tax_class_id . " GROUP BY tax_priority";
  $tax_query = ($ep_uses_mysqli 
    ? mysqli_query($ep4['link'], $tax_rate_query)
    : mysql_query($tax_rate_query)
  );
  if (($ep_uses_mysqli ? mysqli_num_rows($tax_query): mysql_num_rows($tax_query))) {
    while ($tax = ($ep_uses_mysqli ? mysqli_fetch_array($tax_query) : mysql_fetch_array($tax_query))) {
      $tax_multiplier += $tax['tax_rate'];
    }
  }
  unset($project);
  unset($ep_uses_mysqli);
  unset($tax_query);
  unset($tax);

  return $tax_multiplier;
}

function ep_4_get_tax_title_class_id($tax_class_title) {
  global $ep4;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  $tax_class_id_query = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS .
    " WHERE tax_class_title = '" . zen_db_input($tax_class_title) . "'";
  $classes_query = ($ep_uses_mysqli 
    ? mysqli_query($ep4['link'], $tax_class_id_query) 
    : mysql_query($tax_class_id_query)
  );
  $tax_class_array = ($ep_uses_mysqli ? mysqli_fetch_array($classes_query) : mysql_fetch_array($classes_query));
  $tax_class_id = !empty($tax_class_array['tax_class_id']) ? $tax_class_array['tax_class_id'] : 0;

  unset($project);
  unset($ep_uses_mysqli);
  unset($classes_query);
  unset($tax_class_array);

  return $tax_class_id;
}

function print_el_4($item2) {
  $output_display = substr(strip_tags($item2), 0, 10)." | ";
  return $output_display;
}

function print_el1_4($item2) {
  $output_display = sprintf("| %'.4s ", substr(strip_tags($item2), 0, 80));
  return $output_display;
}

// this function needs further review
function smart_tags_4($string,$tags,$crsub,$doit) {
  if ($doit == true) {
    foreach ($tags as $tag => $new) {
      $tag = '/('.$tag.')/';
      $string = preg_replace($tag,$new,$string);
    }
    unset($tag);
    unset($new);
  }
  // we remove problem characters here anyway as they are not wanted..
  $string = preg_replace("/(\r\n|\n|\r)/", "", $string);
  // $crsub is redundant - may add it again later though..
  return $string;
}

function ep_4_check_table_column($table_name,$column_name) {
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  $sql = "SHOW COLUMNS FROM ".$table_name;
  $result = ep_4_query($sql);
  $answer = false;
  while ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
//    $column = $row['Field'];
    if ($row['Field'] != $column_name) {
      continue;
    }
    $answer = true;
    break;
  }

  unset($project);
  unset($ep_uses_mysqli);
  unset($sql);
  unset($result);
//  unset($column);
  unset($row);

  return $answer;
}

function ep_4_remove_product($product_model) {
  global $db, $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error, $zco_notifier, $zc_support_ignore_null, $ep4;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  $sql = "SELECT p.products_id FROM ".TABLE_PRODUCTS . " p";
  switch (EP4_DB_FILTER_KEY) {
    case 'products_model':
      $sql .= " WHERE p.products_model = :products_model:";
      $sql = $db->bindVars($sql, ':products_model:', $product_model, $zc_support_ignore_null);
      break;
    case 'blank_new':
    case 'products_id':
      $sql .= " WHERE p.products_id = :products_id:";
      $sql = $db->bindVars($sql, ':products_id:', $product_model, 'integer');
      break;
    default:
      $sql_add = " WHERE p.products_model = :products_model:";
      $zco_notifier->notify('EP4_FUNCTION_REMOVE_PRODUCT', $product_model, $sql_add);
      $sql .= $sql_add;
      unset($sql_add);
      $sql = $db->bindVars($sql, ':products_model:', $product_model, $zc_support_ignore_null);
      break;
  }
  $products = $db->Execute($sql);
  if (($ep_uses_mysqli ? mysqli_errno($ep4['link']) : mysql_errno())) {
    $ep_stack_sql_error = true;
    if ($ep_debug_logging == true) {
      $string = "MySQL error ".($ep_uses_mysqli ? mysqli_errno($ep4['link']) : mysql_errno()).": ".($ep_uses_mysqli ? mysqli_error($ep4['link']) : mysql_error())."\nWhen executing:\n$sql\n";
      write_debug_log_4($string);
    }
  } elseif ($ep_debug_logging_all == true) {
    $string = "MySQL PASSED\nWhen executing:\n$sql\n";
    write_debug_log_4($string);
  }
  while (!$products->EOF) {
    zen_remove_product($products->fields['products_id']);
    $products->MoveNext();
  }
  unset($project);
  unset($ep_uses_mysqli);
  unset($sql);
  unset($products);
  unset($string);
  return;
}

function ep_4_rmv_chars($filelayout, $active_row, $csv_delimiter = "^") {
//  $datarow = ep_4_rmv_chars($filelayout, $active_row, $csv_delimiter);
  $dataRow = '';

  $problem_chars = array("\r", "\n", "\t"); // carriage return, newline, tab
  foreach ($filelayout as $key => $value) {
//    $thetext = $active_row[$key];
    // remove carriage returns, newlines, and tabs - needs review
    $thetext = '';
    if (isset($active_row[$key]) /*|| array_key_exists($key, $active_row)*/) {
      $thetext = str_replace($problem_chars, ' ', $active_row[$key]);
    }
    // encapsulate data in quotes, and escape embedded quotes in data
    $dataRow .= '"' . str_replace('"', '""', $thetext) . '"' . $csv_delimiter;
  }
  // Remove trailing tab, then append the end-of-line
  $dataRow = rtrim($dataRow, $csv_delimiter) . "\n";

  unset($problem_chars);
  unset($thetext);

  return $dataRow;
}


// DEPRECATED: no calls to this function!
// reset products master categories ID - I do not believe this works correctly - chadd
/*
function ep_4_update_cat_ids() {
  global $db;
  $sql = "SELECT products_id FROM ".TABLE_PRODUCTS;
  $check_products = $db->Execute($sql);
  while (!$check_products->EOF) {
    $sql = "SELECT products_id, categories_id FROM ".TABLE_PRODUCTS_TO_CATEGORIES.
      " WHERE products_id='".$check_products->fields['products_id']."'";
    $check_category = $db->Execute($sql);
    $sql = "UPDATE ".TABLE_PRODUCTS." SET master_categories_id='".$check_category->fields['categories_id'].
      "' WHERE products_id='".$check_products->fields['products_id']."'";
    $update_viewed = $db->Execute($sql);
    $check_products->MoveNext();
  }
}
*/

// DEPRECATED: no calls to this function!
// Better to run: zen_update_products_price_sorter($v_products_id);
// after each new or updated product
/*
function ep_4_update_prices() {
  global $db;
  $sql = "SELECT products_id FROM ".TABLE_PRODUCTS;
  $update_prices = $db->Execute($sql);
  while (!$update_prices->EOF) {
    zen_update_products_price_sorter($update_prices->fields['products_id']);
    $update_prices->MoveNext();
  }
}
*/

// DEPRECATED: no calls to this function
// I am writing all my own attribute processing code
/*function ep_4_update_attributes_sort_order() {
  global $db;
  $all_products_attributes = $db->Execute("select p.products_id, pa.products_attributes_id from ".
    TABLE_PRODUCTS." p, ".
    TABLE_PRODUCTS_ATTRIBUTES." pa "."
    where p.products_id = pa.products_id");
  while (!$all_products_attributes->EOF) {
    $count++;
    //$product_id_updated .= ' - ' . $all_products_attributes->fields['products_id'] . ':' . $all_products_attributes->fields['products_attributes_id'];
    zen_update_attributes_products_option_values_sort_order($all_products_attributes->fields['products_id']);
    $all_products_attributes->MoveNext();
  }
}*/

function write_debug_log_4($string) {
  global $ep_debug_log_path;
  if (!is_dir($ep_debug_log_path)) {
    return;
  }
  $logFile = $ep_debug_log_path.'ep_debug_log.txt';
  $fp = fopen($logFile,'ab');
  if (function_exists('debug_backtrace')) {
    $string .= print_r(debug_backtrace(), true);
  }
  fwrite($fp, $string);
  fclose($fp);
  unset($logFile);
  unset($fp);

  return;
}

function ep_4_query($query) {
  global $ep4, $ep_debug_logging, $ep_debug_logging_all, $ep_stack_sql_error/*, $db*/;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  $result = ($ep_uses_mysqli ? (empty($query) ? false : mysqli_query($ep4['link'], $query)) : mysql_query($query));
  if (($ep_uses_mysqli ? ($result === false ? true : mysqli_errno($ep4['link'])) : mysql_errno())) {
    $ep_stack_sql_error = true;
    if ($ep_debug_logging == true) {
      $string = ($ep_uses_mysqli ? "MySQLi" : "MySQL") . " error ".($ep_uses_mysqli ? ($result === false ? 'Empty query' : mysqli_errno($ep4['link'])) : mysql_errno() ) . ": ".($ep_uses_mysqli ? ($result === false ? 'Empty query' : mysqli_error($ep4['link'])) : mysql_error())."\nWhen executing:\n$query\n";
      if (function_exists('debug_backtrace')) {
        $string .= print_r(debug_backtrace(), true);
      }
      write_debug_log_4($string);
    }
  } elseif ($ep_debug_logging_all == true) {
    $string = ($ep_uses_mysqli ? "MySQLi" : "MySQL") . " PASSED\nWhen executing:\n$query\n";
    if (function_exists('debug_backtrace')) {
      $string .= print_r(debug_backtrace(), true);
    }
    write_debug_log_4($string);
  }
//  unset($project);
//  unset($ep_uses_mysqli);
  unset($string);

  return $result;
}

function install_easypopulate_4() {
  global $db, $zco_notifier, $curver_detail;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  if ( (substr($project,0,5) == "1.3.8") || (substr($project,0,5) == "1.3.9") ) {
    // @todo: Should inspect for an existing record and reuse it if available.

        $group_check = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Easy Populate 4'");

        if (!$group_check->EOF && $group_check->RecordCount() > 0 && $group_check->fields['configuration_group_id'] > 0) {
            $group_id = $group_check->fields['configuration_group_id'];

        } else {
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " (configuration_group_title, configuration_group_description, sort_order, visible) VALUES ('Easy Populate 4', 'Configuration Options for Easy Populate 4', '1', '1')");
            $group_id = mysql_insert_id();
            $db->Execute("UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = " . $group_id . " WHERE configuration_group_id = " . $group_id);
        }
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Uploads Directory',                  'EASYPOPULATE_4_CONFIG_TEMP_DIR', 'temp/', 'Name of directory for your uploads as compared to the setting of Uploads Directory Admin/Catalog.<br /><br />Default is to use YOUR_ADMIN/temp/ by entering temp/ below.<br /><b>Caution:</b> the admin directory folder name should not be entered here as it will be stored in the database.  If the admin directory is to be used please set/verify Uploads Directory Admin/Catalog is set to true.<br /><br />(default is to use the YOUR_ADMIN directory and the below value of: temp/).', ".$group_id.", '10', NULL, now(), 'ep4_directory_check', NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES('Uploads Directory Admin/Catalog',                  'EP4_ADMIN_TEMP_DIRECTORY', 'true', 'Should the admin directory be used to store the export and import files for EP4?<br /><br />This switch affects how Uploads Directory is used.<br /><br />true (default) or<br />false. ', ".$group_id.", '20', NULL, now(), 'ep4_directory_choice_check', 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
('Import/Export Primary Key', 'EP4_DB_FILTER_KEY', 'products_model', 'Select the primary key that is to be used for import of the data.<br /><br />The default for Easy Populate v4 is products_model.<br /><br /> The field products_model is independent of the store, while products_id will require/generate the product information associated with that products_id and could lead to duplication of product. Choosing blank_new will import by products_id and create new products when the products_id is not entered/blank.<br /><br />products_model (default)<br />products_id<br />blank_new', ".$group_id.", '30', NULL, now(), NULL, 'zen_cfg_select_option(array(\'products_model\', \'products_id\', \'blank_new\'),')");
        $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET configuration_description = 'On import, if all possible language identifiers for a field are given, which should be used for the final data? (E.g. products_name_en (language_code), products_name_1 (language_id) are given in the file, which should be used?)<br />This is a \"preference\" unless the option with _only is selected.<br />Default: language_id_only', set_function = 'zen_cfg_select_option(array(\"language_id_only\", \"language_code_only\", \"language_id\", \"language_code\", ),' WHERE configuration_key = 'EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE' 
    ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Import Language Override',       'EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE', 'language_id_only', 'On import, if all possible language identifiers for a field are given, which should be used for the final data? (E.g. products_name_en (language_code), products_name_1 (language_id) are given in the file, which should be used?)<br />This is a \"preference\" unless the option with _only is selected.<br />Default: language_id_only', ".$group_id.", '32', NULL, now(), NULL, 'zen_cfg_select_option(array(\"language_id_only\", \"language_code_only\", \"language_id\", \"language_code\", ),')
    ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Import/Export CSV Delimiter', 'EASYPOPULATE_4_CONFIG_CSV_DELIMITER', '0', 'When exporting and importing files, each field of a record is separated by some \"unique\" character.  This character is called the CSV delimiter and allows the program reading the file to understand where one field ends and another begins.  CSV stands for Comma Separated Value and therefore a comma is the default; however, there are conditions and times when an alternate delimiter is needed or desired.  Choose the delimiter used for the file(s) to be imported/exported.  (default: \",\")<br/><br/>1. \",\" - comma,<br/>2. \";\" - semicolon,<br/>3. \"\t\" - tab,<br/>4. \" \" - space', ".$group_id.", '35', NULL, now(), 'ep4_delimiter_display', 'zen_cfg_select_drop_down(array(array(\'id\'=>\'0\', \'text\'=>\',\'), array(\'id\'=>\'1\', \'text\'=>\';\'), array(\'id\'=>\'2\', \'text\'=>\'\t\'), array(\'id\'=>\'3\', \'text\'=>\' \')),')
    ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
('Export Language Identifier', 'EASYPOPULATE_4_CONFIG_LANGUAGE_EXPORT', 'id', 'Select the export suffix to fields that are language dependent.<br /><br />id: represents the numeric language_id assigned to the language.<br />code: represents the abbreviated language identifier such as en for English.<br />all: exports each language based field with all of the programmed suffixes to be included.<br />(default: id)<br />', ".$group_id.", '35', NULL, now(), NULL, 'zen_cfg_select_option(array(\'id\', \'code\', \'all\'),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
('Export Price Decimals', 'EASYPOPULATE_4_CONFIG_DECIMAL_EXPORT', '2', 'Select the number of decimals to include when exporting data that includes the product price.<br /><br />The exported number will be rounded to this many decimals. (default: 2)<br />', ".$group_id.", '37', NULL, now(), NULL, 'zen_cfg_select_option(array(\'0\', \'1\', \'2\', \'3\', \'4\', \'5\', \'6\', \'7\', \'8\', \'9\'),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
('Upload File Date Format',            'EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT', 'm-d-y', 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.', ".$group_id.", '40', NULL, now(), NULL, 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Default Raw Time',                   'EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME', '09:00:00', 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)', ".$group_id.", '50', NULL, now(), NULL, NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Export Filename Date', 'EASYPOPULATE_4_CONFIG_DATE_SORT', 'Y-m-d', 'When exporting files, a unique date identifier is added.  The default was year as four numbers, text abbreviated month, and two number day all together (Ybd).  To improve on sorting within a file group, an option is now available to export the four number year, two digit month, and two digit day so that files within a group will be in export date order.<br/><br/>The default is now Y-m-d.', ".$group_id.", '55', NULL, now(), NULL, 'zen_cfg_select_option(array(\"Y-m-d\", \"Ymd\", \"Ybd\"),')
    ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Upload/Download Prices Include Tax', 'EASYPOPULATE_4_CONFIG_PRICE_INC_TAX', 'false', 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart.', ".$group_id.", '60', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Update Check',                   'EASYPOPULATE_4_PLUGIN_CHECK', '1', 'Should Easy Populate V4 check the Zen Cart Site for updates? (default: 1 (true)).', ".$group_id.", '65', NULL, now(), NULL, 'zen_cfg_select_option(array(\"1\", \"0\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Verbose Feedback',                   'EASYPOPULATE_4_CONFIG_VERBOSE', 'true', 'When importing, report all messages. Set to false for only warnings and errors. (default: true).', ".$group_id.", '70', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Show all EP4 Filetypes with Files',       'EP4_SHOW_ALL_FILETYPES', 'true', 'When looking at the EP4 Tools screen, should the filename prefix for all specific file types be displayed for all possible file types (true [default]), should only the method(s) that will be used to process the files present be displayed (false), or should there be no assistance be provided on filenaming on the main page (Hidden) like it was until this feature was added? (true, false, or Hidden)', ".$group_id.", '80', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\", \"Hidden\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Replace Blank Image', 'EP4_REPLACE_BLANK_IMAGE', 'false', 'On import, if the image information is blank, then update the image path to the path of the blank image (true)? Otherwise the image path will remain blank (false <Default>).<br /><br />false (Default)<br />true.', ".$group_id.", '90', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),')");
        $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET set_function = 'zen_cfg_select_option(array(\'false\', \'true\'),' WHERE configuration_key = 'EP4_ACTIVATE_BLANK_FEATURED'
        ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Activate Blank Featured', 'EP4_ACTIVATE_BLANK_FEATURED', 'true', 'On import, if the available date and expiry date are both blank, 0, 0001-01-01, or not imported, should the featured product be made automatically active?<br/> (yes=true or no=false)<br/>(Default: yes)', ".$group_id.", '95', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('Remove Blank Specials', 'EASYPOPULATE_4_CONFIG_SPECIALS_REMOVE_AT', 'negative', 'On import, what price level should be used to clear the special from the database.<br /><br /><b>New Default</b>: negative - a negative price entered for the special will delete the special on import.<br />zero: a negative or zero price entered for the special will delete the special on import.<br />', ".$group_id.", '97', NULL, now(), NULL, 'zen_cfg_select_option(array(\'negative\', \'zero\'),')
        ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Make Zero Qty Products Inactive',    'EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE', 'false', 'When uploading, make the status Inactive for products with zero qty (default: false).', ".$group_id.", '100', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Smart Tags Replacement of Newlines', 'EASYPOPULATE_4_CONFIG_SMART_TAGS', 'true', 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting - Note: this legacy code is disabled until further review. (default: true).', ".$group_id.", '110', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Advanced Smart Tags',                'EASYPOPULATE_4_CONFIG_ADV_SMART_TAGS', 'false', 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Note: legacy code is disabled until further review. (default: false).', ".$group_id.", '120', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Auto-extend fields',                'EASYPOPULATE_4_CONFIG_AUTO_EXTEND_FIELD', 'false', 'Extend the field length of fields that are being tracked by this module based on the longest field imported. (default: false).', ".$group_id.", '125', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')");
        $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET set_function = 'zen_cfg_select_option(array(\"true\", \"false\", \"all\"),' WHERE configuration_key = 'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING' 
        ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Debug Logging',                      'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING', 'true', 'Allow Easy Populate to generate an error log on errors only (default: true)', ".$group_id.", '130', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\", \"all\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Maximum Quantity Discounts',         'EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS', '0', 'Maximum number of quantity discounts (price breaks). Controls the use of the auto-determination (0) or setting of the number of discount columns in downloaded file (default: 0).<br><br>A value of 0 or left empty will allow the quantity of columns to be determined. A minimum quantity (original default of 3) will be exported.<br>Entering any other value will export only that number of columns.', ".$group_id.", '140', NULL, now(), NULL, NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Split On Number of Records',         'EASYPOPULATE_4_CONFIG_SPLIT_RECORDS', '2000', 'Number of records to split csv files. Used to break large import files into smaller files. Useful on servers with limited resourses. (default: 2000).', ".$group_id.", '150', NULL, now(), NULL, NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Script Execution Time',              'EASYPOPULATE_4_CONFIG_EXECUTION_TIME', '60', 'Number of seconds for script to run before timeout. May not work on some servers. (default: 60).', ".$group_id.", '160', NULL, now(), NULL, NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Convert Curly Quotes, etc.',         'EASYPOPULATE_4_CONFIG_CURLY_QUOTES', '0', 'Convert Curly Quotes, Em-Dash, En-Dash and Ellipsis characters in fields displayed to customer (default 0).<br><br>0=No Change<br>1=Replace with Basic Characters<br>2=Replace with HTML equivalents', ".$group_id.", '170', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Convert Character 0x92',             'EASYPOPULATE_4_CONFIG_CHAR_92', '1', 'Convert Character 0x92 characters in Product Names &amp; Descriptions (default 1).<br><br>0=No Change<br>1=Replace with Standard Single Quote<br>2=Replace with HMTL equivalant', ".$group_id.", '180', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Enable Products Meta Data',          'EASYPOPULATE_4_CONFIG_META_DATA', '1', 'Enable Products Meta Data Columns (default 1).<br><br>0=Disable<br>1=Enable', ".$group_id.", '190', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Enable Products Music Data',         'EASYPOPULATE_4_CONFIG_MUSIC_DATA', '0', 'Enable Products Music Data Columns (default 0).<br><br>0=Disable<br>1=Enable', ".$group_id.", '200', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('User Defined Products Fields',       'EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS', '', 'User Defined Products Table Fields (comma delimited, no spaces)', ".$group_id.", '210', NULL, now(), NULL, NULL)");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Export URI with Prod and or Cat',       'EASYPOPULATE_4_CONFIG_EXPORT_URI', '0', 'Export the current products or categories URI when exporting data? (Yes - 1 or no - 0)', ".$group_id.", '220', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')
    ");
        $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('EP4-version', 'TOOLS_EASYPOPULATE_4_VERSION', '" . $curver_detail . "', 'This is the version of EP4 installed.', 6, '70', NULL, now(), NULL, 'zen_cfg_select_option(array(\'" . $curver_detail . "\'),')
        ON DUPLICATE KEY UPDATE configuration_value = '" . $curver_detail . "', set_function = 'zen_cfg_select_option(array(\'" . $curver_detail . "\'),'
    ");

  } elseif (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.0') {

        $group_check = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Easy Populate 4'");

        if (!$group_check->EOF && $group_check->RecordCount() > 0 && $group_check->fields['configuration_group_id'] > 0) {
            $group_id = $group_check->fields['configuration_group_id'];

        } else {
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " (configuration_group_title, configuration_group_description, sort_order, visible) VALUES ('Easy Populate 4', 'Configuration Options for Easy Populate 4', '1', '1')");
            if (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3') {
                global $ep4;
                $group_id = mysqli_insert_id($ep4['link']);
            } else {
                $group_id = mysql_insert_id();
            }
        $db->Execute("UPDATE ".TABLE_CONFIGURATION_GROUP." SET sort_order = ".$group_id." WHERE configuration_group_id = ".$group_id);

            zen_register_admin_page('easypopulate_4_config', 'BOX_TOOLS_EASYPOPULATE_4','FILENAME_CONFIGURATION', 'gID='.$group_id, 'configuration', 'Y', 97);
        }
    /*$db->Execute("INSERT INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Uploads Directory',                  'EASYPOPULATE_4_CONFIG_TEMP_DIR', 'temp/', 'Name of directory for your uploads as compared to the setting of Uploads Directory Admin/Catalog.<br /><br />Default is to use YOUR_ADMIN/temp/ by entering temp/ below.<br /><b>Caution:</b> the admin directory folder name should not be entered here as it will be stored in the database.  If the admin directory is to be used please set/verify Uploads Directory Admin/Catalog is set to true.<br /><br />(default is to use the YOUR_ADMIN directory and the below value of: temp/).', ".$group_id.", '10', NULL, now(), 'ep4_directory_check', NULL),
      ('Uploads Directory Admin/Catalog',                  'EP4_ADMIN_TEMP_DIRECTORY', 'true', 'Should the admin directory be used to store the export and import files for EP4?<br /><br />This switch affects how Uploads Directory is used.<br /><br />true (default) or<br />false. ', ".$group_id.", '20', NULL, now(), 'ep4_directory_choice_check', 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Import/Export Primary Key', 'EP4_DB_FILTER_KEY', 'products_model', 'Select the primary key that is to be used for import of the data.<br /><br />The default for Easy Populate v4 is products_model.<br /><br /> The field products_model is independent of the store, while products_id will require/generate the product information associated with that products_id and could lead to duplication of product. Choosing blank_new will import by products_id and create new products when the products_id is not entered/blank.<br /><br />products_model (default)<br />products_id<br />blank_new', ".$group_id.", '30', NULL, now(), NULL, 'zen_cfg_select_option(array(\'products_model\', \'products_id\', \'blank_new\'),'),
      ('Upload File Date Format',            'EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT', 'm-d-y', 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.', ".$group_id.", '40', NULL, now(), NULL, 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),'),
      ('Default Raw Time',                   'EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME', '09:00:00', 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)', ".$group_id.", '50', NULL, now(), NULL, NULL),
      ('Upload/Download Prices Include Tax', 'EASYPOPULATE_4_CONFIG_PRICE_INC_TAX', 'false', 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart.', ".$group_id.", '60', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Verbose Feedback',                   'EASYPOPULATE_4_CONFIG_VERBOSE', 'true', 'When importing, report all messages. Set to false for only warnings and errors. (default: true).', ".$group_id.", '70', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Show all EP4 Filetypes with Files',       'EP4_SHOW_ALL_FILETYPES', 'true', 'When looking at the EP4 Tools screen, should the filename prefix for all specific file types be displayed for all possible file types (true [default]), should only the method(s) that will be used to process the files present be displayed (false), or should there be no assistance be provided on filenaming on the main page (Hidden) like it was until this feature was added? (true, false, or Hidden)', ".$group_id.", '80', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\", \"Hidden\"),'),
      ('Replace Blank Image', 'EP4_REPLACE_BLANK_IMAGE', 'false', 'On import, if the image information is blank, then update the image path to the path of the blank image (true)? Otherwise the image path will remain blank (false <Default>).<br /><br />false (Default)<br />true.', ".$group_id.", '90', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),'),
      ('Activate Blank Featured', 'EP4_ACTIVATE_BLANK_FEATURED', 'true', 'On import, if the available date and expiry date are both blank, 0, 0001-01-01, or not imported, should the featured product be made automatically active?<br/> (yes=true or no=false)<br/>(Default: yes)', ".$group_id.", '95', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),'),
      ('Make Zero Qty Products Inactive',    'EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE', 'false', 'When uploading, make the status Inactive for products with zero qty (default: false).', ".$group_id.", '100', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Smart Tags Replacement of Newlines', 'EASYPOPULATE_4_CONFIG_SMART_TAGS', 'true', 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting - Note: this legacy code is disabled until further review. (default: true).', ".$group_id.", '110', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Advanced Smart Tags',                'EASYPOPULATE_4_CONFIG_ADV_SMART_TAGS', 'false', 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Note: legacy code is disabled until further review. (default: false).', ".$group_id.", '120', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Debug Logging',                      'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING', 'true', 'Allow Easy Populate to generate an error log on errors only (default: true)', ".$group_id.", '130', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),'),
      ('Maximum Quantity Discounts',         'EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS', '3', 'Maximum number of quantity discounts (price breaks). Is the number of discount columns in downloaded file (default: 3).', ".$group_id.", '140', NULL, now(), NULL, NULL),
      ('Split On Number of Records',         'EASYPOPULATE_4_CONFIG_SPLIT_RECORDS', '2000', 'Number of records to split csv files. Used to break large import files into smaller files. Useful on servers with limited resourses. (default: 2000).', ".$group_id.", '150', NULL, now(), NULL, NULL),
      ('Script Execution Time',              'EASYPOPULATE_4_CONFIG_EXECUTION_TIME', '60', 'Number of seconds for script to run before timeout. May not work on some servers. (default: 60).', ".$group_id.", '160', NULL, now(), NULL, NULL),
      ('Convert Curly Quotes, etc.',         'EASYPOPULATE_4_CONFIG_CURLY_QUOTES', '0', 'Convert Curly Quotes, Em-Dash, En-Dash and Ellipsis characters in fields displayed to customer (default 0).<br><br>0=No Change<br>1=Replace with Basic Characters<br>2=Replace with HTML equivalents', ".$group_id.", '170', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),'),
      ('Convert Character 0x92',             'EASYPOPULATE_4_CONFIG_CHAR_92', '1', 'Convert Character 0x92 characters in Product Names &amp; Descriptions (default 1).<br><br>0=No Change<br>1=Replace with Standard Single Quote<br>2=Replace with HMTL equivalant', ".$group_id.", '180', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),'),
      ('Enable Products Meta Data',          'EASYPOPULATE_4_CONFIG_META_DATA', '1', 'Enable Products Meta Data Columns (default 1).<br><br>0=Disable<br>1=Enable', ".$group_id.", '190', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),'),
      ('Enable Products Music Data',         'EASYPOPULATE_4_CONFIG_MUSIC_DATA', '0', 'Enable Products Music Data Columns (default 0).<br><br>0=Disable<br>1=Enable', ".$group_id.", '200', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),'),
      ('User Defined Products Fields',       'EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS', '', 'User Defined Products Table Fields (comma delimited, no spaces)', ".$group_id.", '210', NULL, now(), NULL, NULL),
      ('Export URI with Prod and or Cat',       'EASYPOPULATE_4_CONFIG_EXPORT_URI', '0', 'Export the current products or categories URI when exporting data? (Yes - 1 or no - 0)', ".$group_id.", '220', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')
    ");*/

    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Uploads Directory',                  'EASYPOPULATE_4_CONFIG_TEMP_DIR', 'temp/', 'Name of directory for your uploads as compared to the setting of Uploads Directory Admin/Catalog.<br /><br />Default is to use YOUR_ADMIN/temp/ by entering temp/ below.<br /><b>Caution:</b> the admin directory folder name should not be entered here as it will be stored in the database.  If the admin directory is to be used please set/verify Uploads Directory Admin/Catalog is set to true.<br /><br />(default is to use the YOUR_ADMIN directory and the below value of: temp/).', ".$group_id.", '10', NULL, now(), 'ep4_directory_check', NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Uploads Directory Admin/Catalog',                  'EP4_ADMIN_TEMP_DIRECTORY', 'true', 'Should the admin directory be used to store the export and import files for EP4?<br /><br />This switch affects how Uploads Directory is used.<br /><br />true (default) or<br />false. ', ".$group_id.", '20', NULL, now(), 'ep4_directory_choice_check', 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Import/Export Primary Key', 'EP4_DB_FILTER_KEY', 'products_model', 'Select the primary key that is to be used for import of the data.<br /><br />The default for Easy Populate v4 is products_model.<br /><br /> The field products_model is independent of the store, while products_id will require/generate the product information associated with that products_id and could lead to duplication of product. Choosing blank_new will import by products_id and create new products when the products_id is not entered/blank.<br /><br />products_model (default)<br />products_id<br />blank_new', ".$group_id.", '30', NULL, now(), NULL, 'zen_cfg_select_option(array(\'products_model\', \'products_id\', \'blank_new\'),')
    ");
    $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET configuration_description = 'On import, if all possible language identifiers for a field are given, which should be used for the final data? (E.g. products_name_en (language_code), products_name_1 (language_id) are given in the file, which should be used?)<br />This is a \"preference\" unless the option with _only is selected.<br />Default: language_id_only', set_function = 'zen_cfg_select_option(array(\"language_id_only\", \"language_code_only\", \"language_id\", \"language_code\", ),' WHERE configuration_key = 'EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE' 
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Import Language Override',       'EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE', 'language_id_only', 'On import, if all possible language identifiers for a field are given, which should be used for the final data? (E.g. products_name_en (language_code), products_name_1 (language_id) are given in the file, which should be used?)<br />This is a \"preference\" unless the option with _only is selected.<br />Default: language_id_only', ".$group_id.", '32', NULL, now(), NULL, 'zen_cfg_select_option(array(\"language_id_only\", \"language_code_only\", \"language_id\", \"language_code\", ),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Import/Export CSV Delimiter', 'EASYPOPULATE_4_CONFIG_CSV_DELIMITER', '0', 'When exporting and importing files, each field of a record is separated by some \"unique\" character.  This character is called the CSV delimiter and allows the program reading the file to understand where one field ends and another begins.  CSV stands for Comma Separated Value and therefore a comma is the default; however, there are conditions and times when an alternate delimiter is needed or desired.  Choose the delimiter used for the file(s) to be imported/exported.  (default: \",\")<br/><br/>1. \",\" - comma,<br/>2. \";\" - semicolon,<br/>3. \"\t\" - tab,<br/>4. \" \" - space', ".$group_id.", '35', NULL, now(), 'ep4_delimiter_display', 'zen_cfg_select_drop_down(array(array(\'id\'=>\'0\', \'text\'=>\',\'), array(\'id\'=>\'1\', \'text\'=>\';\'), array(\'id\'=>\'2\', \'text\'=>\'\t\'), array(\'id\'=>\'3\', \'text\'=>\' \')),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Export Language Identifier', 'EASYPOPULATE_4_CONFIG_LANGUAGE_EXPORT', 'id', 'Select the export suffix to fields that are language dependent.<br /><br />id: represents the numeric language_id assigned to the language.<br />code: represents the abbreviated language identifier such as en for English.<br />all: exports each language based field with all of the programmed suffixes to be included.<br />(default: id)<br />', ".$group_id.", '35', NULL, now(), NULL, 'zen_cfg_select_option(array(\'id\', \'code\', \'all\'),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Export Price Decimals', 'EASYPOPULATE_4_CONFIG_DECIMAL_EXPORT', '2', 'Select the number of decimals to include when exporting data that includes the product price.<br /><br />The exported number will be rounded to this many decimals. (default: 2)<br />', ".$group_id.", '37', NULL, now(), NULL, 'zen_cfg_select_option(array(\'0\', \'1\', \'2\', \'3\', \'4\', \'5\', \'6\', \'7\', \'8\', \'9\'),')");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Upload File Date Format',            'EASYPOPULATE_4_CONFIG_FILE_DATE_FORMAT', 'm-d-y', 'Choose order of date values that corresponds to your uploads file, usually generated by MS Excel. Raw dates in your uploads file (Eg 2005-09-26 09:00:00) are not affected, and will upload as they are.', ".$group_id.", '40', NULL, now(), NULL, 'zen_cfg_select_option(array(\"m-d-y\", \"d-m-y\", \"y-m-d\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Default Raw Time',                   'EASYPOPULATE_4_CONFIG_DEFAULT_RAW_TIME', '09:00:00', 'If no time value stipulated in upload file, use this value. Useful for ensuring specials begin after a specific time of the day (default: 09:00:00)', ".$group_id.", '50', NULL, now(), NULL, NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Export Filename Date', 'EASYPOPULATE_4_CONFIG_DATE_SORT', 'Y-m-d', 'When exporting files, a unique date identifier is added.  The default was year as four numbers, text abbreviated month, and two number day all together (Ybd).  To improve on sorting within a file group, an option is now available to export the four number year, two digit month, and two digit day so that files within a group will be in export date order.<br/><br/>The default is now Y-m-d.', ".$group_id.", '55', NULL, now(), NULL, 'zen_cfg_select_option(array(\"Y-m-d\", \"Ymd\", \"Ybd\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Upload/Download Prices Include Tax', 'EASYPOPULATE_4_CONFIG_PRICE_INC_TAX', 'false', 'Choose to include or exclude tax, depending on how you manage prices outside of Zen Cart.', ".$group_id.", '60', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Update Check',                   'EASYPOPULATE_4_PLUGIN_CHECK', '1', 'Should Easy Populate V4 check the Zen Cart Site for updates? (default: 1 (true)).', ".$group_id.", '65', NULL, now(), NULL, 'zen_cfg_select_option(array(\"1\", \"0\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Verbose Feedback',                   'EASYPOPULATE_4_CONFIG_VERBOSE', 'true', 'When importing, report all messages. Set to false for only warnings and errors. (default: true).', ".$group_id.", '70', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Show all EP4 Filetypes with Files',       'EP4_SHOW_ALL_FILETYPES', 'true', 'When looking at the EP4 Tools screen, should the filename prefix for all specific file types be displayed for all possible file types (true [default]), should only the method(s) that will be used to process the files present be displayed (false), or should there be no assistance be provided on filenaming on the main page (Hidden) like it was until this feature was added? (true, false, or Hidden)', ".$group_id.", '80', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\", \"Hidden\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('Replace Blank Image', 'EP4_REPLACE_BLANK_IMAGE', 'false', 'On import, if the image information is blank, then update the image path to the path of the blank image (true)? Otherwise the image path will remain blank (false <Default>).<br /><br />false (Default)<br />true.', ".$group_id.", '90', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),')
    ");
    $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET set_function = 'zen_cfg_select_option(array(\'false\', \'true\'),' WHERE configuration_key = 'EP4_ACTIVATE_BLANK_FEATURED' 
    ");
    $db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('Activate Blank Featured', 'EP4_ACTIVATE_BLANK_FEATURED', 'true', 'On import, if the available date and expiry date are both blank, 0, 0001-01-01, or not imported, should the featured product be made automatically active?<br/> (yes=true or no=false)<br/>(Default: yes)', ".$group_id.", '95', NULL, now(), NULL, 'zen_cfg_select_option(array(\'false\', \'true\'),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('Remove Blank Specials', 'EASYPOPULATE_4_CONFIG_SPECIALS_REMOVE_AT', 'negative', 'On import, what price level should be used to clear the special from the database.<br /><br /><b>New Default</b>: negative - a negative price entered for the special will delete the special on import.<br />zero: a negative or zero price entered for the special will delete the special on import.<br />', ".$group_id.", '97', NULL, now(), NULL, 'zen_cfg_select_option(array(\'negative\', \'zero\'),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Make Zero Qty Products Inactive',    'EASYPOPULATE_4_CONFIG_ZERO_QTY_INACTIVE', 'false', 'When uploading, make the status Inactive for products with zero qty (default: false).', ".$group_id.", '100', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Smart Tags Replacement of Newlines', 'EASYPOPULATE_4_CONFIG_SMART_TAGS', 'true', 'Allows your description fields in your uploads file to have carriage returns and/or new-lines converted to HTML line-breaks on uploading, thus preserving some rudimentary formatting - Note: this legacy code is disabled until further review. (default: true).', ".$group_id.", '110', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Advanced Smart Tags',                'EASYPOPULATE_4_CONFIG_ADV_SMART_TAGS', 'false', 'Allow the use of complex regular expressions to format descriptions, making headings bold, add bullets, etc. Note: legacy code is disabled until further review. (default: false).', ".$group_id.", '120', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Auto-extend fields',                'EASYPOPULATE_4_CONFIG_AUTO_EXTEND_FIELD', 'false', 'Extend the field length of fields that are being tracked by this module based on the longest field imported. (default: false).', ".$group_id.", '125', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
    $db->Execute("UPDATE IGNORE " . TABLE_CONFIGURATION . " SET set_function = 'zen_cfg_select_option(array(\"true\", \"false\", \"all\"),' WHERE configuration_key = 'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING' 
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Debug Logging',                      'EASYPOPULATE_4_CONFIG_DEBUG_LOGGING', 'true', 'Allow Easy Populate to generate an error log on errors only (default: true)', ".$group_id.", '130', NULL, now(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\", \"all\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Maximum Quantity Discounts',         'EASYPOPULATE_4_CONFIG_MAX_QTY_DISCOUNTS', '0', 'Maximum number of quantity discounts (price breaks). Controls the use of the auto-determination (0) or setting of the number of discount columns in downloaded file (default: 0).<br><br>A value of 0 or left empty will allow the quantity of columns to be determined. A minimum quantity (original default of 3) will be exported.<br>Entering any other value will export only that number of columns.', ".$group_id.", '140', NULL, now(), NULL, NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Split On Number of Records',         'EASYPOPULATE_4_CONFIG_SPLIT_RECORDS', '2000', 'Number of records to split csv files. Used to break large import files into smaller files. Useful on servers with limited resourses. (default: 2000).', ".$group_id.", '150', NULL, now(), NULL, NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Script Execution Time',              'EASYPOPULATE_4_CONFIG_EXECUTION_TIME', '60', 'Number of seconds for script to run before timeout. May not work on some servers. (default: 60).', ".$group_id.", '160', NULL, now(), NULL, NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Convert Curly Quotes, etc.',         'EASYPOPULATE_4_CONFIG_CURLY_QUOTES', '0', 'Convert Curly Quotes, Em-Dash, En-Dash and Ellipsis characters in fields displayed to customer (default 0).<br><br>0=No Change<br>1=Replace with Basic Characters<br>2=Replace with HTML equivalents', ".$group_id.", '170', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Convert Character 0x92',             'EASYPOPULATE_4_CONFIG_CHAR_92', '1', 'Convert Character 0x92 characters in Product Names &amp; Descriptions (default 1).<br><br>0=No Change<br>1=Replace with Standard Single Quote<br>2=Replace with HMTL equivalant', ".$group_id.", '180', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\", \"2\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Enable Products Meta Data',          'EASYPOPULATE_4_CONFIG_META_DATA', '1', 'Enable Products Meta Data Columns (default 1).<br><br>0=Disable<br>1=Enable', ".$group_id.", '190', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Enable Products Music Data',         'EASYPOPULATE_4_CONFIG_MUSIC_DATA', '0', 'Enable Products Music Data Columns (default 0).<br><br>0=Disable<br>1=Enable', ".$group_id.", '200', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('User Defined Products Fields',       'EASYPOPULATE_4_CONFIG_CUSTOM_FIELDS', '', 'User Defined Products Table Fields (comma delimited, no spaces)', ".$group_id.", '210', NULL, now(), NULL, NULL)
    ");
    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('Export URI with Prod and or Cat',       'EASYPOPULATE_4_CONFIG_EXPORT_URI', '0', 'Export the current products or categories URI when exporting data? (Yes - 1 or no - 0)', ".$group_id.", '220', NULL, now(), NULL, 'zen_cfg_select_option(array(\"0\", \"1\"),')
    ");


    $db->Execute("INSERT IGNORE INTO ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
      ('EP4-version', 'TOOLS_EASYPOPULATE_4_VERSION', '" . $curver_detail . "', 'This is the version of EP4 installed.', 6, '70', NULL, now(), NULL, 'zen_cfg_select_option(array(\'" . $curver_detail . "\'),')
      ON DUPLICATE KEY
       UPDATE configuration_value = '" . $curver_detail . "', set_function = 'zen_cfg_select_option(array(\'" . $curver_detail . "\'),'
    ");


  } else { // unsupported version
    // i should do something here!
  }
    $zco_notifier->notify('EP4_EXTRA_FUNCTIONS_INSTALL_END', array('group_id' => $group_id));
    unset($project);
    unset($group_id);
}

function remove_easypopulate_4() {
  global $db;
  $project = PROJECT_VERSION_MAJOR.'.'.PROJECT_VERSION_MINOR;
  $ep_uses_mysqli = (PROJECT_VERSION_MAJOR > '1' || PROJECT_VERSION_MINOR >= '5.3');
  if ( (substr($project,0,5) == "1.3.8") || (substr($project,0,5) == "1.3.9") ) {
    $sql = "SELECT configuration_group_id FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_title = 'Easy Populate 4' LIMIT 1";
    $result = ep_4_query($sql);
    if (mysql_num_rows($result)) {
      $ep_group_id =  mysql_fetch_array($result);
      $db->Execute("DELETE FROM ".TABLE_CONFIGURATION." WHERE configuration_group_id = ".$ep_group_id[0]);
      $db->Execute("DELETE FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_id = ".$ep_group_id[0]);
    }
  } elseif (substr($project,0,3) == "1.5") {
    $sql = "SELECT configuration_group_id FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_title = 'Easy Populate 4' LIMIT 1";
    $result = ep_4_query($sql);
    if (($ep_uses_mysqli ? mysqli_num_rows($result) : mysql_num_rows($result))) {
      $ep_group_id =  ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result));
      $db->Execute("DELETE FROM ".TABLE_CONFIGURATION." WHERE configuration_group_id = ".$ep_group_id[0]);
      $db->Execute("DELETE FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_id = ".$ep_group_id[0]);
      $db->Execute("DELETE FROM ".TABLE_ADMIN_PAGES." WHERE page_key = 'easypopulate_4'");
      $db->Execute("DELETE FROM ".TABLE_ADMIN_PAGES." WHERE page_key = 'easypopulate_4_config'");
    }
  } else { // unsupported version
  }
  unset($project);
  unset($ep_uses_mysqli);
  unset($sql);
  unset($result);

}

function ep_4_chmod_check($tempdir) {
  global $messageStack;
  if (!@file_exists((EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir . ".")) { // directory does not exist
    $messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_MISSING, $tempdir, (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN)), 'warning');
    return false;
  }
  // directory exists, test is writeable
  if (!@is_writable((EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN) . $tempdir . ".")) { // directory does not exist
    $messageStack->add(sprintf(EASYPOPULATE_4_MSGSTACK_TEMP_FOLDER_NOT_WRITABLE, $tempdir, (EP4_ADMIN_TEMP_DIRECTORY !== 'true' ? /* Storeside */ DIR_FS_CATALOG : /* Admin side */ DIR_FS_ADMIN)), 'warning');
    return false;
  }
  return true;
}

function ep4_non_admin_temp($ep_debug_log_path) {
  if (EP4_ADMIN_TEMP_DIRECTORY === 'true') {
    return;
  }
  if (strpos(DIR_FS_CATALOG . $ep_debug_log_path, DIR_FS_ADMIN) !== 0) {
    return;
  }
  global $db;
  
  $temp_rem = substr(DIR_FS_CATALOG . $ep_debug_log_path, strlen(DIR_FS_ADMIN));
  $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'true\' where configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', "1", false, 0, true);

  $db->Execute('UPDATE ' . TABLE_CONFIGURATION . ' SET configuration_value = \'' . $temp_rem . '\' WHERE configuration_key = \'EASYPOPULATE_4_CONFIG_TEMP_DIR\'', "1", false, 0, true);
  unset($temp_rem);
    // need a message to  be displayed...
//    zen_redirect(zen_href_link(FILENAME_CONFIGURATION /*, $parameters*/));
    // zen_redirect(zen_href_link(FILENAME_EASYPOPULATE_4));

}

function ep4_directory_check($ep_debug_log_path) {
  global $db;

  ep4_non_admin_temp($ep_debug_log_path);
  $ep_debug_log_path = $db->Execute('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE configuration_key = \'EASYPOPULATE_4_CONFIG_TEMP_DIR\'', '1', false, 0, true);

  return $ep_debug_log_path->fields['configuration_value'];
}

function ep4_delimiter_display($config_value) {
  // global $db;

  // $ep_delim_value_return = '';

  switch ($config_value) {
    case '0':
      $ep_delim_value_return = ',';
      break;
    case '1':
      $ep_delim_value_return = ';';
      break;
    case '2':
      $ep_delim_value_return = 'tab';
      break;
    case '3':
      $ep_delim_value_return = 'space';
      break;
    default:
      $ep_delim_value_return = $ep_delim_value->fields['configuration_value'];
      break;
  }

  return $ep_delim_value_return;
}


function ep4_directory_choice_check($ep_debug_log_path) {
  global $db;

  //$temp_dir = $db->Execute('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' where configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', false, false, 0, true);

  ep4_non_admin_temp($ep_debug_log_path);
  $ep_debug_log_path = $db->Execute('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE configuration_key = \'EP4_ADMIN_TEMP_DIRECTORY\'', '1', false, 0, true);

  // Intended to be a string value that returns for database value storage
  return ($ep_debug_log_path->fields['configuration_value'] == 'true' ? 'true' : 'false');
}
// The following functions are for testing purposes only
// available zen functions of use..
/*
function zen_get_category_name($category_id, $language_id)
function zen_get_category_description($category_id, $language_id)
function zen_get_products_name($product_id, $language_id = 0)
function zen_get_products_description($product_id, $language_id)
function zen_get_products_model($products_id)
*/

function register_globals_vars_check_4 () {
  echo phpversion();
  echo '<br>register_globals = ', ini_get('register_globals'), '<br>';
  print "_GET: "; print_r($_GET); echo '<br />';
  print "_POST: "; print_r($_POST); echo '<br />';
  print "_FILES: "; print_r($_FILES); echo '<br />';
  print "_COOKIE: "; print_r($_COOKIE); echo '<br />';
  print "GLOBALS: "; print_r($GLOBALS); echo '<br />';
  print "_REQUEST: "; print_r($_REQUEST); echo '<br /><br />';
  global $HTTP_POST_FILES;
  print "HTTP_POST_FILES: "; print_r($HTTP_POST_FILES); echo '<br />';
}

// Added function to evaluate the presence of a field in the $filelayout variable
//   where $filelayout is intended to identify the fields in the file.
function ep4_field_in_file($field_name) {
  if (is_null($field_name)) {
    return false;
  }

  return array_key_exists('filelayout', $GLOBALS) &&
    array_key_exists($field_name, $GLOBALS['filelayout']) &&
    array_key_exists('items', $GLOBALS) &&
    array_key_exists($GLOBALS['filelayout'][$field_name], $GLOBALS['items']);
}

function ep4_post_sanitize($post_array) {

  $return_val = array();
  
  if (empty($post_array) || !is_array($post_array)) {
    return array();
  }
  
  if (!empty($_POST)) {
    $tempData = $_POST;
    unset($_POST);
  }

  $lang_vars = array('lid', 'code');
  foreach ($post_array as $post_key => $val) {
    if (array_key_exists('lang', $val)) {
      $vari = $val['lang']['var'];
      foreach ($lang_vars as $lang) {
        $var[$lang] = null;
        if (!array_key_exists($lang, $val['lang'])) {
          continue;
        }
        $data[$lang] = $val['lang'][$lang];
        ${$lang} = key($data[$lang]);
        $var[$lang] = key($data[$lang][${$lang}]);
        $vals[$lang] = $data[$lang][${$lang}][$var[$lang]];
      }

      if (array_key_exists('lid', $vals) && ep4_field_in_file($var['lid'])) {
        $_POST[$post_key][$lid] = ep_4_curly_quotes($vals['lid']);
      }
      if (array_key_exists('code', $vals) && ep4_field_in_file($var['code']) && (EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE == 'language_code' || EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE == 'language_code_only' || !ep4_field_in_file($var['lid']))) {
        $_POST[$post_key][$lid] = ep_4_curly_quotes($vals['code']);
      }
      
      continue;
    }
    
    foreach ($val as $field_title => $var) {
      $var_name = key($var);
      if (ep4_field_in_file($field_title)) {
        $_POST[$post_key] = ep_4_curly_quotes($var[$var_name]);
      }
    }
  }
  
  if (class_exists('AdminRequestSanitizer')) {
    $sanitizer = AdminRequestSanitizer::getInstance();
    $sanitizer->runSanitizers();
    unset($sanitizer);
  }
  
  foreach ($post_array as $post_key => $val) {
    if (array_key_exists('lang', $val)) {

      $vari = $val['lang']['var'];
      foreach ($lang_vars as $lang) {
        $data[$lang] = $val['lang'][$lang];
        ${$lang} = key($data[$lang]);
        $var[$lang] = key($data[$lang][${$lang}]);
        $vals[$lang] = $data[$lang][${$lang}][$var[$lang]];

        $return_val[key($vari)] = array_key_exists($post_key, $_POST) ? $_POST[$post_key] : null;
      }
      continue;
    }
  
    foreach ($val as $field_name => $var) {
      $var_name = key($var);
      if (ep4_field_in_file($field_name)) {
        $return_val[$var_name] = $_POST[$post_key];
      }
    }
  }
  
  unset($_POST);
  if (!empty($tempData)) {
    $_POST = $tempData;
    unset($tempData);
  }
  
  return $return_val;
}

/**
 *
 * Expects $max_len[$field_name . '_found'] to exist as well as $max_len[$field_name]
 * Expects this language constant to exist: 'EASYPOPULATE_4_DISPLAY_RESULT_' . strtoupper($field_name) . '_LONG'
 **/
function ep_4_extend_field($field_data, &$max_len, $field_name, $default_val, $tables = TABLE_PRODUCTS) {
  global $ep_4_strlen, $chosen_key;

  // check size of field, loop on error
  $field_len = $ep_4_strlen($field_data);
  if ($field_len <= $max_len[$field_name]) {
    // Have previously found the longest allowable length for field.
    unset($field_len);
    return false;
  }

  $display_output .= sprintf(constant('EASYPOPULATE_4_DISPLAY_RESULT_' . strtoupper($field_name) . '_LONG'), ${$chosen_key}, $max_len[$field_name], $chosen_key);
  
  if (!empty($max_len[$field_name . '_found']) && $max_len[$field_name . '_found'] >= $field_len) {
    // Longest length for this field has already been recorded and the longest found is still longer than current.
    unset($field_len);
    return false;
  }

  $max_len[$field_name . '_found'] = $field_len;
  if (EASYPOPULATE_4_CONFIG_AUTO_EXTEND_FIELD === 'false') {
    // Fields are not permitted to be extended and provided data won't fit, need to skip trying to enter/alter this data.
    return 'continue'; // short-circuit on error
  }

  $prevent_null = ' NOT NULL';
  if (is_null($default_val)) {
    $prevent_null = ' ';
    $default_val = 'null';
  }
  
  if ($default_val === '') {
    $default_val = "\'\'";
  }
  
  foreach ($tables as $table) {
    $update_field_name_sql = "ALTER TABLE " . $table . " CHANGE " . $field_name . " " . $field_name . " VARCHAR(" . (int)$field_len . ")" . $prevent_null . " DEFAULT " . $default_val . ";";
    $update_field_name = $db->Execute($update_field_name_sql);
    unset($update_field_name_sql);

    zen_record_admin_activity('Extended table ' . $table . ' field ' . zen_db_input($field_name) . ' via EP4 from ' . zen_db_input($max_len[$field_name]) . ' to ' . zen_db_input((int)$field_len) . '.', 'info');
  }

  $max_len[$field_name] = $field_len;

  unset($update_field_name);
  unset($field_len);

  return true;
}

function ep_4_chosen_key_sub($sql, $value) {
  global $db, $chosen_key, $zc_support_ignore_null;

  if (in_array($chosen_key, array('v_products_id', 'v_products_model'))) {
    return $sql;
  }
  
  $chosen_key_sub = $chosen_key;
  if (strpos($chosen_key_sub, 'v_') === 0) {
    $chosen_key_sub = substr($chosen_key_sub, 2);
  }
  $sql = $db->bindVars($sql, ':' . $chosen_key_sub . ':', $value, $zc_support_ignore_null);

  return $sql;
}

function ep4_flush()
{
  print(str_repeat(" ", 300));
  flush();
}

if (!defined('TOPMOST_CATEGORY_PARENT_ID')) {
  define('TOPMOST_CATEGORY_PARENT_ID', '0');
}

/**
 * Get all products_id in a Category and its SubCategories
 * use as:
 * $my_products_id_list = array();
 * $my_products_id_list = ep4_get_categories_products_list($categories_id)
 * @param int $categories_id
 * @param bool $include_deactivated
 * @param bool $include_child
 * @param string $parent_category
 * @param string $display_limit
 * @return array|null
 */
function ep4_get_categories_products_list($categories_id, $include_deactivated = false, $include_child = true, $parent_category = TOPMOST_CATEGORY_PARENT_ID, $display_limit = '')
{
    global $db, $categories_products_id_list;

    if (!empty($display_limit)) {
        $display_limit = $db->prepare_input($display_limit);
    }

    if (!isset($categories_products_id_list) || !is_array($categories_products_id_list)) {
        $categories_products_id_list = array();
    }

    $childCatID = str_replace('_', '', substr($categories_id, strrpos($categories_id, '_')));

    $current_cPath = ($parent_category != TOPMOST_CATEGORY_PARENT_ID ? $parent_category . '_' : '') . $categories_id;

    $sql = "SELECT p.products_id
            FROM " . TABLE_PRODUCTS . " p
            INNER JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c USING (products_id)
            WHERE p2c.categories_id = " . (int)$childCatID .
        (!$include_deactivated ? " AND p.products_status = 1" : '') .
        $display_limit;

    $results = $db->Execute($sql);
    while (!$results->EOF) {
        $categories_products_id_list[$results->fields['products_id']] = $current_cPath;
        $results->MoveNext();
    }

    if ($include_child) {
        $sql = "SELECT categories_id
                FROM " . TABLE_CATEGORIES . "
                WHERE parent_id = " . (int)$childCatID;

        $results = $db->Execute($sql);
        while (!$results->EOF) {
            ep4_get_categories_products_list($results->fields['categories_id'], $include_deactivated, $include_child, $current_cPath, $display_limit);
            $results->MoveNext();
        }
    }
    return $categories_products_id_list;
}

function ep4_get_category_tree($parent_id = '0', $spacing = '', $exclude = '', $category_tree_array = array(), $include_itself = false, $category_has_products = false, $limit = false, $withProd = true, $withAttribs = false, $showOnly = false) {
    global $db, $categories_products_id_list;

    $limit_count = '';
    if ($limit) {
      $limit_count = " limit 1";
    }

    if (!is_array($category_tree_array)) {
      $category_tree_array = array();
    }
    if (empty($category_tree_array) && ($exclude != '0') ) {
      $category_tree_array[] = array('id' => '0', 'text' => TEXT_TOP);
    }

    if ($include_itself) {
      $category = $db->Execute("SELECT cd.categories_name
                                FROM " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                WHERE cd.language_id = " . (int)$_SESSION['languages_id'] . "
                                AND cd.categories_id = " . (int)$parent_id);

      if (!$category->EOF) {
        $category_tree_array[] = array('id' => $parent_id, 'text' => $category->fields['categories_name']);
      }
    }

    $categories = $db->Execute("SELECT c.categories_id, cd.categories_name, c.parent_id
                                FROM " . TABLE_CATEGORIES . " c
                                INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON (c.categories_id = cd.categories_id)
                                WHERE cd.language_id = " . (int)$_SESSION['languages_id'] . "
                                AND c.parent_id = " . (int)$parent_id . "
                                ORDER BY c.sort_order, cd.categories_name");

    $temp = $categories_products_id_list;
    while (!$categories->EOF) {
      if ($category_has_products == true and zen_products_in_category_count($categories->fields['categories_id'], '', false, true) >= 1) {
        $mark = '*';
        if ($withAttribs) {
          $categories_products_id_list = array();
          $products = ep4_get_categories_products_list($categories->fields['categories_id'], true);
          foreach ($products as $products_id => $cat) {
            if (!zen_has_product_attributes($products_id)) {
              continue;
            }
            if (!$withProd) {
              $mark = '';
            }
            $mark .= '-a';
            break;
          }
        }
      } else {
        $mark = '&nbsp;&nbsp;' . ($withProd ? '&nbsp;' : '');
      }
      if ($exclude != $categories->fields['categories_id']) {
        $category_tree_array[] = array('id' => $categories->fields['categories_id'], 'text' => $spacing . $categories->fields['categories_name'] . $mark);
      }
      $category_tree_array = ep4_get_category_tree($categories->fields['categories_id'], $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array, '', $category_has_products, false, $withProd, $withAttribs, $showOnly);
      $categories->MoveNext();
    }

    $categories_products_id_list = $temp;
    unset($temp);
    return $category_tree_array;
  }


function ep4_draw_cat_list($current_cat_id, $withAttribs = true, $withProd = true, $showOnly = false) {
    if (!$withAttribs && $withProd && !$showOnly) {
        return zen_get_category_tree('', '', '0', '', '', true);
    }
    return '';
}


function ep4_get_product_type_handle($str_handle) {
  global $db;
// This function will change the text handle to a numeric one for insert.
// Will return 1 by default if it fails to find the type handler

  $sql = "SELECT type_id FROM " . TABLE_PRODUCT_TYPES . " WHERE type_handler = :type_handler:";
  $sql = $db->bindVars($sql, ':type_handler:', $str_handle , 'string');
  $handler = $db->Execute($sql);

return $handler->fields['type_id'] ?? 1;
}
<?php

/*
 * This is the file processed if the import file is a categorymeta-ep file.
 * $Id: includes\modules\easypopulate_4_import_categorymeta_ep.php, v4.0.35.ZC.2 10-03-2016 mc12345678 $
 */

      while ($items = fgetcsv($handle, 0, $csv_delimiter, $csv_enclosure)) { // read 1 line of data

        @set_time_limit($ep_execution);

        if (!ep4_field_in_file('v_categories_id')) {
          $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_ID_NOT_FOUND, '0');
          $ep_error_count++;
          ep4_flush();
          break;
        } // if category found

        // $items[$filelayout['v_categories_id']];
        // $items[$filelayout['v_categories_image']];
        $sql = 'SELECT categories_id FROM ' . TABLE_CATEGORIES . ' WHERE (categories_id = :categories_id:) LIMIT 1';
        $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
        $result = ep_4_query($sql);
        
        $row = $ep_4_fetch_array($result);
        
        if (!$row) {
          $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_ID_NOT_FOUND, $items[$filelayout['v_categories_id']]);
          $ep_error_count++;
          ep4_flush();
          continue;
        }
        
//        if ($row = ($ep_uses_mysqli ? mysqli_fetch_array($result) : mysql_fetch_array($result))) {
          // UPDATE
          $sql = "UPDATE " . TABLE_CATEGORIES . " SET
            categories_image = :categories_image:,
          last_modified    = CURRENT_TIMESTAMP " . (array_key_exists('v_sort_order', $filelayout) ? ", sort_order = :sort_order:" : "" ) . "
            WHERE (categories_id = :categories_id:)";
          if (ep4_field_in_file('v_categories_image') || ep4_field_in_file('v_sort_order')) {
            if (ep4_field_in_file('v_categories_image')) {
              $sql = $db->bindVars($sql, ':categories_image:', $items[$filelayout['v_categories_image']], 'string');
            }
            if (ep4_field_in_file('v_sort_order')) {
              $sql = $db->bindVars($sql, ':sort_order:', $items[$filelayout['v_sort_order']], 'integer');
            }
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $result = ep_4_query($sql);
            
            if ($result) {
              zen_record_admin_activity('Updated category ' . (int) $items[$filelayout['v_categories_id']] . ' via EP4.', 'info');
            }
          }
          foreach ($langcode as $key => $lang) {
            $lid = $lang['id'];
            $lid_code = $lang['code'];
            // $items[$filelayout['v_categories_name_'.$lid]];
            // $items[$filelayout['v_categories_description_'.$lid]];
            // @TODO: Simplify this evaluation.
            if (!isset($filelayout['v_categories_name_' . $lid]) && !isset($filelayout['v_categories_description_' . $lid]) && !isset($filelayout['v_categories_name_' . $lid_code]) && !isset($filelayout['v_categories_description_' . $lid_code]) &&
              !isset($filelayout['v_metatags_title_' . $lid]) && !isset($filelayout['v_metatags_keywords_' . $lid]) && !isset($filelayout['v_metatags_description_' . $lid]) && !isset($filelayout['v_metatags_title_' . $lid_code]) && !isset($filelayout['v_metatags_keywords_' . $lid_code]) && !isset($filelayout['v_metatags_description_' . $lid_code])) {

              $ep_warning_count++;
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NO_CATEGORY_UPDATE, $items[$filelayout['v_categories_id']]);
              unset($lid);
              unset($lid_code);
              continue;
            }

            if ((isset($filelayout['v_categories_name_' . $lid]) || isset($filelayout['v_categories_description_' . $lid])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || (isset($filelayout['v_categories_name_' . $lid_code]) || isset($filelayout['v_categories_description_' . $lid_code])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
              $sql = "UPDATE " . TABLE_CATEGORIES_DESCRIPTION . " SET ";
              $update_count = false;
              if (isset($filelayout['v_categories_name_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_categories_name_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql .= "categories_name        = :categories_name:";
                $update_count = true;
              }
              if (isset($filelayout['v_categories_description_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_categories_description_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql .= ($update_count ? ", " : "") . "categories_description = :categories_description: ";
                $update_count = true;
              }
              $sql .= "
                WHERE
                (categories_id = :categories_id: AND language_id = :language_id:)";

              // Need to admin sanitize the categories_name and categories_description and handle both languages id and  code.

              $post_array = array(
                'categories_name' => array(
                  'lang' => array(
                    'lid' => array($lid => array('v_categories_name_' . $lid => ep4_field_in_file('v_categories_name_' . $lid) ? $items[$filelayout['v_categories_name_' . $lid]] : ''),),
                    'code' => array($lid_code => array('v_categories_name_' . $lid_code => ep4_field_in_file('v_categories_name_' . $lid_code) ? $items[$filelayout['v_categories_name_' . $lid_code]] : ''),),
                    'var' => array('thiscategorymetaname' => $items[$filelayout['v_categories_id']]), //compact('v_categories_name'),
                  ),
                ),
                'categories_description' => array(
                  'lang' => array(
                    'lid' => array($lid => array('v_categories_description_' . $lid => ep4_field_in_file('v_categories_description_' . $lid) ? $items[$filelayout['v_categories_description_' . $lid]] : ''),),
                    'code' => array($lid_code => array('v_categories_description_' . $lid_code => ep4_field_in_file('v_categories_description_' . $lid_code) ? $items[$filelayout['v_categories_description_' . $lid_code]] : ''),),
                    'var' => array('thiscategorymetadescription' => $items[$filelayout['v_categories_id']]), //compact('v_categories_description'),
                  ),
                ),
              );

              $data_array = ep4_post_sanitize($post_array);
      
              extract($data_array, EXTR_OVERWRITE);
              
              $sql = $db->bindVars($sql, ':categories_name:', $thiscategorymetaname[$lid], 'string');
              $sql = $db->bindVars($sql, ':categories_description:', $thiscategorymetadescription[$lid], 'string');
              $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
              $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
              $result = ep_4_query($sql);
              if ($result) {
                zen_record_admin_activity('Updated category description ' . (int) $items[$filelayout['v_categories_id']] . ' via EP4.', 'info');
              }
            }
            // $items[$filelayout['v_metatags_title_'.$lid]];
            // $items[$filelayout['v_metatags_keywords_'.$lid]];
            // $items[$filelayout['v_metatags_description_'.$lid]];
            // Categories Meta Start
            $sql = "SELECT categories_id FROM " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " WHERE
              (categories_id = :categories_id: AND language_id = :language_id:) LIMIT 1";
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            $result = ep_4_query($sql);
            if ($row = $ep_4_fetch_array($result)) {
              // UPDATE
              if ((isset($filelayout['v_metatags_title_' . $lid]) || isset($filelayout['v_metatags_keywords_' . $lid]) || isset($filelayout['v_metatags_description_' . $lid])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || (isset($filelayout['v_metatags_title_' . $lid_code]) || isset($filelayout['v_metatags_keywords_' . $lid_code]) || isset($filelayout['v_metatags_description_' . $lid_code])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql = "UPDATE " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " SET ";
                  $update_count = false;
                  if (isset($filelayout['v_metatags_title_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_title_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                    $sql .= "metatags_title    = :metatags_title: ";
                    $update_count = true;
                  }
                  if (isset($filelayout['v_metatags_keywords_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_keywords_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                    $sql .= ($update_count ? ", " : "") . "metatags_keywords   = :metatags_keywords:";
                    $update_count = true;
                  }
                  if (isset($filelayout['v_metatags_description_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_description_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                    $sql .= ($update_count ? ", " : "") . "metatags_description = :metatags_description:";
                    $update_count = true;
                  }
                $sql .= "
                WHERE
                (categories_id = :categories_id: AND language_id = :language_id:)";
              }
            } else {
              // NEW - this should not happen
              $sql = "INSERT INTO " . TABLE_METATAGS_CATEGORIES_DESCRIPTION . " SET ";
              $update_count = false;
              if (isset($filelayout['v_metatags_title_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_title_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql .= "metatags_title    = :metatags_title:";
                $update_count = true;
              }
              if (isset($filelayout['v_metatags_keywords_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_keywords_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql .= ($update_count ? ", " : "") . "metatags_keywords   = :metatags_keywords:";
                $update_count = true;
              }
              if (isset($filelayout['v_metatags_description_' . $lid]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || isset($filelayout['v_metatags_description_' . $lid_code]) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
                $sql .= ($update_count ? ", " : "") . "metatags_description = :metatags_description:";
                $update_count = true;
              }
              $sql .= ",
                ";
              $sql .=
                "categories_id    = :categories_id:,
                language_id      = :language_id:";
            }
            
            // Nothing to update, nothing to insert. If counting lines processed, need to increment. @TODO
            if (stripos($sql, 'SELECT') === 0) {
              $ep_warning_count++;
              $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_NO_CATEGORY_UPDATE, $items[$filelayout['v_categories_id']]);
              unset($sql);
              unset($lid);
              unset($lid_code);
              continue;
            }
            // Need to admin sanitize the categories_name and categories_description and handle both languages id and  code.
              $post_array = array(
                'metatags_title' => array(
                  'lang' => array(
                    'lid' => array($lid => array('v_metatags_title_' . $lid => ep4_field_in_file('v_metatags_title_' . $lid) ? $items[$filelayout['v_metatags_title_' . $lid]] : ''),),
                    'code' => array($lid_code => array('v_metatags_title_' . $lid_code => ep4_field_in_file('v_metatags_title_' . $lid_code) ? $items[$filelayout['v_metatags_title_' . $lid_code]] : ''),),
                    'var' => array('thiscategorymetatagstitle' => $items[$filelayout['v_categories_id']]), //compact('v_categories_name'),
                  ),
                ),
                'metatags_keywords' => array(
                  'lang' => array(
                    'lid' => array($lid => array('v_metatags_keywords_' . $lid => ep4_field_in_file('v_metatags_keywords_' . $lid) ? $items[$filelayout['v_metatags_keywords_' . $lid]] : ''),),
                    'code' => array($lid_code => array('v_metatags_keywords_' . $lid_code => ep4_field_in_file('v_metatags_keywords_' . $lid_code) ? $items[$filelayout['v_metatags_keywords_' . $lid_code]] : ''),),
                    'var' => array('thiscategorymetatagskeywords' => $items[$filelayout['v_categories_id']]), //compact('v_categories_description'),
                  ),
                ),
                'metatags_description' => array(
                  'lang' => array(
                    'lid' => array($lid => array('v_metatags_description_' . $lid => ep4_field_in_file('v_metatags_description_' . $lid) ? $items[$filelayout['v_metatags_description_' . $lid]] : ''),),
                    'code' => array($lid_code => array('v_metatags_description_' . $lid_code => ep4_field_in_file('v_metatags_description_' . $lid_code) ? $items[$filelayout['v_metatags_description_' . $lid_code]] : ''),),
                    'var' => array('thiscategorymetatagsdescription' => $items[$filelayout['v_categories_id']]), //compact('v_categories_description'),
                  ),
                ),
              );
              
              $data_array = ep4_post_sanitize($post_array);
              
              extract($data_array, EXTR_OVERWRITE);
            
            $sql = $db->bindVars($sql, ':metatags_title:', $thiscategorymetatagstitle[$lid], 'string');
            $sql = $db->bindVars($sql, ':metatags_keywords:', $thiscategorymetatagskeywords[$lid], 'string');
            $sql = $db->bindVars($sql, ':metatags_description:', $thiscategorymetatagsdescription[$lid], 'string');
            $sql = $db->bindVars($sql, ':categories_id:', $items[$filelayout['v_categories_id']], 'integer');
            $sql = $db->bindVars($sql, ':language_id:', $lid, 'integer');
            if (!$row || (isset($filelayout['v_metatags_title_' . $lid]) || isset($filelayout['v_metatags_keywords_' . $lid]) || isset($filelayout['v_metatags_description_' . $lid])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_code_only' || (isset($filelayout['v_metatags_title_' . $lid_code]) || isset($filelayout['v_metatags_keywords_' . $lid_code]) || isset($filelayout['v_metatags_description_' . $lid_code])) && EASYPOPULATE_4_CONFIG_IMPORT_OVERRIDE != 'language_id_only') {
              $resultcatmeta = ep_4_query($sql);
            }
            if (!empty($resultcatmeta)) {
              zen_record_admin_activity('Inserted/Updated category metatag information ' . (int) $items[(int) $filelayout['v_categories_id']] . ' via EP4.', 'info');
            }
            unset($data_array);
            unset($lid);
            unset($lid_code);
            unset($resultcatmeta);
            $ep_update_count++;
          }
/*        } else { // error Category ID not Found
          $display_output .= sprintf(EASYPOPULATE_4_DISPLAY_RESULT_CATEGORY_ID_NOT_FOUND, $items[$filelayout['v_categories_id']]);
          $ep_error_count++;
        } // if category found*/
        ep4_flush();
      } // while

<?php
/**
 * easypopulate_4_attributes
 *
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: mc12345678 2021 Jan 09 New for ZC v1.5.7 $
 */
?>
<script type="text/javascript">

let orig = null;
function doCollectEP4SelectData()
{
   let str = $('form[name="custom"]').serializeArray();

   zcJS.ajax({
    url: "ajax.php?act=ajaxEasyPopulateV4&method=updateDrop",
    data: str
  }).done(function( response ) {
    let ep_category_filter = $('select[name="ep_category_filter"]');
    if (ep_category_filter.length == 0) {
      return false;
    }
    if (response === false) {
      let oldVal = ep_category_filter.val();
      if (orig != null)
      {
          ep_category_filter.html(orig);
      }
      ep_category_filter.val(oldVal);
      if (window.console) {
        if (typeof(console.log) == 'function') {
          console.log("false path");
        }
      }
    } else {
      if (orig == null) {
        orig = ep_category_filter.html();
      }
      ep_category_filter.html(response.down_filter);
      if (window.console) {
        if (typeof(console.log) == 'function') {
          console.log("other path");
        }
      }
    }
 }).fail(function (e) {
    let ep_category_filter = $('select[name="ep_category_filter"]');
    if (ep_category_filter.length == 0) {
      return false;
    }

    let oldVal = ep_category_filter.val();
    if (orig != null)
    {
      ep_category_filter.html(orig);
    }
    ep_category_filter.val(oldVal);
    if (window.console) {
      if (typeof(console.log) == 'function') {
        console.log("fail path");
      }
    }
 }
 );
}

$(document).ready(function(){
  console.log('ready');
  $('select[name="ep_export_type"], select[name="ep_manufacturer_filter"], select[name="ep_status_filter"]').change(function() {
    doCollectEP4SelectData();
 });
});

</script>

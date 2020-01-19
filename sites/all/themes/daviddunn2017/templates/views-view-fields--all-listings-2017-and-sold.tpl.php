<?php

/**
 * @file
 * Default simple view template to all the fields as a row.
 *
 * - $view: The view in use.
 * - $fields: an array of $field objects. Each one contains:
 *   - $field->content: The output of the field.
 *   - $field->raw: The raw data for the field, if it exists. This is NOT output safe.
 *   - $field->class: The safe class id to use.
 *   - $field->handler: The Views field handler object controlling this field. Do not use
 *     var_export to dump this object, as it can't handle the recursion.
 *   - $field->inline: Whether or not the field should be inline.
 *   - $field->inline_html: either div or span based on the above flag.
 *   - $field->wrapper_prefix: A complete wrapper containing the inline_html to use.
 *   - $field->wrapper_suffix: The closing tag for the wrapper.
 *   - $field->separator: an optional separator that may appear before a field.
 *   - $field->label: The wrap label text to use.
 *   - $field->label_html: The full HTML of the label to use including
 *     configured element type.
 * - $row: The raw result object from the query, with all data it fetched.
 *
 * @ingroup views_templates
 */
?> 



<?php 
  /* IDs */
  $id = $row->_field_data["id"]["entity"]->id;
  $first = $row->_field_data["id"]["entity"]->field_first_name[LANGUAGE_NONE][0]["value"];
  /* Dates */
  $solddate = $row->drealty_listing_inactive_date; 
  $unix = $solddate ;
  $solddate  = gmdate("M Y", $unix);
  $today = time();
  $four_months = $unix + (120 * 24 * 60 * 60);// Four months from sold date
  $unix_date = $unix;
  
  /* Display Boolean */
  $display =  $row->_field_data["id"]["entity"]->field_display[LANGUAGE_NONE][0]["value"];
  
  /* Water Front */
  $waterfront =  $row->_field_data["id"]["entity"]->field_lfd_waterfrontage_18[LANGUAGE_NONE][0]["value"];
  
  /* Status */
  $status = $row->_field_data["id"]["entity"]->field_l_status[LANGUAGE_NONE][0]["value"];
  
  /* DRealty status | 0 = inactive 1 = active */
  $dReatly_status = $row->_field_data["id"]["entity"]->active;
  
  if ($dReatly_status == 0 && $status == "ACTIVE") { $display = 0; }
  
  if ($display == 1){
  
  if ($waterfront == "Yes" && $dReatly_status != 0) { $status = "<span class=\"exclusive\">waterfront!</span>";}
  if ($status == "Exclusive" && $waterfront == "Yes") { $status = "<span class=\"exclusive\">waterfront exclusive!</span>";}
  
  if ($status == "Conditional Sale" && $dReatly_status == 1){ $status = "<span class=\"exclusive\">conditional sale!</span>"; }
  if ($status == "Conditional Sale" && $dReatly_status == 0){ $status = "<span class=\"exclusive\">sold!</span>"; }
  if ($status == "CONDITIONAL SALE" && $dReatly_status == 1){ $status = "<span class=\"exclusive\">conditional sale!</span>"; }
  if ($status == "CONDITIONAL SALE" && $dReatly_status == 0){ $status = "<span class=\"exclusive\">sold!</span>"; }
  if ($status == "Sold / Leased")   { $status = "<span class=\"exclusive\">sold!</span>";}  
  if ($status == "SOLD")            { $status = "<span class=\"exclusive\">sold!</span>";}
  if ($status == "Exclusive")       { $status = "<span class=\"exclusive\">exclusive!</span>";}
  if ($status == "ACTIVE" || $status =="Active")          { $status = "";}
  

  // if ($status == "SOLD" && $waterfront == "Yes") { $status = "<span class=\"exclusive\">sold!</span>";}
  // if ($status == "Conditional Sale" && $dReatly_status == 0 && $waterfront == "Yes"){ $status = "<span class=\"exclusive\">sold!</span>"; }
  // if ($status == "Conditional Sale" && $dReatly_status == 1 && $waterfront == "Yes"){ $status = "<span class=\"exclusive\">conditional sale!</span>"; }
  // if ($status == "CONDITIONAL SALE" && $dReatly_status == 0 && $waterfront == "Yes"){ $status = "<span class=\"exclusive\">sold!</span>"; }
  // if ($status == "CONDITIONAL SALE" && $dReatly_status == 1 && $waterfront == "Yes"){ $status = "<span class=\"exclusive\">conditional sale!</span>"; }
  
  /* Exclusive status */
  $exclusive = NULL;
  if (isset($row->_field_data["id"]["entity"]->field_exclusive[LANGUAGE_NONE])){
    $field_exclusive = $row->_field_data["id"]["entity"]->field_exclusive[LANGUAGE_NONE][0]["value"];
    if ($field_exclusive == 1) {$exclusive = "<span class=\"exclusive\">exclusive!</span>";}
  }
  
  /* Image set up */
  if (isset($row->_field_data["id"]["entity"]->field_listing_image[LANGUAGE_NONE])){
    $path = $row->_field_data["id"]["entity"]->field_listing_image[LANGUAGE_NONE][0]['uri'];
    $image = theme('image_style', array('path' => $path, 'style_name' => 'standard_listing_small_size'));
    if ( $dReatly_status == 1){
      $listing_image = "<a href=\"/properties/".$first."/".$id."/\">".$image."</a>";
      
    } else {
      $listing_image = $image;
    }
  } else {
    $listing_image = null;
  }
  
  $city = $row->_field_data["id"]["entity"]->field_l_city[LANGUAGE_NONE][0]['value'];
  $address =  $row->_field_data["id"]["entity"]->field_l_address[LANGUAGE_NONE][0]['value'];
  if ( $dReatly_status == 1){
    $address = "<a href=\"/properties/".$first."/".$id."/\"><h3>".$address.", ".$city."</h3></a>";
    $more = "<a href=\"/properties/".$first."/".$id."/\" class=\"btn btn-red\">More</a>";
  } else {
    $address = "<h3>".$address.", ".$city."</h3>";
    $more = "<span class=\"sold\">As of ".$solddate."</span>";
  }
  
  $price = null;
  $price = $row->_field_data["id"]["entity"]->field_listing_price;
  if ($price != null){
    $price = number_format($row->_field_data["id"]["entity"]->field_listing_price[LANGUAGE_NONE][0]['value']);
    $price = "<h4 class=\"price\">$".$price."</h4>";
  } else {
    $price = "<h4 class=\"price\">Please Call</h4>";
  }
  
  $remarks = $row->_field_data["id"]["entity"]->field_remarks11[LANGUAGE_NONE][0]['value'];
  $remarks = (strlen($remarks) > 140) ? substr($remarks,0,140).'...' : $remarks;
  $field_l_type_ = $row->_field_data["id"]["entity"]->field_l_type_[LANGUAGE_NONE][0]['value'];
  
  if (isset($row->_field_data["id"]["entity"]->field_listing_mls[LANGUAGE_NONE])){
    $mls = $row->_field_data["id"]["entity"]->field_listing_mls[LANGUAGE_NONE][0]['value'];
  } else {
    $mls = "Unlisted";
  }
  
  if (isset($row->_field_data["id"]["entity"]->field_listing_bedrooms[LANGUAGE_NONE])){
    $bedrooms = $row->_field_data["id"]["entity"]->field_listing_bedrooms[LANGUAGE_NONE][0]['value'];
  } else {
    $bedrooms = "--";
  }
  
  if (isset($row->_field_data["id"]["entity"]->field_listing_full_bathrooms[LANGUAGE_NONE])){
    $full_bath = $row->_field_data["id"]["entity"]->field_listing_full_bathrooms[LANGUAGE_NONE][0]['value'];
  } else {
    $full_bath = "--";
  }
  
  if (isset($row->_field_data["id"]["entity"]->field_listing_partial_bathooms[LANGUAGE_NONE])){
    $half_bath  = $row->_field_data["id"]["entity"]->field_listing_partial_bathooms[LANGUAGE_NONE][0]['value'];
    $full_bath = $full_bath."/".$half_bath;
  }

?>
<div class="row" style="margin-bottom:20px;">
  <div class="col-sm-3">
     <div class="property-image">
        <?php echo $status;?>
        <?php echo $listing_image;?>
     </div>
  </div>
  <div class="col-sm-5">
     <?php echo $address; ?>
     <?php echo $price; ?>
     <?php echo $remarks; ?>

  </div>
  <div class="col-sm-4">
     <div class="row">
        <div class="col-xs-6">
           <b>Type:</b>
        </div>
        <div class="col-xs-6">
           <?php echo $field_l_type_; ?>
        </div>
        <div class="col-xs-6">
           <b>Bedrooms:</b>
        </div>
        <div class="col-xs-6">
           <?php echo $bedrooms; ?>
        </div>

        <div class="col-xs-6">
          <b>Bathrooms:</b>
        </div>
        <div class="col-xs-6">
           <?php echo $full_bath; ?>
        </div>
        <div class="col-xs-6">
          <b>MLS</b>
        </div>
        <div class="col-xs-6">
           <?php echo $mls; ?>
        </div>
        <div class="col-xs-12">
          <?php echo $more; ?>
      </div>
     </div>
  </div>
</div>
<?php   } ?>

<?php

/**
 * @file
 * This template is used to print a single field in a view.
 *
 * It is not actually used in default Views, as this is registered as a theme
 * function which has better performance. For single overrides, the template is
 * perfectly okay.
 *
 * Variables available:
 * - $view: The view object
 * - $field: The field handler object that can process the input
 * - $row: The raw SQL result that can be used
 * - $output: The processed output that will normally be used.
 *
 * When fetching output from the $row, this construct should be used:
 * $data = $row->{$field->field_alias}
 *
 * The above will guarantee that you'll always get the correct data,
 * regardless of any changes in the aliasing that might happen if
 * the view is modified.
 */
 
 /*
 Change the status.  Use If statements. CONDITIONAL SALE, Sold / Leased, SOLD will all display as SOLD.  ACTIVE will not show.
 
 Date.
 Need 3 dates. inactive_date, todays date, inactive_date + 90.
*/
?>

<?php #print $output; ?>
<?php #$data = $row->{$field->inactive-date}; 

?>
<?php 
$unix = $output;
$output = gmdate("F j, Y", $unix);
?>
<?php print $output; ?>
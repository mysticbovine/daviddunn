<?php
/**
 * @file
 * Plugin to provide a google geocoder.
 */

/**
 * Plugins are described by creating a $plugin array which will be used
 * by the system that includes this file.
 */
$plugin = array(
  'title' => t("drealty Google Geocoder"),
  'description' => t('Geocodes via google geocoder'),
  'callback' => 'drealty_geocoder_google',
  'field_types' => array('text', 'text_long', 'addressfield', 'text_with_summary', 'computed', 'taxonomy_term_reference'),
  'field_callback' => 'drealty_geocoder_google_field',
  'settings_callback' => 'drealty_geocoder_google_form',
  'terms_of_service' => 'http://code.google.com/apis/maps/documentation/geocoding/#Limits',
);

/**
 * Process Markup.
 */
function drealty_geocoder_google($address, $options = array()) {
  geophp_load();

  $query = array(
    'address' => $address,
    'sensor' => 'false'
  );

  $url = url("http://maps.googleapis.com/maps/api/geocode/json", array('query' => $query));
  $result = drupal_http_request($url);

  if (isset($result->error)) {
    drupal_set_message(t('Error geocoding - @code @error', array('@code' => $result->code, '@error' => $result->error)), 'error');
    return FALSE;
  }

  $data = json_decode($result->data);

  if ($data->status == 'OK') {
    $geometries = array();

    foreach ($data->results as $item) {
      // Check if we should reject these results
      if (isset($options['reject_results'])) {
        if (in_array($item->geometry->location_type, $options['reject_results'], TRUE)) {
          continue;
        }
      }

      // Construct a geoPHP Geometry depending on what type of geometry we want returned (defaults to point)
      if (!isset($options['geometry_type']) || $options['geometry_type'] == 'point') {
        $geom = new Point($item->geometry->location->lng, $item->geometry->location->lat);
      } elseif ($options['geometry_type'] == 'bounds') {
        $points = array(
          new Point($item->geometry->bounds->southwest->lng, $item->geometry->bounds->southwest->lat),
          new Point($item->geometry->bounds->southwest->lng, $item->geometry->bounds->northeast->lat),
          new Point($item->geometry->bounds->northeast->lng, $item->geometry->bounds->northeast->lat),
          new Point($item->geometry->bounds->northeast->lng, $item->geometry->bounds->southwest->lat),
          new Point($item->geometry->bounds->southwest->lng, $item->geometry->bounds->southwest->lat),
        );
        $geom = new Polygon(array(new LineString($points)));
      } elseif ($options['geometry_type'] == 'viewport') {
        $points = array(
          new Point($item->geometry->viewport->southwest->lng, $item->geometry->viewport->southwest->lat),
          new Point($item->geometry->viewport->southwest->lng, $item->geometry->viewport->northeast->lat),
          new Point($item->geometry->viewport->northeast->lng, $item->geometry->viewport->northeast->lat),
          new Point($item->geometry->viewport->northeast->lng, $item->geometry->viewport->southwest->lat),
          new Point($item->geometry->viewport->southwest->lng, $item->geometry->viewport->southwest->lat),
        );
        $geom = new Polygon(array(new LineString($points)));
      }

      // Add additional metadata to the geometry - it might be useful!
      $geom->data = array();
      $geom->data['geocoder_accuracy'] = $item->geometry->location_type;
      $geom->data['geocoder_formatted_address'] = $item->formatted_address;
      $geom->data['geocoder_address_components'] = $item->address_components;

      $geometries[] = $geom;
    }

    if (empty($geometries))
      return;

    // Check if we should return all results as a compound geometry  
    if (isset($options['all_results'])) {
      if ($options['all_results']) {
        return geoPHP::geometryReduce($geometries);
      }
    }
    // The connonical geometry is the first result (best guesse)
    $geometry = array_shift($geometries);

    // If there are any other geometries, these are auxiliary geometries that represent "alternatives"
    if (count($geometries))
      $geometry->data['geocoder_alternatives'] = $geometries;

    return $geometry;
  }
  else {
    drupal_set_message(t('Error geocoding - @status', array('@status' => $data->status)), 'error');
    return FALSE;
  }
}

function drealty_geocoder_google_field($field, $field_item, $options = array()) {
  if ($field['type'] == 'text' || $field['type'] == 'text_long' || $field['type'] == 'text_with_summary' || $field['type'] == 'computed') {
    return geocoder_google($field_item['value'], $options);
  }
  if ($field['type'] == 'addressfield') {
    $address = geocoder_widget_parse_addressfield($field_item);
    return geocoder_google($address, $options);
  }
  if ($field['type'] == 'taxonomy_term_reference') {
    if(isset($field_item['tid']) && $field_item['tid'] && !empty($field_item['tid'])) {
      $term = taxonomy_term_load($field_item['tid']);
      if($term)
        return geocoder_google($term->name);
    }
  }
}

function drealty_geocoder_google_form($default_values = array()) {
  $form = array();

  $form['geometry_type'] = array(
    '#type' => 'select',
    '#title' => 'Geometry Type',
    '#options' => array(
      'point' => 'Point (default)',
      'bounds' => 'Bounding Box',
      'viewport' => 'Viewport',
    ),
    '#default_value' => isset($default_values['geometry_type']) ? $default_values['geometry_type'] : 'point',
  );

  $form['all_results'] = array(
    '#type' => 'checkbox',
    '#title' => 'Geocode all alternative results',
    '#default_value' => isset($default_values['all_values']) ? $default_values['all_values'] : FALSE,
    '#description' => 'Often an ambiguous address (such as "Springfield USA") can result in multiple hits. By default we only return the first (best guess) result. Check this to return all results as a Multi-Geometry (MultiPoint or MultiPolygon).',
  );

  $form['reject_results'] = array(
    '#type' => 'checkboxes',
    '#title' => 'Reject Results',
    '#options' => array(
      'APPROXIMATE' => 'APPROXIMATE:  indicates that the returned result is approximate.',
      'GEOMETRIC_CENTER' => 'GEOMETRIC_CENTER: indicates that the returned result is the geometric center of a result such as a polyline (for example, a street) or polygon (region).',
      'RANGE_INTERPOLATED' => 'RANGE_INTERPOLATED: indicates that the returned result reflects an approximation (usually on a road) interpolated between two precise points (such as intersections). Interpolated results are generally returned when rooftop geocodes are unavailable for a street address.',
      'ROOFTOP' => 'ROOFTOP: indicates that the returned result is a precise geocode for which we have location information accurate down to street address precision.',
    ),
    '#default_value' => isset($default_values['reject_results']) ? $default_values['reject_results'] : array(),
    '#description' => 'Reject results that do not meet a certain level of quality or precision. Check all types of results to reject.',
  );

  return $form;
}

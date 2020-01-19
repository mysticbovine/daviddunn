<?php

function drealty_admin_settings_page() {
  $item = menu_get_item();
  if ($content = system_admin_menu_block($item)) {
    $output = theme('admin_block_content', array('content' => $content));
  } else {
    $output = t('You do not have any administrative items.');
  }
  return $output;
}

function drealty_metadata_update_form($form, &$form_state, $conid) {

  $form['drealty_metadata_update'] = array(
    '#type' => 'checkbox',
    '#title' => 'Update Metadata for this connection?',
    '#description' => 'RETS Metadata will be updated to refresh the values stored in the database.',
    '#default_value' => variable_get('drealty_metadata_update', 0),
  );

  $form['actions'] = array('#type' => 'actions');

  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
    '#weight' => 40,
  );

  return $form;
}

/**
 * Submit handler.
 */
function drealty_metadata_update_form_submit($form, &$form_state) {
  $conid = $form_state['build_info']['args'][0];
  $dc = new drealtyConnection();
  $metadata = new drealtyMetaData();

  if($conid)
    $connection = $dc->FetchConnection($conid);

  if ($connection && $connection->active && $connection->status) {
    $mappings = $connection->ResourceMappings();

    foreach ($mappings as $mapping) {
      $resource = $metadata->FetchResource($mapping->rid);
      $classes = $connection->FetchClasses($resource);
      foreach ($classes as $key => $class) {
        $metadata->UpdateDrealtyMetadata($connection->conid, $resource, $class);
      }
    }
  }
}

/**
 * Configuration settings for the importer.
 * @param array $form
 * @param array $form_state
 * @return mixed
 */
function drealty_import_admin_settings_form($form, &$form_state) {
  $form['drealty_import_modified_span'] = array(
    '#type' => 'select',
    '#title' => 'Modified Timestamp Difference',
    '#description' => 'When querying for listing updates, what should the date threshold be to query for? Smaller values will yield fewer results. Ideally, this value should be set to 3 days.',
    '#options' => array(
      '1' => '1 day',
      '3' => '3 days',
      '5' => '5 days',
      '7' => '1 week',
      '14' => '2 weeks',
      '30' => '1 month',
      '90' => '3 months',
      '180' => '6 months',
      '365' => '1 year',
      '730' => '2 years',
      '0' => 'Any time',
    ),
    '#default_value' => variable_get('drealty_import_modified_span', 0),
  );

  if (module_exists('drealty_reconciler')) {
    $form['drealty_reconciler_check'] = array(
      '#type' => 'select',
      '#title' => 'Reconcile check',
      '#description' => 'How much time should elapse before a listing is checked to see that it still exists in RETS? A week is usually sufficient.',
      '#options' => array(
        '1' => '1 day',
        '3' => '3 days',
        '5' => '5 days',
        '7' => '1 week',
        '14' => '2 weeks',
        '0' => 'Any Time (all listings)'
      ),
      '#default_value' => variable_get('drealty_reconciler_check', 7),
    );

    $types = array('drealty_office', 'drealty_agent'/*, 'drealty_openhouse'*/);

    $form['reconciler_check_options'] = array(
      '#title' => t('Reconciler Checking Options'),
      '#description' => 'Pick at least one entity for Reconciler to check if it is still active in RETS system.',
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
    );

    $options_check = variable_get('reconciler_check_options', array());
    $form['reconciler_check_options']['drealty_listing'] = array(
      '#type' => 'checkbox',
      '#title' => 'Check drealty_listing entities',
      '#default_value' => isset($options_check['drealty_listing']) ? $options_check['drealty_listing'] : 0,
    );

    foreach ($types as $type) {
      if (module_exists($type)) {
        $form['reconciler_check_options'][$type] = array(
          '#type' => 'checkbox',
          '#title' => 'Check ' . $type . ' entities',
          '#default_value' => isset($options_check[$type]) ? $options_check[$type] : 0,
        );
      }
    }

    $form['drealty_reconciler_delete'] = array(
      '#type' => 'checkbox',
      '#title' => 'Delete inactive listings from the system while reconciling?',
      '#description' => 'By default, all the listings that are removed from RETS are marked as INACTIVE and ARE NOT deleted from the database.',
      '#default_value' => variable_get('drealty_reconciler_delete', 0),
    );
    $form['reconciler_delete_options'] = array(
      '#title' => t('Reconciler Delete Options'),
      '#description' => 'Select which entities you want to be removed from the site if they are not found in RETS.',
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
      '#states' => array(
        'visible' => array(':input[name="drealty_reconciler_delete"]' => array('checked' => TRUE)),
      ),
    );

    $options = variable_get('reconciler_delete_options', array());
    $form['reconciler_delete_options']['drealty_listing'] = array(
      '#type' => 'checkbox',
      '#title' => 'Delete all drealty_listing entities',
      '#default_value' => isset($options['drealty_listing']) ? $options['drealty_listing'] : 0,
    );

    foreach ($types as $type) {
      if (module_exists($type)) {
        $form['reconciler_delete_options'][$type] = array(
          '#type' => 'checkbox',
          '#title' => 'Delete all ' . $type . ' entities',
          '#default_value' => isset($options[$type]) ? $options[$type] : 0,
        );
      }
    }
  }

  return system_settings_form($form);
}
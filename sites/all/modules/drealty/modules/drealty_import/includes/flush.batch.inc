<?php

/**
 * Returns a confirmation form to flush data.
 */
function drealty_import_flush_form($form, &$form_state) {
  $form['header'] = array(
    '#markup' => '<h4>' . t('Perform Flush?') . '</h4>',
  );

  $form['flush_delete_options'] = array(
    '#title' => t('Flush Entity Options'),
    '#tree' => TRUE,
    '#type' => 'fieldset',
    '#collapsed' => FALSE,
    '#collapsible' => TRUE,
  );

  $options = variable_get('flush_delete_options', array());
  $form['flush_delete_options']['drealty_listing'] = array(
    '#type' => 'checkbox',
    '#title' => 'Delete all drealty_listing entities',
  );

  $types = array('drealty_office', 'drealty_agent', 'drealty_openhouse');
  foreach ($types as $type) {
    if (module_exists($type)) {
      $form['flush_delete_options'][$type] = array(
        '#type' => 'checkbox',
        '#title' => 'Delete all ' . $type . ' entities',
        '#default_value' => isset($options[$type]) ? $options[$type] : 0,
      );
    }
  }

  $form['confirm_action'] = array(
    '#type' => 'checkbox',
    '#title' => t('Yes, I want to flush all data in the site for selected entities.'),
    '#required' => TRUE,
  );

  $form['actions'] = array('#type' => 'actions');

  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
    '#weight' => 40,
  );

  return $form;
}

/**
 * Validate handler.
 */
function drealty_import_flush_form_validate($form, &$form_state) {
  $vals = $form_state['values'];
  $selected = array_filter($vals['flush_delete_options']);
  if (empty($selected)) {
    form_set_error('[flush_delete_options]', 'You need to pick at least one option to continue.');
  }
}

/**
 * Submit handler.
 */
function drealty_import_flush_form_submit($form, &$form_state) {
  $entity_types = array_filter($form_state['values']['flush_delete_options']);

  if ($form_state['values']['confirm_action'] == 1 && !empty(array_filter($entity_types))) {
    $entity_types = array_keys($entity_types);

    foreach ($entity_types as $type) {
      $module_exists = TRUE;
      if ($type != 'drealty_listing') {
        $module_exists = module_exists($type);
      }
      if ($module_exists) {
        $query = new EntityFieldQuery();
        $query->entityCondition('entity_type', $type);
        $results = $query->execute();

        if (!empty($results)) {
          foreach ($results[$type] as $record) {
            $operations[] = array(
              'drealty_import_flush_item',
              array(
                'entity_type' => 'drealty_office',
                'entity_id' => $record->id,
                'details' => t('Deleting ' . $type . ' entity id @key...', array('@key' => $record->id))
              )
            );
          }
        }
      }
    }

    if (!empty($operations)) {
      $batch = array(
        'operations' => $operations,
        'title' => t('Performing drealty flush.'),
        'init_message' => t('Initializing'),
        'error_message' => t('An error occurred.'),
        'finished' => 'drealty_import_flush_batch_finished',
        'file' => drupal_get_path('module', 'drealty_import') . '/includes/flush.batch.inc',
      );
      batch_set($batch);
    }
    else {
      drupal_set_message('There are no records of ' . $type .' entities in the database. ', 'warning');
    }
  }
}

function drealty_import_flush_item($entity_type, $entity_id, $message, &$context) {
  entity_delete($entity_type, $entity_id);
  $context['message'] = $message;
  $context['results'][] = $entity_id;
}

function drealty_import_flush_batch_finished($success, $results, $operations) {
  if ($success) {
    $message = t('@count entities flushed.', array('@count' => count($results)));
  }
  else {
    $message = t('Finished with an error.');
  }

  drupal_set_message($message);
}
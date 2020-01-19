<?php

/**
 * @file
 * Returns a confirmation form to import data for a connection.
 */

/**
 * Configuration form to request FULL import of Drealty data via batch.
 */
function drealty_import_full_import_form($form, &$form_state) {
  $form['header'] = array(
    '#markup' => t('Perform Full Import of RETS Listings?'),
  );

  $options = array();
  $connections = entity_load('drealty_connection_entity');

  foreach ($connections as $key => $connection) {
    $options[$connection->conid] = $connection->name;
  }

  $form['connection'] = array(
    '#type' => 'select',
    '#description' => 'Select a connection to process data for.',
    '#title' => 'Connection',
    '#options' => $options,
    '#required' => TRUE,
  );

  $form['confirm_action'] = array(
    '#required' => TRUE,
    '#type' => 'checkbox',
    '#title' => t('Yes, I want to do a full import of all data for listings resource.'),
  );

  $form['actions'] = array('#type' => 'actions');

  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Import'),
    '#weight' => 40,
  );

  return $form;
}

/**
 * Submit handler.
 */
function drealty_import_full_import_form_submit($form, &$form_state) {
  $metadata = new drealtyMetaData();
  $connection = entity_load('drealty_connection_entity', array($form_state['values']['connection']));
  $connection = end($connection);
  $mappings = $connection->ResourceMappings();

  if ($connection->active == 1) {
    $dm = new drealtyDaemon();
    $classes_enabled = FALSE;

    foreach ($mappings as $mapping) {
      if ($mapping->entity_type != 'drealty_listing') {
        continue;
      }

      $resource = $metadata->FetchResource($mapping->rid);
      $classes = $connection->FetchClasses($resource);

      foreach ($classes as $key => $class) {
        if ($class->enabled && $items = $dm->ProcessRetsClass($connection, $resource, $class, $mapping->entity_type, 'resources', TRUE)) {
          $classes_enabled = TRUE;

          foreach ($items as $item) {
            $operations[] = array(
              "drealty_import_full_import_populate_queue",
              array(
                'item' => $item,
                'details' => t('Populating queue for @class class and entity KEY = @key...', array('@class' => $class->systemname, '@key' => $item['remote_id'])),
              ),
            );
          }

          // Perform queries.
          $batch = array(
            'operations' => $operations,
            'title' => t('Creating queue jobs'),
            'init_message' => t('Initializing'),
            'error_message' => t('An error occurred.'),
            'finished' => 'drealty_import_full_import_batch_finished',
            'file' => drupal_get_path('module', 'drealty_import') . '/includes/full.batch.inc',
          );

          batch_set($batch);
          $form_state['redirect'] = 'admin/drealty/drealty-import/full-import';
        }
      }

      if (!$classes_enabled) {
        drupal_set_message('There are no enabled Classes for ' .
          $mapping->entity_type . ' resource. Please, enable at least one class ' .
          'here: <a href="/admin/drealty/connections/manage/' . $connection->name .
          '/resource/' . $mapping->entity_type . '">Configure</a>', 'error');
      }
    }
  }
}

/**
 * Batch operation processing function.
 *
 * @param $item
 * @param $message
 * @param $context
 */
function drealty_import_full_import_populate_queue($item, $message, &$context) {
  $queue = DrealtyImport::getQueue('drealty_import');
  $queue->createItem($item);
  $context['message'] = $message;
  $context['results'][] = $item['remote_id'];
}

/**
 * Batch finished confirmation messages.
 *
 * @param $success
 * @param $results
 * @param $operations
 */
function drealty_import_full_import_batch_finished($success, $results, $operations) {
  if ($success) {
    $message = t('@jobs queue jobs created.', array('@jobs' => count($results)));
  }
  else {
    $message = t('Finished with an error.');
  }

  drupal_set_message($message);
}

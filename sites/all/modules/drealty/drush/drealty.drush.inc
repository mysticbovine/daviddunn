<?php

/**
 * @file
 * Drush integration for dRealty.
 */

/**
 * Implements hook_drush_command().
 */
function drealty_drush_command() {
  $items = array();

  $items['rets-import'] = array(
    'aliases' => array('ri'),
    'description' => 'Import RETS entities with basic fields.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    'options' => array(
      'increment' => FALSE,
    ),
    'examples' => array(
      'drush ri' => 'Imports all data from RETS for enabled classes.',
      'drush ri --increment=TRUE' => 'Imports incremental changes from RETS for 
        enabled connections and classes based on Time period configuration in 
        admin/drealty/drealty-import/config.',
    ),
  );

  // @todo: Functionality is not finished yet, do not use:
  $items['rets-process-queued-properties'] = array(
    'aliases' => array('rpqp'),
    'description' => 'Process any RETS items remaining/stuck in the Drush queue.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['rets-import-media'] = array(
    'aliases' => array('rim'),
    'description' => 'Queue RETS entities for Media Objects processing.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    'options' => array(
      'increment' => FALSE,
    ),
    'examples' => array(
      'drush rim' => 'Imports media for all the entities from RETS based on 
        specified Search configuration.',
      'drush rim --increment=TRUE' => 'Imports media for only those entities from 
        RETS that have been modified within Time period configured in 
        admin/drealty/drealty-import/config.',
    ),
  );

  $items['rets-process-media'] = array(
    'aliases' => array('rpm'),
    'description' => "Process any Media Objects that are queued up in the system.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['rets-flush'] = array(
    'aliases' => array('rfa'),
    'description' => 'Flush ALL RETS items.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['rets-purge-inactive'] = array(
    'aliases' => array('rpi'),
    'description' => "Purge listings older than two weeks from the system.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );
  $items['rets-force-update'] = array(
    'aliases' => array('rfu'),
    'description' => 'Force the update of the selected drealty entity.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );
  return $items;
}

/**
 * Implements hook_drush_help to .
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-command>'
 *
 * @param string $section
 *
 * @return string
 */
function drealty_drush_help($section) {
  switch ($section) {
    case 'drush:rets-import':
      return dt('Run full import of data from RETS System.');

    case 'drush:rets-flush':
      return dt('Deletes Drealty entities records (listings, agents, etc. if 
      chosen) and removes all associated content.');

    case 'drush:rets-list':
      return dt('');

    case 'drush:rets-status':
      return dt('');

    case 'drush:rets-purge-inactive':
      return dt('Deletes Drealty Listing type records and all its data that have
       been deactivated more than 2 weeks ago.');

    case 'drush:rets-reprocess-images':
      return dt('');
  }
}

/**
 * Run import/update of listings from RETS (all listings based on  connection query).
 */
function drush_drealty_rets_import() {
  // Set the time out to infinite.
  set_time_limit(0);

  $increment = drush_get_option('increment', FALSE);
  drush_log(dt('Increment @id', array('@id' => $increment), 'success'));
  module_load_include('php', 'drealty', 'drealtyDaemon');
  $daemon = new drealtyDaemon();
  $daemon->run('resources', $increment);
}

/**
 * Process remaining queue items if the process failed for whatever reason.
 */
function drush_drealty_rets_process_queued_properties() {
  set_time_limit(0);

  module_load_include('php', 'drealty', 'drealtyDaemon');
  $daemon = new drealtyDaemon();
  // @todo: re-design solution to be able to process remaining queue items if the process failed for whatever reason.
  //  $daemon->process_results(...);
}

/**
 * Generate items for drealty_media queue for later processing of Media Objects.
 */
function drush_drealty_rets_import_media() {
  set_time_limit(0);

  $increment = drush_get_option('increment', FALSE);
  module_load_include('php', 'drealty', 'drealtyDaemon');
  $daemon = new drealtyDaemon();
  $daemon->run('media', $increment);
}

/**
 * Process items in the drealty_media queue.
 */
function drush_drealty_rets_process_media() {
  set_time_limit(0);

  module_load_include('php', 'drealty', 'drealtyDaemon');
  $daemon = new drealtyDaemon();
  $daemon->import_media();
}

/**
 * Flush drealty entities from the system.
 */
function drush_drealty_rets_flush() {
  set_time_limit(0);

  $options = array('drealty_listing' => dt('Drealty Listings'));

  if (module_exists('drealty_office')) {
    $options['drealty_office'] = dt('Drealty Office');
  }
  if (module_exists('drealty_agent')) {
    $options['drealty_agent'] = dt('Drealty Agent');
  }
  if (module_exists('drealty_openhouse')) {
    $options['drealty_openhouse'] = dt('Drealty OpenHouse');
  }

  $selection = drush_choice($options, dt('Select an entity type to flush'));

  if (!$selection) {
    return;
  }

  $info = entity_get_info($selection);

  $bundles = array();
  foreach ($info['bundles'] as $key => $bundle) {
    $bundles[$key] = $bundle['label'];
  }
  $bundles['all'] = "All";

  $bundle_selection = drush_choice($bundles, dt('Select a class to flush'));

  if (!$bundle_selection) {
    return;
  }

  $query = db_select($selection, "t")
    ->fields("t", array("id"));

  if ($bundle_selection != 'all') {
    $query->condition('type', $bundle_selection);
  }

  $existing_items = $query->execute()->fetchAll();

  $confirm = drush_confirm(dt("You are about to flush @count @entity(s) of type 
  @type from the system. This cannot be undone. Are you Sure?",
    array(
      '@count' => count($existing_items),
      '@entity' => $selection,
      '@type' => $bundle_selection
    )
  ));

  if (!$confirm) {
    return;
  }

  foreach ($existing_items as $item) {
    if ($entity = entity_load($selection, array($item->id))) {
      $entity = reset($entity);
      drush_log(dt('Deleting @type - @id', array('@id' => $item->id, '@type' => $selection)), 'success');
      $entity->delete();
      unset($entity);
    }
    else {
      drush_log(dt('Could not load @type entity - @id', array('@id' => $item->id, '@type' => $selection)), 'warning');
    }
  }
  unset($existing_items);
}

/**
 * Force entity updates for each enabled drealty entity.
 */
function drush_drealty_rets_force_update() {

  $options = array('drealty_listing' => dt('Drealty Listings'));

  if (module_exists('drealty_office')) {
    $options['drealty_office'] = dt('Drealty Office');
  }
  if (module_exists('drealty_agent')) {
    $options['drealty_agent'] = dt('Drealty Agent');
  }
  if (module_exists('drealty_openhouse')) {
    $options['drealty_openhouse'] = dt('Drealty OpenHouse');
  }

  $selection = drush_choice($options, dt('Select an entity type to Force Updates'));

  if (!$selection) {
    return;
  }

  $num_updated = db_update($selection)
    ->fields(array('hash' => ''))
    ->execute();
  drush_log(dt("Forcing update on @num items.", array('@num' => $num_updated)), 'success');

}

/**
 * Purge inactive listings from the system.
 */
function drush_drealty_rets_purge_inactive() {
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'drealty_listing')
      ->propertyCondition('active', 0)
      ->propertyCondition('inactive_date', strtotime('-2 weeks'), '<');

  $result = $query->execute();

  if (count($result)) {
    $ids = array_keys($result['drealty_listing']);

    foreach ($ids as $id) {
      entity_delete('drealty_listing', $id);
    }
  }
}

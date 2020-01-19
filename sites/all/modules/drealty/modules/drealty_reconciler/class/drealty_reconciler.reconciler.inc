<?php

class DrealtyReconciler {
  public function __construct() {
    $this->dc = new drealtyConnection();
    $this->dm = new drealtyMetaData();
    $this->queue = $this->getQueue();
  }

  /**
   * Clears the queue out.
   */
  public function clearQueue() {
    $this->queue->deleteQueue();
  }

  /**
   * Retrieves the current state of the queue.
   * @return mixed
   */
  public static function getQueue() {
    return DrupalQueue::get('drealty_reconciler');
  }

  /**
   * Updates a Drealty entity.
   * @param $item
   */
  public function processQueueItem($item) {
    if(!empty($item['rets_key'])) {
      $connection = $this->dc->FetchConnection($item['conid']);
      $class = $this->dm->FetchClass($item['class']);
      $resource = $this->dm->FetchResource($class->rid);
      $listing = FALSE;
      $list_entities = array();
      $wrapper = entity_metadata_wrapper($item['entity_type'], $item['id']);

      $field_mappings = $connection->FetchFieldMappings($resource, $class);
      $key_field = $field_mappings['rets_key']->systemname;

      $query = "({$key_field}={$item['rets_key']})";

      $params = array(
        'Format' => 'COMPACT-DECODED',
        'Limit' => "1",
        'Count' => '0',
      );

      $options = variable_get('reconciler_delete_options', '');
      if (!empty($options)) {
        $list_entities = array_keys(array_filter($options));
      }

      if ($item['active'] == 0 && variable_get('drealty_reconciler_delete', 0) == 1) {
        if (!empty($list_entities) && in_array($item['entity_type'], $list_entities)) {
          entity_delete($item['entity_type'], $item['id']);
          module_invoke_all('entity_delete', $wrapper->value(), $item['entity_type']);
          watchdog('drealty_reconcile', "Deleted inactive entity @type: (MLS: @mls ID: @code)", array('@type' => $item['entity_type'], "@mls" => $item['rets_id'], "@code" => $item['id']), WATCHDOG_NOTICE);
        }
      }
      elseif ($this->dc->connect($connection->conid)) {
        $rets = $this->dc->rets;
        $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $params);
        if (!$search) {
          watchdog("drealty_reconcile", 'Search for UniqueID # @key listing (MLS # @mls) was unsuccessful.', array('@key' => $item['rets_key'], '@mls' => $item['rets_id']), WATCHDOG_NOTICE);
//        return FALSE;
        }
        else {
          $listing = $rets->FetchRow($search);
          $rets->FreeResult($search);
        }

        try {
          if ($wrapper) {
            if (!isset($listing) || !$listing) {
              if (variable_get('drealty_reconciler_delete', 0) == 1) {
                if (!empty($list_entities) && in_array($item['entity_type'], $list_entities)) {
                  entity_delete($item['entity_type'], $item['id']);
                  module_invoke_all('entity_delete', $wrapper->value(), $item['entity_type']);
                  watchdog('drealty_reconcile', "Deleted inactive entity @type: (MLS: @mls ID: @code)", array('@type' => $item['entity_type'], "@mls" => $item['rets_id'], "@code" => $item['id']), WATCHDOG_NOTICE);
                }
              }
              else {
                $num_updated = db_update($item['entity_type'])
                  ->fields(array(
                    'last_reconciled' => REQUEST_TIME,
                    'active' => 0,
                    'inactive_date' => REQUEST_TIME))
                  ->condition('conid', $item['conid'])
                  ->condition('class', $item['class'])
                  ->condition('rets_key', $item['rets_key'])
                  ->execute();
                module_invoke_all('entity_update', $wrapper->value(), $item['entity_type']);
              }
            }
            elseif ($listing && is_array($listing)) {
              $num_updated = db_update($item['entity_type'])
                ->fields(array('last_reconciled' => REQUEST_TIME))
                ->condition('conid', $item['conid'])
                ->condition('class', $item['class'])
                ->condition('rets_key', $item['rets_key'])
                ->execute();
              module_invoke_all('entity_update', $wrapper->value(), $item['entity_type']);
            }
          }
        } catch (Exception $e) {
          watchdog("drealty_reconcile", $e->getMessage(), array(), WATCHDOG_ERROR);
        }
        $this->dc->disconnect();
      }
    }
//    else {
//      // Delete corrupted entities without RETS_KEY value:
//      entity_delete($item['entity_type'], $item['id']);
//    }
  }
}
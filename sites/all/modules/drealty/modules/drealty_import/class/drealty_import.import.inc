<?php

class DrealtyImport {

  public function __construct() {
    $this->dc = new drealtyConnection();
    $this->dm = new drealtyMetaData();
    $this->cron_queue = $this->getQueue('drealty_import');
  }

  /**
   * Clears the queue out.
   */
  public function clearQueue($queue) {
    $this->{$queue}->deleteQueue();
  }

  /**
   * Retrieves the current state of the queue.
   * @return mixed
   */
  public static function getQueue($queue) {
    return DrupalQueue::get($queue);
  }


  /**
   * Process an item from the queue.
   * @param $item
   */
  public function processQueueItem($item) {
    try {
      $dm = new drealtyDaemon();
      $entity_type = $item['entity_type'];
      $metadata = new drealtyMetaData();

      // check if the entity exists
      $listing = $dm->entityExists($entity_type, $item['result'][$item['key_field']]);
      if (!($listing)) {
        $entity = $this->create($item['connection'], $item['class'], $item['class_bundle'], $item['remote_id'], $item['result'][$item['key_field']], $entity_type);
        entity_save($entity_type, $entity); // @todo: do we need to save it here ????
      }
      else {
        if (is_array($listing)) {
          $id = array_keys($listing);
          $id = end($id);
          $entity = entity_load($entity_type, array($id));
          $entity = reset($entity);
        }
      }

      $dm->update_single_listing($entity, $item['result']);
    }
    catch (Exception $e) {
      watchdog("drealty_process_queue", $e->getMessage(), array(), WATCHDOG_ERROR);
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Creates an entity ready to be saved.
   * @param $connection
   * @param $class
   * @param $rets_id
   * @param $rets_key
   * @return bool
   */
  public function create($conn_id, $cid, $class_bundle, $rets_id, $rets_key, $entity_type) {
    $entity = entity_create($entity_type, array('type' => $class_bundle));
    $entity->active = 1;
    $entity->conid = $conn_id;
    $entity->class = $cid;
    $entity->label = $rets_id;
    $entity->rets_id = $rets_id;
    $entity->rets_key = $rets_key;
    $entity->created = REQUEST_TIME;
    $entity->changed = REQUEST_TIME;
    $entity->uid = 1;
    $entity->media_hash = 0;
    if ($entity_type == 'drealty_listing') {
      $entity->rets_imported = 1;
    }
    return $entity;
  }
}
<?php

/**
 * @file
 * DRealty's centralized import functionality.
 */

/**
 * Helper class to implement phRETS functionality.
 */
class drealtyDaemon {

  /**
   *
   * @var drealtyConnection
   */
  protected $dc;

  /**
   *
   * @var drealtyMetaData
   */
  protected $dm;

  /**
   *
   * @var DrupalQueueInterface
   */
  protected $queue;

  /**
   *
   * @var DrupalQueueInterface
   */
  protected $media_queue;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->dc = new drealtyConnection();
    $this->dm = new drealtyMetaData();
    $this->queue = DrupalQueue::get('drealty');
    $this->media_queue = DrupalQueue::get('drealty_media');
    $this->cron_queue = DrupalQueue::get('drealty_import');
  }

  /**
   * Clears the queue out.
   */
  public function clearQueue($queue) {
    $this->{$queue}->deleteQueue();
  }

  /**
   * Retrieves the current state of the queue.
   *
   * @return DrupalQueue
   *   Retrieve specified queue.
   */
  public static function getQueue($queue) {
    return DrupalQueue::get($queue);
  }

  /**
   * Update Media Objects configuration.
   */
  public function mediaObjectsConfigure() {
    $connections = $this->dc->FetchConfiguredConnections();
    db_delete("drealty_media_objects")->execute();
    foreach ($connections as $connection) {
      if ($this->dc->connect($connection->conid)) {

        $mappings = $connection->ResourceMappings();
        foreach ($mappings as $mapping) {
          $resource = $this->dm->FetchResource($mapping->rid);
          $classes = $connection->FetchClasses($resource);
          foreach ($classes as $class) {
            if ($object_types = $this->dc->rets->GetMetadataObjects($resource->systemname)) {
              foreach ($object_types as $type) {
                db_insert('drealty_media_objects')
                  ->fields(array('conid', 'rid', 'cid', 'type', 'standardname', 'description', 'mimetype', 'visiblename', 'objecttimestamp', 'objectcount'))
                  ->values(array(
                    'conid' => $connection->conid,
                    'rid' => $resource->rid,
                    'cid' => $class->cid,
                    'type' => $type['ObjectType'],
                    'standardname' => $type['StandardName'],
                    'description' => $type['Description'],
                    'mimetype' => $type['MimeType'],
                    'visiblename' => $type['VisibleName'],
                    'objecttimestamp' => $type['ObjectTimeStamp'],
                    'objectcount' => $type['ObjectCount'],
                  ))
                  ->execute();
              }
            }
          }
        }
        $this->dc->disconnect();
      }
    }
    unset($connections, $mappings, $classes);
    // cache_clear_all();
  }

  /**
   * Initiate data import from RETS.
   *
   * @param string $src_type
   *   Processing RETS basic fields (= resources) or Media Objects (= media).
   * @param bool $increment
   *   Whether to implement incremental update.
   *
   * @return bool
   */
  public function run($src_type, $increment = FALSE) {
    $connections = $this->dc->FetchConnections();
    if ($src_type == 'media') {
      $this->media_queue->deleteQueue();
    }
    foreach ($connections as $connection) {
      if ($connection->active == 1) {
        $mappings = $connection->ResourceMappings();
        foreach ($mappings as $mapping) {
          $resource = $this->dm->FetchResource($mapping->rid);
          $classes = $connection->FetchClasses($resource);
          foreach ($classes as $class) {
            if ($src_type == 'resources') {
              if ($class->enabled && $class->lifetime <= time() - ($class->lastupdate + 60)) {
                $this->ProcessRetsClass($connection, $resource, $class, $mapping->entity_type, $src_type, FALSE, $increment);
                $class->lastupdate = time();
                drupal_write_record('drealty_classes', $class, 'cid');
              }
            }
            elseif ($src_type == 'media' && $class->enabled && $class->process_images) {
              $time_now = time() - 60;
              $this->ProcessRetsClass($connection, $resource, $class, $mapping->entity_type, $src_type, FALSE, $increment);
              $class->media_lastupdate = $time_now;
              drupal_write_record('drealty_classes', $class, 'cid');
            }
          }
        }
      }
    }

    unset($connections, $mappings, $classes);
    // cache_clear_all();
    module_invoke_all('drealty_rets_import_complete');
    return TRUE;
  }

  /**
   * Provides a report to match listings in database vs. RETS system.
   *
   * @param drealtyConnectionEntity $connection
   *
   * @return array|mixed
   */
  public function BuildConnectionReport(drealtyConnectionEntity $connection) {
    $mappings = $connection->ResourceMappings();
    $resources = $connection->Resources();
    $key_field = "";
    $chunks = 0;
    $report_data = array();
    $in_rets = array();

    foreach ($mappings as $mapping) {
      $resource = $this->dm->FetchResource($mapping->rid);
      // Only do it for Listings (not Offices, Agents, or any other resources)
      if ($resource && in_array($resource->systemname, array('Property', 'Listing'))) {
        $classes = $connection->FetchClasses($resource);
        foreach ($classes as $class) {
          if ($class->enabled) {

            $in_db = db_select('drealty_listing', 'dl')
              ->fields('dl', array('id', 'rets_key', 'rets_id'))
              ->condition('conid', $connection->conid)
              ->condition('class', $class->cid)
              ->condition('active', 1)
              ->orderBy('rets_key')
              ->execute()
              ->fetchAllAssoc('rets_key');

            $report_data[$class->cid]['bundle'] = $class->bundle;

            if ($in_db) {
              $report_data[$class->cid]['in_db_count'] = count($in_db);
            }

            $fieldmappings = $connection->FetchFieldMappings($resource, $class);
            $key_field = $fieldmappings['rets_key']->systemname;
            $id_field = $fieldmappings['rets_id']->systemname;
            $fields = array($key_field, $id_field);

            switch ($class->query_type) {
              // @todo: needs work, not accounted for yet
              case DREALTY_QUERY_TYPE_PRICE:
                // @todo: needs work, not accounted for yet
              case DREALTY_QUERY_TYPE_RETS_KEY:
                break;

              case DREALTY_QUERY_TYPE_MANUAL:
                $query = array();
                $custom_query = token_replace($class->override_status_query_text, array('drealty-class' => $class));
                $query[] = $custom_query;
                break;

              case DREALTY_QUERY_TYPE_DEFAULT:
              default:
                $statuses = $class->status_values;
                $status_q = "|$statuses";
                $query_field = 'rets_status';
                $query = array();
                $query[] = "{$fieldmappings[$query_field]->systemname}={$status_q}";
            }

            if ($class->query_type == DREALTY_QUERY_TYPE_PRICE) {
              // @todo: needs work
              drupal_set_message(t("Unsupported query option. Only supports queries with offsets."), 'error');
              return;
            }
            else {
              if ($class->query_type == DREALTY_QUERY_TYPE_RETS_KEY) {
                // @todo: needs work
                drupal_set_message(t("Unsupported query option. Only supports queries with offsets."), 'error');
                return;
              }
              else {
                $offset = 0;
                $rets = $this->dc->rets;
                $this->dc->rets->SetParam("offset_support", TRUE);

                if ($this->dc->connect($connection->conid)) {
                  $q = implode('),(', $query);

                  $optional_params = array(
                    'Format' => 'COMPACT-DECODED',
                  );

                  $search = $rets->SearchQuery($resource->systemname, $class->systemname, "($q)", $optional_params);

                  $in_rets_count = $rets->NumRows();
                  $report_data[$class->cid]['in_rets_count'] = $in_rets_count;

                  if ($in_rets_count > 0) {
                    while ($listing = $rets->FetchRow($search)) {
                      $in_rets[$listing[$key_field]] = $listing[$id_field];

                      if ($in_db && !isset($in_db[$listing[$key_field]])) {
                        $report_data[$class->cid]['missing_from_db'][$listing[$key_field]] = array(
                          'mls' => $listing[$id_field],
                          'modified' => $listing[$class->rets_timestamp_field],
                        );
                      }
                    }
                  }

                  if ($error = $rets->Error()) {
                    watchdog('drealty_report', 'drealty encountered an error: (Type: @type Code: @code Msg: @text)', array(
                      "@type" => $error['type'],
                      "@code" => $error['code'],
                      "@text" => $error['text'],
                    ), WATCHDOG_WARNING);
                  }
                  $rets->FreeResult($search);
                }
              }
            }

            if ($in_rets_count > 0) {
              $diff = array_diff_key($in_db, $in_rets);
              foreach ($diff as $val) {
                $report_data[$class->cid]['need_deactivation'][$val->rets_key] = $val->rets_id;
              }
            }
          }
        }
        unset($mappings, $resources, $fieldmappings, $query);
      }
    }
    $this->dc->disconnect();

    return $report_data;
  }

  /**
   * Process the current class for import.
   *
   * @param drealtyConnectionEntity $connection
   * @param drealtyRetsResource $resource
   * @param drealtyRetsClass $class
   * @param string $entity_type
   * @param string $process_type
   *   Processing RETS basic fields (= resources) or Media Objects (= media).
   * @param mixed $return_items
   *   Indicates whether to return array of found items or just queue them up into $return_items.
   * @param bool $increment
   *   Indicates whether to account for Config Timestamp difference.
   */
  public function ProcessRetsClass($connection, $resource, $class, $entity_type, $process_type = 'resources', $return_items = FALSE, $increment = FALSE) {

    if ($process_type == 'resources') {
      $this->queue->deleteQueue();
    }

    $mappings = $connection->ResourceMappings();
    $resources = $connection->Resources();
    $key_field = "";
    $chunks = 0;

    // Build a list of fields we are going to request from the RETS server.
    $fieldmappings = $connection->FetchFieldMappings($resource, $class);

    $this->MessageErrorLogger("Processing Resource: @res Class: @class  - for Connection: @con", array(
      "@res" => $resource->systemname,
      "@class" => $class->visiblename,
      "@con" => $connection->name,
    ), 'drush', 'notice');

    $key_field = $fieldmappings['rets_key']->systemname;

    // Create queue items for processing entity fields and mark the entities which need media updated.
    switch ($class->query_type) {
      case DREALTY_QUERY_TYPE_PRICE:
        // @todo: check functionality: should it have manual query entry? does it work correctly?
        $this->fetch_listings_offset_not_supported_price($connection, $resource, $class, $key_field, $entity_type, $process_type, $return_items, $increment);
        $process = TRUE;
        break;

      case DREALTY_QUERY_TYPE_MANUAL:
        $query = array();
        $custom_query = token_replace($class->override_status_query_text, array('drealty-class' => $class));

        $this->MessageErrorLogger("Using query: @var", array("@var" => $custom_query), 'drush', 'notice');

        $query[] = $custom_query;
        $process = $this->fetch_listings_offset_supported_default($connection, $resource, $class, $query, $key_field, $entity_type, $process_type, $return_items, $increment);
        break;

      case DREALTY_QUERY_TYPE_RETS_KEY:
        // @todo: check functionality: should it have manual query entry? does it work correctly?
        $this->fetch_listings_offset_not_supported_key($connection, $resource, $class, $key_field, $entity_type, $process_type, $return_items, $increment);
        $process = TRUE;
        break;

      case DREALTY_QUERY_TYPE_DEFAULT:
      default:
        // Build the query.
        $statuses = $class->status_values;
        $status_q = "|$statuses";
        $query_field = 'rets_status';
        $query = array();
        $query[] = "{$fieldmappings[$query_field]->systemname}={$status_q}";
        $process = $this->fetch_listings_offset_supported_default($connection, $resource, $class, $query, $key_field, $entity_type, $process_type, $return_items, $increment);
    }

    // At this point we have data waiting to be processed. Need to process the
    // data which will insert/update/delete the listing data as nodes.
    if ($process && $process_type == 'resources') {
      if ($return_items && is_array($process)) {
        return $process;
      }
      else {
        if ($this->queue->numberOfItems() > 0) {
          $this->MessageErrorLogger("process_results( connection: @connection_name, resource: @resource, class: @class, chunks: @chunks)", array(
            "@connection_name" => $connection->name,
            "@resource" => $resource->systemname,
            "@class" => $class->systemname,
            "@chunks" => $chunks,
          ), 'drush', 'notice');

          // Process queue items, create entities or update basic fields for existing ones.
          if ($process_type == 'resources') {
            $this->process_results($connection, $resource, $class, $entity_type);
          }
        }
        else {
          $this->MessageErrorLogger("No items to process. exiting.", array(), 'drush', 'notice');
        }
      }
    }
    elseif ($process && $process_type == 'media') {
      $this->MessageErrorLogger("Media queue has been generated.", array(), 'drush', 'success');
    }
    else {
      $this->MessageErrorLogger("There was an error returned from RETS of some sort - fetch_listings_offset_supported_default() returned FALSE. No items were queued, none processed.", array(), 'drush', 'warning');
    }

    unset($mappings, $resources, $fieldmappings, $query);
  }

  /**
   * Processing Class based on entity KEY.
   *
   * @param drealtyConnectionEntity $connection
   * @param drealtyRetsResource $resource
   * @param drealtyRetsClass $class
   * @param string $key_field
   * @return int
   */
  function fetch_listings_offset_not_supported_key(drealtyConnectionEntity $connection, $resource, $class, $key_field, $entity_type, $process_type, $return_items, $increment) {
    $rets = $this->dc->rets;

    $chunks = 0;
    $id = 0;

    $query = "({$key_field}={$id}+)";
    $limit = $class->chunk_size;

    $options = array(
      'count' => 1,
      'Format' => 'COMPACT-DECODED',
      'Select' => $key_field,
    );

    if ($this->dc->connect($connection->conid)) {
      // First run the Search based on the KEY value only.
      $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $options);

      if ($error = $rets->Error()) {
        $this->MessageErrorLogger("drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
          "@type" => $error['type'],
          "@code" => $error['code'],
          "@text" => $error['text'],
        ), 'drush', 'error');
      }

      $total = $rets->TotalRecordsFound($search);
      $rets->FreeResult($search);

      $this->MessageErrorLogger("Total Listings @total", array('@total' => $total), 'drush', 'notice');

      $count = 0;
      $listings = array();

//      $options['Select'] = implode(',', $this->get_fields($connection->conid, $class->cid));

      if ($process_type == 'media') {
        $hash_fields = array();
        $time_fields = $this->dm->FetchMediaObjectTimestampFields($connection->conid, $class->cid);

        if ($time_fields) {
          $hash_fields = array_merge($time_fields, array($key_field));

          // @todo: for now, for incremental updates, we'll be queueing media items by parent Resource modification timestamp. Anyway, processing only if media_hash differs.
          // @todo:  OR query doesn't work now. How to account for various Media Timestamps ?  https://groups.google.com/forum/#!topic/phrets/ZiaCvNYLKxA
          $options['Select'] = implode(',', $hash_fields);
        }
      }

      $options['Limit'] = $limit;

      while ($count <= $total) {

        $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $options);

        if ($rets->NumRows($search) > 0) {
          while ($listing = $rets->FetchRow($search)) {
            if ($process_type == 'resources') {
              $listing['hash'] = $this->calculate_hash($listing, $connection, $class);
              $queue_item = array(
                'resource' => $resource->rid,
                'connection' => $connection->conid,
                'class' => $class->cid,
                'entity_type' => $entity_type,
                'result' => $listing,
                'remote_id' => $listing[$resource->keyfield],
              );

              if ($return_items) {
                $queue_items[] = $queue_item;
              }
              else {
                $this->queue->createItem($queue_item);
              }
            }
            elseif ($process_type == 'media') {
              $queue_item = array(
                'conid' => $connection->conid,
                'rid' => $resource->rid,
                'cid' => $class->cid,
                'key_field' => $key_field,
                'key_value' => $listing[$key_field],
                'entity_type' => $entity_type,
                'media_hash' => $this->calculate_hash($listing, $connection, $class, $hash_fields),
              );
              $queue_items[] = $queue_item;
              $this->populate_queue('drealty_media', array($queue_item));
            }
            $count++;
          }

          ksort($listings);
          $last = end($listings);
          reset($listings);

          $id = $last[$key_field];
          $id = (int) $id + 1;

          unset($listings);
        }
        else {
          break;
        }

        $rets->FreeResult($search);
        $this->MessageErrorLogger("[connection: {$connection->name}][resource: {$resource->systemname}][class: {$class->systemname}][downloaded: $count][query: $query]", array(), 'drush', 'notice');

        $query = "({$key_field}={$id}+)";
      }
      $this->dc->disconnect();
    }
    else {
      $error = $rets->Error();
      $this->MessageErrorLogger("drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
        "@type" => $error['type'],
        "@code" => $error['code'],
        "@text" => $error['text'],
      ), 'both', 'error');
    }
  }

  /**
   * Processing Class based on PRICE field, no Offset.
   *
   * @param drealtyConnectionEntity $connection
   * @param drealtyRetsResource $resource
   * @param drealtyRetsClass $class
   * @param string $key_field
   * @return int
   */
  function fetch_listings_offset_not_supported_price(drealtyConnectionEntity $connection, $resource, $class, $key_field, $entity_type, $process_type, $return_items, $increment) {
    $rets = $this->dc->rets;

    $chunks = 0;
    $offset_amount = $class->offset_amount;
    $offset_max = $class->offset_max;
    $offset_start = 0;
    $offset_end = $offset_start + $offset_amount;

    $query = token_replace($class->override_status_query_text, array('drealty-class' => $class));
    $options = array(
      'count' => 1,
      'Format' => 'COMPACT-DECODED',
      'Select' => $key_field,
    );

    if ($this->dc->connect($connection->conid)) {

      $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $options);

      if ($error = $rets->Error()) {
        $this->MessageErrorLogger("drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
          "@type" => $error['type'],
          "@code" => $error['code'],
          "@text" => $error['text'],
        ), 'drush', 'error');
      }

      $total = $rets->TotalRecordsFound($search);
      $rets->FreeResult($search);

      $offset_query = "$query,({$class->offset_field}={$offset_start}-{$offset_end})";
      $count = 0;

      // $options['Select'] = implode(',', $this->get_fields($connection->conid, $class->cid));.
      if ($process_type == 'media') {
        $hash_fields = array();
        $time_fields = $this->dm->FetchMediaObjectTimestampFields($connection->conid, $class->cid);

        if ($time_fields) {
          $hash_fields = array_merge($time_fields, array($key_field));

          // @todo: for now, for incremental updates, we'll be queueing media items by parent Resource modification timestamp. Anyway, processing only if media_hash differs.
          // @todo:  OR query doesn't work now. How to account for various Media Timestamps ?  https://groups.google.com/forum/#!topic/phrets/ZiaCvNYLKxA
          $options['Select'] = implode(',', $hash_fields);
        }
      }

      while ($count < $total) {

        $search = $rets->SearchQuery($resource->systemname, $class->systemname, $offset_query, $options);

        if ($rets->NumRows($search) > 0) {
          while ($listing = $rets->FetchRow($search)) {
            if ($process_type == 'resources') {
              $listing['hash'] = $this->calculate_hash($listing, $connection, $class);
              $queue_item = array(
                'resource' => $resource->rid,
                'connection' => $connection->conid,
                'class' => $class->cid,
                'entity_type' => $entity_type,
                'result' => $listing,
                'remote_id' => $listing[$resource->keyfield],
              );

              if ($return_items) {
                $queue_items[] = $queue_item;
              }
              else {
                $this->queue->createItem($queue_item);
              }
            }
            elseif ($process_type == 'media') {
              $queue_item = array(
                'conid' => $connection->conid,
                'rid' => $resource->rid,
                'cid' => $class->cid,
                'key_field' => $key_field,
                'key_value' => $listing[$key_field],
                'entity_type' => $entity_type,
                'media_hash' => $this->calculate_hash($listing, $connection, $class, $hash_fields),
              );
              $queue_items[] = $queue_item;
              $this->populate_queue('drealty_media', array($queue_item));
            }
            $count++;
          }
        }

        $rets->FreeResult($search);
        $this->MessageErrorLogger("Resource: {$resource->systemname} Class: {$class->systemname} Listings Downloaded: $count Query: $offset_query", array(), 'drush', 'notice');

        if ($offset_end < $offset_max) {
          $offset_start = $offset_end + 1;
          $offset_end += $offset_amount;
          $offset_query = "$query,({$class->offset_field}={$offset_start}-{$offset_end})";
        }
        else {
          $offset_query = "$query,({$class->offset_field}={$offset_max}+)";
        }
      }
      $this->dc->disconnect();
    }
    else {
      $error = $rets->Error();
      $this->MessageErrorLogger("drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
        "@type" => $error['type'],
        "@code" => $error['code'],
        "@text" => $error['text'],
      ), 'both', WATCHDOG_ERROR);
    }
    unset($listings);
  }

  /**
   * Processing Class based on supported Offset while searching.
   *
   * @param drealtyConnectionEntity $connection
   * @param drealtyRetsResource $resource
   * @param drealtyRetsClass $class
   * @param string $query
   * @return int
   */
  function fetch_listings_offset_supported_default(drealtyConnectionEntity $connection, $resource, $class, $query, $key_field, $entity_type, $process_type, $return_items, $increment) {
    $offset = 0;
    $count = 0;
    $rets = $this->dc->rets;
    $limit = $class->chunk_size;
    $queue_items = array();

    if ($limit == 0) {
      $limit = 'NONE';
    }

    $this->dc->rets->SetParam("offset_support", TRUE);

    if ($this->dc->connect($connection->conid)) {
      // Prepare the query.
      $q = implode('),(', $query);
      // $fields = $this->get_fields($connection->conid, $class->cid);.
      $options = array(
        'Format' => 'COMPACT-DECODED',
        'Limit' => "$limit",
        'Count' => '0',
        // 'RestrictedIndicator' => 'xxxx',
        //        'Select' => implode(',', $fields), // @todo: Search fails when uncommented. Should we just skip it? How resource intensive this is?
      );

      if ($process_type == 'media') {
        $hash_fields = array();
        $time_fields = $this->dm->FetchMediaObjectTimestampFields($connection->conid, $class->cid);

        if ($time_fields) {
          $hash_fields = array_merge($time_fields, array($key_field));

          // @todo: for now, for incremental updates, we'll be queueing media items by parent Resource modification timestamp. Anyway, processing only if media_hash differs.
          // @todo:  OR query doesn't work now. How to account for various Media Timestamps ?  https://groups.google.com/forum/#!topic/phrets/ZiaCvNYLKxA
          $options['Select'] = implode(',', $hash_fields);
        }
      }

      // Do the actual search.
      $modified = variable_get('drealty_import_modified_span', 0);
      if ($increment && $modified != 0 && !empty($class->rets_timestamp_field)) {
        if (in_array($modified, array(4, 6, 12))) {
          $date = substr(format_date(strtotime('-' . $modified . ' hours'), 'custom', 'c'), 0, -6) . '+';
        }
        else {
          $date = format_date(strtotime('-' . $modified . ' days'), 'custom', 'Y-m-d') . 'T00:00:00+';
        }
        $search = $rets->SearchQuery($resource->systemname, $class->systemname, $q . ',(' . $class->rets_timestamp_field . '=' . $date . ')', $options);
      }
      else {
        $search = $rets->SearchQuery($resource->systemname, $class->systemname, "$q", $options);
      }

      // Loop through the search results.
      if ($search && $rets->NumRows() > 0) {
        while ($listing = $rets->FetchRow($search)) {
          // Calculate the hash.
          if ($process_type == 'resources') {
            $listing['hash'] = $this->calculate_hash($listing, $connection, $class);
            $queue_item = array(
              'resource' => $resource->rid,
              'key_field' => $resource->keyfield,
              'connection' => $connection->conid,
              'class' => $class->cid,
              'class_bundle' => $class->bundle,
              'entity_type' => $entity_type,
              'result' => $listing,
              'remote_id' => $listing[$resource->keyfield],
            );

            if ($return_items) {
              $queue_items[] = $queue_item;
            }
            else {
              $this->queue->createItem($queue_item);
            }
          }
          elseif ($process_type == 'media') {
            $queue_item = array(
              'conid' => $connection->conid,
              'rid' => $resource->rid,
              'cid' => $class->cid,
              'key_field' => $key_field,
              'key_value' => $listing[$key_field],
              'entity_type' => $entity_type,
              'media_hash' => $this->calculate_hash($listing, $connection, $class, $hash_fields),
            );
            $queue_items[] = $queue_item;
            $this->populate_queue('drealty_media', array($queue_item));
          }

          $this->MessageErrorLogger("Resource: {$resource->systemname} Class: $class->systemname - Queuing Item $count", array(), 'drush', 'notice');
          $count++;
        }
      }

      if ($error = $rets->Error()) {
        $this->MessageErrorLogger("Drealty Search Failed with an error: (Type: @type Code: @code Msg: @text)", array(
          "@type" => $error['type'],
          "@code" => $error['code'],
          "@text" => $error['text'],
        ), 'drush', 'error');
      }

      $rets->FreeResult($search);
      $this->dc->disconnect();

      // Do some cleanup.
      unset($items);

      if ($rets->NumRows() > 0) {
        if ($return_items) {
          return $queue_items;
        }
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    else {
      $error = $rets->Error();
      $this->MessageErrorLogger("drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
        "@type" => $error['type'],
        "@code" => $error['code'],
        "@text" => $error['text'],
      ), 'both', WATCHDOG_ERROR);
      return FALSE;
    }

    return FALSE;
  }

  /**
   *
   */
  protected function populate_queue($queue_name, $items = array()) {
    $queue = $this->getQueue($queue_name);
    foreach ($items as $item) {
      $queue->createItem($item);
    }
  }

  /**
   * Update a single entity data with the values from RETS.
   */
  public function update_single_listing($listing, $rets_item = NULL) {
    $connection = $this->dc->FetchConnection($listing->conid);
    $class = $this->dm->FetchClass($listing->class);
    $resource = $this->dm->FetchResource($class->rid);
    $field_mappings = $connection->FetchFieldMappings($resource, $class);
    $key_field = $field_mappings['rets_key']->systemname;
    $item_context = array(
      'field_mappings' => $field_mappings,
      'connection' => $connection,
      'resource' => $resource,
      'key_field' => $key_field
    );

    $hash_fields = array();
    if ($class->process_images) {
      if ($time_fields = $this->dm->FetchMediaObjectTimestampFields($connection->conid, $class->cid)) {
        $hash_fields = array_merge($time_fields, array($key_field));
      }
    }

    if (!$rets_item) {
      $query = "({$key_field}={$listing->rets_key})";

      // $fields = implode(',', $this->get_fields($connection->conid, $class->cid));
      //      if ($class->process_images && $time_fields) {
      //        foreach ($time_fields as $k) {
      //          if (!in_array($k, array_values($fields)) {
      //            $fields[] = $k;
      //          }
      //        }
      //      }.
      $params = array(
        'Format' => 'COMPACT-DECODED',
        'Limit' => "1",
        'Count' => '0',
        // 'RestrictedIndicator' => 'xxxx',
        //        'Select' => $fields,  // @todo:   with fields uncommented it doesn't return any results (??? where it fails)
      );

      if ($this->dc->connect($connection->conid)) {
        try {
          $rets = $this->dc->rets;
          $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $params);

          if (!$search) {
            watchdog("drealty", 'Search for UniqueID # @key listing (MLS # @mls) was unsuccessful.', array(
              '@key' => $listing->rets_key,
              '@mls' => $listing->rets_id,
            ), WATCHDOG_NOTICE);
            drupal_set_message("Search for this listing was unsuccessful. Either it doesn't exist or there was an error contacting the server.", 'error');
            $this->dc->disconnect();
            return FALSE;
          }

          $item = $rets->FetchRow($search);
          $rets->FreeResult($search);
        }
        catch (Exception $e) {
          watchdog("drealty", $e->getMessage(), array(), WATCHDOG_ERROR);
          drupal_set_message($e->getMessage(), 'error');
          $this->dc->disconnect();
          return FALSE;
        }
        $this->dc->disconnect();
      }
    }
    else {
      $item = $rets_item;
    }

    $listing->hash = 0;
    $listing->changed = time();

    try {
      if ($item && is_array($item)) {
        $listing->hash = $this->calculate_hash($item, $connection, $class);
        // Set as active in case it was disabled before (e.g. off market, cancelled, etc.).
        $listing->active = 1;
        $this->set_field_data($listing, $item, $field_mappings, $listing->entityType(), $class, TRUE);
        $item_context['rets_item'] = $item;
        if ($class->process_images) {
          // Calculate entity MediaHash value based only on EntityKey and all values of the mapped Media timestamp fields.
          $media_hash = $this->calculate_hash($item, $connection, $class, $hash_fields);
          if ($listing->media_hash != $media_hash) {
            $rets_item = array(
              'conid' => $connection->conid,
              'rid' => $resource->rid,
              'cid' => $class->cid,
              'key_field' => $key_field,
              'key_value' => $listing->rets_key,
              'entity_type' => $listing->entityType(),
              'media_hash' => 99,
            );
            $success = $this->import_single_resource_media($rets_item, $listing);
            if ($success) {
              // @todo: when to set it? what if there's no media? look for returned code?
              $listing->media_hash = $media_hash;
            }
          }
        }

        drupal_alter('drealty_import_presave', $listing, $item_context);
      }

      $listing->save();
      module_invoke_all('drealty_entity_save', array(
        &$listing,
        $item_context,
      ));
      return TRUE;
    }
    catch (Exception $e) {
      watchdog("drealty", $e->getMessage(), array(), WATCHDOG_ERROR);
      drupal_set_message($e->getMessage(), 'error');
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Process Drealty queue items which contain BASIC mapped fields data.
   *
   * @param drealtyConnectionEntity $connection
   * @param drealtyRetsResource $resource
   * @param drealtyRetsClass $class
   * @param string $entity_type
   * @param int $chunk_count
   */
  protected function process_results(drealtyConnectionEntity $connection, $resource, $class, $entity_type) {

    global $user;
    $in_rets = array();

    $key_field = 'rets_key';

    $existing_query = db_select($entity_type, "t")
      ->fields("t", array($key_field, "hash", "id"))
      ->condition("conid", $connection->conid)
      ->condition('class', $class->cid);

    if (in_array($entity_type, array(
      'drealty_listing',
      'drealty_office',
      'drealty_agent'
    ))) {
      $existing_query->addField('t', 'active');
    }

    $existing_items = $existing_query->execute()->fetchAllAssoc($key_field);

    // Get the fieldmappings.
    $field_mappings = $connection->FetchFieldMappings($resource, $class);

    // Set $id to the systemname of the entity's corresponding key from the rets feed to make the code easier to read.
    $id = $field_mappings[$key_field]->systemname;

    $item_context = array(
      'field_mappings' => $field_mappings,
      'connection' => $connection,
      'resource' => $resource,
      'key_field' => $key_field,
    );
    $total = $this->queue->numberOfItems();
    $count = 1;
    while ($queue_item = $this->queue->claimItem()) {
      $rets_item = $queue_item->data['result'];
      $in_rets[$rets_item[$id]] = $rets_item[$id];
      $force = FALSE;

      if (!empty($connection->nomap_mode)) {
        // This connection does not use field mappings, just remove the item
        // from the queue and handle expired listings.
        $this->MessageErrorLogger("Got item @name. [@count of @total]", array(
          "@name" => $rets_item[$id],
          "@count" => $count,
          "@total" => $total,
        ), 'drush', 'notice');
        $this->queue->deleteItem($queue_item);
      }
      else {
        if (!isset($existing_items[$rets_item[$id]]) || $existing_items[$rets_item[$id]]->hash != $rets_item['hash'] || $force || (isset($existing_items[$rets_item[$id]]->active) && $existing_items[$rets_item[$id]]->active == 0)) {

          // Allow other modules to preprocess RETS fields before mapping them.
          drupal_alter('drealty_import_rets_item', $rets_item, $item_context);

          // This listing either doesn't exist in the IDX or has changed.
          // determine if we need to update or create a new one.
          if (isset($existing_items[$rets_item[$id]])) {
            // This listing exists so we'll get a reference to it and set the values to what came to us in the RETS result.
            $entities = array($existing_items[$rets_item[$id]]->id);
            $item = entity_load($entity_type, $entities);
            $item = reset($item);
            $is_new = FALSE;
          }
          else {
            $item = entity_create($entity_type, array(
              'conid' => $connection->conid,
              'type' => $class->bundle,
            ));
            $is_new = TRUE;
            $item->created = time();
          }

          $item->conid = $connection->conid;
          $item->hash = $rets_item['hash'];
          $item->changed = time();
          $item->class = $class->cid;
          $item->rets_imported = TRUE;
          $item->uid = $user->uid;
          // @todo: There's no ACTIVE field on OpenHouse entity - this creates PHP notices
          $item->active = 1;
          $item->inactive_date = NULL;
          $item->media_hash = 0;

          // Set the title to the rets_id initially, then it can be changed in hook_presave() if needed.
          $item->label = $rets_item[$field_mappings['rets_id']->systemname];

          $this->set_field_data($item, $rets_item, $field_mappings, $entity_type, $class);

          $item_context['rets_item'] = $rets_item;

          try {
            drupal_alter('drealty_import_presave', $item, $item_context);
            $item->save();
            module_invoke_all('drealty_entity_save', array(
              &$item,
              $item_context,
            ));
            $this->queue->deleteItem($queue_item);
            $this->MessageErrorLogger("Saving item @name. [@count of @total]", array(
              "@name" => $item->label,
              "@count" => $count,
              "@total" => $total,
            ), 'drush', 'notice');
          }
          catch (Exception $e) {
            $this->MessageErrorLogger($e->getMessage(), array(), 'drush', 'error');
            // $this->queue->releaseItem($queue_item); //@todo: what if exception saving due to fields errors? entity is not saved. delete queue item? or let it be stuck ?
          }
          unset($item);
        }
        else {
          // Skipping this item.
          $this->MessageErrorLogger("Skipping item @name. [@count of @total]", array(
            "@name" => $rets_item[$id],
            "@count" => $count,
            "@total" => $total,
          ), 'drush', 'error');

          $this->queue->deleteItem($queue_item);
        }
      }
      $count++;
      drupal_get_messages();
      drupal_static_reset();
    }

    // @todo:  if RETS_QUERY changed from the time the items where first imported with Drush - this will cause setting INACTIVE listings that are not inactive. Better use reconciler logic?
    // https://www.drupal.org/node/1803422
    // Handle expired listings.
    //    if (count($in_rets) && in_array($entity_type, array('drealty_listing', 'drealty_office', 'drealty_agent'))) {
    //      $this->handle_expired($in_rets, $entity_type, $connection->conid, $class);
    //    }
  }

  /**
   * Initiate processing of Media for each Resource class.
   */
  public function import_media() {
    $total = $this->media_queue->numberOfItems();
    $count = 1;

    while ($queue_item = $this->media_queue->claimItem()) {
      $rets_item = $queue_item->data;
      $this->MessageErrorLogger("START Processing of Media Items for @entity_type with KEY @id. [@count of @total]", array(
        "@entity_type" => $rets_item['entity_type'],
        "@id" => $rets_item['key_value'],
        "@count" => $count,
        "@total" => $total,
      ), 'drush', 'success');
      if ($this->dc->connect($rets_item['conid']) && $success = $this->import_single_resource_media($rets_item)) {
        // Skipping this item.
        $this->MessageErrorLogger("Processed Media Items for @entity_type with KEY @id. [@count of @total]", array(
          "@entity_type" => $rets_item['entity_type'],
          "@id" => $rets_item['key_value'],
          "@count" => $count,
          "@total" => $total,
        ), 'drush', 'success');
        $this->media_queue->deleteItem($queue_item);
      }
      else {
        // Skipping this item.
        $this->MessageErrorLogger("Was not able to process the Media for @entity_type with KEY @id. Skipping item. [@count of @total]", array(
          "@entity_type" => $rets_item['entity_type'],
          "@id" => $rets_item['key_value'],
          "@count" => $count,
          "@total" => $total,
        ), 'drush', 'notice');
        $this->media_queue->deleteItem($queue_item);
      }
      $count++;
      $this->dc->disconnect();
    }
    return TRUE;
  }

  /**
   * Import all of the Media Objects data from RETS and assign to entity fields.
   *
   * @param $rets_item
   * @param $db_entity
   */
  public function import_single_resource_media($rets_item, &$db_entity = FALSE) {
    $mapped_fields = array();
    $media_field_items = array();

    if (!$db_entity) {
      $save_entity = TRUE;
    }

    if (!$db_entity && $entity_exists = $this->entityExists($rets_item['entity_type'], $rets_item['key_value'], NULL, $rets_item['conid'], $rets_item['cid'])) {
      $entity_exists = reset($entity_exists);
      $db_entity = entity_load($rets_item['entity_type'], array($entity_exists->id));
      $db_entity = reset($db_entity);
    }
    elseif (!$db_entity && !$entity_exists) {
      $this->MessageErrorLogger("No Property exists in the database with the Key value @key.", array('@key' => $rets_item['key_value']), 'drush', 'warning');
      return FALSE;
    }

    if ($db_entity) {
      if ($db_entity->media_hash != $rets_item['media_hash']) {
        $mappings = $this->dm->FetchMediaObjectFieldMappings($rets_item['conid'], $rets_item['cid'], $rets_item['rid']);
        if ($mappings) {
          foreach ($mappings as $f_name => $f_val) {
            if ($obj = $this->dm->FetchMediaObjects($rets_item['conid'], NULL, NULL, $f_val->media_obj_id)) {
              $obj = reset($obj);
              $mapped_fields[$f_val->field_name] = array(
                'field_name' => $f_val->field_name,
                'format' => $f_val->data['format'],
                'field_type' => $f_val->field_api_type,
                'obj_type' => $obj->type
              );
            }
          }
        }

        if (count($mapped_fields) && ($this->dc->is_connected() || (!$this->dc->is_connected() && $this->dc->connect($rets_item['conid'])))) {
          foreach ($mapped_fields as $m => $v) {
            $resource = $this->dm->FetchResource($rets_item['rid']);
            $format = ($v['format'] == 'data') ? 0 : 1;

            if (isset($mappings[$v['field_name']]->data['media_resource'])) {
              $field = db_select('drealty_field_mappings', 'm')
                ->fields('m', array('field_name'))
                ->condition('conid', $rets_item['conid'])
                ->condition('cid', $rets_item['cid'])
                ->condition('rid', $rets_item['rid'])
                ->condition('systemname', $mappings['field_high_res']->data['resource_field'])
                ->execute()->fetchField();

              if ($field && isset($db_entity->{$field}) && !empty($db_entity->{$field})) {
                $val = is_array($db_entity->{$field}) ? $db_entity->{$field}[LANGUAGE_NONE][0]['value'] : $db_entity->{$field};
                $media_resource = $this->dm->FetchResource($mappings[$v['field_name']]->data['media_resource']);
                $classes = $this->dm->FetchClasses($rets_item['conid'], $media_resource);
                $query = "({$mappings[$v['field_name']]->data['media_resource_field']}=$val)";
                if (!empty($mappings[$v['field_name']]->data['query'])) {
                  $query .= ',' . $mappings[$v['field_name']]->data['query'];
                }

                $options = array(
                  'Format' => 'COMPACT-DECODED',
                  'Limit' => "NONE",
                  'Count' => '0',
                );

                foreach ($classes as $c) {
                  $search = $this->dc->rets->SearchQuery($media_resource->systemname, $c->systemname, "$query", $options);
                  if (!$search) {
                    // @todo: log empty result ?
                  }
                  elseif ($search && $this->dc->rets->NumRows() > 0) {
                    while ($entity = $this->dc->rets->FetchRow($search)) {
                      $rets_media_items[] = $entity;
                    }
                  }
                }
                if ($rets_media_items) {
                  module_invoke_all('drealty_media_entity_process', array(
                    'media' => $rets_media_items,
                    'rets_item' => $rets_item,
                    'fieldname' => $v['field_name']
                  ));
                }
              }
            }
            elseif ($results = $this->dc->rets->GetObject($resource->systemname, $v['obj_type'], $rets_item['key_value'], '*', $format)) {
              $i = 0;
              if (count($results) == 1 && $results[0]['Success'] === FALSE) {
                continue;
              }
              else {
                $media_field_items[$m]['class_id'] = $rets_item['cid'];
                $media_field_items[$m]['entity_type'] = $rets_item['entity_type'];
                $media_field_items[$m] += $v;

                foreach ($results as $media) {
                  if ($media['Success'] !== FALSE) {
                    $media_field_items[$m]['items'][$i] = $media;
                  }
                  $i++;
                }
              }
            }
          }
          $this->dc->disconnect();
        }

        if (count($media_field_items)) {
          $this->set_media_field_data($media_field_items, $db_entity);
          $db_entity->media_hash = $rets_item['media_hash'];
          if (isset($save_entity)) {
            $db_entity->changed = time();
            $db_entity->save();
            module_invoke_all('entity_update', $db_entity, $rets_item['entity_type']);
            $this->MessageErrorLogger("Saved entity.", array(), 'drush', 'notice');
          }
          return TRUE;
        }
        elseif (isset($rets_media_items)) {
          $this->MessageErrorLogger("Media items have been found. Hook drealty_media_entity_process() needs to be implemented for processing.", array(), 'drush', 'notice');
          return FALSE;
        }
        else {
          $this->MessageErrorLogger("No Media items have been found. Nothing to process.", array(), 'drush', 'notice');
          return FALSE;
        }
      }
      else {
        $this->MessageErrorLogger("Media Hash did not change. Skipping the item.", array(), 'drush', 'notice');
        return FALSE;
      }
    }
  }

  /**
   * Function to handle the logic of what to do with expired listings.
   *
   * @param array $in_rets
   * @param array $conid
   * @param drealtyRetsClass $class
   */
  protected function handle_expired($in_rets, $entity_type, $conid, $class) {
    $results = db_select($entity_type, 'dl')
      ->fields('dl', array('id', 'rets_key'))
      ->condition('conid', $conid)
      ->condition('class', $class->cid)
      ->condition('active', 1)
      ->execute()
      ->fetchAllAssoc('rets_key');

    $diff = array_diff_key($results, $in_rets);

    foreach ($diff as $item) {
      $num_updated = db_update($entity_type)
        ->fields(array(
          'changed' => REQUEST_TIME,
          'active' => 0,
          'inactive_date' => REQUEST_TIME,
        ))
        ->condition('conid', $conid)
        ->condition('class', $class->cid)
        ->condition('rets_key', $item->rets_key)
        ->execute();
    }
  }

  /**
   * Calculate an md5 hash on the resulting listing used to determine if we need
   * to perform an update.
   *
   * @param array $items
   * @param $connection
   * @param $class
   *
   * @return string
   */
  protected function calculate_hash(array $items, $connection, $class, $forced_items = array()) {

    $cache = &drupal_static(__FUNCTION__);
    $tmp = '';

    if (!empty($forced_items)) {
      foreach ($forced_items as $i) {
        $tmp .= drupal_strtolower(trim($items[$i]));
      }
    }
    else {
      if (empty($cache[$connection->conid]) || empty($cache[$connection->conid][$class->cid]) || empty($forced_items)) {
        $field_mappings = db_select('drealty_field_mappings', 'dfm')
          ->fields('dfm')
          ->condition('conid', $connection->conid)
          ->condition('cid', $class->cid)
          ->condition('hash_exclude', FALSE)
          ->execute()
          ->fetchAll();

        $cache[$connection->conid][$class->cid] = $field_mappings;
      }
      $fields = $cache[$connection->conid][$class->cid];

      foreach ($fields as $field) {
        switch ($field->field_api_type) {
          case 'addressfield':
          case 'location':
          case 'geofield':
            if (!empty($field->data) && $data = unserialize($field->data)) {
              foreach ($data as $item) {
                $tmp .= drupal_strtolower(trim($items[$item]));
              }
            }
            break;

          default:
            $tmp .= drupal_strtolower(trim($items[$field->systemname]));
        }
      }
    }

    return md5($tmp);
  }

  /**
   * Get mapped fields from database.
   */
  public function get_fields($conid, $class_id) {
    $results = db_select('drealty_field_mappings', 'dfm')
      ->fields('dfm')
      ->condition('conid', $conid)
      ->condition('cid', $class_id)
      ->execute();

    $fields = array();
    foreach ($results as $result) {
      switch ($result->field_api_type) {
        case 'addressfield':
        case 'location':
        case 'geofield':
          if (!empty($result->data) && $data = unserialize($result->data)) {
            foreach ($data as $item) {
              $fields[] = $item;
            }
          }
          break;

        default:
          $fields[] = $result->systemname;
      }
    }
    return array_unique($fields);
  }

  /**
   * Set media mapped fields values.
   */
  protected function set_media_field_data($media_field_items, &$db_entity) {

    foreach ($media_field_items as $item) {
      $class = $this->dm->FetchClass($item['class_id']);
      $field_info = field_info_field($item['field_name']);
      $field_instance = field_info_instance($item['entity_type'], $item['field_name'], $class->bundle);

      if (in_array($item['field_type'], array('image', 'file'))) {
        $media_dir = file_default_scheme() . "://";
        $media_dir = !empty($field_instance['settings']['file_directory']) ? $media_dir . $field_instance['settings']['file_directory'] : $media_dir;

        if (!file_prepare_directory($media_dir, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY)) {
          watchdog("drealty_import", "Failed to create %directory.", array('%directory' => $media_dir), WATCHDOG_WARNING);
          return;
        }
        else {
          if (!is_dir($media_dir)) {
            watchdog("drealty_import", "Failed to locate %directory.", array('%directory' => $media_dir), WATCHDOG_WARNING);
            return;
          }
        }
      }

      try {
        switch ($item['field_type']) {
          case 'file':
          case 'image':
            $this->DeleteOldFieldFiles($db_entity, $item['field_name']);
            $db_entity->{$item['field_name']} = array();

            if (isset($item['items']) && count($item['items'])) {
              foreach ($item['items'] as $media) {
                if ($item['field_type'] == 'image') {
                  $filename = $media['Content-ID'] . '-' . REQUEST_TIME . '-' . $media['Object-ID'] . '.jpg';
                }
                else {
                  $type = explode('/', $media['Content-Type']);
                  $filename = $media['Content-ID'] . '-' . REQUEST_TIME . '-' . rawurlencode($media['Content-Description']) . '.' . $type[1];
                }
                $filepath = !empty($field_instance['settings']['file_directory']) ? $media_dir . '/' . $filename : $media_dir . $filename;

                if (strlen($media['Data']) > 170 && $item['format'] == 'data') {
                  // Ensure that there is enough data to actually make a file.
                  $file = file_save_data($media['Data'], $filepath, FILE_EXISTS_REPLACE);
                }
                elseif (!empty($media['Location']) && $item['format'] == 'url') {
                  $file = system_retrieve_file($media['Location'], $filepath, $managed = TRUE, $replace = FILE_EXISTS_REPLACE);
                }

                if ($file) {
                  if ($item['field_type'] == 'image') {
                    $file->alt = 'Resource #' . $db_entity->rets_id . ': image #' . $media['Object-ID'];
                    $file->title = !empty($media['Content-Description']) ? $media['Content-Description'] : $file->alt;
                  }
                  else {
                    $file->description = $media['Content-Description'];
                    $file->display = 1;
                  }

                  $db_entity->{$item['field_name']}[LANGUAGE_NONE][] = (array) $file;
                }
              }
            }
            break;

          case 'text_with_summary':
          case 'text_long':
            $db_entity->{$item['field_name']} = array();
            if (isset($item['items']) && count($item['items'])) {
              foreach ($item['items'] as $media) {
                $db_entity->{$item['field_name']}[LANGUAGE_NONE][]['value'] = !empty($media['Data']) ? $media['Data'] : NULL;
              }
            }
            break;

          case 'text':
            $db_entity->{$item['field_name']} = array();
            if (isset($item['items']) && count($item['items'])) {
              foreach ($item['items'] as $media) {
                if (!empty($media['Location'])) {
                  $db_entity->{$item['field_name']}[LANGUAGE_NONE][]['value'] = $media['Location'];
                }
              }
            }
            break;

          case 'link_field':
            $db_entity->{$item['field_name']} = array();
            if (isset($item['items']) && count($item['items'])) {
              foreach ($item['items'] as $media) {
                $link_title = ($field_instance['settings']['title'] == 'value') ? $field_instance['settings']['title_value'] : $field_instance['settings']['title_value'];
                if (!empty($media['Location'])) {
                  $db_entity->{$item['field_name']}[LANGUAGE_NONE][] = array(
                    'url' => $media['Location'],
                    'title' => !empty($media['Content-Description']) ? $media['Content-Description'] : (empty($link_title) ? 'Media URL' : $link_title),
                    'attributes' => serialize(array('title' => !empty($field_instance['settings']['attributes']['title']) ? $field_instance['settings']['attributes']['title'] : (empty($media['Content-Description']) ? 'Media URL' : $media['Content-Description']))),
                  );
                }
              }
            }
            break;
        }
      }
      catch (Exception $e) {
        $this->MessageErrorLogger('Drealty Error setting the values from Media Objects: ' . $e->getMessage(), array(), 'both', WATCHDOG_ERROR);
      }
    }
  }

  /**
   * Set basic RETS mapped fields values.
   */
  public function set_field_data(&$item, $rets_item, $field_mappings, $entity_type, $class) {

    foreach ($field_mappings as $mapping) {
      $item->{$mapping->field_name} = array();

      switch ($mapping->field_api_type) {
        case 'addressfield':
          // Get the default country code if one exists for the address.
          $field_info = field_info_instance($entity_type, $mapping->field_name, $class->bundle);
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['country'] = isset($field_info['default_value'][0]['country']) ? $field_info['default_value'][0]['country'] : 'US';
          if (isset($mapping->data['address_1']) && isset($rets_item[$mapping->data['address_1']])) {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['thoroughfare'] = $rets_item[$mapping->data['address_1']];
            if (isset($mapping->data['address_1_1']) && isset($rets_item[$mapping->data['address_1_1']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['thoroughfare'] .= " " . $rets_item[$mapping->data['address_1_1']];
            }
            if (isset($mapping->data['address_1_2']) && isset($rets_item[$mapping->data['address_1_2']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['thoroughfare'] .= " " . $rets_item[$mapping->data['address_1_2']];
            }
            if (isset($mapping->data['address_1_3']) && isset($rets_item[$mapping->data['address_1_3']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['thoroughfare'] .= " " . $rets_item[$mapping->data['address_1_3']];
            }
          }
          else {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['thoroughfare'] = NULL;
          }
          if (isset($mapping->data['address_2']) && isset($rets_item[$mapping->data['address_2']])) {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['premise'] = $rets_item[$mapping->data['address_2']];
            if (isset($mapping->data['address_2_1']) && isset($rets_item[$mapping->data['address_2_1']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['premise'] .= " " . $rets_item[$mapping->data['address_2_1']];
            }
            if (isset($mapping->data['address_2_2']) && isset($rets_item[$mapping->data['address_2_2']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['premise'] .= " " . $rets_item[$mapping->data['address_2_2']];
            }
            if (isset($mapping->data['address_2_3']) && isset($rets_item[$mapping->data['address_2_3']])) {
              $item->{$mapping->field_name}[LANGUAGE_NONE][0]['premise'] .= " " . $rets_item[$mapping->data['address_2_3']];
            }
          }
          else {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['premise'] = NULL;
          }
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['locality'] = isset($mapping->data['city']) ? $rets_item[$mapping->data['city']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['administrative_area'] = isset($mapping->data['state']) ? $rets_item[$mapping->data['state']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['sub_administrative_area'] = isset($mapping->data['county']) ? $rets_item[$mapping->data['county']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['postal_code'] = isset($mapping->data['zip']) ? $rets_item[$mapping->data['zip']] : NULL;
          break;

        case 'location':
          // Get the default country code if one exists for the address.
          $field_info = field_info_instance($entity_type, $mapping->field_name, $class->bundle);
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['country'] = isset($field_info['default_value'][0]['country']) ? $field_info['default_value'][0]['country'] : 'CA';
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['street'] = isset($rets_item[$mapping->data['street']]) ? $rets_item[$mapping->data['street']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['additional'] = isset($mapping->data['additional']) ? $rets_item[$mapping->data['additional']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['city'] = isset($mapping->data['city']) ? $rets_item[$mapping->data['city']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['stateorprovince'] = isset($mapping->data['stateorprovince']) ? $rets_item[$mapping->data['stateorprovince']] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['postal_code'] = isset($mapping->data['postal_code']) ? $rets_item[$mapping->data['postal_code']] : NULL;
          break;

        case 'geofield':
          if (isset($rets_item[$mapping->data['lat']]) && isset($rets_item[$mapping->data['lon']])
            && is_numeric($rets_item[$mapping->data['lat']]) && is_numeric($rets_item[$mapping->data['lon']])
          ) {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['lat'] = $rets_item[$mapping->data['lat']];
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['lon'] = $rets_item[$mapping->data['lon']];
          }
          break;

        case 'entityreference':
          if (!empty($mapping->data['field_value']) && isset($rets_item[$mapping->data['field_value']])) {
            $field_info = field_info_field($mapping->field_name);
            $multi_value = _drealty_check_rets_field_cardinality($this->dc->FetchConnection($class->conid), $class->rid, $class->systemname, $mapping->data['field_value']);
            if ($multi_value != 'LookupMulti' && $field_info) {
              $query = new EntityFieldQuery();
              $query->entityCondition('entity_type', $field_info['settings']['target_type']);
              if (count($field_info['settings']['handler_settings']['target_bundles'])) {
                $query->entityCondition('bundle', array_keys($field_info['settings']['handler_settings']['target_bundles']), "IN");
              }
              $query->fieldCondition($mapping->data['field_name'], 'value', $rets_item[$mapping->data['field_value']], 'LIKE');
              $result = $query->execute();

              if (!empty($result) && isset($result[$field_info['settings']['target_type']])) {
                $ids = array_keys($result[$field_info['settings']['target_type']]);
                foreach ($ids as $id) {
                  $item->{$mapping->field_name}[LANGUAGE_NONE][]['target_id'] = $id;
                }
              }
            }
          }
          break;

        case 'text_long':
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = isset($rets_item[$mapping->systemname]) ? $rets_item[$mapping->systemname] : NULL;
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['format'] = 'plain_text';
          break;

        case 'number_integer':
        case 'number_decimal':
        case 'number_float':
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = empty($rets_item[$mapping->systemname]) ? 0 : is_numeric($rets_item[$mapping->systemname]) ? $rets_item[$mapping->systemname] : NULL;
          break;

        case 'list_boolean':
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = in_array($rets_item[$mapping->systemname], array('true', 'True', 'TRUE', 'yes', 'Yes', 'y', 'Y', '1', 'on', 'On', 'ON', TRUE, 1), TRUE) ? 1 : 0;
          break;

        case 'taxonomy_term_reference':
          if (isset($rets_item[$mapping->systemname]) && !empty($rets_item[$mapping->systemname])) {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = $rets_item[$mapping->systemname];
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['tid'] = NULL;
          }
          break;

        case 'drealty':
          $item->{$mapping->field_name} = isset($rets_item[$mapping->systemname]) ? $rets_item[$mapping->systemname] : NULL;
          break;

        case 'datetime':
          $item->{$mapping->field_name} = array();
          if (isset($rets_item[$mapping->systemname]) && !empty($rets_item[$mapping->systemname])) {
            $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = $rets_item[$mapping->systemname];
          }
          break;

        default:
          $item->{$mapping->field_name}[LANGUAGE_NONE][0]['value'] = isset($rets_item[$mapping->systemname]) ? $rets_item[$mapping->systemname] : NULL;
      }
    }
  }

  /**
   * Delete files attached to specific field on the entity.
   */
  public function DeleteOldFieldFiles($entity, $field_name) {
    // Delete out any existing images.
    if (isset($entity->{$field_name}[LANGUAGE_NONE])) {
      foreach ($entity->{$field_name}[LANGUAGE_NONE] as $key => $file) {
        $file = file_load($file['fid']);
        if (!empty($file)) {
          file_delete($file);
        }
      }
    }
  }

  /**
   * Runs a Search query against RETS, e.g. on Drealty "Developer Tools' page.
   */
  function perform_query(drealtyConnectionEntity $connection, $resource, $class, $query) {
    $items = array();
    $rets = $this->dc->rets;
    $limit = $class->chunk_size;
    if ($limit == 0) {
      $limit = 'NONE';
    }
    $count = 0;

    $this->dc->rets->SetParam("offset_support", TRUE);

    if ($this->dc->connect($connection->conid)) {
      $optional_params = array(
        'Format' => 'COMPACT-DECODED',
        'Limit' => "$limit",
      );

      // Do the actual search.
      $search = $rets->SearchQuery($resource->systemname, $class->systemname, $query, $optional_params);

      // Loop through the search results.
      while ($listing = $rets->FetchRow($search)) {
        if ($count < 10) {
          $items[] = $listing;
        }
        $count++;
      }

      $rets->FreeResult($search);

      $this->dc->disconnect();

      return $items;
    }
    else {
      $error = $rets->Error();
      watchdog('drealty', "drealty encountered an error: (Type: @type Code: @code Msg: @text)", array(
        "@type" => $error['type'],
        "@code" => $error['code'],
        "@text" => $error['text']
      ), WATCHDOG_ERROR);
    }
  }

  /**
   * Message Logging for both - CLI and UI invocation.
   */
  public function MessageErrorLogger($msg, $options = array(), $storage = 'both', $error_type = WATCHDOG_ERROR) {
    if (drupal_is_cli() && function_exists('drush_main') && in_array($storage, array('both', 'drush'))) {
      if (is_integer($error_type)) {
        switch ($error_type) {
          case WATCHDOG_NOTICE:
            $error_type = 'notice';
            break;

          case WATCHDOG_ERROR:
            $error_type = 'error';
            break;

          case WATCHDOG_WARNING:
            $error_type = 'warning';
            break;

          case WATCHDOG_INFO:
            $error_type = 'success';
            break;
        }
      }
      drush_log(dt($msg, $options), $error_type);
    }
    if (!drupal_is_cli() && in_array($storage, array('both', 'watchdog'))) {
      watchdog('drealty', $msg, $options, $error_type);
    }
  }

  /**
   * EntityFieldQuery to check if Drealty entity still exists in the database.
   */
  public function entityExists($entity_type, $key = NULL, $mls = NULL, $conid = NULL, $cid = NULL) {
    if (!$key && !$mls) {
      return FALSE;
    }
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', $entity_type);
    if (!empty($key)) {
      $query->propertyCondition('rets_key', $key);
    }
    if (!empty($mls)) {
      $query->propertyCondition('rets_id', $mls);
    }
    if (!empty($conid)) {
      $query->propertyCondition('conid', $conid);
    }
    if (!empty($cid)) {
      $query->propertyCondition('class', $cid);
    }
    $result = $query->execute();
    return $result ? $result[$entity_type] : FALSE;
  }

}

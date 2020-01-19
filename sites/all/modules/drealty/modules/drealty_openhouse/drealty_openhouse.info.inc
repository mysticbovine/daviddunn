<?php

/**
 *
 * Implements hook_entity_property_info
 *
 */
function drealty_openhouse_entity_property_info() {
  $info = array();
  $properties = &$info['drealty_openhouse']['properties'];

  $properties['id'] = array(
    'label' => t('Drealty OpenHouse Id'),
    'type' => 'integer',
    'description' => t('The unique ID of the drealty office'),
    'schema field' => 'id',
  );
  $properties['type'] = array(
    'label' => t('Drealty OpenHouse Type'),
    'type' => 'text',
    'description' => t('The drealty property type'),
    'schema field' => 'type',
  );
  $properties['hash'] = array(
    'label' => t('Drealty hash'),
    'type' => 'text',
    'description' => t('The crc32 hash value of the listing data.'),
    'schema field' => 'hash',
    'getter callback' => 'entity_property_verbatim_get',
    'setter callback' => 'entity_property_verbatim_set',
  );
  $properties['rets_key'] = array(
    'label' => t('Drealty OpenHouse RETS Key'),
    'type' => 'text',
    'description' => t('The RETS System Unique ID for the drealty office'),
    'schema field' => 'rets_key',
  );
  $properties['rets_id'] = array(
    'label' => t('Drealty OpenHouse RETS ID'),
    'type' => 'text',
    'description' => t('The RETS Public Unique ID for the drealty office'),
    'schema field' => 'rets_id',
  );
  $properties['label'] = array(
    'label' => t('Drealty OpenHouse Label'),
    'type' => 'text',
    'description' => t('The drealty entity label.'),
    'schema field' => 'label',
    'getter callback' => 'entity_property_verbatim_get',
    'setter callback' => 'entity_property_verbatim_set',
  );
  return $info;
}
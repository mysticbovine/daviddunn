<?php

/**
 * @file
 * Adds Filters to Search API indexing implementation.
 */

class DrealtySearchAPIAlter extends SearchApiAbstractAlterCallback {

  /**
   * Only support indexes containing entities.
   *
   * @see SearchApiAlterCallbackInterface::supportsIndex()
   */
  public function supportsIndex(SearchApiIndex $index) {
    return (bool) entity_get_info($index->item_type);
  }

  /**
   * If a realestate is not active, don't even index it.
   *
   * @param array $items
   */
  public function alterItems(array &$items) {
    // Prevent session information from being saved while indexing.
    drupal_save_session(FALSE);

    // Force the current user to anonymous to prevent access bypass in search
    // indexes.
    $original_user = $GLOBALS['user'];
    $GLOBALS['user'] = drupal_anonymous_user();

    $type = $this->index->item_type;
    if (in_array($type, array(
      'drealty_listing',
      'drealty_agent',
      'drealty_office',
    ))) {
      foreach ($items as $id => &$item) {
        if ($item->active == 0) {
          unset($items[$id]);
        }
      }
    }

    // Restore the user.
    $GLOBALS['user'] = $original_user;
    drupal_save_session(TRUE);
  }

}

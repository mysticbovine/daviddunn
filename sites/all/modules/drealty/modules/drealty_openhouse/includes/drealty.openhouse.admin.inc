<?php

class DrealtyOpenHouseUIController extends EntityDefaultUIController {

  public function hook_menu() {

    $id_count = count(explode('/', $this->path));
    $wildcard = isset($this->entityInfo['admin ui']['menu wildcard']) ? $this->entityInfo['admin ui']['menu wildcard'] : '%' . $this->entityType;

    $items['drealty_openhouse/add'] = array(
      'title' => 'Add dRealty Listing',
      'title callback' => 'check_plain',
      'page callback' => 'drealty_openhouse_form_wrapper',
      'page arguments' => array('add', drealty_openhouse_create(array('rets_imported' => FALSE))),
      'access arguments' => array('create drealty listing'),
      'file' => 'drealty.openhouse.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']) . '/includes',
      'type' => MENU_NORMAL_ITEM,
    );
    

    $items['drealty_openhouse/' . $wildcard] = array(
      'title callback' => 'drealty_openhouse_page_title',
      'title arguments' => array(1),
      'page callback' => 'drealty_openhouse_view',
      'page arguments' => array(1),
      'access callback' => 'drealty_access',
      'access arguments' => array('view', 1),
      'type' => MENU_CALLBACK,
    );

    $items['drealty_openhouse/' . $wildcard . '/view'] = array(
      'title' => 'View',
      'type' => MENU_DEFAULT_LOCAL_TASK,
      'weight' => -10,
    );

    // Loading and editing model entities
    $items['drealty_openhouse/' . $wildcard . '/edit'] = array(
      'title' => 'Edit',
      'page callback' => 'drealty_openhouse_form_wrapper',
      'page arguments' => array('edit', 1),
      'access callback' => 'drealty_access',
      'access arguments' => array('edit', 1),
      'weight' => 0,
      'context' => MENU_CONTEXT_PAGE | MENU_CONTEXT_INLINE,
      'type' => MENU_LOCAL_TASK,
      'file' => 'drealty.openhouse.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']) . '/includes'
    );

    $items['drealty_openhouse/' . $wildcard . '/delete'] = array(
      'title' => 'Delete',
      'page callback' => 'drealty_openhouse_form_wrapper',
      'page arguments' => array('delete', 1),
      'access callback' => 'drealty_access',
      'access arguments' => array('delete', 1),
      'type' => MENU_LOCAL_TASK,
      'context' => MENU_CONTEXT_INLINE,
      'weight' => 10,
      'file' => 'drealty.openhouse.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']) . '/includes'
    );

    if (module_exists('devel')) {
      $items['drealty_openhouse/' . $wildcard . '/devel'] = array(
        'title' => 'Devel',
        'page callback' => 'devel_load_object',
        'page arguments' => array('node', 1),
        'access arguments' => array('access devel information'),
        'type' => MENU_LOCAL_TASK,
        'file' => 'devel.pages.inc',
        'file path' => drupal_get_path('module', 'devel'),
        'weight' => 100,
      );
      $items['drealty_openhouse/' . $wildcard . '/devel/load'] = array(
        'title' => 'Load',
        'type' => MENU_DEFAULT_LOCAL_TASK,
      );
      $items['drealty_openhouse/' . $wildcard . '/devel/render'] = array(
        'title' => 'Render',
        'page callback' => 'devel_render_object',
        'page arguments' => array('drealty_openhouse', 1),
        'access arguments' => array('access devel information'),
        'file' => 'devel.pages.inc',
        'file path' => drupal_get_path('module', 'devel'),
        'type' => MENU_LOCAL_TASK,
        'weight' => 100,
      );
    }

    $items['drealty_openhouse/' . $wildcard . '/rets_refresh'] = array(
      'title' => 'Update RETS Data',
      'page callback' => 'drupal_get_form',
      'page arguments' => array('drealty_update_single_listing', 1),
      'access arguments' => array('access drealty admin'),
      'type' => MENU_LOCAL_TASK,
      'context' => MENU_CONTEXT_INLINE | MENU_CONTEXT_PAGE,
      'weight' => 99,
    );

    return $items;
  }

}


/**
 * Page callback for adding listings.
 */
function drealty_openhouse_add_page() {
  $item = menu_get_item();
  if ($content = system_admin_menu_block($item)) {
    $output = theme('admin_block_content', array('content' => $content));
  } else {
    $output = t('You do not have any administrative items.');
  }
  return $output;
}

/**
 * Page callback form managing listings.
 */
function drealty_openhouse_admin($type = 'new') {
  $edit = $_POST;
  if (isset($edit['operation']) && ($edit['operation'] == 'delete') && isset($edit['listings']) && $edit['listings']) {
    return drupal_get_form('drealty_openhouse_multiple_delete_confirm');
  }
}

/**
 * Utility function to get the listing edit form.
 *
 * @param $op
 *   The operation the form should perform.
 * @param $listing
 *  The listing to perform the operation on.
 */
function drealty_openhouse_form_wrapper($op, $listing) {
  if ($op == 'create' || $op == 'add') {
    drupal_set_title(t('<em>@op @type</em> listing', array('@op' => ucfirst($op), '@type' => $listing->type)), PASS_THROUGH);
  } else {
    drupal_set_title(t('<em>@op @type</em> listing @title : #@rets_id', array('@op' => ucfirst($op), '@type' => $listing->type, '@title' => $listing->label, '@rets_id' => $listing->rets_key)), PASS_THROUGH);
  }
  return drupal_get_form('drealty_openhouse_edit_form', $op, $listing);
}

/**
 * Form builder for the listing form.
 */
function drealty_openhouse_edit_form($form, &$form_state, $op, $listing) {

  $form_state['listing'] = $listing;


  if ($listing->rets_imported) {
    
  }

  field_attach_form('drealty_openhouse', $listing, $form, $form_state);

  $form['actions'] = array(
    '#type' => 'container',
    '#attributes' => array('class' => array('form-actions')),
    '#weight' => 400,
  );

  // We add the form's #submit array to this button along with the actual submit
  // handler to preserve any submit handlers added by a form callback_wrapper.
  $submit = array();

  if (!empty($form['#submit'])) {
    $submit += $form['#submit'];
  }

  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save listing'),
    '#submit' => $submit + array('drealty_openhouse_edit_form_submit'),
  );

  if (!empty($listing->name) && !$listing->rets_imported) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete listing'),
      '#suffix' => l(t('Cancel'), 'admin/content/drealty_openhouses'),
      '#submit' => $submit + array('drealty_openhouse_form_submit_delete'),
      '#weight' => 45,
    );
  }

  return $form;
}

/**
 * Submit handler for the listing form.
 */
function drealty_openhouse_edit_form_submit($form, &$form_state) {
  // grab the listing
  $listing = $form_state['listing'];
  // attach any fields from the field api
  field_attach_submit('drealty_openhouse', $listing, $form, $form_state);
  // save the listing
  $listing->save();
  // redirect back to the content list
  $form_state['redirect'] = 'admin/content/drealty_openhouses';
}
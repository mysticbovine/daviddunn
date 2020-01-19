<?php

/**
 * Implementation hook_views_default_views().
 */

function drealty_views_default_views() {
	static $views;
  if (isset($views)) {
    return $views;
  }

  // No views cached, generate the default views array and return
  $views = array();
  $views += _drealty_default_views_listings();
  return $views;
}

/**
 * Local "internal" function as a wrapper around Listings view
 */
function _drealty_default_views_listings() {

	$view = new view();
	$view->name = 'drealty_listings_admin';
	$view->description = '';
	$view->tag = 'default';
	$view->base_table = 'drealty_listing';
	$view->human_name = 'Drealty Listings Admin';
	$view->core = 7;
	$view->api_version = '3.0';
	$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

	/* Display: Master */
	$handler = $view->new_display('default', 'Master', 'default');
	$handler->display->display_options['title'] = 'Drealty Listings';
	$handler->display->display_options['use_ajax'] = TRUE;
	$handler->display->display_options['use_more_always'] = FALSE;
	$handler->display->display_options['access']['type'] = 'perm';
	$handler->display->display_options['access']['perm'] = 'access drealty admin';
	$handler->display->display_options['cache']['type'] = 'none';
	$handler->display->display_options['query']['type'] = 'views_query';
	$handler->display->display_options['exposed_form']['type'] = 'basic';
	$handler->display->display_options['pager']['type'] = 'full';
	$handler->display->display_options['pager']['options']['items_per_page'] = '30';
	$handler->display->display_options['style_plugin'] = 'table';
	$handler->display->display_options['style_options']['columns'] = array(
		'id' => 'id',
		'label' => 'label',
		'rets_key' => 'rets_key',
		'rets_id' => 'rets_id',
		'field_rets_listing_modified' => 'field_rets_listing_modified',
		'field_drealty_status' => 'field_drealty_status',
		'active' => 'active',
		'inactive_date' => 'inactive_date',
		'nothing' => 'nothing',
		'nothing_1' => 'nothing',
		'nothing_2' => 'nothing',
	);
	$handler->display->display_options['style_options']['default'] = 'id';
	$handler->display->display_options['style_options']['info'] = array(
		'id' => array(
			'sortable' => 1,
			'default_sort_order' => 'desc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'label' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'rets_key' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'rets_id' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'field_rets_listing_modified' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'field_drealty_status' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'active' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'inactive_date' => array(
			'sortable' => 1,
			'default_sort_order' => 'asc',
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'nothing' => array(
			'align' => '',
			'separator' => '&nbsp;&nbsp;',
			'empty_column' => 0,
		),
		'nothing_1' => array(
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
		'nothing_2' => array(
			'align' => '',
			'separator' => '',
			'empty_column' => 0,
		),
	);
	/* Field: Drealty Listing: Drealty Listing Id */
	$handler->display->display_options['fields']['id']['id'] = 'id';
	$handler->display->display_options['fields']['id']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['id']['field'] = 'id';
	$handler->display->display_options['fields']['id']['label'] = 'Internal Id';
	$handler->display->display_options['fields']['id']['separator'] = '';
	/* Field: Drealty Listing: Drealty Listing Label */
	$handler->display->display_options['fields']['label']['id'] = 'label';
	$handler->display->display_options['fields']['label']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['label']['field'] = 'label';
	$handler->display->display_options['fields']['label']['label'] = 'Internal Label';
	/* Field: Drealty Listing: Drealty Listing RETS Key */
	$handler->display->display_options['fields']['rets_key']['id'] = 'rets_key';
	$handler->display->display_options['fields']['rets_key']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['rets_key']['field'] = 'rets_key';
	$handler->display->display_options['fields']['rets_key']['label'] = 'RETS Key';
	/* Field: Drealty Listing: Drealty Listing RETS ID */
	$handler->display->display_options['fields']['rets_id']['id'] = 'rets_id';
	$handler->display->display_options['fields']['rets_id']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['rets_id']['field'] = 'rets_id';
	$handler->display->display_options['fields']['rets_id']['label'] = 'RETS ID';
	/* Field: Drealty Listing: RETS Listing Modified */
	$handler->display->display_options['fields']['field_rets_listing_modified']['id'] = 'field_rets_listing_modified';
	$handler->display->display_options['fields']['field_rets_listing_modified']['table'] = 'field_data_field_rets_listing_modified';
	$handler->display->display_options['fields']['field_rets_listing_modified']['field'] = 'field_rets_listing_modified';
	$handler->display->display_options['fields']['field_rets_listing_modified']['label'] = 'MLS Last Modified';
	$handler->display->display_options['fields']['field_rets_listing_modified']['settings'] = array(
		'format_type' => 'medium',
		'custom_date_format' => '',
		'fromto' => 'both',
		'multiple_number' => '',
		'multiple_from' => '',
		'multiple_to' => '',
		'show_remaining_days' => 0,
	);
	/* Field: Drealty Listing: Status */
	$handler->display->display_options['fields']['field_drealty_status']['id'] = 'field_drealty_status';
	$handler->display->display_options['fields']['field_drealty_status']['table'] = 'field_data_field_drealty_status';
	$handler->display->display_options['fields']['field_drealty_status']['field'] = 'field_drealty_status';
	$handler->display->display_options['fields']['field_drealty_status']['label'] = 'MLS Status';
	/* Field: Drealty Listing: Drealty Active Status */
	$handler->display->display_options['fields']['active']['id'] = 'active';
	$handler->display->display_options['fields']['active']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['active']['field'] = 'active';
	$handler->display->display_options['fields']['active']['label'] = 'Active in MLS ?';
	$handler->display->display_options['fields']['active']['separator'] = '';
	/* Field: Drealty Listing: Drealty Inactive Date */
	$handler->display->display_options['fields']['inactive_date']['id'] = 'inactive_date';
	$handler->display->display_options['fields']['inactive_date']['table'] = 'drealty_listing';
	$handler->display->display_options['fields']['inactive_date']['field'] = 'inactive_date';
	$handler->display->display_options['fields']['inactive_date']['label'] = 'Inactive Date (timestamp)';
	$handler->display->display_options['fields']['inactive_date']['separator'] = '';
	/* Field: Edit Link */
	$handler->display->display_options['fields']['nothing']['id'] = 'nothing';
	$handler->display->display_options['fields']['nothing']['table'] = 'views';
	$handler->display->display_options['fields']['nothing']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing']['ui_name'] = 'Edit Link';
	$handler->display->display_options['fields']['nothing']['label'] = 'Actions';
	$handler->display->display_options['fields']['nothing']['alter']['text'] = '<a href="/drealty_listing/[id]/edit?destination=/admin/content/drealty_listings">Edit</a>';
	/* Field: Delete Link */
	$handler->display->display_options['fields']['nothing_1']['id'] = 'nothing_1';
	$handler->display->display_options['fields']['nothing_1']['table'] = 'views';
	$handler->display->display_options['fields']['nothing_1']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing_1']['ui_name'] = 'Delete Link';
	$handler->display->display_options['fields']['nothing_1']['label'] = 'Delete';
	$handler->display->display_options['fields']['nothing_1']['alter']['text'] = '<a href="/drealty_listing/[id]/delete?destination=/admin/content/drealty_listings">Delete</a>';
	/* Field: RETS refresh link */
	$handler->display->display_options['fields']['nothing_2']['id'] = 'nothing_2';
	$handler->display->display_options['fields']['nothing_2']['table'] = 'views';
	$handler->display->display_options['fields']['nothing_2']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing_2']['ui_name'] = 'RETS refresh link';
	$handler->display->display_options['fields']['nothing_2']['label'] = 'Refresh RETS data';
	$handler->display->display_options['fields']['nothing_2']['alter']['text'] = '<a href="/drealty_listing/[id]/rets_refresh?destination=/admin/content/drealty_listings">Refresh RETS data</a>';
	/* Filter criterion: Drealty Listing: Drealty Active Status */
	$handler->display->display_options['filters']['active']['id'] = 'active';
	$handler->display->display_options['filters']['active']['table'] = 'drealty_listing';
	$handler->display->display_options['filters']['active']['field'] = 'active';
	$handler->display->display_options['filters']['active']['exposed'] = TRUE;
	$handler->display->display_options['filters']['active']['expose']['operator_id'] = 'active_op';
	$handler->display->display_options['filters']['active']['expose']['label'] = 'Drealty Active Status';
	$handler->display->display_options['filters']['active']['expose']['operator'] = 'active_op';
	$handler->display->display_options['filters']['active']['expose']['identifier'] = 'active';
	$handler->display->display_options['filters']['active']['is_grouped'] = TRUE;
	$handler->display->display_options['filters']['active']['group_info']['label'] = 'Active in MLS ?';
	$handler->display->display_options['filters']['active']['group_info']['identifier'] = 'active';
	$handler->display->display_options['filters']['active']['group_info']['group_items'] = array(
		1 => array(
			'title' => 'Yes',
			'operator' => '=',
			'value' => array(
				'value' => '1',
				'min' => '',
				'max' => '',
			),
		),
		2 => array(
			'title' => 'No',
			'operator' => '=',
			'value' => array(
				'value' => '0',
				'min' => '',
				'max' => '',
			),
		),
	);
	/* Filter criterion: Drealty Listing: Drealty Listing RETS ID */
	$handler->display->display_options['filters']['rets_id']['id'] = 'rets_id';
	$handler->display->display_options['filters']['rets_id']['table'] = 'drealty_listing';
	$handler->display->display_options['filters']['rets_id']['field'] = 'rets_id';
	$handler->display->display_options['filters']['rets_id']['exposed'] = TRUE;
	$handler->display->display_options['filters']['rets_id']['expose']['operator_id'] = 'rets_id_op';
	$handler->display->display_options['filters']['rets_id']['expose']['label'] = 'RETS ID';
	$handler->display->display_options['filters']['rets_id']['expose']['description'] = 'MLS Number (e.g. 765382)';
	$handler->display->display_options['filters']['rets_id']['expose']['operator'] = 'rets_id_op';
	$handler->display->display_options['filters']['rets_id']['expose']['identifier'] = 'rets_id';
	$handler->display->display_options['filters']['rets_id']['expose']['remember_roles'] = array(
		2 => '2',
		1 => 0,
		3 => 0,
		6 => 0,
		5 => 0,
		7 => 0,
	);
	/* Filter criterion: Drealty Listing: Drealty Listing RETS Key */
	$handler->display->display_options['filters']['rets_key']['id'] = 'rets_key';
	$handler->display->display_options['filters']['rets_key']['table'] = 'drealty_listing';
	$handler->display->display_options['filters']['rets_key']['field'] = 'rets_key';
	$handler->display->display_options['filters']['rets_key']['exposed'] = TRUE;
	$handler->display->display_options['filters']['rets_key']['expose']['operator_id'] = 'rets_key_op';
	$handler->display->display_options['filters']['rets_key']['expose']['label'] = 'RETS Key';
	$handler->display->display_options['filters']['rets_key']['expose']['description'] = 'MLS Unique Key';
	$handler->display->display_options['filters']['rets_key']['expose']['operator'] = 'rets_key_op';
	$handler->display->display_options['filters']['rets_key']['expose']['identifier'] = 'rets_key';
	$handler->display->display_options['filters']['rets_key']['expose']['remember_roles'] = array(
		2 => '2',
		1 => 0,
		3 => 0,
		6 => 0,
		5 => 0,
		7 => 0,
	);

	/* Display: Page */
	$handler = $view->new_display('page', 'Page', 'page');
	$handler->display->display_options['path'] = 'admin/content/drealty_listings';
	$handler->display->display_options['menu']['type'] = 'tab';
	$handler->display->display_options['menu']['title'] = 'Drealty Listings';
	$handler->display->display_options['menu']['weight'] = '0';
	$handler->display->display_options['menu']['name'] = 'management';
	$handler->display->display_options['menu']['context'] = 0;
	$handler->display->display_options['menu']['context_only_inline'] = 0;
	
	return array($view->name => $view);
}
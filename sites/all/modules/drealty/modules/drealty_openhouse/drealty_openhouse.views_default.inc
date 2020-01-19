<?php

/**
 * Implementation hook_views_default_views().
 */

function drealty_openhouse_views_default_views() {
	static $views;
  if (isset($views)) {
    return $views;
  }

  // No views cached, generate the default views array and return
  $views = array();
  $views += _drealty_openhouse_default_views_listings();
  return $views;
}

/**
 * Local "internal" function as a wrapper around Open Houses view
 */
function _drealty_openhouse_default_views_listings() {

	$view = new view();
	$view->name = 'drealty_openhouse_admin';
	$view->description = '';
	$view->tag = 'default';
	$view->base_table = 'drealty_openhouse';
	$view->human_name = 'Drealty Open Houses Admin';
	$view->core = 7;
	$view->api_version = '3.0';
	$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

	/* Display: Master */
	$handler = $view->new_display('default', 'Master', 'default');
	$handler->display->display_options['title'] = 'Drealty Open Houses';
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
		'active' => 'active',
		'label' => 'label',
		'rets_id' => 'rets_id',
		'rets_key' => 'rets_key',
		'nothing' => 'nothing',
		'nothing_1' => 'nothing',
		'nothing_2' => 'nothing',
	);
	$handler->display->display_options['style_options']['default'] = '-1';
	$handler->display->display_options['style_options']['info'] = array(
		'id' => array(
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
		'label' => array(
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
		'rets_key' => array(
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
	/* Field: Drealty OpenHouse: Drealty OpenHouse Id */
	$handler->display->display_options['fields']['id']['id'] = 'id';
	$handler->display->display_options['fields']['id']['table'] = 'drealty_openhouse';
	$handler->display->display_options['fields']['id']['field'] = 'id';
	$handler->display->display_options['fields']['id']['label'] = 'Internal Id';
	/* Field: Drealty OpenHouse: Drealty OpenHouse Label */
	$handler->display->display_options['fields']['label']['id'] = 'label';
	$handler->display->display_options['fields']['label']['table'] = 'drealty_openhouse';
	$handler->display->display_options['fields']['label']['field'] = 'label';
	$handler->display->display_options['fields']['label']['label'] = 'Internal Label';
	/* Field: Drealty OpenHouse: Drealty OpenHouse RETS ID */
	$handler->display->display_options['fields']['rets_id']['id'] = 'rets_id';
	$handler->display->display_options['fields']['rets_id']['table'] = 'drealty_openhouse';
	$handler->display->display_options['fields']['rets_id']['field'] = 'rets_id';
	$handler->display->display_options['fields']['rets_id']['label'] = 'RETS ID';
	/* Field: Drealty OpenHouse: Drealty OpenHouse RETS Key */
	$handler->display->display_options['fields']['rets_key']['id'] = 'rets_key';
	$handler->display->display_options['fields']['rets_key']['table'] = 'drealty_openhouse';
	$handler->display->display_options['fields']['rets_key']['field'] = 'rets_key';
	$handler->display->display_options['fields']['rets_key']['label'] = 'RETS Key';
	/* Field: Edit Link */
	$handler->display->display_options['fields']['nothing']['id'] = 'nothing';
	$handler->display->display_options['fields']['nothing']['table'] = 'views';
	$handler->display->display_options['fields']['nothing']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing']['ui_name'] = 'Edit Link';
	$handler->display->display_options['fields']['nothing']['label'] = 'Actions';
	$handler->display->display_options['fields']['nothing']['alter']['text'] = '<a href="/drealty_openhouse/[id]/edit?destination=/admin/content/drealty_openhouse">Edit</a>';
	/* Field: Delete Link */
	$handler->display->display_options['fields']['nothing_1']['id'] = 'nothing_1';
	$handler->display->display_options['fields']['nothing_1']['table'] = 'views';
	$handler->display->display_options['fields']['nothing_1']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing_1']['ui_name'] = 'Delete Link';
	$handler->display->display_options['fields']['nothing_1']['label'] = 'Delete';
	$handler->display->display_options['fields']['nothing_1']['alter']['text'] = '<a href="/drealty_openhouse/[id]/delete?destination=/admin/content/drealty_openhouse">Delete</a>';
	/* Field: Refresh RETS */
	$handler->display->display_options['fields']['nothing_2']['id'] = 'nothing_2';
	$handler->display->display_options['fields']['nothing_2']['table'] = 'views';
	$handler->display->display_options['fields']['nothing_2']['field'] = 'nothing';
	$handler->display->display_options['fields']['nothing_2']['ui_name'] = 'Refresh RETS';
	$handler->display->display_options['fields']['nothing_2']['label'] = 'Refresh RETS data';
	$handler->display->display_options['fields']['nothing_2']['alter']['text'] = '<a href="/drealty_openhouse/[id]/rets_refresh?destination=/admin/content/drealty_openhouse">Refresh RETS data</a>';
	/* Filter criterion: Drealty OpenHouse: Drealty OpenHouse RETS ID */
	$handler->display->display_options['filters']['rets_id']['id'] = 'rets_id';
	$handler->display->display_options['filters']['rets_id']['table'] = 'drealty_openhouse';
	$handler->display->display_options['filters']['rets_id']['field'] = 'rets_id';
	$handler->display->display_options['filters']['rets_id']['group'] = 1;
	$handler->display->display_options['filters']['rets_id']['exposed'] = TRUE;
	$handler->display->display_options['filters']['rets_id']['expose']['operator_id'] = 'rets_id_op';
	$handler->display->display_options['filters']['rets_id']['expose']['label'] = 'RETS ID';
	$handler->display->display_options['filters']['rets_id']['expose']['description'] = 'MLS Number';
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
	/* Filter criterion: Drealty OpenHouse: Drealty OpenHouse RETS Key */
	$handler->display->display_options['filters']['rets_key']['id'] = 'rets_key';
	$handler->display->display_options['filters']['rets_key']['table'] = 'drealty_openhouse';
	$handler->display->display_options['filters']['rets_key']['field'] = 'rets_key';
	$handler->display->display_options['filters']['rets_key']['group'] = 1;
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
	$handler->display->display_options['path'] = 'admin/content/drealty_openhouse';
	$handler->display->display_options['menu']['type'] = 'tab';
	$handler->display->display_options['menu']['title'] = 'Drealty Open Houses';
	$handler->display->display_options['menu']['weight'] = '0';
	$handler->display->display_options['menu']['name'] = 'management';
	$handler->display->display_options['menu']['context'] = 0;
	$handler->display->display_options['menu']['context_only_inline'] = 0;
	
	return array($view->name => $view);
}
<?php

/**
 * @file
 * Contains the administrative UI for Panels renderer pipelines.
 */

class panels_pipelines_ui extends ctools_export_ui {

  public function hook_menu(&$items) {
    // During updates, this can run before our schema is set up, so our
    // plugin can be empty.
    if (empty($this->plugin['schema'])) {
      return;
    }

    // Make the list page appear as a tab under the general Panels settings.
    $this->plugin['menu']['items']['list callback']['type'] = MENU_LOCAL_TASK;

    parent::hook_menu($items);
  }

  public function get_default_operation_trail($item, $operations) {
    return array('operations', 'summary');
  }

  public function get_operations($item) {
    $operations = array();

    // The primary "operations" group is anonymous.
    $operations['operations'] = array(
      '#type' => 'ctools_operation_group',
    );

    $operations['operations']['summary'] = array(
      '#type' => 'link',
      '#title' => t('Summary'),
      '#description' => t('See a summary of all the renderers in this pipeline.'),
      '#weight' => -10,
      '#no-ajax' => TRUE,
      '#operation' => array(
        'type' => 'summary',
      ),
    );

    $operations['operations']['general'] = array(
      '#type' => 'link',
      '#title' => t('General'),
      '#description' => t('Manage general settings for this pipeline.'),
      '#weight' => 0,
      '#operation' => array(
        'form' => 'ctools_export_ui_edit_item_form',
      ),
    );

    $operations['operations']['renderers'] = array(
      '#type' => 'ctools_operation_group',
      '#title' => t('Renderers'),
      '#weight' => 10,
    );

    $operations['operations']['renderers'] += $this->get_renderer_operations($item);

    $operations['actions'] = array(
      '#type' => 'ctools_operation_group',
    );

    if (empty($item->disabled)) {
      $operations['actions']['disable'] = array(
        '#type' => 'link',
        '#title' => t('Disable'),
        '#description' => t('Disable this pipeline'),
      );
    }
    else {
      $operations['actions']['enable'] = array(
        '#type' => 'link',
        '#title' => t('Disable'),
        '#description' => t('Disable this pipeline'),
      );
    }


    // Action to add a new IPE renderer. This is hardcoded and crappy; can be
    // made better & flexible later.
    $operations['actions']['add-renderer'] = array(
      '#type' => 'link',
      '#title' => t('Add renderer'),
      '#no-ajax' => TRUE,
      '#description' => t('Add another renderer to this pipeline.'),
      '#operation' => array(
        'form' => 'panels_pipelines_add_renderer',
      ),
    );

    drupal_alter('ctools_export_ui_operations', $operations, $this);
    return $operations;
  }

  public function get_renderer_operations($item) {
    ctools_include('plugins', 'panels');
    $renderers = array();

    $plugins = panels_get_display_renderers();

    // First, load up the unique ones actually named in the settings
    foreach ($item->settings['renderers'] as $name => $renderer) {
      $plugin = $plugins[$renderer['renderer']];

      $group = array(
        '#type' => 'ctools_operation_group',
        '#title' => $name,
        '#collapsible' => TRUE,
      );

      // Pull in any operations dictated by the plugin, first.
      if (!empty($plugin['operations'])) {
        $group += $plugin['operations'];
      }

      $group['criteria'] = array(
        '#type' => 'link',
        '#title' => t('Selection rules'),
        '#description' => t('Manage the criteria that determine whether or not this renderer will be used.'),
        '#operation' => array(
          'form' => 'panels_pipelines_edit_selection_criteria',
          'instance name' => $name,
        ),
      );

      // Only add the content & layout selector forms if the renderer indicates
      // it provides an editing interface.
      if (!empty($plugin['editor'])) {
        $group['layouts'] = array(
          '#type' => 'link',
          '#title' => t('Layouts'),
          '#description' => t('Control which layouts should be allowed when using this renderer.'),
          '#operation' => array(
            'form' => 'panels_pipelines_allowed_layouts_form',
            'instance name' => $name,
          ),
        );
        $group['content'] = array(
          '#type' => 'link',
          '#title' => t('Content'),
          '#description' => t('Control the set of content (panes) that will be available for users to select when using this renderer.'),
          '#operation' => array(
            'form' => 'panels_pipelines_content_set_form',
            'instance name' => $name,
          ),
        );
      }

      $renderers[$name] = $group;
    }

    // Then add the unmodifiable standard one that is always there, always last

    return $renderers;
  }

  public function render_operation_type_summary($form_state, $info, $operation) {
    return array(
      'title' => t('Cool summary, bro.'),
      'content' => t('Blah blah lotsa info about all the contained pipelines blah.'),
    );
  }

  public function add_renderer_form($form, &$form_state) {
    $pipeline = $form_state['item'];
    $renderers = panels_get_display_renderers();

    $options = array();
    foreach ($renderers as $id => $plugin) {
      // TODO for now we're just doing frontend renderers in pipelines
      if (!empty($plugin['frontend renderer'])) {
        $options[$id] = $plugin['title'];
      }
    }

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#description' => t('Administrative title for this renderer. If left blank, a title will be assigned automatically.'),
    );

    $form['renderer'] = array(
      '#type' => 'select',
      '#title' => t('Renderer'),
      '#options' => $options,
      '#description' => t('Select a renderer plugin to use. Different renderer plugins have different options.'),
    );

    return $form;
  }

  public function add_renderer_form_submit($form, &$form_state) {

  }
}



function panels_pipelines_add_renderer($form, &$form_state) {
  return $form_state['object']->add_renderer_form($form, $form_state);
}

function panels_pipelines_add_renderer_submit($form, &$form_state) {
  return $form_state['object']->add_renderer_form_submit($form, $form_state);
}

function panels_pipelines_edit_selection_criteria($form, &$form_state) {
  $name = $form_state['operation']['instance name'];
  $renderer = &$form_state['item']->settings['renderers'][$name];
  if (!isset($renderer['access'])) {
    $renderer['access'] = array();
  }

  ctools_include('context');
  ctools_include('modal');
  ctools_include('ajax');
  ctools_modal_add_plugin_js(ctools_get_access_plugins());
  ctools_include('context-access-admin');

  $form_state['module'] = 'panels_pipeline';
  // Encode a bunch of info into the argument so we can get our cache later
  $form_state['callback argument'] = $form_state['item']->name . '*' . $name;
  $form_state['access'] = $renderer['access'];
  $form_state['no buttons'] = TRUE;
  // only allow generic, globally-available contexts. at least for now
  // $form_state['contexts'] = ctools_context_load_contexts($form_state['item'], array());
  $form_state['contexts'] = array();

  $form['markup'] = array(
      '#markup' => '<div class="description">' .
  t('If more than one renderer is attached to this pipeline, when a panel utilizing this pipeline is visited, each renderer is given an opportunity to be used. Starting from the first renderer and working to the last, each one tests to see if its selection rules will pass. The first renderer that meets its criteria (as specified below) will be used. If no renderer can be chosen, the standard renderer will be selected as a fallback.') .
      '</div>',
  );

  $form = ctools_access_admin_form($form, $form_state);
  return $form;
}

/**
 * Form for selecting the set of allowed layouts on for a given renderer.
 *
 * Have to write a new form because the existing form is so *horribly* coded
 * as to be unusable.
 *
 * @param array $form
 * @param array $form_state
 */
function panels_pipelines_allowed_layouts_form($form, &$form_state) {
  ctools_add_js('layout', 'panels');

  $layouts = panels_get_layouts();
  $options = array();
  foreach ($layouts as $id => $layout) {
    $options[$id] = panels_print_layout_icon($id, $layout, check_plain($layout['title']));
  }

  $renderer = &$form_state['item']->settings['renderers'][$form_state['operation']['instance name']];
  $defaults = empty($renderer['options']['layouts']) ? $options : $renderer['options']['layouts'];

  $form['layouts'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Select allowed layouts'),
    '#options' => $options,
    '#description' => t('Check the boxes for all layouts you want to allow users choose from when picking a layout. You must allow at least one layout.'),
    '#default_value' => $defaults,
    '#prefix' => '<div class="clearfix panels-layouts-checkboxes">',
    '#suffix' => '</div>',
    '#checkall' => TRUE,
  );

  return $form;
}

function panels_pipelines_allowed_layouts_form_validate($form, &$form_state) {
  $selected = array_filter($form_state['values']['layouts']);
  if (empty($selected)) {
    form_set_error('layouts', 'You must choose at least one layout to allow.');
  }
}

function panels_pipelines_allowed_layouts_form_submit($form, &$form_state) {
  $name = $form_state['operation']['instance name'];
  $renderer = &$form_state['item']->settings['renderers'][$name];

  foreach ($form_state['values']['layouts'] as $layout => $setting) {
    $renderer['options']['layouts'][$layout] = $setting;
  }
}

function panels_pipelines_content_set_form($form, &$form_state) {
  ctools_include('plugins', 'panels');
  ctools_include('content');
  ctools_add_css('panels_page', 'panels');

  $renderer = &$form_state['item']->settings['renderers'][$form_state['operation']['instance name']];
  if (empty($renderer['options']['content'])) {
    $renderer['options']['content'] = array(
      'new_type_rule' => array('other' => TRUE),
      'allowed_types' => array(),
    );
  }

  $default_types = $renderer['options']['content']['new_type_rule'];

  $content_types = ctools_get_content_types();
  foreach ($content_types as $id => $info) {
    if (empty($info['single'])) {
      $default_options[$id] = t('New @s', array('@s' => $info['title']));
      $default_types[$id] = TRUE;
    }
  }

  $default_options['other'] = t('New content of other types');
  $form['panels_common_default'] = array(
    '#type' => 'checkboxes',
    '#title' => t('New content behavior'),
    '#description' => t('Select the default behavior of new content added to the system. If checked, new content will automatically be immediately available when this pipeline and renderer are selected. If unchecked, new content will not be available until specifically allowed here.'),
    '#options' => $default_options,
    '#default_value' => array_keys(array_filter($default_types)),
  );

  $available_content_types = ctools_content_get_all_types();
  $allowed_content_types = $renderer['options']['content']['allowed_types'];

  $allowed = array();
  foreach ($available_content_types as $id => $types) {
    foreach ($types as $type => $info) {
      $key = $id . '-' . $type;
      $checkboxes = empty($content_types[$id]['single']) ? $id : 'other';
      $options[$checkboxes][$key] = $info['title'];
      if (!isset($allowed_content_types[$key])) {
        $allowed[$checkboxes][$key] = isset($default_types[$id]) ? $default_types[$id] : $default_types['other'];
      }
      else {
        $allowed[$checkboxes][$key] = $allowed_content_types[$key];
      }
    }
  }

  $form['content_types'] = array(
    '#tree' => TRUE,
    '#prefix' => '<div class="clearfix">',
    '#suffix' => '</div>',
  );
  // cheat a bit
  $content_types['other'] = array('title' => t('Other'), 'weight' => 10);
  foreach ($content_types as $id => $info) {
    if (isset($allowed[$id])) {
      $form['content_types'][$id] = array(
        '#prefix' => '<div class="panels-page-type-container">',
        '#suffix' => '</div>',
        '#type' => 'checkboxes',
        '#title' => t('Allowed @s content', array('@s' => $info['title'])),
        '#options' => $options[$id],
        '#default_value' => array_keys(array_filter($allowed[$id])),
        '#checkall' => TRUE,
      );
    }
  }

  return $form;
}

function panels_pipelines_content_set_form_submit($form, &$form_state) {
  $renderer = &$form_state['item']->settings['renderers'][$form_state['operation']['instance name']];
  $renderer['options']['content']['allowed_types'] = call_user_func_array('array_merge', $form_state['values']['content_types']);
  $renderer['options']['content']['new_type_rule'] = $form_state['values']['panels_common_default'];
}

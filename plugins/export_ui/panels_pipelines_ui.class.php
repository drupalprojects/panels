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

    $operations['actions']['disable'] = array(
      '#type' => 'link',
      '#title' => t('Disable'),
      '#description' => t('Disable this pipeline'),
    );

    // Action to add a new IPE renderer. This is hardcoded and crappy; can be
    // made better & flexible later.
    $operations['actions']['add-ipe'] = array(
      '#type' => 'link',
      '#title' => t('Add IPE renderer'),
      '#description' => t('Add an IPE renderer to this pipeline.'),
      '#operation' => array(
        'form' => array('panels_pipelines_add_ipe_renderer'),
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
  $defaults = empty($renderer['layouts']) ? $options : $renderer['layouts'];

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
    $renderer['layouts'][$layout] = $setting;
  }
}

function panels_pipelines_content_set_form($form, &$form_state) {
  ctools_include('plugins', 'panels');
  ctools_include('content');
  ctools_add_css('panels_page', 'panels');

  $renderer = &$form_state['item']->settings['renderers'][$form_state['operation']['instance name']];
  $default_types = $renderer['content'];

  $content_types = ctools_get_content_types();
  foreach ($content_types as $id => $info) {
    if (empty($info['single'])) {
      $default_options[$id] = t('New @s', array('@s' => $info['title']));
    }
  }

  $default_options['other'] = t('New content of other types');
  $form['panels_common_default'] = array(
    '#type' => 'checkboxes',
    '#title' => t('New content behavior'),
    '#description' => t('Select the default behavior of new content added to the system. If checked, new content will automatically be immediately available to be added to Panels pages. If not checked, new content will not be available until specifically allowed here.'),
    '#options' => $default_options,
    '#default_value' => array_keys(array_filter($default_types)),
  );

  return $form;
}

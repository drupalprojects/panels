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
    return array('operations', 'general');
  }

  public function get_operations($item) {
    $operations = array();

    // The primary "operations" group is anonymous.
    $operations['operations'] = array(
      '#type' => 'ctools_operation_group',
    );

/*     $operations['operations']['summary'] = array(
      '#type' => 'link',
      '#title' => t('Summary'),
      '#description' => t('See a summary of all the renderers in this pipeline.'),
      '#weight' => -10,
      '#no-ajax' => TRUE,
      '#operation' => array(
        'type' => 'summary',
      ),
    ); */

    $operations['operations']['general'] = array(
      '#type' => 'link',
      '#title' => t('General settings'),
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

    $operations['operations']['renderers'] += $this->get_all_renderer_operations($item);

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


    // Action to add a new renderer.
    $operations['actions']['add-renderer'] = array(
      '#type' => 'link',
      '#title' => t('Add renderer'),
      '#no-ajax' => TRUE,
      '#description' => t('Add another renderer to this pipeline.'),
      '#operation' => array(
        'form' => 'panels_pipelines_add_renderer',
        'no update and save' => TRUE,
        'silent' => TRUE,

        'form info' => array(
          'finish text' => t('Create renderer'),
        ),
      ),
    );

    // Special operation used for configuring a new renderer before saving it.
    if (isset($item->new_renderer)) {
      $plugin = panels_get_display_renderer($item->new_renderer['renderer']);
      $operations['actions']['configure'] = array(
        '#type' => 'hidden',
        '#title' => t('Configure'),
        '#description' => t('Configure the new renderer prior to adding it to the pipeline.'),
        '#no-ajax' => FALSE,
        '#operation' => array(
          'no update and save' => TRUE,
          'form info' => array(
            'show trail' => TRUE,
            'show back' => TRUE,
            'finish text' => t('Create renderer'),
            'finish callback' => 'panels_pipelines_add_renderer_finish',
          ),
          'form' => $this->get_renderer_operations_form_info($plugin['name']),
        ),
      );
    }

    drupal_alter('ctools_export_ui_operations', $operations, $this);
    return $operations;
  }

  public function render_operation($form_state, $operations, $trail) {
    // derive the renderer instance being acted on, if possible. kinda icky.
    if ($trail[0] == 'operations' && $trail[1] == 'renderers' &&
        !empty($form_state['item']->settings['renderers'][$trail[2]])) {
      $form_state['renderer_instance'] = &$form_state['item']->settings['renderers'][$trail[2]];
      $form_state['instance_name'] = $trail[2];
    }
    // Adding a new renderer, grab it from the special spot.
    else if ($trail[0] == 'actions' && $trail[1] == 'configure') {
      $form_state['renderer_instance'] = &$form_state['item']->new_renderer;
      $form_state['instance_name'] = 'new';
    }
    return parent::render_operation($form_state, $operations, $trail);
  }

  public function get_all_renderer_operations($item) {
    ctools_include('plugins', 'panels');
    $renderers = array();

    // First, load up the unique ones actually named in the settings
    foreach ($item->settings['renderers'] as $renderer) {
      $group = $this->get_renderer_operations($renderer['renderer']);
      $group += array(
        '#title' => $renderer['title'],
        '#type' => 'ctools_operation_group',
        '#collapsible' => TRUE,
      );
      $renderers[] = $group;
    }

    return $renderers;
  }

  /**
   * Given the string name of a renderer plugin, return a renderable array of
   * operations info suitable for use in constructing the operations nav.
   *
   * @param string $renderer_name
   * 	The name of the renderer plugin from which to extract operations info.
   */
  public function get_renderer_operations($renderer_name) {
    $plugin = panels_get_display_renderer($renderer_name);

    $group = array();

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
      ),
      '#weight' => -3,
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
        ),
        '#weight' => -2,
      );
      $group['content'] = array(
        '#type' => 'link',
        '#title' => t('Content'),
        '#description' => t('Control the set of content (panes) that will be available for users to select when using this renderer.'),
        '#operation' => array(
          'form' => 'panels_pipelines_content_set_form',
        ),
        '#weight' => -1,
      );
    }

    uasort($group, 'element_sort');

    return $group;
  }

  /**
   * Returns an array of forms info suitable for creating a form wizard on
   * behalf a named renderer plugin.
   *
   * @param string $renderer_name
   *   The name of the renderer plugin from which to extract form info.
   */
  public function get_renderer_operations_form_info($renderer_name) {
    // Let the other method to the bulk of the compiling work.
    $group = $this->get_renderer_operations($renderer_name);

    $forms = $order = array();
    foreach ($group as $name => $operation) {
      $order[$name] = $operation['#title'];
      $forms[$name] = array(
        // TODO this will break if some renderer wants a complex form and has a
        // non-string here
        'form id' => $operation['#operation']['form'],
      );
    }
    return array(
      'forms' => $forms,
      'order' => $order,
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

  public function add_renderer_form_validate($form, &$form_state) {
    // TODO input validation on the title field
  }

  public function add_renderer_form_submit($form, &$form_state) {
    $form_state['no_rerender'] = TRUE;

    $plugin = panels_get_display_renderer($form_state['values']['renderer']);

    // Assign a default title if none was provided.
    if (empty($form_state['values']['title'])) {
      $form_state['values']['title'] = empty($plugin['title']) ? t('New renderer') : $plugin['title'];
    }

    $form_state['item']->new_renderer = $plugin['pipeline defaults'];
    $form_state['item']->new_renderer['title'] = $form_state['values']['title'];

    $form_state['new trail'] = array('actions', 'configure');
  }

  /**
   * Performs final cleanup after completing a form wizard for adding a new
   * renderer.
   *
   * Sets appropriate redirects and moves the new renderer config into its
   * permanent storage location.
   */
  public function add_renderer_finish(&$form_state) {
    $item = &$form_state['item'];

    // Attach the new renderer to its permanent location.
    $item->settings['renderers'][] = $item->new_renderer;
    end($item->settings['renderers']);
    $name = key($item->settings['renderers']);

    // Figure out the first form for this renderer and use that, else go back
    // to the overview page.

    $ops = $this->get_renderer_operations_form_info($item->new_renderer['renderer']);
    $trails = array_keys($ops['order']);
    $final = reset($trails);

    unset($item->new_renderer);

    if (!empty($final)) {
      $form_state['new trail'] = array('operations', 'renderers', $name, $final);
    }
    else {
      $form_state['new trail'] = array('operations', 'general');
    }

    $this->edit_operation_finish($form_state);
  }

  /**
   * Override purely for the purpose of initializing values on $item->settings.
   *
   * @see ctools_export_ui::edit_cache_get_key()
   */
  public function edit_cache_get($item, $op = 'edit') {
    if ($op !== 'add') {
      return parent::edit_cache_get($item, $op);
    }
    $item = ctools_export_crud_new($this->plugin['schema']);
    $item->settings = array(
      'renderers' => array(),
    );
    return $item;
  }
}

function panels_pipelines_add_renderer($form, &$form_state) {
  return $form_state['object']->add_renderer_form($form, $form_state);
}

function panels_pipelines_add_renderer_validate($form, &$form_state) {
  return $form_state['object']->add_renderer_form_validate($form, $form_state);
}

function panels_pipelines_add_renderer_submit($form, &$form_state) {
  return $form_state['object']->add_renderer_form_submit($form, $form_state);
}

/**
 * Finish callback for the form wizard for creating a new renderer instance on
 * a pipeline.
 *
 */
function panels_pipelines_add_renderer_finish(&$form_state) {
  return $form_state['object']->add_renderer_finish($form_state);
}

function panels_pipelines_edit_selection_criteria($form, &$form_state) {
  if (!isset($form_state['renderer_instance']['access'])) {
    $form_state['renderer_instance']['access'] = array();
  }

  ctools_include('context');
  ctools_include('modal');
  ctools_include('ajax');
  ctools_modal_add_plugin_js(ctools_get_access_plugins());
  ctools_include('context-access-admin');

  $form_state['module'] = 'panels_pipeline';
  // Encode a bunch of info into the argument so we can get our cache later
  $form_state['callback argument'] = $form_state['item']->name . '*' . $form_state['instance_name'];
  $form_state['access'] = $form_state['renderer_instance']['access'];
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

  $defaults = empty($form_state['renderer_instance']['options']['layouts']) ? $options : $form_state['renderer_instance']['options']['layouts'];

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
  foreach ($form_state['values']['layouts'] as $layout => $setting) {
    $form_state['renderer_instance']['options']['layouts'][$layout] = $setting;
  }
}

function panels_pipelines_content_set_form($form, &$form_state) {
  ctools_include('plugins', 'panels');
  ctools_include('content');
  ctools_add_css('panels_page', 'panels');

  if (empty($form_state['renderer_instance']['options']['content'])) {
    $form_state['renderer_instance']['options']['content'] = array(
      'new_type_rule' => array('other' => TRUE),
      'allowed_types' => array(),
    );
  }

  $default_types = $form_state['renderer_instance']['options']['content']['new_type_rule'];

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
  $allowed_content_types = $form_state['renderer_instance']['options']['content']['allowed_types'];

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
  $form_state['renderer_instance']['options']['content']['allowed_types'] = call_user_func_array('array_merge', $form_state['values']['content_types']);
  $form_state['renderer_instance']['options']['content']['new_type_rule'] = $form_state['values']['panels_common_default'];
}

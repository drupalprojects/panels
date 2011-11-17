<?php

$plugin = array(
  'schema' => 'panels_renderer_pipeline',
  'access' => 'administer panels pipelines',

  'menu' => array(
    'menu prefix' => 'admin/structure/panels',
    'menu item' => 'pipelines',
    'menu title' => 'Pipelines',
    'menu description' => 'Add, edit or delete Panels pipelines.',
  ),

  'title singular' => t('pipeline'),
  'title singular proper' => t('Pipeline'),
  'title plural' => t('pipelines'),
  'title plural proper' => t('Pipelines'),

  'handler' => array(
    'class' => 'panels_pipelines_ui',
  ),
);


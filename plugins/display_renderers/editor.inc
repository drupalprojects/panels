<?php

$plugin = array(
  'renderer' => 'panels_renderer_editor',
  'title' => t('Editor'),
  'frontend renderer' => FALSE,
  'backend renderer' => TRUE,
  'editor' => TRUE,

  'pipeline defaults' => array(
    'title' => '',
    'access' => array(),
    'renderer' => 'editor',
    'options' => array(
      'content' => array(),
      'layouts' => array(),
      'general' => array(),
    ),
  ),
);

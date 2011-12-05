<?php

$plugin = array(
  'renderer' => 'panels_renderer_standard',
  'title' => t('Standard'),

  'pipeline defaults' => array(
    'title' => '',
    'access' => array(),
    'renderer' => 'standard',
    'options' => array(
      'content' => array(),
      'layouts' => array(),
      'general' => array(),
    ),
  ),
);

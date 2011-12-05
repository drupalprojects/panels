<?php
/**
 * Create a simple renderer plugin that renders a layout but the content is
 * already rendered, not in panes.
 */
$plugin = array(
  'renderer' => 'panels_renderer_simple',
  'title' => t('Simple'),

  'pipeline defaults' => array(
    'title' => '',
    'access' => array(),
    'renderer' => 'simple',
    'options' => array(
      'content' => array(),
      'layouts' => array(),
      'general' => array(),
    ),
  ),
);

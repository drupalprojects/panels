<?php

/**
 * @file
 * Contains \Drupal\page_manager\Plugin\DisplayVariant\PanelsDisplayVariant.
 */

namespace Drupal\panels\Plugin\DisplayVariant;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\page_manager\Plugin\BlockVariantInterface;
use Drupal\page_manager\Plugin\BlockVariantTrait;
use Drupal\page_manager\Plugin\ConditionVariantInterface;
use Drupal\page_manager\Plugin\ConditionVariantTrait;
use Drupal\page_manager\Plugin\ContextAwareVariantInterface;
use Drupal\page_manager\Plugin\ContextAwareVariantTrait;
use Drupal\layout_plugin\Layout;
use Drupal\layout_plugin\Plugin\Layout\LayoutInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display variant that simply contains blocks.
 *
 * @DisplayVariant(
 *   id = "panels_variant",
 *   admin_label = @Translation("Panels")
 * )
 */
class PanelsDisplayVariant extends VariantBase implements ContextAwareVariantInterface, ConditionVariantInterface, ContainerFactoryPluginInterface, BlockVariantInterface {

  use BlockVariantTrait;
  use ContextAwareVariantTrait;
  use ConditionVariantTrait;

  /**
   * The layout handler.
   *
   * @var \Drupal\layout_plugin\Plugin\Layout\LayoutInterface
   */
  protected $layout;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new PanelsDisplayVariant.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account, UuidInterface $uuid_generator, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->uuidGenerator = $uuid_generator;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('context.handler'),
      $container->get('current_user'),
      $container->get('uuid'),
      $container->get('token')
    );
  }

  /**
   * Returns instance of the layout plugin used by this page variant.
   *
   * @return \Drupal\layout_plugin\Plugin\Layout\LayoutInterface
   *   Layout plugin instance.
   */
  public function getLayout() {
    if (!isset($this->layout)) {
      $this->layout = Layout::layoutPluginManager()->createInstance($this->configuration['layout'], []);
    }
    return $this->layout;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionNames() {
    return $this->getLayout()->getPluginDefinition()['region_names'];
  }

  /**
   * Build render arrays for each of the regions.
   *
   * @return
   *   An associative array keyed by region id, containing the render array
   *   representing the content of each region.
   */
  protected function buildRegions() {
    $build = [];
    $contexts = $this->getContexts();
    foreach ($this->getRegionAssignments() as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = Html::getClass("block-region-$region");
      $build[$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build[$region]['#suffix'] = '</div>';

      /** @var $blocks \Drupal\block\BlockPluginInterface[] */
      $weight = 0;
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler()->applyContextMapping($block, $contexts);
        }
        if ($block->access($this->account)) {
          $block_render_array = [
            '#theme' => 'block',
            '#attributes' => [],
            '#weight' => $weight++,
            '#configuration' => $block->getConfiguration(),
            '#plugin_id' => $block->getPluginId(),
            '#base_plugin_id' => $block->getBaseId(),
            '#derivative_plugin_id' => $block->getDerivativeId(),
          ];
          if (!empty($block_render_array['#configuration']['label'])) {
            $block_render_array['#configuration']['label'] = SafeMarkup::checkPlain($block_render_array['#configuration']['label']);
          }
          $block_render_array['content'] = $block->build();

          $build[$region][$block_id] = $block_render_array;
        }
      }
    }
    $build['#title'] = $this->renderPageTitle($this->configuration['page_title']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $regions = $this->buildRegions();
    if ($layout = $this->getLayout()) {
      return $layout->build($regions);
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Allow to configure the page title, even when adding a new display.
    // Default to the page label in that case.
    $form['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#description' => $this->t('Configure the page title that will be used for this display.'),
      '#default_value' => $this->configuration['page_title'] ?: '',
    ];

    if (!$this->id()) {
      $form['layout'] = [
        '#title' => $this->t('Layout'),
        '#type' => 'select',
        '#options' => Layout::getLayoutOptions(['group_by_category' => TRUE]),
        '#default_value' => NULL
      ];

      return $form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if ($form_state->hasValue('layout')) {
      $this->configuration['layout'] = $form_state->getValue('layout');
    }

    if ($form_state->hasValue('page_title')) {
      $this->configuration['page_title'] = $form_state->getValue('page_title');
    }

    // If the blocks were rearranged, update their values.
    if ($form_state->hasValue('blocks')) {
      foreach ($form_state->getValue('blocks') as $block_id => $block_values) {
        $this->updateBlock($block_id, $block_values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account = NULL) {
    // If no blocks are configured for this variant, deny access.
    if (empty($this->configuration['blocks'])) {
      return FALSE;
    }

    // Delegate to the conditions.
    if ($this->determineSelectionAccess($this->getContexts()) === FALSE) {
      return FALSE;
    }

    return parent::access($account);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'blocks' => [],
      'selection_conditions' => [],
      'selection_logic' => 'and',
      'page_title' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    foreach ($this->getBlockCollection() as $instance) {
      $this->calculatePluginDependencies($instance);
    }
    foreach ($this->getSelectionConditions() as $instance) {
      $this->calculatePluginDependencies($instance);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'selection_conditions' => $this->getSelectionConditions()->getConfiguration(),
      'blocks' => $this->getBlockCollection()->getConfiguration(),
    ] + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionLogic() {
    return $this->configuration['selection_logic'];
  }

  /**
   * Renders the page title and replaces tokens.
   *
   * @param string $page_title
   *   The page title that should be rendered.
   *
   * @return string
   *   The page title after replacing any tokens.
   */
  protected function renderPageTitle($page_title) {
    $data = $this->getContextAsTokenData();
    return $this->token->replace($page_title, $data);
  }

  /**
   * Returns available context as token data.
   *
   * @return array
   *   An array with token data values keyed by token type.
   */
  protected function getContextAsTokenData() {
    $data = array();
    foreach ($this->getContexts() as $context) {
      // @todo Simplify this when token and typed data types are unified in
      //   https://drupal.org/node/2163027.
      if (strpos($context->getContextDefinition()->getDataType(), 'entity:') === 0) {
        $token_type = substr($context->getContextDefinition()->getDataType(), 7);
        if ($token_type == 'taxonomy_term') {
          $token_type = 'term';
        }
        $data[$token_type] = $context->getContextValue();
      }
    }
    return $data;
  }

  /**
   * Wraps drupal_html_class().
   *
   * @return string
   */
  protected function drupalHtmlClass($class) {
    return drupal_html_class($class);
  }

  /**
   * {@inheritdoc}
   */
  protected function contextHandler() {
    return $this->contextHandler;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectionConfiguration() {
    return $this->configuration['selection_conditions'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBlockConfig() {
    return $this->configuration['blocks'];
  }

  /**
   * {@inheritdoc}
   */
  protected function uuidGenerator() {
    return $this->uuidGenerator;
  }

}

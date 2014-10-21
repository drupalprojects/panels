<?php

/**
 * @file
 * Contains \Drupal\page_manager\Plugin\DisplayVariant\PanelsDisplayVariant.
 */

namespace Drupal\panels\Plugin\DisplayVariant;

use Drupal\Component\Plugin\ContextAwarePluginInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\page_manager\PageExecutable;
use Drupal\page_manager\Plugin\BlockVariantInterface;
use Drupal\page_manager\Plugin\BlockVariantTrait;
use Drupal\page_manager\Plugin\ConditionVariantInterface;
use Drupal\page_manager\Plugin\ConditionVariantTrait;
use Drupal\page_manager\Plugin\ContextAwareVariantInterface;
use Drupal\page_manager\Plugin\ContextAwareVariantTrait;
use Drupal\page_manager\Plugin\PageAwareVariantInterface;
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
class PanelsDisplayVariant extends VariantBase implements ContextAwareVariantInterface, ConditionVariantInterface, ContainerFactoryPluginInterface, PageAwareVariantInterface, BlockVariantInterface {

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
   * The page executable.
   *
   * @var \Drupal\page_manager\PageExecutable
   */
  protected $executable;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextHandlerInterface $context_handler, AccountInterface $account, UuidInterface $uuid_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->contextHandler = $context_handler;
    $this->account = $account;
    $this->uuidGenerator = $uuid_generator;
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
      $container->get('uuid')
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
      $this->layout = Layout::layoutPluginManager()->createInstance($this->configuration['layout'], array());
    }
    return $this->layout;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionNames() {
    return $this->getLayout()->getRegionNames();
  }

  /**
   * Build render arrays for each of the regions.
   *
   * @return
   *   An associative array keyed by region id, containing the render array
   *   representing the content of each region.
   */
  protected function buildRegions() {
    $build = array();
    $contexts = $this->getContexts();
    foreach ($this->getRegionAssignments() as $region => $blocks) {
      if (!$blocks) {
        continue;
      }

      $region_name = $this->drupalHtmlClass("block-region-$region");
      $build[$region]['#prefix'] = '<div class="' . $region_name . '">';
      $build[$region]['#suffix'] = '</div>';

      /** @var $blocks \Drupal\block\BlockPluginInterface[] */
      $weight = 0;
      foreach ($blocks as $block_id => $block) {
        if ($block instanceof ContextAwarePluginInterface) {
          $this->contextHandler()->applyContextMapping($block, $contexts);
        }
        if ($block->access($this->account)) {
          $block_render_array = array(
            '#theme' => 'block',
            '#attributes' => array(),
            '#weight' => $weight++,
            '#configuration' => $block->getConfiguration(),
            '#plugin_id' => $block->getPluginId(),
            '#base_plugin_id' => $block->getBaseId(),
            '#derivative_plugin_id' => $block->getDerivativeId(),
          );
          $block_render_array['#configuration']['label'] = String::checkPlain($block_render_array['#configuration']['label']);
          $block_render_array['content'] = $block->build();

          $build[$region][$block_id] = $block_render_array;
        }
      }
    }
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

    // Do not allow blocks to be added until the display variant has been saved.
    if (!$this->id()) {
      $form['layout'] = array(
        '#title' => $this->t('Layout'),
        '#type' => 'select',
        '#options' => Layout::getLayoutOptions(array('group_by_category' => TRUE)),
        '#default_value' => NULL
      );

      return $form;
    }

    // Determine the page ID, used for links below.
    $page_id = $this->executable->getPage()->id();

    // Set up the attributes used by a modal to prevent duplication later.
    $attributes = array(
      'class' => array('use-ajax'),
      'data-accepts' => 'application/vnd.drupal-modal',
      'data-dialog-options' => Json::encode(array(
        'width' => 'auto',
      )),
    );
    $add_button_attributes = NestedArray::mergeDeep($attributes, array(
      'class' => array(
        'button',
        'button--small',
        'button-action',
      ),
    ));

    if ($block_assignments = $this->getRegionAssignments()) {
      // Build a table of all blocks used by this display variant.
      $form['block_section'] = array(
        '#type' => 'details',
        '#title' => $this->t('Blocks'),
        '#open' => TRUE,
      );
      $form['block_section']['add'] = array(
        '#type' => 'link',
        '#title' => $this->t('Add new block'),
        '#url' => Url::fromRoute('page_manager.display_variant_select_block', [
          'page' => $page_id,
          'display_variant_id' => $this->id(),
        ]),
        '#attributes' => $add_button_attributes,
        '#attached' => array(
          'library' => array(
            'core/drupal.ajax',
          ),
        ),
      );
      $form['block_section']['blocks'] = array(
        '#type' => 'table',
        '#header' => array(
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Region'),
          $this->t('Weight'),
          $this->t('Operations'),
        ),
        '#empty' => $this->t('There are no regions for blocks.'),
        // @todo This should utilize https://drupal.org/node/2065485.
        '#parents' => array('display_variant', 'blocks'),
      );
      // Loop through the blocks per region.
      foreach ($block_assignments as $region => $blocks) {
        // Add a section for each region and allow blocks to be dragged between
        // them.
        $form['block_section']['blocks']['#tabledrag'][] = array(
          'action' => 'match',
          'relationship' => 'sibling',
          'group' => 'block-region-select',
          'subgroup' => 'block-region-' . $region,
          'hidden' => FALSE,
        );
        $form['block_section']['blocks']['#tabledrag'][] = array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'block-weight',
          'subgroup' => 'block-weight-' . $region,
        );
        $form['block_section']['blocks'][$region] = array(
          '#attributes' => array(
            'class' => array('region-title', 'region-title-' . $region),
            'no_striping' => TRUE,
          ),
        );
        $form['block_section']['blocks'][$region]['title'] = array(
          '#markup' => $this->getRegionName($region),
          '#wrapper_attributes' => array(
            'colspan' => 5,
          ),
        );
        $form['block_section']['blocks'][$region . '-message'] = array(
          '#attributes' => array(
            'class' => array(
              'region-message',
              'region-' . $region . '-message',
              empty($blocks) ? 'region-empty' : 'region-populated',
            ),
          ),
        );
        $form['block_section']['blocks'][$region . '-message']['message'] = array(
          '#markup' => '<em>' . t('No blocks in this region') . '</em>',
          '#wrapper_attributes' => array(
            'colspan' => 5,
          ),
        );

        /** @var $blocks \Drupal\block\BlockPluginInterface[] */
        foreach ($blocks as $block_id => $block) {
          $row = array(
            '#attributes' => array(
              'class' => array('draggable'),
            ),
          );
          $row['label']['#markup'] = $block->label();
          $row['id']['#markup'] = $block->getPluginId();
          // Allow the region to be changed for each block.
          $row['region'] = array(
            '#title' => $this->t('Region'),
            '#title_display' => 'invisible',
            '#type' => 'select',
            '#options' => $this->getRegionNames(),
            '#default_value' => $this->getRegionAssignment($block_id),
            '#attributes' => array(
              'class' => array('block-region-select', 'block-region-' . $region),
            ),
          );
          // Allow the weight to be changed for each block.
          $configuration = $block->getConfiguration();
          $row['weight'] = array(
            '#type' => 'weight',
            '#default_value' => isset($configuration['weight']) ? $configuration['weight'] : 0,
            '#title' => t('Weight for @block block', array('@block' => $block->label())),
            '#title_display' => 'invisible',
            '#attributes' => array(
              'class' => array('block-weight', 'block-weight-' . $region),
            ),
          );
          // Add the operation links.
          $operations = array();
          $operations['edit'] = array(
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('page_manager.display_variant_edit_block', [
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          );
          $operations['delete'] = array(
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('page_manager.display_variant_delete_block', [
              'page' => $page_id,
              'display_variant_id' => $this->id(),
              'block_id' => $block_id,
            ]),
            'attributes' => $attributes,
          );

          $row['operations'] = array(
            '#type' => 'operations',
            '#links' => $operations,
          );
          $form['block_section']['blocks'][$block_id] = $row;
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if ($form_state->hasValue('layout')) {
      $this->configuration['layout'] = $form_state->getValue('layout');
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
    return parent::defaultConfiguration() + array(
      'blocks' => array(),
      'selection_conditions' => array(),
      'selection_logic' => 'and',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    foreach ($this->getBlockBag() as $instance) {
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
    return array(
      'selection_conditions' => $this->getSelectionConditions()->getConfiguration(),
      'blocks' => $this->getBlockBag()->getConfiguration(),
    ) + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionLogic() {
    return $this->configuration['selection_logic'];
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
  public function setExecutable(PageExecutable $executable) {
    $this->executable = $executable;
    return $this;
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

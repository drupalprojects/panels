<?php

/**
 * @file
 * Contains \Drupal\Tests\panels\Unit\PanelsDisplayVariantTest.
 */

namespace Drupal\Tests\panels\Unit;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant
 * @group Panels
 */
class PanelsDisplayVariantTest extends UnitTestCase {

  /**
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationForm() {
    $account = $this->prophesize(AccountInterface::class);
    $context_handler = $this->prophesize(ContextHandlerInterface::class);
    $uuid_generator = $this->prophesize(UuidInterface::class);
    $token = $this->prophesize(Token::class);
    $layout_manager = $this->prophesize(PluginManagerInterface::class);

    $display_variant = new PanelsDisplayVariant([], '', [], $context_handler->reveal(), $account->reveal(), $uuid_generator->reveal(), $token->reveal(), $layout_manager->reveal());

    $values = ['page_title' => "Go hang a salami, I'm a lasagna hog!"];

    $form = [];
    $form_state = (new FormState())->setValues($values);
    $display_variant->submitConfigurationForm($form, $form_state);

    $property = new \ReflectionProperty($display_variant, 'configuration');
    $property->setAccessible(TRUE);
    $this->assertSame($values['page_title'], $property->getValue($display_variant)['page_title']);
  }

}

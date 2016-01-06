<?php

/**
 * @file
 * Contains \Drupal\panels_ipe\PanelsStorageManager.
 */

namespace Drupal\panels\Storage;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;

/**
 * Panels storage manager service.
 */
class PanelsStorageManager implements PanelsStorageManagerInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a PanelsStorageManager.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * An associative array of Panels storages services keyed by storage type.
   *
   * @var \Drupal\panels\Storage\PanelsStorageInterface[]
   */
  protected $storage = [];

  /**
   * {@inheritdoc}
   */
  public function addStorage(PanelsStorageInterface $storage, $storage_type) {
    $this->storage[$storage_type] = $storage;
  }

  /**
   * Gets a storage service.
   *
   * @param string $storage_type
   *   The storage type used by the storage service.
   *
   * @return \Drupal\panels\Storage\PanelsStorageInterface
   *   The Panels storage service with the given storage type.
   *
   * @throws \Exception
   *   If there is no Panels storage service with the given storage type.
   */
  protected function getStorage($storage_type) {
    if (!isset($this->storage[$storage_type])) {
      throw new \Exception('Cannot find storage service: ' . $storage_type);
    }
    return $this->storage[$storage_type];
  }

  /**
   * {@inheritdoc}
   */
  public function load($storage_type, $id) {
    $storage = $this->getStorage($storage_type);
    return $storage->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function save(PanelsDisplayVariant $panels_display) {
    $storage = $this->getStorage($panels_display->getStorageType());
    $storage->save($panels_display);
  }

  /**
   * {@inheritdoc}
   */
  public function access($storage_type, $id, $op, AccountInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->currentUser->getAccount();
    }
    return $this->getStorage($storage_type)->access($id, $op, $account);
  }

}

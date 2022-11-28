<?php

namespace Drupal\huacaya;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;

/**
 * Utility functions for firing events to queues.
 */
class HuacayaUtils {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * Gets the id URL of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose URL you want.
   *
   * @return string
   *   The entity URL.
   *
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   *   Thrown if the given entity does not specify a "canonical" template.
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getEntityUrl(EntityInterface $entity) {
    $undefined = $this->languageManager->getLanguage('und');
    return $entity->toUrl('canonical', [
      'absolute' => TRUE,
      'language' => $undefined,
    ])->toString();
  }

  /**
   * Gets the downloadable URL for a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file whose URL you want.
   *
   * @return string
   *   The file URL.
   */
  public function getDownloadUrl(FileInterface $file) {
    return $file->createFileUrl(FALSE);
  }

  /**
   * Gets the URL for an entity's REST endpoint.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose REST endpoint you want.
   * @param string $format
   *   REST serialization format.
   *
   * @return string
   *   The REST URL.
   */
  public function getRestUrl(EntityInterface $entity, $format = '') {
    $undefined = $this->languageManager->getLanguage('und');
    $entity_type = $entity->getEntityTypeId();
    $rest_url = Url::fromRoute(
      "rest.entity.$entity_type.GET",
      [$entity_type => $entity->id()],
      ['absolute' => TRUE, 'language' => $undefined]
    )->toString();
    if (!empty($format)) {
      $rest_url .= "?_format=$format";
    }
    return $rest_url;
  }

}

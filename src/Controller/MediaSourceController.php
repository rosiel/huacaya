<?php

namespace Drupal\huacaya\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\huacaya\MediaSource\MediaSourceService;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handle creating new files and attaching them to Media.
 */
class MediaSourceController extends ControllerBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Service for business logic.
   *
   * @var \Drupal\huacaya\MediaSource\MediaSourceService
   */
  protected MediaSourceService $service;

  /**
   * MediaSourceController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\huacaya\MediaSource\MediaSourceService $service
   *   Service for business logic.
   */
  public function __construct(Connection $database, MediaSourceService $service) {
    $this->database = $database;
    $this->service = $service;
  }

  /**
   * Controller's create method for dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The App Container.
   *
   * @return \Drupal\huacaya\Controller\MediaSourceController
   *   Controller instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('huacaya.media_source_service')
    );
  }

  /**
   * Adds file to existing media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media to which file is added.
   * @param string $destination_field
   *   The name of the media field to add file reference.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   201 on success with a Location link header.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function attachToMedia(
    Media $media,
    string $destination_field,
    Request $request
  ) {
    $content_location = $request->headers->get('Content-Location', "");
    if (empty($content_location)) {
      throw new BadRequestHttpException("Missing Content-Location header");
    }

    $content_type = $request->headers->get('Content-Type', "");
    if (empty($content_type)) {
      throw new BadRequestHttpException("Missing Content-Type header");
    }

    // Since we create both a Media and its File,
    // start a transaction.
    $transaction = $this->database->startTransaction();

    try {
      $this->service->putToMedia(
        $media,
        $destination_field,
        $request->getContent(TRUE),
        $content_type,
        $content_location
      );
      // Should only see this with a GET request for testing.
      return new Response("<h1>Complete</h1>");
    }
    catch (HttpException $e) {
      $transaction->rollBack();
      throw $e;
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw new HttpException(500, $e->getMessage());
    }
  }

  /**
   * Checks for permissions to update a node and update media.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account for user making the request.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Route match to get Node from url params.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function attachToMediaAccess(AccountInterface $account, RouteMatch $route_match) {
    $media = $route_match->getParameter('media');
    return AccessResult::allowedIf($media->access('update', $account));
  }

}

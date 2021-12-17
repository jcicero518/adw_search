<?php

/**
 * @file
 * Contains \Drupal\adw_search\EventSubscriber\AdwSearchSubscriber.
 */

namespace Drupal\adw_search\EventSubscriber;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AdwSearchSubscriber implements EventSubscriberInterface  {

  public function onRespond(FilterResponseEvent $event) {
    $response = $event->getResponse();

    /**
     * Updated for LogicException: The content cannot be set on a StreamedResponse instance.
     */
    if (!$response instanceof BinaryFileResponse && !$response instanceof StreamedResponse) {
      // remove Generator meta tag
      $content = $response->getContent();
      $content = preg_replace('/[\n]<meta name="Generator"[^>]*>/','', $content);
      $response->setContent($content);

      // remove x-generator http header
      if ($response->headers->has('x-generator')){
        $response->headers->remove('x-generator');
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // this attaches our "onRespond" method above to the "KernalEvents::Response" event
	  // so that it will run when that event happens and we can take out the metatag we're trying to take out.
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    //$events[KernelEvents::VIEW][] = ['onView'];
    return $events;
  }

}

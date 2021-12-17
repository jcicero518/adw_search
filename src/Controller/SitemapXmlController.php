<?php

namespace Drupal\adw_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class SitemapXmlController extends ControllerBase {

  /**
   * @var AliasManager $aliasManager
   */
  protected $aliasManager;

  /**
   * @param AliasManager $alias_manager
   */
  public function __construct(AliasManager $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  /**
   * @param ContainerInterface $container
   * @return SitemapXmlController|static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_alias.manager')
    );
  }

  /**
   * @return array|mixed|null
   */
  private function getSiteFrontPath() {
    return $this->config('system.site')->get('page.front');
  }

  /**
	* {@inheritdoc}
	*/
	public function content() {

		$sitemapEntries = [];
    global $base_url;

		// get settings
		$config = $this->config('adw_search.settings');
		$contentTypeSelection = $config->get('sitemap_xml_content_types') ?? [];

		$pathsToRemove = explode("\n", $config->get('sitemap_xml_omit_pages'));
		foreach ($pathsToRemove as $key => $value) {
			$pathsToRemove[$key] = trim($value);
		}

		// loop over content types
		foreach($contentTypeSelection as $contentTypeMachineName => $contentTypeSelectedValue) {
			//if this content type is selected for output
			if ($contentTypeSelectedValue !== 0) {

        /**
         * Query for published nodes only
         */
        $queryPublished = \Drupal::entityQuery('node')
          ->condition('type', $contentTypeMachineName)
          ->condition('status', 1);

				$nidsOfThisType = $queryPublished->execute();

        $nodeStorage = $this->entityTypeManager()->getStorage('node');

				foreach ($nidsOfThisType as $nid) {
					$thisPath = $this->aliasManager->getAliasByPath('/node/' . $nid);
          /** @var Node $node */
          $node = $nodeStorage->load($nid);

          $include = TRUE;

          /**
           * Exclude certain event content types based on the specified date if present
           */
          if ($node->hasField('field_date')) {
            $eventDates = $node->get('field_date')->getValue();
            $eventDate = end($eventDates)['value'];
            $include = strtotime($eventDate) > time();
          }

          // If this has a legit path and not just the node/* path and it's not in our list of paths to remove
          if (($thisPath !== '/node/'. $nid && !in_array($thisPath, $pathsToRemove, TRUE)) && $include === TRUE) {
            $last_changed = $node->getChangedTime();
            $priority = '0.5';

            $sitemapEntries[$thisPath]['path'] = $thisPath;
            $sitemapEntries[$thisPath]['last_changed_raw'] = $last_changed;
            $sitemapEntries[$thisPath]['last_changed'] = date('Y-m-d', $last_changed);
            $sitemapEntries[$thisPath]['priority'] = $priority;
					}
				}
			}
		}

		// Add the ones from admin settings
		$pathsToAdd = explode("\n", $config->get('sitemap_xml_additional_pages'));

		$additionalPaths = [];

    foreach ($pathsToAdd as $path) {
		  $path = trim($path);
		  $additionalPaths[$path] = [
		    'path' => $path,
        'last_changed_raw' => (string)time(),
        'last_changed' => date('Y-m-d'),
        'priority' => '0.5'
      ];
		}

		if (count($additionalPaths) !== 0) {
		  $sitemapEntries = array_merge($sitemapEntries, $additionalPaths);
    }

		ksort($sitemapEntries);

    $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>';
    $xmlContent .= '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($sitemapEntries as $entry) {
      if (!empty($entry)) {
        $xmlContent .= '<url>';
        $xmlContent .= '<loc>' . $base_url . $entry['path'] . '</loc>';
        $xmlContent .= '<lastmod>' . $entry['last_changed'] . '</lastmod>';
        // TODO:  home page path alias will be empty, use path matcher instead?
        if (empty($entry['path'])) {
          $xmlContent .= '<changefreq>daily</changefreq>';
          $xmlContent .= '<priority>1.0</priority>';
        } else {
          $xmlContent .= '<priority>' . $entry['priority'] . '</priority>';
        }
        $xmlContent .= '</url>';
      }
    }
    $xmlContent .= '</urlset>';

		$response = new Response();
		$response->setContent($xmlContent);
		$response->headers->set('Content-Type', 'text/xml');
		return $response;
	}

}

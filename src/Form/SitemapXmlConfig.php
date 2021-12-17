<?php

namespace Drupal\adw_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

class SitemapXmlConfig extends ConfigFormBase implements TrustedCallbackInterface {

  /**
   * @var LanguageManagerInterface $languageManager
   */
  protected $languageManager;
  /**
   * @var EntityTypeBundleInfoInterface $entityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * @param ConfigFactoryInterface $config_factory
   * @param LanguageManagerInterface $language_manager
   * @param EntityTypeBundleInfoInterface $entity_type_bundle_info
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($config_factory);

    $this->languageManager = $language_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;

  }

  /**
   * @param ContainerInterface $container
   * @return SitemapXmlConfig|ConfigFormBase|static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  public static function trustedCallbacks() {
    return ['validateOptions'];
  }

  public static function validateOptions(array $options) {

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_sitemap_xml';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'adw_search.settings',
    ];
  }

  /**
   * Uses EntityTypeBundleInfoInterface instead for performance
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $nodeBundles = &drupal_static(__METHOD__);
    $cid = 'adw_search.node_bundle_info_' . $this->languageManager->getCurrentLanguage()->getId();

    if ($cache = \Drupal::cache()->get($cid)) {
      $nodeBundles = $cache->data;
    } else {
      $nodeBundles = $this->entityTypeBundleInfo->getBundleInfo('node');
      \Drupal::cache()->set($cid, $nodeBundles);
    }

    $nodeOptions = [];
    foreach ($nodeBundles as $machineName => $label) {
      $nodeOptions[$machineName] = $label['label'];
    }

    $config = $this->config('adw_search.settings');

    if (!$config->get('sitemap_xml_content_types')) {
      $config->set('sitemap_xml_content_types', []);
    }

    $form['sitemap_xml_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Include these content types in the sitemap.xml'),
      '#default_value' => $config->get('sitemap_xml_content_types'),
      '#options' => $nodeOptions,
    ];

    $form['sitemap_xml_additional_pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional Pages to include on sitemap '),
      '#default_value' => $config->get('sitemap_xml_additional_pages'),
      '#description' => 'Enter one page per line. Include the leading slash.',
    ];

    $form['sitemap_xml_omit_pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages to omit from sitemap'),
      '#default_value' => $config->get('sitemap_xml_omit_pages'),
      '#description' => 'Enter one page per line. Include the leading slash.',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $additional_pages = $form_state->getValue('sitemap_xml_additional_pages');
    $omit_pages = $form_state->getValue('sitemap_xml_omit_pages');

    if (!empty($additional_pages)) {
      $toArray = explode("\n", $additional_pages);
      foreach ($toArray as $item) {
        if ((substr($item, 0, 1)) !== '/') {
          $form_state->setErrorByName(
            'sitemap_xml_additional_pages',
            $this->t('Each line must start with a leading slash: /')
          );
        }
      }
    }

    if (!empty($omit_pages)) {
      $toArray = explode("\n", $omit_pages);
      foreach ($toArray as $item) {
        if ((substr($item, 0, 1)) !== '/') {
          $form_state->setErrorByName(
            'sitemap_xml_omit_pages',
            $this->t('Each line must start with a leading slash: /')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('adw_search.settings')
      ->set('sitemap_xml_content_types', $form_state->getValue('sitemap_xml_content_types'))
      ->set('sitemap_xml_additional_pages', $form_state->getValue('sitemap_xml_additional_pages'))
      ->set('sitemap_xml_omit_pages', $form_state->getValue('sitemap_xml_omit_pages'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}

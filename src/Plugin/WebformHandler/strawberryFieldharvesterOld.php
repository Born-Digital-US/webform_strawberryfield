<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformHandler;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileInterface;
use Drupal\strawberryfield\Tools\Ocfl\OcflHelper;

trigger_error('The Webformhandler " strawberryField_webform_handler" is deprecated in Webform_strawberryfield 1.0.0-RC1 and will be removed before 1.0.0. Instead, use \Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester.', E_USER_DEPRECATED);


/**
 * Form submission handler when Webform is used as strawberyfield widget.
 *
 * @WebformHandler(
 *   id = " strawberryField_webform_handler",
 *   label = @Translation("A strawberryField harvester (DEPRECATED)"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("StrawberryField Harvester (DEPRECATED)"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 *
 * @deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. Use
 *   \Drupal\Core\Entity\EntityBase instead.
 *
 */
class strawberryFieldharvesterOld extends strawberryFieldharvester {
  public function getSummary() {
    // Broken/missing/deprecated webform handlers do not need a summary.
     return [];
  }
}

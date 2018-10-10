<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\webformSubmissionInterface;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
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
use Drupal\webform_strawberryfield\Tools\Ocfl\OcflHelper;
use Drupal\Core\Asset;
use Drupal\webform\Utility\WebformFormHelper;

/**
 * Form submission handler when Webform is used as strawberyfield widget.
 *
 * @WebformHandler(
 *   id = " strawberryField_webform_handler",
 *   label = @Translation("A strawberryField harvester"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("StrawberryField Harvester"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class strawberryFieldharvester extends WebformHandlerBase
{
    /**
     * @var bool
     */
    private $isWidgetDriven = FALSE;

    /**
     * The entityTypeManager factory.
     *
     * @var $entityTypeManage EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var \Drupal\webform\WebformTokenManagerInterface
     */
    protected $tokenManager;
    /**
     * @var \Drupal\Core\File\FileSystemInterface
     */
    protected $fileSystem;

    /**
     * @var \Drupal\file\FileUsage\FileUsageInterface
     */
    protected $fileUsage;
    /**
     * @var \Drupal\Component\Transliteration\TransliterationInterface
     */
    protected $transliteration;
    /**
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;


    /**
     * strawberryFieldharvester constructor.
     * @param array $configuration
     * @param $plugin_id
     * @param $plugin_definition
     * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     * @param \Drupal\webform\WebformSubmissionConditionsValidatorInterface $conditions_validator
     * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
     * @param \Drupal\Core\File\FileSystemInterface $file_system
     * @param $file_usage
     * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
     * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, WebformTokenManagerInterface $token_manager, FileSystemInterface $file_system, FileUsageInterface $file_usage, TransliterationInterface $transliteration, LanguageManagerInterface $language_manager) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory,  $entity_type_manager,  $conditions_validator);
        $this->entityTypeManager = $entity_type_manager;
        $this->tokenManager = $token_manager;
        $this->fileSystem = $file_system;
        $this->fileUsage = $file_usage;
        $this->transliteration = $transliteration;
        $this->languageManager = $language_manager;
    }


    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('logger.factory'),
          $container->get('config.factory'),
          $container->get('entity_type.manager'),
          $container->get('webform_submission.conditions_validator'),
          $container->get('webform.token_manager'),
          $container->get('file_system'),
          // Soft depend on "file" module so this service might not be available.
          $container->get('file.usage'),
          $container->get('transliteration'),
          $container->get('language_manager')
        );
    }

    /**
     * @return bool
     */
    public function isWidgetDriven(): bool
    {
        return $this->isWidgetDriven;
    }

    /**
     * @param bool $isWidgetDriven
     */
    public function setIsWidgetDriven(bool $isWidgetDriven): void
    {
        $this->isWidgetDriven = $isWidgetDriven;
    }



    /**
     * {@inheritdoc}
     */
    public function postLoad(WebformSubmissionInterface $webform_submission)
    {
        parent::postLoad($webform_submission); // TODO: Change the autogenerated stub

    }


    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        // @TODO this will be sent to Esmero.
        return [
          'submission_url' => 'https://api.example.org/SOME/ENDPOINT',
          'upload_scheme' => 'public://'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary() {
        $configuration = $this->getConfiguration();
        $settings = $configuration['settings'];
        return [
            '#settings' => $settings,
          ] + parent::getSummary();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['submission_url'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Secondary submission URL to api.example.org'),
          '#description' => $this->t('The URL to post the submission data to.'),
          '#default_value' => $this->configuration['submission_url'],
          '#required' => TRUE,
        ];
        $scheme_options = OcflHelper::getVisibleStreamWrappers();
        $form['upload_scheme'] = [
          '#type' => 'radios',
          '#title' => $this->t('Permanent destination for uploaded files'),
          '#description' => $this->t('The URL to post the submission data to.'),
          '#default_value' => $this->configuration['upload_scheme'],
          '#required' => TRUE,
          '#options' => $scheme_options,
        ];

        return $form;
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        $this->applyFormStateToConfiguration($form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function preSave(WebformSubmissionInterface $webform_submission)
    {

        $values = $webform_submission->getData();
        $cleanvalues = $values;

        // Check which elements carry files around

        $allelements = $webform_submission->getWebform()->getElementsInitializedAndFlattened();
        foreach ($allelements as $element) {
            if ($element['#type'] == 'webform_image_file' || $element['#type'] == 'webform_document_file') {

                $originalelement = $webform_submission->getWebform()->getElement($element['#webform_key']);
                $this->processFileField($originalelement, $webform_submission, $cleanvalues);
            }
        }

        if (isset($values["strawberry_field_widget_state_id"])) {

            $this->setIsWidgetDriven(TRUE);

            $this->messenger()->addMessage($this->t('super persistent!'));
            $tempstore = \Drupal::service('user.private_tempstore')->get('archipel');


            // @TODO add a full-blown values cleaner
            // @TODO add the webform name used to create this as additional KEY
            // @TODO make sure widget can read that too.
            // @If Widget != setup form, ask for User feedback
            // @TODO, i need to alter node submit handler to add also the
            // Entities full URL as an @id to the top of the saved JSON.
            // FUN!
            unset($cleanvalues ["strawberry_field_widget_state_id"]);
            unset($cleanvalues["strawberry_field_stored_values"]);

            // That way we keep track who/what created this.
            $cleanvalues["strawberry_field_widget_id"] = $this->getWebform()->id();

            $cleanvalues = json_encode( $cleanvalues, JSON_PRETTY_PRINT);

            $tempstore->set($values["strawberry_field_widget_state_id"] , $cleanvalues);


        } elseif ($this->IsWidgetDriven()) {
            $this->messenger()->addWarning($this->t('We lost TV reception in the middle of the match...'));
        }

        parent::preSave($webform_submission); // TODO: Change the autogenerated stub
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        // All data is available here $webform_submission->getData()));
        // @TODO what should be validated here?
        parent::validateForm($form, $form_state, $webform_submission); // TODO: Change the autogenerated stub
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {

        $values = $webform_submission->getData();
        $this->messenger()->addMessage($webform_submission->getState());


        // Temporary persistance of data while we collect them for Node Entity use
        // Binding happens through a unique value passed from the widget to this
        // via 'data' structure that a webform submission entity uses.
        $cleanvalues = $values;


        if (isset($values["strawberry_field_widget_state_id"])) {

            $this->setIsWidgetDriven(true);
        }

        /*    $this->messenger()->addMessage($this->t('super persistent!'));
            $tempstore = \Drupal::service('user.private_tempstore')->get('archipel');


            // @TODO add a full-blown values cleaner
            // @TODO add the webform name used to create this as additional KEY
            // @TODO make sure widget can read that too.
            // @If Widget != setup form, ask for User feedback
            // @TODO, i need to alter node submit handler to add also the
            // Entities full URL as an @id to the top of the saved JSON.
            // FUN!
            unset($cleanvalues ["strawberry_field_widget_state_id"]);
            unset($cleanvalues["strawberry_field_stored_values"]);

            // That way we keep track who/what created this.
            $cleanvalues["strawberry_field_widget_id"] = $this->getWebform()->id();

            $cleanvalues = json_encode($cleanvalues, JSON_PRETTY_PRINT);

            $tempstore->set($values["strawberry_field_widget_state_id"] , $cleanvalues);


        } elseif ($this->IsWidgetDriven()) {
            $this->messenger()->addWarning($this->t('We lost TV reception in the middle of the match...'));
        }*/


        // Get the URL to post the data to.
        // @todo esmero a.k.a as Fedora-mockingbird
        $post_url = $this->configuration['submission_url'];
    }

    public function alterElements(array &$elements, WebformInterface $webform)
    {

        $elements2 = &WebformFormHelper::flattenElements($elements);
        foreach($elements2 as &$element) {
            if ($element['#type'] == 'webform_image_file')  {
                if (!isset( $element['#uri_original_scheme']))  {
                    $element['#uri_original_scheme'] = $element['#uri_scheme'];
                }
                $element['#uri_scheme'] = $this->configuration['upload_scheme'];
            }

        }
        parent::alterElements(
          $elements,
          $webform
        ); // TODO: Change the autogenerated stub
    }


    /**
     * Process temp files and make them permanent
     *
     * @param array $element
     *   An associative array containing the file webform element.
     * @param \Drupal\webform\webformSubmissionInterface $webform_submission
     */
    public function processFileField(array $element, WebformSubmissionInterface $webform_submission, &$cleanvalues) {

        $key = $element['#webform_key'];
        $original_data = $webform_submission->getOriginalData();
        $data = $webform_submission->getData();

        $value = isset($cleanvalues[$key]) ? $cleanvalues[$key] : [];
        $fids = (is_array($value)) ? $value : [$value];

        $original_value = isset($original_data[$key]) ? $original_data[$key] : [];
        $original_fids = (is_array($original_value)) ? $original_value : [$original_value];

        // Delete the old file uploads?

        $delete_fids = array_diff($original_fids, $fids);

        // @TODO what do we do with removed files?
        // Idea. Check the fileUsage. If there is still some other than this one
        // don't remove.
        // But also, if a revision is using it? what a mess!
        // @see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::deleteFiles

        // Exit if there is no fids.
        if (empty($fids)) {
            return;
        }

        /** @var \Drupal\file\FileInterface[] $files */
        $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
        $fileinfo = [];
        //@TODO refactor this urgently to a NEW TECHMD class.
        foreach ($files as $file) {
            $uri = $file->getFileUri();
            $md5 = md5_file($uri);
            // This is working but just proof of concept.
            $fileinfo=[
              'type' => 'Image',
              'url' =>  $uri,
              'checksum' => $md5,
              'for' =>  $key,
              'fid' => (int) $file->id(),
              'name' => $file->getFilename()
            ];
            $relativefolder = substr($md5,0,3);

            $source_uri = $file->getFileUri();
            $realpath_uri = $this->fileSystem->realpath($source_uri);
            if (empty(!$realpath_uri)) {

                $command = escapeshellcmd(
                  '/usr/local/bin/fido  '.$realpath_uri.' -pronom_only -matchprintf '
                );
                $output = shell_exec($command. '"OK,%(info.puid)" -q');

            }
            // We will use the original scheme here since our HD operations are over.
            $destination_uri = $this->getFileDestinationUri($element, $file, $relativefolder, $webform_submission);

            $fileinfo_many[$destination_uri] = $fileinfo;

        }
        $cleanvalues['Media'] = $fileinfo_many;

    }


    protected function getFileDestinationUri(array $element, FileInterface $file, $relativefolder, webformSubmissionInterface $webform_submission) {

        // Get current location of the file
        $destination_folder = $this->fileSystem->dirname($file->getFileUri());
        $destination_filename = $file->getFilename();
        $destination_extension = pathinfo($destination_filename, PATHINFO_EXTENSION);

        $current_scheme = $this->fileSystem->uriScheme($file->getFileUri());

        //https://api.drupal.org/api/drupal/core%21includes%21file.inc/function/file_uri_scheme/8.2.x
        $original_scheme = $element['#uri_scheme'];

        if (strpos($destination_folder, '/_sid_')) {
            $destination_folder = str_replace('/webform/'.$webform_submission->getWebform()->id().'/_sid_', '/' . $relativefolder, $destination_folder);
            $destination_folder = str_replace($current_scheme, $original_scheme, $destination_folder);
        }

        // Replace tokens in filename if we are instructed so.
        if (isset($element['#file_name']) && $element['#file_name']) {
            $destination_filename = $this->tokenManager->replace($element['#file_name'], $webform_submission) . '.' . $destination_extension;
        }

        // Sanitize filename.
        // @see http://stackoverflow.com/questions/2021624/string-sanitizer-for-filename

        if (!empty($element['#sanitize'])) {
            $destination_extension = mb_strtolower($destination_extension);

            $destination_basename = substr(pathinfo($destination_filename, PATHINFO_BASENAME), 0, -strlen(".$destination_extension"));
            $destination_basename =  mb_strtolower($destination_basename);
            $destination_basename = $this->transliteration->transliterate($destination_basename, $this->languageManager->getCurrentLanguage()->getId(), '-');
            $destination_basename = preg_replace('([^\w\s\d\-_~,;:\[\]\(\].]|[\.]{2,})', '', $destination_basename);
            $destination_basename = preg_replace('/\s+/', '-', $destination_basename);
            $destination_basename = trim($destination_basename, '-');
            // If the basename if empty use the element's key.
            if (empty($destination_basename)) {
                $destination_basename = $element['#webform_key'];
            }

            $destination_filename = $destination_basename . '.' . $destination_extension;
        }

        return $destination_folder . '/' . $destination_filename;
    }

    public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
        // We really want to avoid being redirected. This is how it is done.
        //@TODO manage file upload if there is no submission save handler
        //@ see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::postSave

        $form_state->disableRedirect();
    }

    public function preprocessConfirmation(array &$variables)
    {

        if ($this->isWidgetDriven()) {
            unset($variables['back']);
        }
    }

}

<?php

namespace Drupal\huacaya\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Stomp\Client;
use Stomp\Exception\StompException;
use Stomp\StatefulStomp;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config form for Huacaya settings.
 */
class HuacayaSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'huacaya.settings';
  const BROKER_URL = 'broker_url';
  const BROKER_USER = 'broker_user';
  const BROKER_PASSWORD = 'broker_password';
  const JWT_EXPIRY = 'jwt_expiry';
  const TIME_INTERVALS = [
    'sec',
    'second',
    'min',
    'minute',
    'hour',
    'day',
    'week',
    'month',
    'year',
  ];

  /**
   * The saved password (if set).
   *
   * @var string
   */
  private $brokerPassword;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    $this->setConfigFactory($config_factory);
    $this->brokerPassword = $this->config(self::CONFIG_NAME)->get(self::BROKER_PASSWORD);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'huacaya_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['broker_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Broker'),
      '#open' => TRUE,
    ];
    $form['broker_info'][self::BROKER_URL] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => $config->get(self::BROKER_URL),
    ];
    $broker_user = $config->get(self::BROKER_USER);
    $form['broker_info']['provide_user_creds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide user identification'),
      '#default_value' => $broker_user ? TRUE : FALSE,
    ];
    $state_selector = 'input[name="provide_user_creds"]';
    $form['broker_info'][self::BROKER_USER] = [
      '#type' => 'textfield',
      '#title' => $this->t('User'),
      '#default_value' => $broker_user,
      '#states' => [
        'visible' => [
          $state_selector => ['checked' => TRUE],
        ],
        'required' => [
          $state_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $form['broker_info'][self::BROKER_PASSWORD] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('If this field is left blank and the user is filled out, the current password will not be changed.'),
      '#states' => [
        'visible' => [
          $state_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $form[self::JWT_EXPIRY] = [
      '#type' => 'textfield',
      '#title' => $this->t('JWT Expiry'),
      '#default_value' => $config->get(self::JWT_EXPIRY),
      '#description' => $this->t('A positive time interval expression. Eg: "60 secs", "2 days", "10 hours", "7 weeks". Be sure you provide the time units (@unit), plurals are accepted.',
        ['@unit' => implode(", ", self::TIME_INTERVALS)]
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate broker url by actually connecting with a stomp client.
    $brokerUrl = $form_state->getValue(self::BROKER_URL);
    // Attempt to subscribe to a dummy queue.
    try {
      $client = new Client($brokerUrl);
      if ($form_state->getValue('provide_user_creds')) {
        $broker_password = $form_state->getValue(self::BROKER_PASSWORD);
        // When stored password type fields aren't rendered again.
        if (!$broker_password) {
          // Use the stored password if it exists.
          if (!$this->brokerPassword) {
            $form_state->setErrorByName(self::BROKER_PASSWORD, $this->t('A password must be supplied'));
          }
          else {
            $broker_password = $this->brokerPassword;
          }
        }
        $client->setLogin($form_state->getValue(self::BROKER_USER), $broker_password);
      }
      $stomp = new StatefulStomp($client);
      $stomp->subscribe('dummy-queue-for-validation');
      $stomp->unsubscribe();
    }
    // Invalidate the form if there's an issue.
    catch (StompException $e) {
      $form_state->setErrorByName(
        self::BROKER_URL,
        $this->t(
          'Cannot connect to message broker at @broker_url',
          ['@broker_url' => $brokerUrl]
        )
      );
    }

    // Validate jwt expiry as a valid time string.
    $expiry = trim($form_state->getValue(self::JWT_EXPIRY));
    $expiry = strtolower($expiry);
    if (strtotime($expiry) === FALSE) {
      $form_state->setErrorByName(
        self::JWT_EXPIRY,
        $this->t(
          '"@expiry" is not a valid time or interval expression.',
          ['@expiry' => $expiry]
        )
      );
    }
    elseif (substr($expiry, 0, 1) == "-") {
      $form_state->setErrorByName(
        self::JWT_EXPIRY,
        $this->t('Time or interval expression cannot be negative')
      );
    }
    elseif (intval($expiry) === 0) {
      $form_state->setErrorByName(
        self::JWT_EXPIRY,
        $this->t('No numeric interval specified, for example "1 day"')
      );
    }
    else {
      if (!preg_match("/\b(" . implode("|", self::TIME_INTERVALS) . ")s?\b/", $expiry)) {
        $form_state->setErrorByName(
          self::JWT_EXPIRY,
          $this->t("No time interval found, please include one of (@int). Plurals are also accepted.",
            ['@int' => implode(", ", self::TIME_INTERVALS)]
          )
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);

    $broker_password = $form_state->getValue(self::BROKER_PASSWORD);

    // If there's no user set delete what may have been here before as password
    // fields will also be blank.
    if (!$form_state->getValue('provide_user_creds')) {
      $config->clear(self::BROKER_USER);
      $config->clear(self::BROKER_PASSWORD);
    }
    else {
      $config->set(self::BROKER_USER, $form_state->getValue(self::BROKER_USER));
      // If the password has changed update it as well.
      if ($broker_password && $broker_password != $this->brokerPassword) {
        $config->set(self::BROKER_PASSWORD, $broker_password);
      }
    }

    $config
      ->set(self::BROKER_URL, $form_state->getValue(self::BROKER_URL))
      ->set(self::JWT_EXPIRY, $form_state->getValue(self::JWT_EXPIRY))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\huacaya;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\huacaya\Form\HuacayaSettingsForm;
use Stomp\Client;
use Stomp\StatefulStomp;

/**
 * StatefulStomp static factory.
 */
class StompFactory {

  /**
   * Factory function.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config.
   *
   * @return \Stomp\StatefulStomp
   *   Stomp client.
   */
  public static function create(ConfigFactoryInterface $config) {
    // Get broker url from config.
    $settings = $config->get(HuacayaSettingsForm::CONFIG_NAME);
    $brokerUrl = $settings->get(HuacayaSettingsForm::BROKER_URL);
    $brokerUser = $settings->get(HuacayaSettingsForm::BROKER_USER);
    // Try a sensible default if one hasn't been configured.
    if (empty($brokerUrl)) {
      $brokerUrl = "tcp://localhost:61613";
    }

    $client = new Client($brokerUrl);
    if ($brokerUser) {
      $client->setLogin($brokerUser, $settings->get(HuacayaSettingsForm::BROKER_PASSWORD));
    }
    return new StatefulStomp($client);
  }

}

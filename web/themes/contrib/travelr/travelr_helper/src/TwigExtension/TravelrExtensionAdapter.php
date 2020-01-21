<?php

namespace Drupal\travelr_helper\TwigExtension;
/**
 * Load custom twig functions from Pattern Lab
 */
class TravelrExtensionAdapter extends \Twig_Extension {

  public function __construct() {
    TravelrExtensionLoader::init();
  }

  public function getFunctions() {
    return TravelrExtensionLoader::get();
  }

}

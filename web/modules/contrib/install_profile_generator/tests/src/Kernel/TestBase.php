<?php

namespace Drupal\Tests\install_profile_generator\Kernel {

  use Drupal\KernelTests\KernelTestBase;

  /**
   * Test base class the exists to mock Drush methods.
   */
  abstract class TestBase extends KernelTestBase {
  }

}

namespace {

  use Drupal\Component\Render\FormattableMarkup;

  if (!function_exists('dt')) {

    /**
     * Dummy replacement for testing as Drush methods are not available.
     *
     * @param string $message
     *   A string containing placeholders. The string itself will not be escaped,
     *   any unsafe content must be in $args and inserted via placeholders.
     * @param array $arguments
     *   An array with placeholder replacements, keyed by placeholder. See
     *   \Drupal\Component\Render\FormattableMarkup::placeholderFormat() for
     *   additional information about placeholders.
     *
     * @return string
     *   The string with the placeholders replaced.
     */
    function dt($message, array $arguments = []) {
      return (string) (new FormattableMarkup($message, $arguments));
    }

  }
}
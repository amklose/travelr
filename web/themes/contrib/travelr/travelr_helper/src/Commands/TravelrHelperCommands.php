<?php

namespace Drupal\travelr_helper\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Drush;
use Drush\Commands\DrushCommands;
use Webmozart\PathUtil\Path;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class TravelrHelperCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * Create a new theme based on the Travelr theme.
   *
   * @param $name
   *   The name of your theme.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option description
   *   A short description of your new theme.
   * @option machine-name
   *   The machine-readable name of your new theme. This will be auto-generated from the human-readable name if omitted.
   * @usage drush travelr "New Theme Name"
   *   Creates a new theme called "New Theme Name" with a machine name of "new_theme_name".
   * @usage drush travelr "New Theme Name" --machine-name=new_theme
   *   Creates a new theme called "New Theme Name" with a machine name of "new_theme".
   *
   * @command travelr
   * @throws \Exception
   */
  public function travelr($name, array $options = ['description' => null, 'machine-name' => null]) {
    // Get new theme options.
    if (!isset($name)) {
      $name = $options['name'];
    }
    $machine_name = $options['machine-name'] ?: $this->travelr_machine_name($name);
    $description = $options['description'];

    // Validate the command.
    if (!$this->travelr_theme_exists('travelr')) {
      throw new \Exception(dt('Where is the Travelr theme? I could not find it.'));
    }

    if ($this->travelr_theme_exists($machine_name)) {
      throw new \Exception(dt('A theme with that name already exists. The machine-readable name must be unique.'));
    }

    if (!$machine_name || !preg_match('/^[a-z][a-z0-9_]*$/', $machine_name)) {
      throw new \Exception(dt('The machine name was invalid or could not be generated properly. It must start '
        . 'with a letter and may only contain lowercase letters, numbers, and underscores.'));
    }

    // Get theme paths.
    $drupalRoot = Drush::bootstrapManager()->getRoot();
    $travelr_path = Path::join($drupalRoot, drupal_get_path('theme', 'travelr'));
    $theme_path = substr($travelr_path, 0, strrpos( $travelr_path, '/'));
    $new_path = Path::join($theme_path, $machine_name);

    // Copy the Travelr theme directory recursively to the new theme’s location.
    drush_op('drush_copy_dir', $travelr_path, $new_path);

    // Remove Travelr’s helper module from the new theme.
    $this->travelr_recursive_rm(Path::join($new_path, 'travelr_helper'));

    // Rename the .info.yml file.
    $travelr_info_file = Path::join($new_path, 'travelr.info.yml');
    $new_info_file = Path::join($new_path, $machine_name . '.info.yml');
    drush_op('rename', $travelr_info_file, $new_info_file);

    // Update the .info.yml file based on the command options.
    $changes = [
      'Travelr' => $name,
      'Sass-based starter theme.' => $description,
      'travelr' => $machine_name,
    ];
    $this->travelr_file_str_replace($new_info_file, array_keys($changes), $changes);

    // Rename the .breakpoints.yml file.
    $travelr_info_file = Path::join($new_path, 'travelr.breakpoints.yml');
    $new_info_file = Path::join($new_path, $machine_name . '.breakpoints.yml');
    drush_op('rename', $travelr_info_file, $new_info_file);

    // Rename the .libraries.yml file.
    $travelr_libraries_file = Path::join($new_path, 'travelr.libraries.yml');
    $new_libraries_file = Path::join($new_path, $machine_name . '.libraries.yml');
    drush_op('rename', $travelr_libraries_file, $new_libraries_file);

    // Rename the .theme file.
    $travelr_theme_file = Path::join($new_path, 'travelr.theme');
    $new_theme_file = Path::join($new_path, $machine_name . '.theme');
    drush_op('rename', $travelr_theme_file, $new_theme_file);

    // Replace all occurrences of 'travelr' with the machine name of the new theme.
    $breakpoints_file = $machine_name . '.breakpoints.yml';
    $libraries_file = $machine_name . '.libraries.yml';
    $theme_file = $machine_name . '.theme';
    $files = [
      $breakpoints_file,
      $libraries_file,
      $theme_file,
      'js/scripts.js',
      'includes/block.inc',
      'includes/field.inc',
      'includes/form.inc',
      'includes/html.inc',
      'includes/navigation.inc',
      'includes/taxonomy.inc',
      'includes/views.inc',
    ];
    foreach ($files as $file) {
      $this->travelr_file_str_replace(Path::join($new_path, $file), 'travelr', $machine_name);
    }

    // Notify user of the newly created theme.
    $this->io()->block(dt(
      "\nThe \"!name\" theme has been created in: !path\n",
      [
        '!name' => $name,
        '!path' => $new_path,
      ]
    ), 'SUCCESS', 'fg=black;bg=green', ' ! ');

    // Warn the user that they might have some additional steps.
    $this->io()->caution(dt('If you want to remove the travelr theme entirely, be sure to copy and rename the '
     . 'travelr_helper module first.'));
    $this->io()->note(dt('The gulp commands for !name are still travelrBuild and travelrWatch. If you want to change '
      . 'those, you will need to modify the gulpfile and any build processes and/or CI tools.', [
        '!name' => $name,
    ]));
  }

  /**
   * Converts $name to a machine-readable format.
   */
  private function travelr_machine_name($name) {
    $name = str_replace(' ', '_', strtolower($name));
    $search = [
      // Remove characters not valid in function names.
      '/[^a-z0-9_]/',
      // Functions must begin with an alpha character.
      '/^[^a-z]+/',
    ];
    $name = preg_replace($search, '', $name);
    $name = str_replace('__', '_', $name);
    $name = trim($name, '_');

    return $name;
  }

  /**
   * Checks if $theme_name already exists in Drupal.
   */
  private function travelr_theme_exists($theme_name) {
    $theme_handler = \Drupal::service('theme_handler');
    $themes = $theme_handler->listInfo();

    return isset($themes[$theme_name]);
  }

  /**
   * Replace strings in a file.
   */
  private function travelr_file_str_replace($file_path, $find, $replace) {
    $file_path = Path::normalize($file_path);
    $file_contents = file_get_contents($file_path);
    $file_contents = str_replace($find, $replace, $file_contents);
    drush_op('file_put_contents', $file_path, $file_contents);
  }

  /**
   * Recursively removes all files and subfolders in a directory,
   * before finally deleting the directory itself.
   *
   * @param string $path
   *   Path to the top-level directory
   */
  private function travelr_recursive_rm($path) {
    if (is_dir($path)) {
      $dir_contents = scandir($path);
      foreach ($dir_contents as $item) {
        if ($item !== '.' && $item !== '..') {
          $subpath = Path::join($path, $item);
          if (is_dir($subpath)) {
            $this->travelr_recursive_rm($subpath);
          } else {
            drush_op('unlink', $subpath);
          }
        }
      }
      drush_op('rmdir', $path);
    }
  }
}

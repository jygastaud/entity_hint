<?php

/**
 * Implements hook_drush_command().
 */
function entity_hint_drush_command() {
  $items = [];

  $items['entity-hint'] = [
    'description' => 'Generate metadata hints for entities.',
    'callback'    => 'entity_hint_drush_command_callback',
    'examples'    => [
      'drush entity-hint > private/files/entity-hints.php'
    ],
  ];

  return $items;
}

function entity_hint_drush_command_callback() {
  require_once __DIR__ . '/entity_hint.module';

  $types = array_keys(entity_get_info());
  $output = [];
  foreach ($types as $type) {
    $output[] = entity_hint($type);

    foreach (array_keys(entity_get_info($type)['bundles']) as $bundle) {
      $output[] = entity_hint($type, $bundle);
    }
  }

  print "<?php\n\n" . implode("\n\n", $output);
}

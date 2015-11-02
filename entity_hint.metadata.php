<?php

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag as Tag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\TraitGenerator;

function entity_hint($entityType, $bundle = NULL) {
  if (NULL === $bundle) {
    return (new EntityTypeHintGenerator($entityType))->generate();
  }

  return (new EntityBundleHintGenerator($entityType, $bundle))->generate();
}

class EntityTypeHintGenerator {
  /** @var string */
  protected $entityType;

  /** @var EntityDrupalWrapper */
  protected $wrapper;

  /** @var array */
  protected $entity_info;

  public function __construct($entityType) {
    $this->entityType = $entityType;
    $this->wrapper = entity_metadata_wrapper($entityType);
    $this->entity_info = $this->wrapper->entityInfo();
  }

  protected function generateClassName() {
    $name = ucwords(str_replace('_', ' ', $this->entityType)) . 'MetadataWrapperTrait';
    return str_replace(' ', '', $name);
  }

  public function generate() {
    $metadata = (new TraitGenerator($this->generateClassName()))
      ->setDocBlock($this->generateDocBlock());

    foreach ($this->wrapper->getPropertyInfo() as $name => $info) {
      $metadata->addProperty('PROPERTY_' . strtoupper($name), $name, PropertyGenerator::FLAG_STATIC);
    }

    foreach ($this->wrapper->getPropertyInfo() as $name => $info) {
      $metadata->addPropertyFromGenerator($this->generateProperty($name, $info));
    }

    return $metadata->generate();
  }

  protected function generateDocBlock() {
    $entity_info = $this->wrapper->entityInfo();
    $doc = new DocBlockGenerator($entity_info['description']);

    if (isset($entity_info['label'])) {
      $doc->setShortDescription($entity_info['label']);
    }

    return $doc;
  }

  private function generateProperty($name, $info) {
    $label = isset($info['label']) ? $info['label'] : '';
    $description = isset($info['description']) ? strip_tags($info['description']) : '';
    $doc = new DocBlockGenerator($label, $label !== $description ? $description : '');
    if (isset($info['type'])) {
      $base_type = get_class($this->wrapper->get($name));
      $doc->setTag(new Tag('var', "{$base_type} {$info['type']}"));
    }

    if (!empty($info['required'])) {
      $doc->setTag(new Tag('required'));
    }

    $property = new PropertyGenerator($name);
    $property->setDocBlock($doc);
    return $property;
  }
}

class EntityBundleHintGenerator extends EntityTypeHintGenerator {
  private $bundle;

  public function __construct($entityType, $bundle) {
    $this->entityType = $entityType;
    $this->entity_info = entity_get_info($entityType);
    $this->bundle = $bundle;

    if ($bundleKey = $this->entity_info['bundle keys']['bundle']) {
      $this->wrapper = entity_metadata_wrapper($entityType, entity_create($entityType, [$bundleKey => $bundle]));
    }
    else {
      $this->wrapper = entity_metadata_wrapper($entityType);
    }
  }

  public function generate() {
    $metadata = (new ClassGenerator($this->generateClassName()))
      ->setDocBlock($this->generateDocBlock())
      ->setExtendedClass('EntityDrupalWrapper')
      ->setFinal(TRUE)
      ->addTrait(parent::generateClassName());

    foreach (field_info_instances($this->entityType, $this->bundle) as $name => $info) {
      $metadata->addConstant(
        str_replace('FIELD_FIELD_', 'FIELD_', 'FIELD_' . strtoupper($name)),
        $name
      );
    }

    foreach (field_info_instances($this->entityType, $this->bundle) as $name => $info) {
      $metadata->addPropertyFromGenerator($this->generateField($name, $info));
    }

    return $metadata->generate();
  }

  protected function generateClassName() {
    $name = ucwords(str_replace('_', ' ', $this->entityType . ' ' . $this->bundle)) . 'MetadataWrapper';
    return str_replace(' ', '', $name);
  }

  private function generateField($name, $info) {
    $label = isset($info['label']) ? $info['label'] : '';
    $description = isset($info['description']) ? $info['description'] : '';
    $doc = new DocBlockGenerator(NULL, $label !== $description ? $description : '');
    $doc->setTag(new Tag('field', $label));

    $field = field_info_field($name);
    if ('entityreference' === $field['type']) {
      $refType = $field['settings']['target_type'];
      $types = [];

      foreach ($field['settings']['handler_settings']['target_bundles'] as $refBundle) {
        $refVar = ucwords(str_replace('_', ' ', $refType . ' ' . $refBundle)) . 'MetadataWrapper';
        $types[] = str_replace(' ', '', $refVar);
      }

      $types = $types ?: ['Base' . ucwords($refType) . 'MetadataWrapper'];

      $doc->setTag(new Tag('var', implode('|', $types)));
    }
    else {
      try {
        $baseType = get_class($this->wrapper->get($name));
        $doc->setTag(new Tag('var', $baseType));
      }
      catch (EntityMalformedException $e) {
        $doc->setTag(new Tag('var', 'EntityValueWrapper|EntityListWrapper'));
      }
    }

    if (!empty($info['required'])) {
      $doc->setTag(new Tag('required'));
    }

    return (new PropertyGenerator($name))->setDocBlock($doc);
  }
}

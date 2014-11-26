<?php

class TermsSynchronizer extends SynchronizerBase {
    private $schema;

    function __construct(Storage $storage, wpdb $database, DbSchemaInfo $dbSchema) {
        parent::__construct($storage, $database, $dbSchema, 'terms');
        $this->schema = $dbSchema;
    }

    protected function transformEntities($entities) {
        $transformedEntities = array();
        foreach ($entities as $id => $entity) {
            $entityCopy = $entity;
            unset($entityCopy['taxonomies']); // taxonomies are synchronized by TermTaxonomySynchronizer
            $entityCopy[$this->schema->getEntityInfo('terms')->idColumnName] = $id;

            $transformedEntities[] = $entityCopy;
        }
        return $transformedEntities;
    }
}
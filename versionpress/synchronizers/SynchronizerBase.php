<?php

abstract class SynchronizerBase implements Synchronizer {

    private $entityName;
    private $idColumnName;

    /**
     * @var EntityStorage
     */
    private $storage;

    /**
     * @var wpdb
     */
    private $database;

    /**
     * @var DbSchemaInfo
     */
    private $dbSchema;

    function __construct(EntityStorage $storage, wpdb $database, DbSchemaInfo $dbSchema, $entityName) {
        $this->storage = $storage;
        $this->database = $database;
        $this->dbSchema = $dbSchema;
        $this->entityName = $entityName;
        $this->idColumnName = $dbSchema->getIdColumnName($this->entityName);
    }

    function synchronize() {
        $entities = $this->loadEntitiesFromStorage();
        $this->updateDatabase($entities);
        $this->fixReferences($entities);
        $this->mirrorDatabaseToStorage();
    }

    private function updateDatabase($entities) {
        $entities = $this->filterEntities($entities);

        $this->addOrUpdateEntities($entities);
        $this->deleteEntitiesWhichAreNotInStorage($entities);
    }

    private function fixReferences($entities) {
        $hasReferences = $this->dbSchema->hasReferences($this->entityName);
        if (!$hasReferences)
            return;

        $usedReferences = array();
        foreach ($entities as $entity) {
            $referenceDetails = $this->fixReferencesOfOneEntity($entity);
            $usedReferences = array_merge($usedReferences, $referenceDetails);
        }

        if (count($usedReferences) > 0) { // update reference table
            $referenceTableName = $this->getPrefixedTableName('vp_references');

            $insertQuery = "INSERT INTO {$referenceTableName} (" . join(array_keys($usedReferences[0]), ', ') . ")
            VALUES (" . join(array_map(function ($values) {
                    return join($values, ', ');
                }, $usedReferences), '), (') . ")
            ON DUPLICATE KEY UPDATE reference_vp_id = VALUES(reference_vp_id)";
            $this->executeQuery($insertQuery);

            $constraintOfAllExistingReferences = 'NOT((' . join(array_map(function ($a) {
                    return join(Arrays::parametrize($a), ' AND ');
                }, $usedReferences), ') OR (') . '))';
            $deleteQuery = "DELETE FROM {$referenceTableName} WHERE {$constraintOfAllExistingReferences} AND `table` = \"{$this->entityName}\"";
            $this->executeQuery($deleteQuery);
        }

        $references = $this->dbSchema->getReferences($this->entityName);
        foreach($references as $referenceName => $_){ // update foreign keys by VersionPress references
            $updateQuery = "UPDATE {$this->getPrefixedTableName($this->entityName)} entity SET `{$referenceName}` =
            (SELECT reference_id FROM {$this->getPrefixedTableName('vp_reference_details')} ref
            WHERE ref.id=entity.{$this->idColumnName} AND `table` = \"{$this->entityName}\" and reference = \"{$referenceName}\")";
            $this->executeQuery($updateQuery);
        }
    }

    private function mirrorDatabaseToStorage() {

        $entitiesInDatabase = $this->getEntitiesFromDatabase();
        $entitiesInStorage = $this->loadEntitiesFromStorage();

        $getEntityId = function ($entity) {
            return $entity[$this->idColumnName];
        };

        $dbEntityIds = array_map($getEntityId, $entitiesInDatabase);
        $storageEntityIds = array_map($getEntityId, $entitiesInStorage);

        $entitiesToDelete = array_diff($storageEntityIds, $dbEntityIds);
        $entitiesToSave = array_diff($dbEntityIds, $storageEntityIds);

        foreach ($entitiesToDelete as $entityId) {
            $this->storage->delete(array($this->idColumnName => $entityId));
        }

        foreach ($entitiesToSave as $key => $entityId) {
            $entity = $entitiesInDatabase[$key];
            $entity = $this->extendEntityWithIdentifier($entity);           // TODO: remove duplicity with install script
            $entity = $this->replaceForeignKeysWithIdentifiers($entity);    // ----
            $this->storage->save($entity);
        }
    }

    private function updateEntityInDatabase($entity) {
        $updateQuery = $this->buildUpdateQuery($entity);
        $this->executeQuery($updateQuery);
    }

    private function createEntityInDatabase($entity) {
        $createQuery = $this->buildCreateQuery($entity);
        $this->executeQuery($createQuery);
        $id = $this->database->insert_id;
        $this->createIdentifierRecord($entity['vp_id'], $id);
        return $id;
    }

    private function getId($vpId) {
        $vpIdTableName = $this->getPrefixedTableName('vp_id');
        return $this->database->get_var("SELECT id FROM $vpIdTableName WHERE `table` = \"$this->entityName\" AND vp_id = UNHEX('$vpId')");
    }

    private function getPrefixedTableName($tableName) {
        return $this->dbSchema->getPrefixedTableName($tableName);
    }

    protected function buildUpdateQuery($updateData) {
        $id = $this->getId($updateData['vp_id']);
        $query = "UPDATE {$this->getPrefixedTableName($this->entityName)} SET";
        foreach ($updateData as $key => $value) {
            if ($key == $this->idColumnName) continue;
            if (Strings::startsWith($key, 'vp_')) continue;
            $query .= " `$key` = " . (is_numeric($value) ? $value : '"' . mysql_real_escape_string($value) . '"') . ',';
        }
        $query[strlen($query) - 1] = ' '; // strip the last comma
        $query .= " WHERE $this->idColumnName = $id";
        return $query;
    }

    private function isExistingEntity($vpId) {
        return (bool)$this->getId($vpId);
    }

    protected function buildCreateQuery($entity) {
        unset($entity[$this->idColumnName]);
        $columns = array_keys($entity);
        $columns = array_filter($columns, function ($column) {
            return !Strings::startsWith($column, 'vp_');
        });
        $columnsString = join(', ', array_map(function ($column) {
            return "`$column`";
        }, $columns));

        $query = "INSERT INTO {$this->getPrefixedTableName($this->entityName)} ({$columnsString}) VALUES (";

        foreach ($columns as $column) {
            $query .= (is_numeric($entity[$column]) ? $entity[$column] : '"' . mysql_real_escape_string($entity[$column]) . '"') . ", ";
        }

        $query[strlen($query) - 2] = ' '; // strip the last comma
        $query .= ");";
        return $query;
    }

    /** used for debugging */
    private function executeQuery($query) {
        $result = $this->database->query($query);
        return $result;
    }

    private function createIdentifierRecord($vp_id, $id) {
        $query = "INSERT INTO {$this->getPrefixedTableName('vp_id')} (`table`, vp_id, id)
            VALUES (\"{$this->entityName}\", UNHEX('$vp_id'), $id)";
        $this->executeQuery($query);
    }

    private function deleteEntitiesWhichAreNotInStorage($entities) {
        if(count($entities) == 0)
            return;
        $vpIds = array_map(function ($entity) {
            return 'UNHEX("' . $entity['vp_id'] . '")';
        }, $entities);

        $ids = $this->database->get_col("SELECT id FROM {$this->getPrefixedTableName('vp_id')} " .
        "WHERE `table` = \"{$this->entityName}\" AND vp_id NOT IN (" . join(",", $vpIds) . ")");

        if (count($ids) == 0)
            return;

        $idsString = join(',', $ids);

        $this->executeQuery("DELETE FROM {$this->getPrefixedTableName($this->entityName)} WHERE {$this->idColumnName} IN ({$idsString})");
        $this->executeQuery("DELETE FROM {$this->getPrefixedTableName('vp_id')} WHERE `table` = \"{$this->entityName}\" AND id IN ({$idsString})"); // using cascade delete in mysql
    }

    private function getEntitiesFromDatabase() {
        return $this->database->get_results("SELECT * FROM {$this->getPrefixedTableName($this->entityName)}", ARRAY_A);
    }

    private function fixReferencesOfOneEntity($entity) {
        $references = $this->getAllReferences($entity);

        $referencesDetails = array();
        foreach ($references as $referenceName => $reference) {
            if ($reference == "")
                continue;

            $referencesDetails[] = array(
                '`table`' => "\"" . $this->entityName . "\"",
                'reference' => "\"" . $referenceName . "\"",
                'vp_id' => 'UNHEX("' . $entity['vp_id'] . '")',
                'reference_vp_id' => 'UNHEX("' . $reference . '")'
            );
        }

        return $referencesDetails;
    }

    private function getAllReferences($entity) {
        $references = array();
        $referencesInfo = $this->dbSchema->getReferences($this->entityName);

        foreach ($entity as $key => $value) {
            if (Strings::startsWith($key, 'vp_')) {
                $key = Strings::substring($key, 3);
                if (isset($referencesInfo[$key]))
                    $references[$key] = $value;
            }
        }

        return $references;
    }

    private function extendEntityWithIdentifier($entity) {
        $entity['vp_id'] = $this->getIdForEntity($this->entityName, $entity[$this->idColumnName]);
        return $entity;
    }

    private function getIdForEntity($entityName, $id) {
        return $this->database->get_var("SELECT HEX(vp_id) FROM {$this->getPrefixedTableName('vp_id')}
        WHERE `table` = \"{$entityName}\" AND id = {$id}");
    }

    private function replaceForeignKeysWithIdentifiers($entity) {
        if(!$this->dbSchema->hasReferences($this->entityName))
            return $entity;

        $references = $this->dbSchema->getReferences($this->entityName);

        foreach($references as $referenceName => $referenceInfo) {
            if(!isset($entity[$referenceName]) || $entity[$referenceName] == 0)
                continue;
            $entity['vp_' . $referenceName] = $this->getIdForEntity($referenceInfo['table'], $entity[$referenceName]);
        }

        return $entity;
    }

    protected function filterEntities($entities) {
        return $entities;
    }

    protected  function transformEntities($entities) {
        return $entities;
    }

    private function loadEntitiesFromStorage() {
        $entities = $this->storage->loadAll();
        $entities = $this->transformEntities($entities);
        return $entities;
    }

    private function addOrUpdateEntities($entities) {
        foreach ($entities as $entity) {
            $vpId = $entity['vp_id'];
            $isExistingEntity = $this->isExistingEntity($vpId);

            if ($isExistingEntity) {
                $this->updateEntityInDatabase($entity);
            } else {
                $this->createEntityInDatabase($entity);
            }
        }
    }
}
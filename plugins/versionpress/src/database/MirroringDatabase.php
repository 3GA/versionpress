<?php
/**
 * Mirroring database sends every change in DB (insert, update, delete) to file mirror
 */
class MirroringDatabase extends ExtendedWpdb {

    /**
     * @var Mirror
     */
    private $mirror;

    /**
     * @var DbSchemaInfo
     */
    private $dbSchemaInfo;

    function __construct($dbUser, $dbPassword, $dbName, $dbHost, Mirror $mirror, DbSchemaInfo $dbSchemaInfo) {
        parent::__construct($dbUser, $dbPassword, $dbName, $dbHost);
        $this->mirror = $mirror;
        $this->dbSchemaInfo = $dbSchemaInfo;
    }

    function insert($table, $data, $format = null) {
        $result = parent::insert($table, $data, $format);
        $id = $this->insert_id;
        $entityName = $this->stripTablePrefix($table);
        $shouldBeSaved = $this->mirror->shouldBeSaved($entityName, $data);

        if (!$shouldBeSaved)
            return $result;

        if ($this->dbSchemaInfo->getEntityInfo($entityName)->usesGeneratedVpids) {
            $data['vp_id'] = $this->generateId();
            $this->saveId($entityName, $id, $data['vp_id']);
        }

        if ($this->dbSchemaInfo->getEntityInfo($entityName)->hasReferences) {
            $data = $this->saveReferences($entityName, $data);
        }

        $data[$this->dbSchemaInfo->getEntityInfo($entityName)->idColumnName] = $id;

        $data = $this->fillId($entityName, $data, $id);
        $this->mirror->save($entityName, $data);

        $this->insert_id = $id; // it was reset by saving id and references
        return $result;
    }

    function update($table, $data, $where, $format = null, $where_format = null) {
        $result = parent::update($table, $data, $where, $format, $where_format);
        $entityName = $this->stripTablePrefix($table);

        $data = array_merge($data, $where);

        $shouldBeSaved = $this->mirror->shouldBeSaved($entityName, $data);
        if (!$shouldBeSaved) {
            return $result;
        }

        if ($this->dbSchemaInfo->getEntityInfo($entityName)->usesGeneratedVpids) {

            $idColumnName = $this->dbSchemaInfo->getEntityInfo($entityName)->idColumnName;
            $ids = array();

            if ($entityName === 'usermeta') {
                $ids[] = $this->getUsermetaId($data['user_id'], $data['meta_key']);
            } elseif ($entityName === 'postmeta') {
                $ids[] = $this->getPostMetaId($data['post_id'], $data['meta_key']);
            } elseif (isset($where[$idColumnName])) {
                $ids[] = $where[$idColumnName];
            } else {
                $ids = $this->getIdsForRestriction($entityName, $where);
            }

            foreach ($ids as $id) {
                $vpId = $this->getVpId($entityName, $id);


                if (!$vpId) {
                    $data['vp_id'] = $this->generateId();
                    $this->saveId($entityName, $id, $data['vp_id']);
                } else {
                    $data['vp_id'] = $vpId;
                }

                if ($this->dbSchemaInfo->getEntityInfo($entityName)->hasReferences) {
                    $data = $this->saveReferences($entityName, $data);
                }

                $this->mirror->save($entityName, $data);
            }
            return $result;
        }

        $this->mirror->save($entityName, $data);
        return $result;
    }

    function delete($table, $where, $where_format = null) {
        $result = parent::delete($table, $where, $where_format);

        $entityName = $this->stripTablePrefix($table);

        if ($this->dbSchemaInfo->getEntityInfo($entityName)->usesGeneratedVpids) {
            $ids = array();
            $hasReferences = $this->dbSchemaInfo->getEntityInfo($entityName)->hasReferences;
            $idColumnName = $this->dbSchemaInfo->getEntityInfo($entityName)->idColumnName;
            if(isset($where[$idColumnName])) {
                $ids[] = $where[$idColumnName];
            } else {
                $ids = $this->getIdsForRestriction($entityName, $where);
            }

            foreach ($ids as $id) {
                if ($hasReferences) {
                    $this->deleteReferences($entityName, $id);
                }

                $where['vp_id'] = $this->getVpId($entityName, $id);
                $this->deleteId($entityName, $id);
                $this->mirror->delete($entityName, $where);
            }

            return $result;
        }

        $this->mirror->delete($entityName, $where);
        return $result;
    }

    private function stripTablePrefix($tableName) {
        return substr($tableName, strlen($this->prefix));
    }

    private function addTablePrefix($entityName) {
        return $this->prefix . $entityName;
    }

    private function saveId($entityName, $id, $vpId) {
        $vpIdTableName = $this->getVpIdTableName();
        $query = "INSERT INTO $vpIdTableName (`vp_id`, `table`, `id`) VALUES (UNHEX('$vpId'), \"$entityName\", $id)";
        $this->query($query);
    }

    private function saveReference($entityName, $referenceName, $vpId, $id) {
        $referenceId = $this->getReferenceId($entityName, $referenceName, $id);

        if ($referenceId === null)
            return null;

        $this->creteReferenceRecord($entityName, $referenceName, $vpId, $referenceId);
        return $referenceId;
    }

    private function deleteReferences($entityName, $id) {
        $referencesTableName = $this->getVpReferenceTableName();
        $vpId = $this->getVpId($entityName, $id);
        $deleteQuery = "DELETE FROM $referencesTableName WHERE vp_id = UNHEX('$vpId')";
        $this->query($deleteQuery);
    }

    private function deleteId($entityName, $id) {
        $vpIdTableName = $this->getVpIdTableName();
        $deleteQuery = "DELETE FROM $vpIdTableName WHERE `table` = \"$entityName\" AND id = $id";
        $this->query($deleteQuery);
    }

    private function hasId($entityName, $id) {
        return (bool)$this->getVpId($entityName, $id);
    }

    private function getVpIdTableName() {
        return $this->addTablePrefix('vp_id');
    }

    private function getVpReferenceTableName() {
        return $this->addTablePrefix('vp_references');
    }

    private function creteReferenceRecord($entityName, $referenceName, $vpId, $referenceId) {
        $vpReferenceTableName = $this->getVpReferenceTableName();

        $query = "INSERT INTO $vpReferenceTableName (`table`, `reference`, `vp_id`, `reference_vp_id`)
                    VALUES (\"$entityName\", \"$referenceName\", UNHEX('$vpId'), UNHEX('$referenceId'))
                    ON DUPLICATE KEY UPDATE `reference_vp_id` = VALUES(reference_vp_id)";
        $this->query($query);
    }

    private function getReferenceId($entityName, $referenceName, $id) {
        $reference = $this->dbSchemaInfo->getEntityInfo($entityName)->references[$referenceName];
        $referenceId = $this->getVpId($reference, $id);
        return $referenceId;
    }

    private function saveReferences($entityName, $data) {
        $references = $this->dbSchemaInfo->getEntityInfo($entityName)->references;
        foreach ($references as $referenceName => $referenceInfo) {
            if (isset($data[$referenceName]) && $data[$referenceName] > 0) {
                $referenceId = $this->saveReference($entityName, $referenceName, $data['vp_id'], $data[$referenceName]);
                $data['vp_' . $referenceName] = $referenceId;
            }
            unset($data[$referenceName]);
        }
        return $data;
    }

    private function generateId() {
        return IdUtil::newId();
    }

    private function fillId($entityName, $data, $id) {
        $idColumnName = $this->dbSchemaInfo->getEntityInfo($entityName)->idColumnName;
        if (!isset($data[$idColumnName])) {
            $data[$idColumnName] = $id;
        }
        return $data;
    }

    private function getVpId($entityName, $id) {
        $vpIdTableName = $this->getVpIdTableName();
        $getVpIdSql = "SELECT HEX(vp_id) FROM $vpIdTableName WHERE `table` = \"$entityName\" AND id = $id";
        return $this->get_var($getVpIdSql);
    }

    private function getUsermetaId($user_id, $meta_key) {
        $getMetaIdSql = "SELECT umeta_id FROM {$this->prefix}usermeta WHERE meta_key = \"$meta_key\" AND user_id = $user_id";
        return $this->get_var($getMetaIdSql);
    }

    private function getPostMetaId($post_id, $meta_key) {
        $getMetaIdSql = "SELECT meta_id FROM {$this->prefix}postmeta WHERE meta_key = \"$meta_key\" AND post_id = $post_id";
        return $this->get_var($getMetaIdSql);
    }

    /**
     * Returns all ids from DB suitable for given restriction.
     * E.g. all comment_id values where comment_post_id = 1
     * @param string $entityName
     * @param array $where
     * @return array
     */
    private function getIdsForRestriction($entityName, $where) {
        $idColumnName = $this->dbSchemaInfo->getEntityInfo($entityName)->idColumnName;
        $table = $this->dbSchemaInfo->getPrefixedTableName($entityName);

        $sql = "SELECT {$idColumnName} FROM {$table} WHERE ";
        $sql .= join(
            " AND ",
            array_map(
                function ($column) {
                    return "`$column` = %s";
                },
                array_keys($where)
            )
        );
        $ids = $this->get_col($this->prepare($sql, $where));
        return $ids;
    }
}
<?php

class TermRelationshipsSynchronizer implements Synchronizer {

    /**
     * @var PostStorage
     */
    private $postStorage;
    private $postIdColumnName;
    /**
     * @var ExtendedWpdb
     */
    private $database;
    /**
     * @var DbSchemaInfo
     */
    private $dbSchema;

    function __construct(EntityStorage $postStorage, ExtendedWpdb $database, DbSchemaInfo $dbSchema) {
        $this->postStorage = $postStorage;
        $this->postIdColumnName = $dbSchema->getIdColumnName('posts');
        $this->database = $database;
        $this->dbSchema = $dbSchema;
    }

    function synchronize() {
        $entities = $this->loadEntitiesFromStorage();
        $this->truncateTable();
        $this->fillTable($entities);
    }

    private function transformEntities($entities) {
        $relationships = array();

        foreach($entities as $post) {
            if(isset($post['category']))
                foreach($post['category'] as $category)
                    $relationships[] = array(
                        'vp_object_id' => $post['vp_id'],
                        'vp_term_taxonomy_id' => $category
                    );
            if(isset($post['post_tag']))
                foreach($post['post_tag'] as $tag)
                    $relationships[] = array(
                        'vp_object_id' => $post['vp_id'],
                        'vp_term_taxonomy_id' => $tag
                    );
        }

        return $relationships;
    }

    private function loadEntitiesFromStorage() {
        return $this->transformEntities($this->postStorage->loadAll());
    }

    private function getVpIdsMap($entities) {
        $vpIds = array();
        foreach($entities as $entity) {
            $vpIds[] = $entity['vp_object_id'];
            $vpIds[] = $entity['vp_term_taxonomy_id'];
        }

        $hexVpIds = array_map(function($vpId) { return "UNHEX('" . $vpId  . "')"; }, $vpIds);

        return $this->database->get_results('SELECT HEX(vp_id), id FROM ' . $this->dbSchema->getPrefixedTableName('vp_id') . ' WHERE vp_id IN (' . join(', ', $hexVpIds) . ')', ARRAY_MAP);
    }

    private function truncateTable() {
        $this->database->query('TRUNCATE TABLE ' . $this->dbSchema->getPrefixedTableName('term_relationships'));
    }

    private function fillTable($entities) {
        $vpIdsMap = $this->getVpIdsMap($entities);
        $sql = 'INSERT INTO ' . $this->dbSchema->getPrefixedTableName('term_relationships') . ' (object_id, term_taxonomy_id) VALUES ';
        $valuesSql = array();
        foreach($entities as $entity) {
            $valuesSql[] = "(" . $vpIdsMap[$entity['vp_object_id']] . ", " . $vpIdsMap[$entity['vp_term_taxonomy_id']] . ")";
        }
        $sql .= join(', ', $valuesSql);

        $this->database->query($sql);
    }
}
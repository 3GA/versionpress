<?php

namespace VersionPress\Synchronizers;

use VersionPress\Database\DbSchemaInfo;
use VersionPress\Storages\Storage;
use wpdb;

class TermTaxonomySynchronizer extends SynchronizerBase {

    /** @var wpdb */
    private $database;

    function __construct(Storage $storage, $wpdb, DbSchemaInfo $dbSchema) {
        parent::__construct($storage, $wpdb, $dbSchema, 'term_taxonomy');
        $this->database = $wpdb;
    }

    function synchronize($task, $entitiesToSynchronize = null) {
        $this->database->query("drop index term_id_taxonomy on {$this->database->term_taxonomy}");
        $result = parent::synchronize($task, $entitiesToSynchronize);
        $this->database->query("create unique index term_id_taxonomy on {$this->database->term_taxonomy}(term_id, taxonomy)");
        return $result;
    }
}

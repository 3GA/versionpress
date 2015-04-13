<?php

namespace VersionPress\Tests\StorageTests;

use VersionPress\Database\EntityInfo;
use VersionPress\Storages\TermsStorage;
use VersionPress\Utils\FileSystem;

class TermsStorageTest extends \PHPUnit_Framework_TestCase {
    /** @var TermsStorage */
    private $storage;

    private $testingTerm = array(
        "name" => "Uncategorized",
        "slug" => "uncategorized",
        "term_group" => 0,
        "vp_id" => "566D438B716C404D8CC384AE8F86A974",
    );

    /**
     * @test
     */
    public function savedTermEqualsLoadedTerm() {
        $this->storage->save($this->testingTerm);
        $loadedTerm = $this->storage->loadEntity($this->testingTerm['vp_id']);
        $this->assertEquals($this->testingTerm, $loadedTerm);
    }

    /**
     * @test
     */
    public function loadAllReturnsOnlyOriginalEntities() {
        $this->storage->save($this->testingTerm);
        $loadedTerms = $this->storage->loadAll();
        $this->assertTrue(count($loadedTerms) === 1);
        $this->assertEquals($this->testingTerm, reset($loadedTerms));
    }

    /**
     * @test
     */
    public function savedTermDoesNotContainVpIdKey() {
        $this->storage->save($this->testingTerm);
        $fileName = $this->storage->getEntityFilename($this->testingTerm['vp_id']);
        $content = file_get_contents($fileName);
        $this->assertFalse(strpos($content, 'vp_id'), 'Entity contains a vp_id key');
    }

    protected function setUp() {
        parent::setUp();
        $entityInfo = new EntityInfo(array(
            'term' => array(
                'table' => 'terms',
                'id' => 'term_id',
            )
        ));

        $this->storage = new TermsStorage(__DIR__ . '/terms.ini', $entityInfo);
    }

    protected function tearDown() {
        parent::tearDown();
        FileSystem::remove(__DIR__ . '/terms.ini');
    }
}

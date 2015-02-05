<?php

namespace VersionPress\Tests\StorageTests;

use VersionPress\Database\EntityInfo;
use VersionPress\Storages\CommentStorage;
use VersionPress\Utils\FileSystem;

class CommentStorageTest extends \PHPUnit_Framework_TestCase {
    /** @var CommentStorage */
    private $storage;

    private $testingComment = array(
        "comment_author" => "Mr WordPress",
        "comment_author_email" => "",
        "comment_author_url" => "https://wordpress.org/",
        "comment_author_IP" => "",
        "comment_date" => "2015-02-02 14:19:59",
        "comment_date_gmt" => "2015-02-02 14:19:59",
        "comment_content" => "Hi, this is a comment.",
        "comment_karma" => 0,
        "comment_approved" => 1,
        "comment_agent" => "",
        "comment_type" => "",
        "vp_id" => "927D63C187164CA1BCEAB2B13B29C8F0",
        "vp_comment_post_ID" => "F0E1B6313B7A48E49A1B38DF382B350D",
    );

    /**
     * @test
     */
    public function savedCommentEqualsLoadedComment() {
        $this->storage->save($this->testingComment);
        $loadedComment = $this->storage->loadEntity($this->testingComment['vp_id']);
        $this->assertTrue($this->testingComment == $loadedComment);
    }

    protected function setUp() {
        parent::setUp();
        $entityInfo = new EntityInfo(array(
            'comment' => array(
                'table' => 'comments',
                'id' => 'ID',
                'references' => array (
                    'comment_post_ID' => 'post',
                    'comment_parent' => 'comment',
                )
            )
        ));
        mkdir(__DIR__ . '/comments');
        $this->storage = new CommentStorage(__DIR__ . '/comments', $entityInfo);
    }

    protected function tearDown() {
        parent::tearDown();
        FileSystem::remove(__DIR__ . '/comments');
    }
}

global $wpdb;
$wpdb = new wpdbFake;

class wpdbFake {
    public $prefix;
    function get_row() {
        $fakePost = new \stdClass();
        $fakePost->post_title = "";
        return $fakePost;
    }
}
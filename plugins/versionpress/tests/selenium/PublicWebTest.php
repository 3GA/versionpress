<?php

class PublicWebTest extends SeleniumTestCase {

    private static $testPostId;
    private static $testPost = array(
        "post_type" => "post",
        "post_status" => "publish",
        "post_title" => "Test post for testing public web",
        "post_date" => "2012-11-10 09:08:07",
        "post_content" => "Test post content",
        "post_author" => 1
    );

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        self::$autologin = false;
        self::$testPostId = WpAutomation::createPost(self::$testPost);
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        WpAutomation::deletePost(self::$testPostId);
    }

    /**
     * @test
     * @testdox Public web is accessible
     */
    public function publicWebIsAccessible() {
        $this->url("?p=" . self::$testPostId);
        $this->assertStringStartsWith(self::$testPost["post_title"], $this->title());
    }

    /**
     * Same test as {@link CommentsTest::testNewComment}
     * @test
     * @depends publicWebIsAccessible
     */
    public function commentCanBeAdded() {
        $commitAsserter = new CommitAsserter($this->gitRepository);

        $this->url('?p=' . self::$testPostId);

        $this->byCssSelector('#author')->value("John Tester");
        $this->byCssSelector('#email')->value("john.tester@example.com");
        $this->byCssSelector('#comment')->value("Public comment");

        $this->byCssSelector('#submit')->click();
        $this->waitAfterRedirect();

        $commitAsserter->assertNumCommits(1);
        $commitAsserter->assertCommitAction("comment/create-pending");
        $commitAsserter->assertCommitPath("A", "%vpdb%/comments/%VPID%.ini");
        $commitAsserter->assertCleanWorkingDirectory();
    }
}
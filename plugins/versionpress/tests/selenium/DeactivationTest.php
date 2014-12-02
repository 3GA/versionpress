<?php

/**
 * Tests VersionPress deactivation / reactivation / uninstallation flow.
 */
class DeactivationTest extends SeleniumTestCase {

    /**
     * VP is installed here in "before class" to prevent empty open browser seemingly
     * doing nothing.
     */
    public static function setUpBeforeClass() {
        WpAutomation::setUpSite();
        WpAutomation::installVersionPress();
    }

    /**
     * @test
     */
    public function stepZero() {
        $this->loginIfNecessary();
        $this->_activateVersionPress();
        $this->_initializeVersionPress();
    }


    /**
     * Scenario: cancel deactivation
     *
     * @test
     */
    public function clickDeactivateButThenCancel() {
        $this->url('wp-admin/plugins.php');
        $this->byCssSelector('#versionpress .deactivate a')->click();

        $this->assertContains('versionpress/admin/deactivate.php', $this->url());

        $this->byCssSelector('#cancel_deactivation')->click();

        $this->assertContains('wp-admin/plugins.php', $this->url());
        $this->assertFileExists(self::$config->getSitePath() . '/wp-content/db.php');
    }


    /**
     * Scenario: confirm deactivation
     *
     * @test
     */
    public function clickDeactivateAndConfirmThat() {

        $this->byCssSelector('#versionpress .deactivate a')->click();  // takes us to the deactivation screen
        $this->byCssSelector('#confirm_deactivation')->click(); // takes us to plugins.php


        $this->assertFileNotExists(self::$config->getSitePath() . '/wp-content/db.php');
        $this->assertFileNotExists(self::$config->getSitePath() . '/wp-content/vpdb/.active');
        $this->assertFileExists(self::$config->getSitePath() . '/.git');

    }

    /**
     * Scenario: reactivate plugin
     *
     * @test
     */
    public function reactivatePlugin() {
        $this->_activateVersionPress();
        $this->assertFileExists(self::$config->getSitePath() . '/wp-content/db.php');
    }

    /**
     * @test
     */
    public function deactivateAndUninstall() {
        $this->byCssSelector('#versionpress .deactivate a')->click();  // takes us to the deactivation screen
        $this->byCssSelector('#confirm_deactivation')->click(); // takes us to plugins.php

        $this->byCssSelector('#versionpress .delete a')->click();

        $this->byCssSelector('.wrap form:nth-of-type(1) input#submit')->click();

        $this->assertFileNotExists(self::$config->getSitePath() . '/wp-content/db.php');
        $this->assertFileNotExists(self::$config->getSitePath() . '/wp-content/plugins/versionpress');
        $this->assertFileNotExists(self::$config->getSitePath() . '/.git');

    }


    //---------------------
    // Helper functions
    //---------------------

    private function _activateVersionPress()
    {
        $this->url('wp-admin/plugins.php');
        $this->byCssSelector('#versionpress .activate a')->click();
    }

    private function _initializeVersionPress()
    {
        $this->url('wp-admin/admin.php?page=versionpress/admin/index.php');
        $this->byCssSelector('#activate-versionpress-btn')->click();
    }

}

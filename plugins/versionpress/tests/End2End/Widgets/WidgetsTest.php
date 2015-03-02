<?php

namespace VersionPress\Tests\End2End\Widgets;

use Exception;
use VersionPress\Tests\End2End\Utils\End2EndTestCase;
use VersionPress\Tests\Utils\CommitAsserter;

class WidgetsTest extends End2EndTestCase {

    /** @var IWidgetsWorker */
    private static $worker;

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        self::clearSidebars();
        try {
            self::$wpAutomation->deleteOption('widget_calendar');
        } catch (Exception $e) {
            // It's ok. This option does not exist in the clear WP.
        }
    }

    /**
     * @test
     * @testdox Creating first widget of given type creates new option
     */
    public function creatingFirstWidgetOfGivenTypeCreatesOption() {
        self::$worker->prepare_createWidget();

        $commitAsserter = new CommitAsserter($this->gitRepository);

        self::$worker->createWidget();

        $commitAsserter->assertNumCommits(1);
        $commitAsserter->assertCommitAction('option/create');
        $commitAsserter->assertCountOfAffectedFiles(1);
        $commitAsserter->assertCommitPath('M', '%vpdb%/options.ini');
        $commitAsserter->assertCleanWorkingDirectory();
    }

    /**
     * @test
     * @testdox Creating second widget of given type updates the option
     * @depends creatingFirstWidgetOfGivenTypeCreatesOption
     */
    public function creatingSecondWidgetOfGivenTypeUpdatesOption() {
        self::$worker->prepare_createWidget();

        $commitAsserter = new CommitAsserter($this->gitRepository);

        self::$worker->createWidget();

        $commitAsserter->assertNumCommits(1);
        $commitAsserter->assertCommitAction('option/edit');
        $commitAsserter->assertCountOfAffectedFiles(1);
        $commitAsserter->assertCommitPath('M', '%vpdb%/options.ini');
        $commitAsserter->assertCleanWorkingDirectory();
    }

    /**
     * @test
     * @testdox Editing widget creates 'option/edit' action.
     */
    public function editingWidgetCreatesOptionEditAction() {
        self::$worker->prepare_editWidget();

        $commitAsserter = new CommitAsserter($this->gitRepository);

        self::$worker->editWidget();

        $commitAsserter->assertNumCommits(1);
        $commitAsserter->assertCommitAction('option/edit');
        $commitAsserter->assertCountOfAffectedFiles(1);
        $commitAsserter->assertCommitPath('M', '%vpdb%/options.ini');
        $commitAsserter->assertCleanWorkingDirectory();
    }

    /**
     * @test
     * @testdox Deleting widget creates 'option/edit' action
     */
    public function deletingWidgetCreatesOptionEditAction() {
        self::$worker->prepare_deleteWidget();

        $commitAsserter = new CommitAsserter($this->gitRepository);

        self::$worker->deleteWidget();

        $commitAsserter->assertNumCommits(1);
        $commitAsserter->assertCommitAction('option/edit');
        $commitAsserter->assertCountOfAffectedFiles(1);
        $commitAsserter->assertCommitPath('M', '%vpdb%/options.ini');
        $commitAsserter->assertCleanWorkingDirectory();
    }

    private static function clearSidebars() {
        $sidebars = self::$wpAutomation->getSidebars();
        if (count($sidebars) == 0) {
            self::fail('Tests can be run only with theme with sidebars');
        }
        foreach ($sidebars as $sidebar) {
            $widgets = self::$wpAutomation->getWidgets($sidebar);
            self::$wpAutomation->deleteWidgets($widgets);
        }
    }
}

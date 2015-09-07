<?php

namespace VersionPress\Tests\End2End\Themes;

use VersionPress\Tests\End2End\Utils\WpCliWorker;

class ThemesTestWpCliWorker extends WpCliWorker implements IThemesTestWorker {

    private $themeInfo;
    private $secondThemeInfo;

    public function setThemeInfo($themeInfo) {
        $this->themeInfo = $themeInfo;
    }

    public function setSecondThemeInfo($themeInfo) {
        $this->secondThemeInfo = $themeInfo;
    }

    public function prepare_uploadTheme() {
    }

    public function uploadTheme() {
        $this->wpAutomation->runWpCliCommand('theme', 'install', array($this->themeInfo['zipfile']));
    }

    public function prepare_switchTheme() {
    }

    public function switchTheme() {
        $this->wpAutomation->runWpCliCommand('theme', 'activate', array($this->themeInfo['stylesheet']));
    }

    public function prepare_deleteTheme() {
    }

    public function deleteTheme() {
        $this->wpAutomation->runWpCliCommand('theme', 'delete', array($this->themeInfo['stylesheet']));
    }

    public function prepare_uploadTwoThemes() {
    }

    public function uploadTwoThemes() {
        $this->wpAutomation->runWpCliCommand('theme', 'install', array($this->themeInfo['zipfile'], $this->secondThemeInfo['zipfile']));
    }

    public function prepare_deleteTwoThemes() {
    }

    public function deleteTwoThemes() {
        $this->wpAutomation->runWpCliCommand('theme', 'delete', array($this->themeInfo['stylesheet'], $this->secondThemeInfo['stylesheet']));
    }
}
<?php

class TestConfigTest extends PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function neonParsingWorks() {

        $config = new TestConfig(__DIR__ . "/../test-config.sample.neon");

        // 'selenium' section
        $this->assertEquals("C:/Path/To/FirefoxPortable/App/Firefox/firefox.exe", $config->seleniumConfig->firefoxBinary);
        $this->assertEquals(500, $config->seleniumConfig->postCommitWaitTime);

        // 'sites' section
        $this->assertEquals(2, count($config->sites));
        $this->assertEquals("VP Test @ WampServer", $config->sites["vp01"]->title);
        $this->assertFalse($config->sites["vp01"]->isVagrant);

        // 'vp-config' section
        $this->assertEquals(null, $config->sites["vp01"]->vpConfig["git-binary"]);
        $this->assertEquals("/usr/bin/git", $config->sites["vagrant-php53"]->vpConfig["git-binary"]);

    }
}

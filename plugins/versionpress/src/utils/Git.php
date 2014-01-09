<?php

abstract class Git {

    private static $gitRoot = null;

    // Constants
    private static $ADD_AND_COMMIT_COMMAND = "git add -A %s && git commit -m %s";
    private static $RELPATH_TO_GIT_ROOT_COMMAND = "git rev-parse --show-cdup";
    private static $INIT_COMMAND = "git init -q";
    private static $ASSUME_UNCHANGED_COMMAND = "git update-index --assume-unchanged %s";
    private static $COMMIT_MESSAGE_PREFIX = '[VP] ';

    static function commit($message, $directory = "") {
        chdir(dirname(__FILE__));
        if ($directory === "" && self::$gitRoot === null) {
            self::detectGitRoot();
        }
        $directory = $directory === "" ? self::$gitRoot : $directory;
        $gitAddPath = $directory . "/" . "*";

        self::runShellCommand(self::$ADD_AND_COMMIT_COMMAND, $gitAddPath, self::$COMMIT_MESSAGE_PREFIX . $message);
    }

    static function isVersioned($directory) {
        chdir($directory);
        return self::runShellCommand('git status') !== NULL;
    }

    static function createGitRepository($directory) {
        chdir($directory);
        self::runShellCommand(self::$INIT_COMMAND);
    }

    private static function detectGitRoot() {
        self::$gitRoot = trim(self::runShellCommand(self::$RELPATH_TO_GIT_ROOT_COMMAND), "/\n");
        self::$gitRoot = self::$gitRoot === '' ? '.' : self::$gitRoot;
    }

    private static function runShellCommand($command, $args = '') {
        $functionArgs = func_get_args();
        array_shift($functionArgs); // Remove $command
        $escapedArgs = @array_map("escapeshellarg", $functionArgs);
        $commandWithArguments = vsprintf($command, $escapedArgs);
        return @shell_exec($commandWithArguments);
    }

    public static function pull() {
        self::runShellCommand("git pull -s recursive -X theirs origin master");
    }

    public static function push() {
        self::runShellCommand("git push origin master");
    }

    public static function assumeUnchanged($filename) {
        self::runShellCommand(self::$ASSUME_UNCHANGED_COMMAND, $filename);
    }

    public static function log() {
        $log = self::runShellCommand("git log --pretty=oneline");
        $commits = explode("\n", $log);
        return array_map(function ($commit){
            list($id, $message) = explode(" ", $commit, 2);
            return array("id" => $id, "message" => $message);
        }, $commits);
    }
}
<?php

define('CONFIG_FILE', __DIR__ . '/test-config.ini');
is_file(CONFIG_FILE) or die('Create test-config.ini for automation to work');
WpAutomation::$config = new TestConfig(parse_ini_file(CONFIG_FILE));

/**
 * Automates some common tasks like setting up a WP site etc.
 *
 * Note: Currently, the intention is to add supported tasks as public methods to this class. If this gets
 * unwieldy it will probably be split into multiple files / classes.
 */
class WpAutomation {

    /**
     * Config loaded from test-config.ini
     *
     * @var TestConfig
     */
    public static $config;


    /**
     * Does a full setup of a WP site including removing the old site,
     * downloading files from wp.org, setting up a fresh database, executing
     * the install script etc.
     */
    public static function setUpSite() {
        self::prepareFiles();
        self::createConfigFile();
        self::clearDatabase();
        self::installWordpress();
    }


    /**
     * Puts WP directory to a default state, as if one manually downloaded the
     * WordPress ZIP and extracted it there.
     */
    private static function prepareFiles() {
        self::ensureCleanInstallationIsAvailable();
        \Nette\Utils\FileSystem::delete(self::$config->getSitePath() . '/*');
        Nette\Utils\FileSystem::copy(self::getCleanInstallationPath(), self::$config->getSitePath());
    }

    /**
     * Ensures that the clean installation of WordPress is available locally. If not,
     * it downloads it from wp.org and stores it as `<clean-installations-dir>/<version>`.
     */
    private static function ensureCleanInstallationIsAvailable() {
        if (!is_dir(self::getCleanInstallationPath())) {
            $downloadPath = self::getCleanInstallationPath();
            $wpVersion = self::$config->getWpVersion();
            $downloadCommand = "wp core download --path=\"$downloadPath\" --version=$wpVersion";

            self::exec($downloadCommand, self::$config->getCleanInstallationsPath());
        }
    }


    /**
     * Returns a path where a clean installation of the configured WP version is stored and cached.
     *
     * @return string
     */
    private static function getCleanInstallationPath() {
        return self::$config->getCleanInstallationsPath() . '/' . self::$config->getWpVersion();
    }


    /**
     * Creates wp-config.php based on values in test-config.ini
     */
    private static function createConfigFile() {
        $args = array();
        $args["--dbname"] = self::$config->getDbName();
        $args["--dbuser"] = self::$config->getDbUser();
        if (self::$config->getDbPassword()) $args["--dbpass"] = self::$config->getDbPassword();
        if (self::$config->getDbPassword()) $args["--dbhost"] = self::$config->getDbHost();

        $configCommand = "wp core config";
        foreach ($args as $argName => $argValue) {
            $configCommand .= " $argName=\"$argValue\"";
        }

        self::exec($configCommand, self::$config->getSitePath());
    }

    /**
     * Deletes all tables from the database.
     */
    private static function clearDatabase() {
        $mysqli = new mysqli(self::$config->getDbHost(), self::$config->getDbUser(), self::$config->getDbPassword(), self::$config->getDbName());
        $res = $mysqli->query('show tables');
        while($row = $res->fetch_row()) {
            $dropTableSql = "DROP TABLE $row[0]";
            $mysqli->query($dropTableSql);
        }
    }

    /**
     * Installs WordPress. Assumes that files have been prepared on the file system, database is clean
     * and wp-config.php has been created.
     */
    private static function installWordpress() {
        $installCommand = sprintf('wp core install --url="%s" --title="%s" --admin_name="%s" --admin_email="%s" --admin_password="%s"',
            self::$config->getSiteUrl(),
            self::$config->getSiteTitle(),
            self::$config->getAdminName(),
            self::$config->getAdminEmail(),
            self::$config->getAdminPassword()
        );

        self::exec($installCommand, self::$config->getSitePath());
    }





    /**
     * Executes a $command from $executionPath
     *
     * @param string $command
     * @param string $executionPath Working directory for the command
     * @return string
     */
    private static function exec($command, $executionPath) {
        echo "Executing command: " . $command . "\n";
        $cwd = getcwd();
        chdir($executionPath);
        $result = exec($command);
        chdir($cwd);
        return $result;
    }
}
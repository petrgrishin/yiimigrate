<?php
/*
 * This file is part of the yiimigrate package.
 *
 * (c) Petr Grishin <petr.grishin@grishini.ru>
 * (c) Anton Tyutin <anton@tyutin.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Command;

use Exception;
use Yii;

Yii::import('system.cli.commands.MigrateCommand');

/**
 * Command for manage migrations of application and modules.
 */
class MigrateCommand extends \MigrateCommand{

    const DEFAULT_MIGRATIONS_DIR = 'migrations';

    const COLOR_BLACK   = '30';
    const COLOR_RED     = '31';
    const COLOR_GREEN   = '32';
    const COLOR_YELLOW  = '33';
    const COLOR_BLUE    = '34';
    const COLOR_MAGENTA = '35';
    const COLOR_CYAN    = '36';
    const COLOR_WHITE   = '37';

    private $_migrationToFileMap = null;

    public $module = null;

    /**
     * @return string
     */
    public function getHelp() {
        $help = <<<EOD
Applies ALL new migrations including migrate all registered application modules:
  php yiic migrate up

Applies new migrations only for the selected module:
  php yiic migrate up --module=moduleNameFromConfiguration

Creates a new migration for the selected module:
  php yiic migrate create migrateName --module=moduleNameFromConfiguration


EOD;
        return $help . parent::getHelp();
    }

    public function beforeAction($action, $params) {
        $this->module && $this->migrationPath = $this->getModuleMigratePathAlias(Yii::app()->getModule($this->module));
        return parent::beforeAction($action, $params);
    }

    protected function afterAction($action, $params, $exitCode = 0) {
        $this->printColor('');
        return parent::afterAction($action, $params, $exitCode);
    }

    protected function instantiateMigration($class) {
        $this->loadMigration($class);
        $migration = new $class;
        $migration->setDbConnection($this->getDbConnection());
        return $migration;
    }

    protected function getNewMigrations() {
        $applied = array_flip($this->getAppliedMigrations());
        $migrations = array_filter(
            array_keys($this->getMigrationToFileMap()),
            function ($migration) use ($applied) {
                return !isset($applied[$migration]);
            }
        );
        sort($migrations);
        return $migrations;
    }

    /**
     * Fetching migration class to file name under
     * requested path.
     * @param  string $migrationPath
     * @return array [migrationClass => filePath]
     */
    protected function getMigrationFiles($migrationPath) {
        $files = array();
        $handle = opendir($migrationPath);
        while(false !== $file = readdir($handle)) {
            if($file === '.' || $file === '..') {
                continue;
            }
            $path = $migrationPath . DIRECTORY_SEPARATOR . $file;
            if(preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path)) {
                $files[$matches[1]] = $path;
            }
        }
        closedir($handle);
        return $files;
    }

    /**
     * @return array [timeMarkFromClassName => migrationClass]
     */
    protected function getAppliedMigrations() {
        $applied = array();
        foreach($this->getMigrationHistory(-1) as $version => $time) {
            $applied[substr($version,1,13)] = $version;
        }
        return $applied;
    }

    /**
     * @return array     List of existed migration directories of plugged modules
     */
    private function getModulesMigrationPaths() {
        $paths = array();
        foreach (Yii::app()->getModules() as $name => $config) {
            if (isset($paths[$config['class']])) {
                continue;
            }
            if (file_exists($moduleMigratePath = $this->getMigrationsPath(Yii::app()->getModule($name)))) {
                $this->printColor("Load module `{$name}` migrations from " . $moduleMigratePath, self::COLOR_YELLOW);
                $paths[$config['class']] = $moduleMigratePath;
            } else {
                $this->printColor("Module `{$name}` does not have migrations directory", self::COLOR_CYAN);
            }
        }
        return array_values($paths);
    }

    /**
     * Build and cache migration files map
     * @return array [migrationClass => filePath]
     */
    protected function getMigrationToFileMap() {
        if (null === $this->_migrationToFileMap) {
            $files = array();

            $this->printColor('Load application migrations from ' . $this->migrationPath);
            $paths = array(
                $this->migrationPath,
            );
            if (!$this->module) {
                $paths = array_merge($paths, $this->getModulesMigrationPaths());
            }
            foreach ($paths as $migrationPath) {
                $moduleFiles = $this->getMigrationFiles($migrationPath);
                $files = array_merge($files, $moduleFiles);
            }
            $this->_migrationToFileMap = $files;
        }
        return $this->_migrationToFileMap;
    }

    /**
     * @param string $className Class name to load
     */
    private function loadMigration($className) {
        $migrationToFileMap = $this->getMigrationToFileMap();
        if (!isset($migrationToFileMap[$className])) {
            $this->printColor(
                "Cannot load migration class `{$className}`."
                    . " Probably the migration belongs to module that has been unplugged.",
                self::COLOR_RED);
            exit(1);
        }
        require_once($migrationToFileMap[$className]);
    }

    private function printColor($str, $color = self::COLOR_GREEN) {
        echo "\033[1;{$color}m{$str}\033[0m\n";
    }

    /**
     * @param \CModule $moduleInstance
     * @throws \Exception
     * @return string
     */
    private function getModuleMigratePathAlias($moduleInstance) {
        if (!$moduleInstance) {
            throw new Exception('Module not found');
        }
        $moduleMigratePathAlias = 'modules.' . $moduleInstance->id;
        Yii::setPathOfAlias($moduleMigratePathAlias, $this->getMigrationsPath($moduleInstance));
        return $moduleMigratePathAlias;
    }

    protected function getTemplate() {
        if($this->templateFile!==null)
            return file_get_contents(Yii::getPathOfAlias($this->templateFile).'.php');
        else
            return <<<EOD
<?php

class {ClassName} extends CDbMigration {

    public function safeUp() {

    }

    public function safeDown() {

    }
}
EOD;
    }

    /**
     * @return string
     */
    static public function className() {
        return get_called_class();
    }

    /**
     * @param \CModule $moduleInstance
     *
     * @return string
     */
    private function getMigrationsPath($moduleInstance)
    {
        $migrationsDirName = $moduleInstance instanceof MigrationsConfigurationInterface
            ? $moduleInstance->migrationsDirectory()
            : self::DEFAULT_MIGRATIONS_DIR;
        $moduleMigratePath = $moduleInstance->basePath . DIRECTORY_SEPARATOR . $migrationsDirName;

        return $moduleMigratePath;
    }
}

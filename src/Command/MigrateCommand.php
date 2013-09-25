<?php
/**
 * @author Petr Grishin <petr.grishin@grishini.ru>
 * @author Anton Tyutin <anton@tyutin.ru>
 */

namespace Command;

use Yii;

Yii::import('system.cli.commands.MigrateCommand');

class MigrateCommand extends \MigrateCommand{

    const MIGRATE_PATH = 'migrations';

    private $_migrationToFileMap = null;

    public $module = null;

    public function beforeAction($action, $params) {

        if ($this->module && $module = Yii::app()->getModule($this->module)) {
            $moduleMigratePath = $module->basePath . DIRECTORY_SEPARATOR . self::MIGRATE_PATH;
            $moduleMigratePathAlias = 'modules.' . $module->id;
            Yii::setPathOfAlias($moduleMigratePathAlias, $moduleMigratePath);
            $this->migrationPath = $moduleMigratePathAlias;
        }

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

    protected function getAppliedMigrations() {
        $applied = array();
        foreach($this->getMigrationHistory(-1) as $version => $time) {
            $applied[substr($version,1,13)] = $version;
        }
        return $applied;
    }

    /**
     * @return array
     */
    private function getModulesMigrationPaths() {
        $paths = array();
        foreach (Yii::app()->getModules() as $name => $config) {
            if (isset($paths[$config['class']])) {
                continue;
            }
            if (file_exists($moduleMigratePath = Yii::app()->getModule($name)->basePath . DIRECTORY_SEPARATOR . self::MIGRATE_PATH)) {
                $this->printColor("Load module `{$name}` migrations from " . $moduleMigratePath, 'orange');
                $paths[$config['class']] = $moduleMigratePath;
            } else {
                $this->printColor("Module `{$name}` does not have migration dir", 'red');
            }
        }
        return array_values($paths);
    }

    /**
     * @return array [filePath => migrationClass]
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
     * @param $className
     */
    private function loadMigration($className) {
        $migrationToFileMap = $this->getMigrationToFileMap();
        if (!isset($migrationToFileMap[$className])) {
            $this->printColor("Cannot load migration class `{$className}`. Probably the migration belongs to module that has been unplugged.", 'red');
            exit(1);
        }
        require_once($migrationToFileMap[$className]);
    }

    private function printColor($str, $color = 'green') {
        $colorCodeAvalible = array(
            'orange' => 33,
            'green'  => 32,
            'red'    => 31
        );
        $colorCode = $colorCodeAvalible[$color];
        echo "\033[01;{$colorCode}m{$str}\033[0m\n";
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
}
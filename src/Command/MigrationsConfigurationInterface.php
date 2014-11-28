<?php
/*
 * This file is part of the petrgrishin/yiimigrate package.
 *
 * (c) Petr Grishin <petr.grishin@grishini.ru>
 * (c) Anton Tyutin <anton@tyutin.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Command;

interface MigrationsConfigurationInterface
{

    /**
     * Returns migrations directory path relative by module directory
     *
     * @return string
     */
    public function migrationsDirectory();

}

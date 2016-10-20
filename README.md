yiimigrate
==========

Migrate Command supporting modules for Yii 1.1

Current version
---------------
0.6

Install 
-------

composer.json:
```json
{
  "require": {
    "petrgrishin/yiimigrate": "0.6"
  }
}
```

config:
```php
<?php
return array(
  'commandMap' => array(
    'migrate' => array(
      'class' => \Command\MigrateCommand::className(),
    ),
  ),
);
```

Usage
-----

Applies ALL new migrations including migrate all registered application modules:
```
php yiic migrate up
```

Applies new migrations only for the selected module:
```
php yiic migrate up --module=moduleNameFromConfiguration
```

Creates a new migration for the selected module:
```
php yiic migrate create migrateName --module=moduleNameFromConfiguration
```

Applies ALL new migrations and minimize output:
```
php yiic migrate up --silent=1
```

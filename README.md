yiimigrate
==========

Migrate сommand supporting modules for Yii 1.1

Install 
-------

composer.json:
```json
{
  "require": {
    "petrgrishin/yiimigrate": "dev-master"
  }
}
```

Usage
-----

Applies ALL new migrations including migrate all registred application modules:
```
php yiic migrate up
```

Applies new migrations only for the selected module:
```
php yiic migrate up --module=moduleNameInConfiguration
```

Creates a new migration for the selected module:
```
php yiic migrate create migrateName --module=moduleNameInConfiguration
```

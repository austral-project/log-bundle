# Upgrade to 4.0

`austral/log-bundle` is new in Austral 4.0: there is no 3.x version to upgrade from.

## Requirements

- PHP 8.4 minimum
- Symfony 7.4, with MonologBundle
- Doctrine ORM 3

## Installation in an application upgraded from Austral 3.1

```diff
+ "austral/log-bundle": "^4.0"
```

1. Register the bundle in `config/bundles.php` if you do not use Symfony Flex.
2. Create the entities `App\Entity\Austral\LogBundle\ErrorLog` and `App\Entity\Austral\LogBundle\ErrorOccurrence` from the skeletons of `src/Skeleton/Entity/`.
3. Create the tables: `doctrine:migrations:diff`, then `doctrine:migrations:migrate`.

The Monolog configuration of the application does not change: the bundle adds its own handler.

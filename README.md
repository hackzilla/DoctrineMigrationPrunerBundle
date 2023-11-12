# Doctrine Migration Pruner Bundle

## Features
- Automatically prunes old migration files and their corresponding database entries just before running new Doctrine migrations.
- Designed to work in production; migration files should be absent, leaving only the database entries to be removed.
- Handles Doctrine migrations' multiple configurations.
- Prevents warnings about missing migration files if you manually removed them.

### Supported Platforms

- MySQL
- Sqlite

## Prerequisites
- Requires Doctrine Migration Bundle.
- Tested on Symfony 6, but should work wherever Doctrine Migrations Bundle v3.* is compatible.

## Installation
To install the Doctrine Migration Pruner Bundle, you can use composer:

```bash
composer require hackzilla/doctrine-migration-pruner-bundle
```

## Configuration
Add the following to your application's config:

```yaml
hackzilla_doctrine_migration_pruner:
  remove_migrations_before: '2007-05-01'  # Can be null or a valid date-time
```

It's advisable to start with an earlier date-time.

## Usage
Run your Doctrine migrations as you normally would:

```bash
bin/console doctrine:migrations:migrate
```

Note: The pruning operation will not execute if the `--dry-run` option has been specified.

## Testing
There are currently no tests available.

However, you can use 
[Doctrine Migration Pruner Repo](https://github.com/hackzilla/DoctrineMigrationPruner) to test the migration pruning.

## Contributions and Issues
See all contributors on [GitHub](https://github.com/hackzilla/DoctrineMigrationPrunerBundle/graphs/contributors).

Please report issues using GitHub's issue tracker: [GitHub Repo](https://github.com/hackzilla/DoctrineMigrationPrunerBundle)

If you find this project useful, consider [buying me a coffee](https://www.buymeacoffee.com/hackzilla).

## License

This bundle is released under the MIT license. See the [LICENSE](https://github.com/hackzilla/DoctrineMigrationPrunerBundle/blob/main/LICENSE) file for details.

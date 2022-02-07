# Contributing

For running tests, copy `phpunit.xml.dist` to `phpunit.xml` and change configuration for `AMQP` DSN connection. This can be automatically copied with `composer install` command.

Before commit and pull request, use this command for apply:

- [PHP Coding Standards Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).
- [PHPLint](https://github.com/overtrue/phplint).
- [Psalm](https://psalm.dev/).
- Run phpunit with defined AMQP connection string in `phpunit.xml`.

```sh

./lint

```

After running `. /lint`, push new created and committed branch.
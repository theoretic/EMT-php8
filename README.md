# EMT-php8

Типограф Муравьёва ( [mdash.ru](http://mdash.ru/) ), портированный на php8+.

## Что изменено

- типограф переписан для обеспечения совместимости с php8+
- изменено правило mdash_symbol_to_html_mdash (теперь символ &mdash; должен быть окружён пробелами)
- добавлено правило double_minus_to_html_mdash (последовательность " -- " заменяется на " &mdash; ")

## Как установить

	composer require atispro/emt-php8

##
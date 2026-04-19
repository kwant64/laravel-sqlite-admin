# Laravel SQLite Admin

SQLite beheerpaneel voor Laravel met middleware-ondersteuning (zoals `web`, `auth`, `can:*`).

## Installatie

1. Voeg package toe via Composer.

Voor een VCS repository:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/jouw-org/laravel-sqlite-admin"
    }
  ]
}
```

Daarna:

```bash
composer require php-lite-admin/laravel-sqlite-admin
```

Voor een lokaal pad (bijvoorbeeld naast je Laravel project):

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../phpLiteAdmin",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Daarna:

```bash
composer require php-lite-admin/laravel-sqlite-admin:@dev
```

2. Publiceer config:

```bash
php artisan vendor:publish --tag=sqlite-admin-config
```

3. Stel middleware en pad in `config/sqlite-admin.php`:

```php
'middleware' => ['web', 'auth'],
'path' => 'sqlite-admin',
'db_root' => database_path(),
'allow_absolute_paths' => false,
```

4. Open:

```text
/sqlite-admin
```

Bij standaardconfig is dat:

```text
https://jouwdomein.nl/sqlite-admin
```

## Features

- Database kiezen of aanmaken
- Tabellen/views overzicht
- Browse met sortering + paginatie
- Insert / Edit / Delete
- SQL runner
- SQL import
- SQL export (database of per tabel)
- Sticky `Delete` kolom in browse voor minder misclicks

## Security advies

- Gebruik altijd middleware zoals `auth` of fijnmaziger `can:...`
- Laat `allow_absolute_paths` standaard op `false`
- Beperk `db_root` tot een veilige map
- Zet route alleen open voor beheerders

<?php

declare(strict_types=1);

namespace PhpLiteAdmin\LaravelSqliteAdmin\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

class SqliteAdminController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $postAction = (string)$request->input('form_action', '');

        if ($request->isMethod('post') && $postAction === 'clear_db') {
            $request->session()->forget($this->sessionKey());
            return redirect()->route('sqlite-admin.index');
        }

        if ($request->isMethod('post') && $postAction === 'choose_db') {
            $requested = (string)$request->input('db_path', '');
            $normalized = $this->normalizeDbPath($requested);
            $allowCreate = $request->boolean('create_db');

            if ($normalized === null) {
                return $this->redirectWithFlash('error', 'Ongeldig database pad.');
            }

            if (!file_exists($normalized)) {
                if (!$allowCreate) {
                    return $this->redirectWithFlash(
                        'error',
                        'Bestand bestaat niet. Vink "maak aan" aan om te maken.'
                    );
                }

                $directory = dirname($normalized);
                if (!is_writable($directory)) {
                    return $this->redirectWithFlash('error', 'Map is niet schrijfbaar: ' . $directory);
                }

                if (@touch($normalized) === false) {
                    return $this->redirectWithFlash('error', 'Kon database bestand niet aanmaken.');
                }
            }

            $request->session()->put($this->sessionKey(), $normalized);
            return redirect()->route('sqlite-admin.index');
        }

        $selectedDbPath = (string)$request->session()->get($this->sessionKey(), '');

        $pdo = null;
        $dbObjects = [];
        $objectsByName = [];

        if ($selectedDbPath !== '') {
            try {
                $pdo = $this->connectToDatabase($selectedDbPath);
                $dbObjects = $this->fetchDbObjects($pdo);
                foreach ($dbObjects as $object) {
                    $name = (string)($object['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $objectsByName[$name] = [
                        'name' => $name,
                        'type' => (string)($object['type'] ?? ''),
                        'sql' => (string)($object['sql'] ?? ''),
                    ];
                }
            } catch (Throwable $exception) {
                $request->session()->forget($this->sessionKey());
                return $this->redirectWithFlash('error', 'Kon database niet openen: ' . $exception->getMessage());
            }
        }

        $view = (string)$request->query('view', 'dashboard');
        $tableParam = (string)$request->query('table', (string)$request->input('table', ''));
        $tableExists = $tableParam !== '' && isset($objectsByName[$tableParam]);
        $tableIsView = $tableExists && $objectsByName[$tableParam]['type'] === 'view';

        $sqlInput = '';
        $sqlRows = [];
        $sqlColumns = [];
        $sqlMessage = '';
        $sqlError = '';

        if ($request->isMethod('post') && $postAction !== '' && $postAction !== 'choose_db' && $postAction !== 'clear_db') {
            if ($pdo === null) {
                return $this->redirectWithFlash('error', 'Selecteer eerst een database.');
            }

            if ($postAction === 'run_sql') {
                $view = 'sql';
                $sqlInput = trim((string)$request->input('sql', ''));
                if ($sqlInput === '') {
                    $sqlError = 'SQL is leeg.';
                } else {
                    try {
                        $statement = $pdo->prepare($sqlInput);
                        $statement->execute();
                        if ($statement->columnCount() > 0) {
                            $sqlRows = $statement->fetchAll(PDO::FETCH_ASSOC);
                            if ($sqlRows !== []) {
                                $sqlColumns = array_keys($sqlRows[0]);
                            } else {
                                for ($i = 0; $i < $statement->columnCount(); $i++) {
                                    $meta = $statement->getColumnMeta($i);
                                    $sqlColumns[] = (string)($meta['name'] ?? ('kolom_' . ($i + 1)));
                                }
                            }
                            $sqlMessage = 'Query uitgevoerd. Resultaatrijen: ' . count($sqlRows);
                        } else {
                            $sqlMessage = 'Statement uitgevoerd. Gewijzigde rijen: ' . $statement->rowCount();
                        }
                    } catch (Throwable $exception) {
                        $sqlError = $exception->getMessage();
                    }
                }
            }

            if ($postAction === 'create_table') {
                $newTableName = trim((string)$request->input('new_table_name', ''));
                $newTableSchema = trim((string)$request->input('new_table_schema', ''));

                if ($newTableName === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $newTableName)) {
                    return $this->redirectWithFlash(
                        'error',
                        'Tabelnaam is ongeldig. Gebruik letters, cijfers en underscore.',
                        ['view' => 'dashboard']
                    );
                }

                if ($newTableSchema === '') {
                    return $this->redirectWithFlash('error', 'Kolomdefinitie mag niet leeg zijn.', ['view' => 'dashboard']);
                }

                try {
                    $pdo->exec('CREATE TABLE ' . $this->qi($newTableName) . ' (' . $newTableSchema . ')');
                    return $this->redirectWithFlash('success', 'Tabel aangemaakt: ' . $newTableName, ['view' => 'dashboard']);
                } catch (Throwable $exception) {
                    return $this->redirectWithFlash(
                        'error',
                        'Create table fout: ' . $exception->getMessage(),
                        ['view' => 'dashboard']
                    );
                }
            }

            if ($postAction === 'drop_object') {
                $name = (string)$request->input('table', '');
                if (!isset($objectsByName[$name])) {
                    return $this->redirectWithFlash('error', 'Object bestaat niet.', ['view' => 'dashboard']);
                }

                $type = (string)$objectsByName[$name]['type'];
                $dropSql = $type === 'view'
                    ? 'DROP VIEW ' . $this->qi($name)
                    : 'DROP TABLE ' . $this->qi($name);

                try {
                    $pdo->exec($dropSql);
                    return $this->redirectWithFlash('success', ucfirst($type) . ' verwijderd: ' . $name, ['view' => 'dashboard']);
                } catch (Throwable $exception) {
                    return $this->redirectWithFlash(
                        'error',
                        'Verwijderen mislukt: ' . $exception->getMessage(),
                        ['view' => 'dashboard']
                    );
                }
            }

            if ($postAction === 'insert_row') {
                $table = (string)$request->input('table', '');
                if (!isset($objectsByName[$table]) || $objectsByName[$table]['type'] !== 'table') {
                    return $this->redirectWithFlash('error', 'Alleen tabellen ondersteunen INSERT.', ['view' => 'dashboard']);
                }

                $columns = $this->fetchTableColumns($pdo, $table);
                $values = $request->input('value', []);
                $isNull = $request->input('is_null', []);
                $useDefault = $request->input('use_default', []);

                $insertColumns = [];
                $placeholders = [];
                $params = [];
                $i = 0;

                foreach ($columns as $column) {
                    $columnName = (string)$column['name'];
                    if (is_array($useDefault) && isset($useDefault[$columnName])) {
                        continue;
                    }
                    $insertColumns[] = $this->qi($columnName);

                    if (is_array($isNull) && isset($isNull[$columnName])) {
                        $placeholders[] = 'NULL';
                        continue;
                    }

                    $paramName = 'v' . $i++;
                    $placeholders[] = ':' . $paramName;
                    $rawValue = is_array($values) ? ($values[$columnName] ?? '') : '';
                    $params[$paramName] = is_scalar($rawValue) ? (string)$rawValue : '';
                }

                try {
                    if ($insertColumns === []) {
                        $statement = $pdo->prepare('INSERT INTO ' . $this->qi($table) . ' DEFAULT VALUES');
                    } else {
                        $sql = 'INSERT INTO ' . $this->qi($table)
                            . ' (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                        $statement = $pdo->prepare($sql);
                        $this->bindParams($statement, $params);
                    }
                    $statement->execute();
                    return $this->redirectWithFlash('success', 'Rij ingevoegd in ' . $table . '.', [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                } catch (Throwable $exception) {
                    return $this->redirectWithFlash('error', 'Insert fout: ' . $exception->getMessage(), [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                }
            }

            if ($postAction === 'update_row') {
                $table = (string)$request->input('table', '');
                if (!isset($objectsByName[$table]) || $objectsByName[$table]['type'] !== 'table') {
                    return $this->redirectWithFlash('error', 'Alleen tabellen ondersteunen UPDATE.', ['view' => 'dashboard']);
                }

                $locator = $this->decodeLocator((string)$request->input('row_locator', ''));
                if ($locator === null) {
                    return $this->redirectWithFlash('error', 'Rij locator is ongeldig.', [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                }

                $columns = $this->fetchTableColumns($pdo, $table);
                $values = $request->input('value', []);
                $isNull = $request->input('is_null', []);

                $set = [];
                $params = [];
                $i = 0;

                foreach ($columns as $column) {
                    $columnName = (string)$column['name'];
                    if (is_array($isNull) && isset($isNull[$columnName])) {
                        $set[] = $this->qi($columnName) . ' = NULL';
                        continue;
                    }

                    $param = 'u' . $i++;
                    $set[] = $this->qi($columnName) . ' = :' . $param;
                    $rawValue = is_array($values) ? ($values[$columnName] ?? '') : '';
                    $params[$param] = is_scalar($rawValue) ? (string)$rawValue : '';
                }

                try {
                    [$where, $whereParams] = $this->buildWhereFromLocator($locator, 'w');
                    $sql = 'UPDATE ' . $this->qi($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . $where;
                    $statement = $pdo->prepare($sql);
                    $this->bindParams($statement, $params + $whereParams);
                    $statement->execute();
                    return $this->redirectWithFlash('success', 'Rij bijgewerkt in ' . $table . '.', [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                } catch (Throwable $exception) {
                    return $this->redirectWithFlash('error', 'Update fout: ' . $exception->getMessage(), [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                }
            }

            if ($postAction === 'delete_row') {
                $table = (string)$request->input('table', '');
                if (!isset($objectsByName[$table]) || $objectsByName[$table]['type'] !== 'table') {
                    return $this->redirectWithFlash('error', 'Alleen tabellen ondersteunen DELETE.', ['view' => 'dashboard']);
                }

                $locator = $this->decodeLocator((string)$request->input('row_locator', ''));
                if ($locator === null) {
                    return $this->redirectWithFlash('error', 'Rij locator is ongeldig.', [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                }

                try {
                    [$where, $whereParams] = $this->buildWhereFromLocator($locator, 'd');
                    $sql = 'DELETE FROM ' . $this->qi($table) . ' WHERE ' . $where;
                    $statement = $pdo->prepare($sql);
                    $this->bindParams($statement, $whereParams);
                    $statement->execute();
                    return $this->redirectWithFlash('success', 'Rij verwijderd uit ' . $table . '.', [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                } catch (Throwable $exception) {
                    return $this->redirectWithFlash('error', 'Delete fout: ' . $exception->getMessage(), [
                        'view' => 'browse',
                        'table' => $table,
                    ]);
                }
            }

            if ($postAction === 'import_sql') {
                $script = trim((string)$request->input('sql_script', ''));
                $file = $request->file('sql_file');
                if ($file !== null && $file->isValid()) {
                    $fileSql = file_get_contents($file->getRealPath());
                    if (is_string($fileSql) && trim($fileSql) !== '') {
                        $script = $script !== '' ? ($script . "\n\n" . $fileSql) : $fileSql;
                    }
                }

                if ($script === '') {
                    return $this->redirectWithFlash('error', 'Geen SQL gevonden om te importeren.', ['view' => 'import']);
                }

                try {
                    $pdo->beginTransaction();
                    $pdo->exec($script);
                    $pdo->commit();
                    return $this->redirectWithFlash('success', 'Import voltooid.', ['view' => 'import']);
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    return $this->redirectWithFlash('error', 'Import fout: ' . $exception->getMessage(), ['view' => 'import']);
                }
            }
        }

        $dbFiles = $this->discoverDbFiles($this->dbRoot());
        $hasDb = $pdo !== null;
        $flash = $request->session()->get('sqlite_admin_flash');
        $request->session()->forget('sqlite_admin_flash');

        $rowCounts = [];
        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        $createSql = '';
        $browseData = null;
        $editData = [
            'row' => null,
            'rowError' => '',
            'encodedLocator' => '',
        ];

        if ($hasDb && $view === 'dashboard') {
            foreach ($dbObjects as $object) {
                if (($object['type'] ?? '') !== 'table') {
                    continue;
                }
                $name = (string)$object['name'];
                try {
                    $rowCounts[$name] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $this->qi($name))->fetchColumn();
                } catch (Throwable) {
                    $rowCounts[$name] = null;
                }
            }
        }

        if ($hasDb && $view === 'structure' && $tableExists) {
            $columns = $this->fetchTableColumns($pdo, $tableParam);
            if (!$tableIsView) {
                $indexes = $this->fetchIndexInfo($pdo, $tableParam);
                $foreignKeys = $this->fetchForeignKeys($pdo, $tableParam);
            }
            $createSql = (string)($objectsByName[$tableParam]['sql'] ?? '');
        }

        if ($hasDb && $view === 'browse' && $tableExists) {
            $columns = $this->fetchTableColumns($pdo, $tableParam);
            $identity = $tableIsView
                ? ['type' => 'none', 'columns' => []]
                : $this->determineIdentityStrategy($pdo, $tableParam, $columns);

            $columnNames = array_map(static fn(array $col): string => (string)$col['name'], $columns);
            $sort = (string)$request->query('sort', '');
            $direction = strtolower((string)$request->query('dir', 'asc'));
            $direction = $direction === 'desc' ? 'desc' : 'asc';

            $sortableColumns = $columnNames;
            if (($identity['type'] ?? '') === 'rowid') {
                $sortableColumns[] = '__rowid__';
            }
            if (!in_array($sort, $sortableColumns, true)) {
                $sort = '';
            }

            $perPage = (int)$request->query('per_page', $this->defaultPerPage());
            $perPage = max(1, min($this->maxPerPage(), $perPage));
            $page = max(1, (int)$request->query('page', 1));

            $totalRows = (int)$pdo->query('SELECT COUNT(*) FROM ' . $this->qi($tableParam))->fetchColumn();
            $offset = ($page - 1) * $perPage;

            $selectPrefix = ($identity['type'] ?? '') === 'rowid' ? 'rowid AS __rowid__, ' : '';
            $orderClause = '';
            if ($sort !== '') {
                $sortSql = $sort === '__rowid__' ? 'rowid' : $this->qi($sort);
                $orderClause = ' ORDER BY ' . $sortSql . ' ' . strtoupper($direction);
            }

            $dataSql = 'SELECT ' . $selectPrefix . '* FROM ' . $this->qi($tableParam)
                . $orderClause
                . ' LIMIT ' . $perPage
                . ' OFFSET ' . $offset;

            $rows = $pdo->query($dataSql)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $locator = null;
                if (($identity['type'] ?? '') === 'pk') {
                    $keys = [];
                    foreach ((array)$identity['columns'] as $pkColumn) {
                        $keys[(string)$pkColumn] = $row[(string)$pkColumn] ?? null;
                    }
                    $locator = $this->encodeLocator(['type' => 'pk', 'keys' => $keys]);
                } elseif (($identity['type'] ?? '') === 'rowid') {
                    $locator = $this->encodeLocator(['type' => 'rowid', 'rowid' => $row['__rowid__'] ?? null]);
                }
                $row['__locator'] = $locator;
            }
            unset($row);

            $totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));

            $browseData = [
                'columns' => $columns,
                'columnNames' => $columnNames,
                'identity' => $identity,
                'rows' => $rows,
                'sort' => $sort,
                'direction' => $direction,
                'page' => $page,
                'perPage' => $perPage,
                'totalRows' => $totalRows,
                'totalPages' => $totalPages,
            ];
        }

        if ($hasDb && $view === 'insert' && $tableExists && !$tableIsView) {
            $columns = $this->fetchTableColumns($pdo, $tableParam);
        }

        if ($hasDb && $view === 'edit' && $tableExists && !$tableIsView) {
            $columns = $this->fetchTableColumns($pdo, $tableParam);
            $encodedLocator = (string)$request->query('row', '');
            $locator = $this->decodeLocator($encodedLocator);
            if ($locator === null) {
                $editData['rowError'] = 'Ongeldige rij locator.';
            } else {
                try {
                    $row = $this->fetchRowByLocator($pdo, $tableParam, $locator, false);
                    if ($row === null) {
                        $editData['rowError'] = 'Rij niet gevonden.';
                    } else {
                        $editData['row'] = $row;
                        $editData['encodedLocator'] = $encodedLocator;
                    }
                } catch (Throwable $exception) {
                    $editData['rowError'] = $exception->getMessage();
                }
            }
        }

        if ($hasDb && $view === 'sql' && $sqlInput === '' && $tableExists) {
            $sqlInput = 'SELECT * FROM ' . $this->qi($tableParam) . ' LIMIT 100;';
        }

        return view('sqlite-admin::panel', [
            'view' => $view,
            'tableParam' => $tableParam,
            'tableExists' => $tableExists,
            'tableIsView' => $tableIsView,
            'dbFiles' => $dbFiles,
            'hasDb' => $hasDb,
            'selectedDbPath' => $selectedDbPath,
            'dbObjects' => $dbObjects,
            'objectsByName' => $objectsByName,
            'rowCounts' => $rowCounts,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreignKeys' => $foreignKeys,
            'createSql' => $createSql,
            'browseData' => $browseData,
            'editData' => $editData,
            'sqlInput' => $sqlInput,
            'sqlRows' => $sqlRows,
            'sqlColumns' => $sqlColumns,
            'sqlMessage' => $sqlMessage,
            'sqlError' => $sqlError,
            'flash' => is_array($flash) ? $flash : null,
            'dbRoot' => $this->dbRoot(),
            'allowAbsolutePaths' => $this->allowAbsolutePaths(),
            'maxPerPage' => $this->maxPerPage(),
            'appTitle' => 'SQLite Admin (Laravel)',
        ]);
    }

    public function download(Request $request): Response|RedirectResponse
    {
        $selectedDbPath = (string)$request->session()->get($this->sessionKey(), '');
        if ($selectedDbPath === '') {
            return $this->redirectWithFlash('error', 'Selecteer eerst een database.', ['view' => 'dashboard']);
        }

        try {
            $pdo = $this->connectToDatabase($selectedDbPath);
        } catch (Throwable $exception) {
            $request->session()->forget($this->sessionKey());
            return $this->redirectWithFlash('error', 'Kon database niet openen: ' . $exception->getMessage());
        }

        $downloadMode = (string)$request->query('download', '');
        $table = (string)$request->query('table', '');

        if ($downloadMode === 'db') {
            $sql = $this->exportDumpSql($pdo, $selectedDbPath, null);
            $name = pathinfo($selectedDbPath, PATHINFO_FILENAME);
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name ?: 'sqlite');
            $filename = ($safe !== '' ? $safe : 'sqlite') . '-' . date('Ymd-His') . '.sql';

            return response($sql, 200, [
                'Content-Type' => 'application/sql; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        if ($downloadMode === 'table' && $table !== '') {
            $sql = $this->exportDumpSql($pdo, $selectedDbPath, $table);
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $table);
            $filename = ($safe !== '' ? $safe : 'sqlite') . '-' . date('Ymd-His') . '.sql';

            return response($sql, 200, [
                'Content-Type' => 'application/sql; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return $this->redirectWithFlash('error', 'Ongeldige export keuze.', ['view' => 'export']);
    }

    private function redirectWithFlash(string $type, string $message, array $query = []): RedirectResponse
    {
        return redirect()
            ->route('sqlite-admin.index', $query)
            ->with('sqlite_admin_flash', [
                'type' => $type,
                'message' => $message,
            ]);
    }

    private function sessionKey(): string
    {
        return (string)config('sqlite-admin.session_key', 'sqlite_admin.db_path');
    }

    private function dbRoot(): string
    {
        return (string)config('sqlite-admin.db_root', database_path());
    }

    private function allowAbsolutePaths(): bool
    {
        return (bool)config('sqlite-admin.allow_absolute_paths', false);
    }

    private function defaultPerPage(): int
    {
        return max(1, (int)config('sqlite-admin.default_per_page', 50));
    }

    private function maxPerPage(): int
    {
        return max(1, (int)config('sqlite-admin.max_per_page', 500));
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '\\\\');
        }

        return str_starts_with($path, '/');
    }

    private function normalizeDbPath(string $inputPath): ?string
    {
        $inputPath = trim(str_replace("\0", '', $inputPath));
        if ($inputPath === '') {
            return null;
        }

        if (str_contains($inputPath, '://')) {
            return null;
        }

        $candidate = $inputPath;
        if (!$this->isAbsolutePath($candidate)) {
            $candidate = rtrim($this->dbRoot(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate;
        } elseif (!$this->allowAbsolutePaths()) {
            return null;
        }

        $directory = dirname($candidate);
        if (!is_dir($directory)) {
            return null;
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            return null;
        }

        if (!$this->allowAbsolutePaths()) {
            $root = realpath($this->dbRoot());
            if ($root === false) {
                return null;
            }
            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $directoryPrefix = rtrim($realDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($directoryPrefix, $rootPrefix)) {
                return null;
            }
        }

        $resolved = $realDirectory . DIRECTORY_SEPARATOR . basename($candidate);

        if (file_exists($resolved)) {
            $realFile = realpath($resolved);
            if ($realFile === false) {
                return null;
            }

            if (!$this->allowAbsolutePaths()) {
                $root = realpath($this->dbRoot());
                if ($root === false) {
                    return null;
                }
                $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $filePrefix = rtrim($realFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (!str_starts_with($filePrefix, $rootPrefix)) {
                    return null;
                }
            }

            return $realFile;
        }

        return $resolved;
    }

    private function discoverDbFiles(string $root): array
    {
        $files = [];

        foreach (['*.db', '*.sqlite', '*.sqlite3'] as $pattern) {
            $matches = glob(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern);
            if ($matches === false) {
                continue;
            }

            foreach ($matches as $match) {
                if (is_file($match)) {
                    $files[] = $match;
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    private function connectToDatabase(string $dbPath): PDO
    {
        $pdo = new PDO(
            'sqlite:' . $dbPath,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    private function qi(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function bindParams(PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = ':' . $key;
            $type = PDO::PARAM_STR;

            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            }

            $statement->bindValue($param, $value, $type);
        }
    }

    private function fetchDbObjects(PDO $pdo): array
    {
        $sql = <<<'SQL'
SELECT name, type, sql
FROM sqlite_master
WHERE type IN ('table', 'view')
  AND name NOT LIKE 'sqlite_%'
ORDER BY type, name
SQL;

        return $pdo->query($sql)->fetchAll();
    }

    private function fetchTableColumns(PDO $pdo, string $table): array
    {
        return $pdo->query('PRAGMA table_info(' . $this->qi($table) . ')')->fetchAll();
    }

    private function fetchTableSql(PDO $pdo, string $table): string
    {
        $stmt = $pdo->prepare('SELECT sql FROM sqlite_master WHERE type = :type AND name = :name LIMIT 1');
        $stmt->execute(['type' => 'table', 'name' => $table]);
        $sql = $stmt->fetchColumn();

        return is_string($sql) ? $sql : '';
    }

    private function fetchIndexInfo(PDO $pdo, string $table): array
    {
        $indexes = $pdo->query('PRAGMA index_list(' . $this->qi($table) . ')')->fetchAll();
        foreach ($indexes as &$index) {
            $name = (string)($index['name'] ?? '');
            $index['columns'] = [];
            if ($name !== '') {
                $index['columns'] = $pdo->query('PRAGMA index_info(' . $this->qi($name) . ')')->fetchAll();
            }
        }
        unset($index);

        return $indexes;
    }

    private function fetchForeignKeys(PDO $pdo, string $table): array
    {
        return $pdo->query('PRAGMA foreign_key_list(' . $this->qi($table) . ')')->fetchAll();
    }

    private function determineIdentityStrategy(PDO $pdo, string $table, array $columns): array
    {
        $pk = [];
        foreach ($columns as $column) {
            $position = (int)($column['pk'] ?? 0);
            if ($position > 0) {
                $pk[$position] = (string)$column['name'];
            }
        }

        if ($pk !== []) {
            ksort($pk);
            return [
                'type' => 'pk',
                'columns' => array_values($pk),
            ];
        }

        $tableSql = $this->fetchTableSql($pdo, $table);
        $withoutRowId = stripos($tableSql, 'WITHOUT ROWID') !== false;
        if (!$withoutRowId) {
            return [
                'type' => 'rowid',
                'columns' => ['__rowid__'],
            ];
        }

        return [
            'type' => 'none',
            'columns' => [],
        ];
    }

    private function encodeLocator(array $locator): string
    {
        $json = json_encode($locator, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decodeLocator(string $encoded): ?array
    {
        $encoded = trim($encoded);
        if ($encoded === '') {
            return null;
        }

        $base64 = strtr($encoded, '-_', '+/');
        $pad = strlen($base64) % 4;
        if ($pad > 0) {
            $base64 .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($base64, true);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildWhereFromLocator(array $locator, string $prefix = 'w'): array
    {
        $type = (string)($locator['type'] ?? '');
        $parts = [];
        $params = [];
        $counter = 0;

        if ($type === 'rowid') {
            if (!array_key_exists('rowid', $locator)) {
                throw new RuntimeException('Ongeldige row locator.');
            }
            $parts[] = 'rowid = :' . $prefix . $counter;
            $params[$prefix . $counter] = $locator['rowid'];

            return [implode(' AND ', $parts), $params];
        }

        if ($type === 'pk') {
            $keys = $locator['keys'] ?? null;
            if (!is_array($keys) || $keys === []) {
                throw new RuntimeException('Ongeldige primary-key locator.');
            }

            foreach ($keys as $column => $value) {
                if (!is_string($column) || $column === '') {
                    continue;
                }

                if ($value === null) {
                    $parts[] = $this->qi($column) . ' IS NULL';
                    continue;
                }

                $param = $prefix . $counter;
                $parts[] = $this->qi($column) . ' = :' . $param;
                $params[$param] = $value;
                $counter++;
            }

            if ($parts === []) {
                throw new RuntimeException('Kon geen WHERE clause opbouwen.');
            }

            return [implode(' AND ', $parts), $params];
        }

        throw new RuntimeException('Onbekende locator type.');
    }

    private function fetchRowByLocator(PDO $pdo, string $table, array $locator, bool $includeRowId = false): ?array
    {
        [$where, $params] = $this->buildWhereFromLocator($locator, 'f');
        $selectPrefix = $includeRowId ? 'rowid AS __rowid__, ' : '';
        $sql = 'SELECT ' . $selectPrefix . '* FROM ' . $this->qi($table) . ' WHERE ' . $where . ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function exportDumpSql(PDO $pdo, string $dbPath, ?string $onlyTable = null): string
    {
        ob_start();

        echo "-- SQLite dump\n";
        echo '-- Source: ' . basename($dbPath) . "\n";
        echo '-- Generated: ' . date(DATE_ATOM) . "\n\n";
        echo "PRAGMA foreign_keys=OFF;\n";
        echo "BEGIN TRANSACTION;\n\n";

        if ($onlyTable === null) {
            $objects = $pdo->query(
                "SELECT type, name, tbl_name, sql
                 FROM sqlite_master
                 WHERE type IN ('table', 'view', 'index', 'trigger')
                   AND name NOT LIKE 'sqlite_%'
                 ORDER BY CASE type
                            WHEN 'table' THEN 1
                            WHEN 'view' THEN 2
                            WHEN 'index' THEN 3
                            WHEN 'trigger' THEN 4
                          END, name"
            )->fetchAll();
        } else {
            $stmt = $pdo->prepare(
                "SELECT type, name, tbl_name, sql
                 FROM sqlite_master
                 WHERE (name = :name OR tbl_name = :name)
                   AND type IN ('table', 'view', 'index', 'trigger')
                 ORDER BY CASE type
                            WHEN 'table' THEN 1
                            WHEN 'view' THEN 2
                            WHEN 'index' THEN 3
                            WHEN 'trigger' THEN 4
                          END, name"
            );
            $stmt->execute(['name' => $onlyTable]);
            $objects = $stmt->fetchAll();
        }

        $tablesForData = [];

        foreach ($objects as $object) {
            $type = (string)($object['type'] ?? '');
            $name = (string)($object['name'] ?? '');
            $sql = trim((string)($object['sql'] ?? ''));
            if ($type === '' || $name === '' || $sql === '') {
                continue;
            }

            if (!str_ends_with($sql, ';')) {
                $sql .= ';';
            }

            echo '-- ' . strtoupper($type) . ': ' . $name . "\n";
            echo $sql . "\n\n";

            if ($type === 'table') {
                $tablesForData[] = $name;
            }
        }

        foreach ($tablesForData as $table) {
            $columns = $this->fetchTableColumns($pdo, $table);
            $columnNames = array_map(static fn(array $col): string => (string)$col['name'], $columns);
            if ($columnNames === []) {
                continue;
            }

            $columnSql = implode(', ', array_map(fn(string $name): string => $this->qi($name), $columnNames));
            $stmt = $pdo->query('SELECT * FROM ' . $this->qi($table));
            echo '-- DATA: ' . $table . "\n";

            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $values = [];
                foreach ($columnNames as $columnName) {
                    $values[] = $this->sqlLiteral($pdo, $row[$columnName] ?? null);
                }

                echo 'INSERT INTO ' . $this->qi($table)
                    . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n";
            }

            echo "\n";
        }

        echo "COMMIT;\n";

        return (string)ob_get_clean();
    }

    private function sqlLiteral(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_resource($value)) {
            $value = (string)stream_get_contents($value);
        }

        return $pdo->quote((string)$value);
    }
}

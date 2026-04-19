@php
    $renderCell = function ($value): string {
        if ($value === null) {
            return '<span class="null">NULL</span>';
        }

        if (is_resource($value)) {
            $value = (string) stream_get_contents($value);
        }

        $text = (string) $value;
        if ($text === '') {
            return '<span class="empty">(leeg)</span>';
        }

        if (strlen($text) > 180) {
            $short = substr($text, 0, 177) . '...';
            return '<span title="' . e($text) . '">' . e($short) . '</span>';
        }

        return e($text);
    };

    $urlWith = function (array $changes = []): string {
        $query = request()->query();
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        return route('sqlite-admin.index', $query);
    };
@endphp
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appTitle }}</title>
    <style>
        :root {
            --bg: #f8fafc;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --border: #dbe3ec;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --danger: #b91c1c;
            --danger-dark: #991b1b;
            --ok: #166534;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at 15% 0%, #ccfbf1 0%, transparent 35%),
                radial-gradient(circle at 90% 0%, #e0f2fe 0%, transparent 30%),
                var(--bg);
            color: var(--text);
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            min-height: 100vh;
        }
        .wrapper {
            width: min(1180px, calc(100% - 2rem));
            margin: 1rem auto 2rem;
        }
        .topbar {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding: .85rem 1rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, .88);
            border-radius: 14px;
            backdrop-filter: blur(4px);
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.06);
        }
        .brand h1 {
            margin: 0;
            font-size: 1rem;
        }
        .meta {
            color: var(--muted);
            font-size: .86rem;
            margin-top: .15rem;
        }
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .45rem;
        }
        a.link {
            text-decoration: none;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .38rem .6rem;
            background: var(--panel);
            font-size: .88rem;
        }
        a.link.active {
            border-color: var(--accent);
            color: var(--accent-dark);
            background: #f0fdfa;
        }
        a.link.success {
            border-color: var(--ok);
            background: var(--ok);
            color: #fff;
        }
        a.link.success:hover {
            background: #14532d;
            border-color: #14532d;
        }
        button {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel);
            color: var(--text);
            padding: .43rem .68rem;
            font: inherit;
            font-size: .88rem;
            cursor: pointer;
        }
        button.primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }
        button.primary:hover { background: var(--accent-dark); }
        button.danger {
            border-color: var(--danger);
            background: var(--danger);
            color: #fff;
        }
        button.danger:hover { background: var(--danger-dark); }
        .grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        .panel {
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--panel);
            padding: .95rem;
            overflow: auto;
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.05);
        }
        .panel h2, .panel h3 {
            margin: 0 0 .8rem 0;
            font-size: 1.02rem;
        }
        .inline { display: inline; }
        .alert {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: .62rem .72rem;
            margin-top: .7rem;
            background: #fff;
        }
        .alert.error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #7f1d1d;
        }
        .alert.success {
            border-color: #bbf7d0;
            background: #f0fdf4;
            color: #14532d;
        }
        .form-grid {
            display: grid;
            gap: .7rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: .3rem;
            font-size: .88rem;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .5rem .62rem;
            font: inherit;
            font-size: .88rem;
            color: var(--text);
            background: #fff;
        }
        textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.35;
        }
        .hint {
            color: var(--muted);
            font-size: .8rem;
            margin-top: .2rem;
        }
        .table-wrap {
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .84rem;
            min-width: 680px;
        }
        th, td {
            border-bottom: 1px solid var(--border);
            padding: .45rem .5rem;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        th.sticky-right {
            right: 0;
            z-index: 4;
            box-shadow: -10px 0 12px -12px rgba(2, 6, 23, 0.55);
        }
        td.sticky-right {
            position: sticky;
            right: 0;
            z-index: 2;
            background: var(--panel);
            box-shadow: -10px 0 12px -12px rgba(2, 6, 23, 0.55);
        }
        tr:hover td { background: #fafcfe; }
        tr:hover td.sticky-right { background: #fafcfe; }
        tr.clickable-row { cursor: pointer; }
        .null {
            color: #b45309;
            font-style: italic;
        }
        .empty {
            color: #64748b;
            font-style: italic;
        }
        .monospace {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .8rem;
            word-break: break-all;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            align-items: center;
            margin-bottom: .75rem;
        }
        .small {
            font-size: .78rem;
            color: var(--muted);
        }
        pre.sql {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 8px;
            padding: .85rem;
            overflow: auto;
            margin: 0;
            font-size: .78rem;
        }
        .checkboxes {
            display: flex;
            gap: .85rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: .35rem;
            font-size: .8rem;
            color: var(--muted);
        }
        .right { text-align: right; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="topbar">
        <div class="brand">
            <div>
                <h1>{{ $appTitle }}</h1>
                @if($hasDb)
                    <div class="meta monospace">{{ $selectedDbPath }}</div>
                @else
                    <div class="meta">Geen database geselecteerd</div>
                @endif
            </div>
        </div>
        <div class="actions">
            @if($hasDb)
                <a class="link {{ $view === 'dashboard' ? 'active' : '' }}" href="{{ route('sqlite-admin.index', ['view' => 'dashboard']) }}">Dashboard</a>
                <a class="link {{ $view === 'sql' ? 'active' : '' }}" href="{{ route('sqlite-admin.index', ['view' => 'sql']) }}">SQL</a>
                <a class="link {{ $view === 'import' ? 'active' : '' }}" href="{{ route('sqlite-admin.index', ['view' => 'import']) }}">Import</a>
                <a class="link {{ $view === 'export' ? 'active' : '' }}" href="{{ route('sqlite-admin.index', ['view' => 'export']) }}">Export</a>
                <form method="post" action="{{ route('sqlite-admin.index') }}" class="inline">
                    @csrf
                    <input type="hidden" name="form_action" value="clear_db">
                    <button type="submit">Andere DB</button>
                </form>
            @endif
        </div>
    </div>

    @if(is_array($flash))
        @php
            $flashType = in_array($flash['type'] ?? '', ['success', 'error'], true) ? $flash['type'] : 'success';
        @endphp
        <div class="alert {{ $flashType }}">{{ $flash['message'] ?? '' }}</div>
    @endif

    @if(!$hasDb)
        <div class="grid">
            <section class="panel">
                <h2>Database kiezen of maken</h2>
                <form method="post" action="{{ route('sqlite-admin.index') }}">
                    @csrf
                    <input type="hidden" name="form_action" value="choose_db">
                    <label for="db_path">Pad naar sqlite bestand</label>
                    <input id="db_path" name="db_path" type="text" placeholder="database.sqlite of /volledig/pad/database.sqlite" required>
                    <div class="checkboxes">
                        <label><input type="checkbox" name="create_db" value="1"> maak bestand aan als het nog niet bestaat</label>
                    </div>
                    <p class="hint">
                        {{ $allowAbsolutePaths ? 'Absolute paden zijn toegestaan.' : 'Alleen paden onder db_root zijn toegestaan.' }}
                        <span class="monospace">{{ $dbRoot }}</span>
                    </p>
                    <button class="primary" type="submit">Open database</button>
                </form>
            </section>

            <section class="panel">
                <h3>Gevonden in {{ $dbRoot }}</h3>
                @if($dbFiles === [])
                    <p class="small">Geen .db/.sqlite/.sqlite3 bestanden gevonden.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Bestand</th>
                                <th class="right">Actie</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($dbFiles as $file)
                                <tr>
                                    <td class="monospace">{{ $file }}</td>
                                    <td class="right">
                                        <form method="post" action="{{ route('sqlite-admin.index') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="form_action" value="choose_db">
                                            <input type="hidden" name="db_path" value="{{ $file }}">
                                            <button type="submit">Open</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @else
        <div class="grid">
            @if($view === 'dashboard')
                <section class="panel">
                    <h2>Objecten</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Naam</th>
                                <th>Type</th>
                                <th>Rijen</th>
                                <th class="right">Acties</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($dbObjects === [])
                                <tr>
                                    <td colspan="4">Nog geen tabellen of views.</td>
                                </tr>
                            @else
                                @foreach($dbObjects as $object)
                                    @php
                                        $name = (string) ($object['name'] ?? '');
                                        $type = (string) ($object['type'] ?? '');
                                        $isTable = $type === 'table';
                                    @endphp
                                    <tr class="clickable-row"
                                        data-href="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $name]) }}"
                                        tabindex="0"
                                        role="link"
                                        aria-label="Browse {{ $name }}">
                                        <td class="monospace">{{ $name }}</td>
                                        <td>{{ $type }}</td>
                                        <td>{{ $isTable ? ($rowCounts[$name] ?? '?') : '-' }}</td>
                                        <td class="right">
                                            <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $name]) }}">Browse</a>
                                            <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'structure', 'table' => $name]) }}">Structure</a>
                                            @if($isTable)
                                                <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'insert', 'table' => $name]) }}">Insert</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <h3>Nieuwe tabel</h3>
                    <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'dashboard']) }}">
                        @csrf
                        <input type="hidden" name="form_action" value="create_table">
                        <div class="form-grid">
                            <div>
                                <label for="new_table_name">Tabelnaam</label>
                                <input id="new_table_name" name="new_table_name" type="text" placeholder="users" required>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label for="new_table_schema">Kolomdefinitie</label>
                                <textarea id="new_table_schema" name="new_table_schema" placeholder="id INTEGER PRIMARY KEY AUTOINCREMENT,&#10;name TEXT NOT NULL,&#10;email TEXT UNIQUE"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="primary">Tabel aanmaken</button>
                    </form>
                </section>
            @elseif($view === 'structure' && $tableExists)
                <section class="panel">
                    <div class="toolbar">
                        <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}">Browse</a>
                        @if(!$tableIsView)
                            <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'insert', 'table' => $tableParam]) }}">Insert</a>
                        @endif
                    </div>
                    <h2>Structure: {{ $tableParam }}</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Naam</th>
                                <th>Type</th>
                                <th>Not Null</th>
                                <th>Default</th>
                                <th>PK</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($columns as $column)
                                <tr>
                                    <td>{{ $column['cid'] ?? '' }}</td>
                                    <td class="monospace">{{ $column['name'] ?? '' }}</td>
                                    <td>{{ $column['type'] ?? '' }}</td>
                                    <td>{{ (int)($column['notnull'] ?? 0) === 1 ? 'YES' : 'NO' }}</td>
                                    <td class="monospace">{{ $column['dflt_value'] ?? 'NULL' }}</td>
                                    <td>{{ (int)($column['pk'] ?? 0) > 0 ? (int)$column['pk'] : '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                @if(!$tableIsView)
                    <section class="panel">
                        <h3>Indexes</h3>
                        @if($indexes === [])
                            <p class="small">Geen indexes.</p>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Naam</th>
                                        <th>Unique</th>
                                        <th>Kolommen</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($indexes as $index)
                                        <tr>
                                            <td class="monospace">{{ $index['name'] ?? '' }}</td>
                                            <td>{{ (int)($index['unique'] ?? 0) === 1 ? 'YES' : 'NO' }}</td>
                                            <td class="monospace">
                                                {{ implode(', ', array_map(static fn($col) => (string)($col['name'] ?? ''), $index['columns'] ?? [])) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="panel">
                        <h3>Foreign Keys</h3>
                        @if($foreignKeys === [])
                            <p class="small">Geen foreign keys.</p>
                        @else
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>From</th>
                                        <th>To Table</th>
                                        <th>To Column</th>
                                        <th>On Update</th>
                                        <th>On Delete</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($foreignKeys as $fk)
                                        <tr>
                                            <td class="monospace">{{ $fk['from'] ?? '' }}</td>
                                            <td class="monospace">{{ $fk['table'] ?? '' }}</td>
                                            <td class="monospace">{{ $fk['to'] ?? '' }}</td>
                                            <td>{{ $fk['on_update'] ?? '' }}</td>
                                            <td>{{ $fk['on_delete'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="panel">
                    <h3>Create SQL</h3>
                    <pre class="sql">{{ $createSql }}</pre>
                    <div style="margin-top: .7rem;">
                        <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'dashboard']) }}" class="inline" onsubmit="return confirm('Weet je zeker dat je dit object wilt verwijderen?');">
                            @csrf
                            <input type="hidden" name="form_action" value="drop_object">
                            <input type="hidden" name="table" value="{{ $tableParam }}">
                            <button type="submit" class="danger">{{ $tableIsView ? 'View verwijderen' : 'Tabel verwijderen' }}</button>
                        </form>
                    </div>
                </section>
            @elseif($view === 'browse' && $tableExists && is_array($browseData))
                @php
                    $identityType = $browseData['identity']['type'] ?? 'none';
                    $colspan = count($browseData['columnNames'])
                        + ($identityType === 'none' ? 0 : 2)
                        + ($identityType === 'rowid' ? 1 : 0);
                @endphp
                <section class="panel">
                    <div class="toolbar">
                        <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'structure', 'table' => $tableParam]) }}">Structure</a>
                        @if(!$tableIsView)
                            <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'insert', 'table' => $tableParam]) }}">Insert</a>
                        @endif
                    </div>
                    <h2>Browse: {{ $tableParam }}</h2>
                    <p class="small">
                        Rijen: {{ $browseData['totalRows'] }} |
                        Pagina {{ $browseData['page'] }} / {{ $browseData['totalPages'] }} |
                        Per pagina {{ $browseData['perPage'] }}
                    </p>

                    <form method="get" class="toolbar" action="{{ route('sqlite-admin.index') }}">
                        <input type="hidden" name="view" value="browse">
                        <input type="hidden" name="table" value="{{ $tableParam }}">
                        <label for="per_page" style="margin:0;">Per pagina</label>
                        <input id="per_page" type="number" min="1" max="{{ $maxPerPage }}" name="per_page" value="{{ $browseData['perPage'] }}" style="width: 90px;">
                        <button type="submit">Toepassen</button>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                @if($identityType !== 'none')
                                    <th>Edit</th>
                                @endif
                                @if($identityType === 'rowid')
                                    @php
                                        $nextDir = ($browseData['sort'] === '__rowid__' && $browseData['direction'] === 'asc') ? 'desc' : 'asc';
                                    @endphp
                                    <th><a class="link" href="{{ $urlWith(['view' => 'browse', 'table' => $tableParam, 'sort' => '__rowid__', 'dir' => $nextDir, 'page' => 1]) }}">rowid</a></th>
                                @endif
                                @foreach($browseData['columnNames'] as $columnName)
                                    @php
                                        $nextDir = ($browseData['sort'] === $columnName && $browseData['direction'] === 'asc') ? 'desc' : 'asc';
                                    @endphp
                                    <th>
                                        <a class="link" href="{{ $urlWith(['view' => 'browse', 'table' => $tableParam, 'sort' => $columnName, 'dir' => $nextDir, 'page' => 1]) }}">{{ $columnName }}</a>
                                    </th>
                                @endforeach
                                @if($identityType !== 'none')
                                    <th class="right sticky-right">Delete</th>
                                @endif
                            </tr>
                            </thead>
                            <tbody>
                            @if(($browseData['rows'] ?? []) === [])
                                <tr>
                                    <td colspan="{{ $colspan }}">Geen rijen.</td>
                                </tr>
                            @else
                                @foreach($browseData['rows'] as $row)
                                    @php $locator = $row['__locator'] ?? ''; @endphp
                                    <tr>
                                        @if($identityType !== 'none')
                                            <td style="white-space: nowrap;">
                                                @if($locator !== null && $locator !== '')
                                                    <a class="link success" href="{{ route('sqlite-admin.index', ['view' => 'edit', 'table' => $tableParam, 'row' => $locator]) }}">Edit</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @endif
                                        @if($identityType === 'rowid')
                                            <td class="monospace">{{ $row['__rowid__'] ?? '' }}</td>
                                        @endif
                                        @foreach($browseData['columnNames'] as $columnName)
                                            <td>{!! $renderCell($row[$columnName] ?? null) !!}</td>
                                        @endforeach
                                        @if($identityType !== 'none')
                                            <td class="right sticky-right" style="white-space: nowrap;">
                                                @if($locator !== null && $locator !== '')
                                                    <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}" class="inline" onsubmit="return confirm('Rij verwijderen?');">
                                                        @csrf
                                                        <input type="hidden" name="form_action" value="delete_row">
                                                        <input type="hidden" name="table" value="{{ $tableParam }}">
                                                        <input type="hidden" name="row_locator" value="{{ $locator }}">
                                                        <button type="submit" class="danger">Delete</button>
                                                    </form>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="toolbar" style="margin-top:.65rem;">
                        @if($browseData['page'] > 1)
                            <a class="link" href="{{ $urlWith(['page' => $browseData['page'] - 1]) }}">Vorige</a>
                        @endif
                        @if($browseData['page'] < $browseData['totalPages'])
                            <a class="link" href="{{ $urlWith(['page' => $browseData['page'] + 1]) }}">Volgende</a>
                        @endif
                    </div>
                </section>
            @elseif($view === 'insert' && $tableExists && !$tableIsView)
                <section class="panel">
                    <div class="toolbar">
                        <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}">Terug naar browse</a>
                    </div>
                    <h2>Insert: {{ $tableParam }}</h2>
                    <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}">
                        @csrf
                        <input type="hidden" name="form_action" value="insert_row">
                        <input type="hidden" name="table" value="{{ $tableParam }}">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Kolom</th>
                                    <th>Type</th>
                                    <th>Waarde</th>
                                    <th>Null</th>
                                    <th>Default overslaan</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($columns as $column)
                                    @php
                                        $columnName = (string) ($column['name'] ?? '');
                                        $hasDefault = ($column['dflt_value'] ?? null) !== null || (int)($column['pk'] ?? 0) > 0;
                                    @endphp
                                    <tr>
                                        <td class="monospace">{{ $columnName }}</td>
                                        <td>{{ $column['type'] ?? '' }}</td>
                                        <td><input type="text" name="value[{{ $columnName }}]" value=""></td>
                                        <td><input type="checkbox" name="is_null[{{ $columnName }}]" value="1"></td>
                                        <td><input type="checkbox" name="use_default[{{ $columnName }}]" value="1" {{ $hasDefault ? 'checked' : '' }}></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top:.7rem;">
                            <button type="submit" class="primary">Insert rij</button>
                        </div>
                    </form>
                </section>
            @elseif($view === 'edit' && $tableExists && !$tableIsView)
                <section class="panel">
                    <div class="toolbar">
                        <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}">Terug naar browse</a>
                    </div>
                    <h2>Edit: {{ $tableParam }}</h2>
                    @if(($editData['rowError'] ?? '') !== '')
                        <div class="alert error">{{ $editData['rowError'] }}</div>
                    @else
                        <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'browse', 'table' => $tableParam]) }}">
                            @csrf
                            <input type="hidden" name="form_action" value="update_row">
                            <input type="hidden" name="table" value="{{ $tableParam }}">
                            <input type="hidden" name="row_locator" value="{{ $editData['encodedLocator'] ?? '' }}">
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Kolom</th>
                                        <th>Type</th>
                                        <th>Waarde</th>
                                        <th>NULL</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($columns as $column)
                                        @php
                                            $columnName = (string)($column['name'] ?? '');
                                            $value = $editData['row'][$columnName] ?? null;
                                            $isNull = $value === null;
                                        @endphp
                                        <tr>
                                            <td class="monospace">{{ $columnName }}</td>
                                            <td>{{ $column['type'] ?? '' }}</td>
                                            <td>
                                                <input type="text" name="value[{{ $columnName }}]" value="{{ $isNull ? '' : (string)$value }}">
                                            </td>
                                            <td><input type="checkbox" name="is_null[{{ $columnName }}]" value="1" {{ $isNull ? 'checked' : '' }}></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top:.7rem;">
                                <button type="submit" class="primary">Update rij</button>
                            </div>
                        </form>
                    @endif
                </section>
            @elseif($view === 'sql')
                <section class="panel">
                    <h2>SQL Runner</h2>
                    <form method="post" action="{{ route('sqlite-admin.index', ['view' => 'sql', 'table' => $tableParam !== '' ? $tableParam : null]) }}">
                        @csrf
                        <input type="hidden" name="form_action" value="run_sql">
                        <label for="sql">Query</label>
                        <textarea id="sql" name="sql">{{ $sqlInput }}</textarea>
                        <div class="toolbar">
                            <button type="submit" class="primary">Uitvoeren</button>
                        </div>
                    </form>

                    @if($sqlError !== '')
                        <div class="alert error">{{ $sqlError }}</div>
                    @endif
                    @if($sqlMessage !== '')
                        <div class="alert success">{{ $sqlMessage }}</div>
                    @endif
                    @if($sqlColumns !== [])
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    @foreach($sqlColumns as $columnName)
                                        <th>{{ $columnName }}</th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($sqlRows as $row)
                                    <tr>
                                        @foreach($sqlColumns as $columnName)
                                            <td>{!! $renderCell($row[$columnName] ?? null) !!}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @elseif($view === 'import')
                <section class="panel">
                    <h2>SQL Import</h2>
                    <form method="post" enctype="multipart/form-data" action="{{ route('sqlite-admin.index', ['view' => 'import']) }}">
                        @csrf
                        <input type="hidden" name="form_action" value="import_sql">
                        <label for="sql_script">SQL script (optioneel)</label>
                        <textarea id="sql_script" name="sql_script" placeholder="INSERT INTO ...;"></textarea>
                        <label for="sql_file">SQL bestand uploaden (optioneel)</label>
                        <input id="sql_file" type="file" name="sql_file" accept=".sql,text/plain">
                        <p class="hint">Als beide velden gevuld zijn, worden ze samengevoegd en in 1 transactie uitgevoerd.</p>
                        <button type="submit" class="primary">Import uitvoeren</button>
                    </form>
                </section>
            @elseif($view === 'export')
                <section class="panel">
                    <h2>Export</h2>
                    <p class="small">Download SQL dumps van de hele database of per tabel/view.</p>
                    <div class="toolbar">
                        <a class="link" href="{{ route('sqlite-admin.download', ['download' => 'db']) }}">Download hele database (.sql)</a>
                    </div>
                    @if($dbObjects !== [])
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Object</th>
                                    <th>Type</th>
                                    <th class="right">Export</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($dbObjects as $object)
                                    @php
                                        $name = (string) ($object['name'] ?? '');
                                        $type = (string) ($object['type'] ?? '');
                                    @endphp
                                    <tr>
                                        <td class="monospace">{{ $name }}</td>
                                        <td>{{ $type }}</td>
                                        <td class="right">
                                            <a class="link" href="{{ route('sqlite-admin.download', ['download' => 'table', 'table' => $name]) }}">Download</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @else
                <section class="panel">
                    <h2>Onbekende view</h2>
                    <a class="link" href="{{ route('sqlite-admin.index', ['view' => 'dashboard']) }}">Terug naar dashboard</a>
                </section>
            @endif
        </div>
    @endif
</div>
<script>
document.addEventListener('click', function (event) {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }
    if (target.closest('a, button, input, textarea, select, label, form')) {
        return;
    }

    const row = target.closest('tr[data-href]');
    if (!row) {
        return;
    }

    const href = row.getAttribute('data-href');
    if (href) {
        window.location.href = href;
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }
    const row = target.closest('tr[data-href]');
    if (!row) {
        return;
    }

    const href = row.getAttribute('data-href');
    if (href) {
        event.preventDefault();
        window.location.href = href;
    }
});
</script>
</body>
</html>

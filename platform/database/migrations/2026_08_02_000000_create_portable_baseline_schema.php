<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rebuild the consolidated application schema on an empty database.
     *
     * The historical migrations were consolidated into the SQLite snapshot.
     * This baseline deliberately stops when it detects an existing application
     * schema, so a production database is never reshaped implicitly.
     */
    public function up(): void
    {
        if (Schema::hasTable('organizations') || Schema::hasTable('users')) {
            return;
        }

        $schema = $this->parseSnapshot(database_path('schema/sqlite-schema.sql'));
        $createdTables = [];

        foreach ($schema['tables'] as $tableName => $definition) {
            if ($tableName === 'migrations' || Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table) use ($tableName, $definition): void {
                $foreignKeyColumns = array_values(array_unique(array_merge(
                    ...array_map(
                        static fn (array $foreign): array => $foreign['columns'],
                        $definition['foreign']
                    )
                )));

                foreach ($definition['columns'] as $column) {
                    $this->addColumn($table, $tableName, $column, $foreignKeyColumns);
                }

                foreach ($definition['primary'] as $columns) {
                    $table->primary($columns, $this->safeIdentifier('pk_'.$tableName));
                }

                foreach ($definition['indexes'] as $index) {
                    $name = $this->safeIdentifier($index['name']);
                    $index['unique']
                        ? $table->unique($index['columns'], $name)
                        : $table->index($index['columns'], $name);
                }
            });
            $createdTables[] = $tableName;
        }

        // Foreign keys are installed only after every table exists. This keeps
        // the baseline portable to MySQL/MariaDB, which validates references at
        // CREATE TABLE time (unlike SQLite).
        foreach ($schema['tables'] as $tableName => $definition) {
            if (! in_array($tableName, $createdTables, true) || $definition['foreign'] === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $definition, $createdTables): void {
                foreach ($definition['foreign'] as $position => $foreign) {
                    if (! in_array($foreign['table'], $createdTables, true) && ! Schema::hasTable($foreign['table'])) {
                        continue;
                    }

                    $constraint = $table->foreign(
                        $foreign['columns'],
                        $this->safeIdentifier('fk_'.$tableName.'_'.$position)
                    )->references($foreign['references'])->on($foreign['table']);

                    if ($foreign['on_delete'] !== null) {
                        $constraint->onDelete($foreign['on_delete']);
                    }

                    if ($foreign['on_update'] !== null) {
                        $constraint->onUpdate($foreign['on_update']);
                    }
                }
            });
        }
    }

    /**
     * A consolidated baseline is intentionally non-destructive on rollback.
     * Dropping it could erase a complete production database in one operation.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }

    /**
     * @param  array{name: string, type: string, remainder: string}  $definition
     * @param  array<int, string>  $foreignKeyColumns
     */
    private function addColumn(
        Blueprint $table,
        string $tableName,
        array $definition,
        array $foreignKeyColumns
    ): void {
        $name = $definition['name'];
        $type = strtolower($definition['type']);
        $remainder = $definition['remainder'];
        $autoIncrement = str_contains(strtolower($remainder), 'autoincrement');
        $inlinePrimary = preg_match('/\bprimary\s+key\b/i', $remainder) === 1;

        if ($type === 'integer' && $autoIncrement && $inlinePrimary) {
            $column = $table->id($name);
        } elseif ($type === 'integer' && (
            $name === 'model_id'
            || str_ends_with($name, '_id')
            || in_array($name, $foreignKeyColumns, true)
        )) {
            $column = $table->unsignedBigInteger($name);
        } elseif ($type === 'integer') {
            $column = $table->integer($name);
        } elseif ($type === 'tinyint(1)') {
            $column = $table->boolean($name);
        } elseif ($type === 'numeric') {
            $column = $table->decimal($name, 20, 6);
        } elseif ($type === 'float') {
            $column = $table->float($name);
        } elseif ($type === 'datetime') {
            $column = $table->dateTime($name);
        } elseif ($type === 'text') {
            $column = $table->text($name);
        } elseif ($type === 'varchar') {
            $enumValues = $this->enumValues($name, $remainder);
            $column = $enumValues === []
                ? $table->string($name, $this->stringLength($tableName, $name))
                : $table->enum($name, $enumValues);
        } else {
            throw new RuntimeException("Unsupported baseline column type [{$type}] on [{$tableName}.{$name}].");
        }

        if (! preg_match('/\bnot\s+null\b/i', $remainder) && ! ($autoIncrement && $inlinePrimary)) {
            $column->nullable();
        }

        $default = $this->defaultValue($remainder, $type);
        if ($default['present']) {
            if ($default['current']) {
                $column->useCurrent();
            } else {
                $column->default($default['value']);
            }
        }

        if ($inlinePrimary && ! $autoIncrement) {
            $column->primary();
        }
    }

    /**
     * @return array{tables: array<string, array{columns: array<int, array{name: string, type: string, remainder: string}>, primary: array<int, array<int, string>>, foreign: array<int, array{columns: array<int, string>, table: string, references: array<int, string>, on_delete: ?string, on_update: ?string}>, indexes: array<int, array{name: string, columns: array<int, string>, unique: bool}>}>}
     */
    private function parseSnapshot(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Unable to read consolidated schema snapshot [{$path}].");
        }

        $tables = [];
        preg_match_all(
            '/CREATE TABLE IF NOT EXISTS "([^"]+)"\s*\((.*?)\);/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $tableName = $match[1];
            $tables[$tableName] = [
                'columns' => [],
                'primary' => [],
                'foreign' => [],
                'indexes' => [],
            ];

            foreach ($this->splitDefinitions($match[2]) as $rawDefinition) {
                $definition = trim($rawDefinition);

                if (preg_match('/^"([^"]+)"\s+(integer|tinyint\(1\)|numeric|float|datetime|text|varchar)\s*(.*)$/is', $definition, $column)) {
                    $tables[$tableName]['columns'][] = [
                        'name' => $column[1],
                        'type' => strtolower($column[2]),
                        'remainder' => trim($column[3]),
                    ];

                    continue;
                }

                if (preg_match('/^primary\s+key\s*\((.*?)\)$/is', $definition, $primary)) {
                    $tables[$tableName]['primary'][] = $this->quotedIdentifiers($primary[1]);

                    continue;
                }

                if (preg_match(
                    '/^foreign\s+key\s*\((.*?)\)\s+references\s+(?:"([^"]+)"|([A-Za-z_][A-Za-z0-9_]*))\s*\((.*?)\)(.*)$/is',
                    $definition,
                    $foreign
                )) {
                    $tail = $foreign[5];
                    $tables[$tableName]['foreign'][] = [
                        'columns' => $this->quotedIdentifiers($foreign[1]),
                        'table' => $foreign[2] !== '' ? $foreign[2] : $foreign[3],
                        'references' => $this->quotedIdentifiers($foreign[4]),
                        'on_delete' => $this->referentialAction($tail, 'delete'),
                        'on_update' => $this->referentialAction($tail, 'update'),
                    ];
                }
            }
        }

        preg_match_all(
            '/CREATE\s+(UNIQUE\s+)?INDEX\s+"([^"]+)"\s+on\s+"([^"]+)"\s*\((.*?)\);/is',
            $sql,
            $indexMatches,
            PREG_SET_ORDER
        );

        foreach ($indexMatches as $index) {
            if (! isset($tables[$index[3]])) {
                continue;
            }

            $tables[$index[3]]['indexes'][] = [
                'name' => $index[2],
                'columns' => $this->quotedIdentifiers($index[4]),
                'unique' => trim($index[1]) !== '',
            ];
        }

        if (count($tables) < 60) {
            throw new RuntimeException('The consolidated schema snapshot is incomplete or could not be parsed.');
        }

        return ['tables' => $tables];
    }

    /** @return array<int, string> */
    private function splitDefinitions(string $body): array
    {
        $definitions = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($body);

        for ($position = 0; $position < $length; $position++) {
            $character = $body[$position];

            if ($quote !== null) {
                $current .= $character;
                if ($character === $quote) {
                    if ($position + 1 < $length && $body[$position + 1] === $quote) {
                        $current .= $body[++$position];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            }

            if ($character === ',' && $depth === 0) {
                $definitions[] = $current;
                $current = '';

                continue;
            }

            $current .= $character;
        }

        if (trim($current) !== '') {
            $definitions[] = $current;
        }

        return $definitions;
    }

    /** @return array<int, string> */
    private function quotedIdentifiers(string $value): array
    {
        preg_match_all('/"([^"]+)"/', $value, $matches);

        return $matches[1];
    }

    private function referentialAction(string $value, string $operation): ?string
    {
        if (! preg_match('/on\s+'.preg_quote($operation, '/').'\s+(cascade|restrict|set\s+null|no\s+action)/i', $value, $match)) {
            return null;
        }

        return strtolower(preg_replace('/\s+/', ' ', $match[1]));
    }

    /** @return array<int, string> */
    private function enumValues(string $column, string $remainder): array
    {
        if (! preg_match('/check\s*\(\s*"'.preg_quote($column, '/').'"\s+in\s*\((.*?)\)\s*\)/is', $remainder, $match)) {
            return [];
        }

        preg_match_all("/'((?:''|[^'])*)'/", $match[1], $values);

        return array_map(static fn (string $value): string => str_replace("''", "'", $value), $values[1]);
    }

    /** @return array{present: bool, current: bool, value: mixed} */
    private function defaultValue(string $remainder, string $type): array
    {
        if (! preg_match('/\bdefault\s*(?:\(\s*)?(CURRENT_TIMESTAMP|\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|[-+]?[0-9]+(?:\.[0-9]+)?)(?:\s*\))?/i', $remainder, $match)) {
            return ['present' => false, 'current' => false, 'value' => null];
        }

        $raw = trim($match[1]);
        if (strcasecmp($raw, 'CURRENT_TIMESTAMP') === 0) {
            return ['present' => true, 'current' => true, 'value' => null];
        }

        if (($raw[0] === "'" && str_ends_with($raw, "'")) || ($raw[0] === '"' && str_ends_with($raw, '"'))) {
            $raw = substr($raw, 1, -1);
            $raw = str_replace(["''", '""'], ["'", '"'], $raw);
        }

        $value = match ($type) {
            'tinyint(1)' => (bool) ((int) $raw),
            'integer' => (int) $raw,
            'numeric', 'float' => (float) $raw,
            default => $raw,
        };

        return ['present' => true, 'current' => false, 'value' => $value];
    }

    private function stringLength(string $table, string $column): int
    {
        return match ($table.'.'.$column) {
            'config_rules.config_key', 'config_rules.scope_id' => 100,
            'config_rules.scope_type' => 50,
            default => 255,
        };
    }

    private function safeIdentifier(string $identifier): string
    {
        if (strlen($identifier) <= 60) {
            return $identifier;
        }

        return substr($identifier, 0, 50).'_'.substr(sha1($identifier), 0, 8);
    }
};

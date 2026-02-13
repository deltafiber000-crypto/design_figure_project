<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TARGET_COLUMNS = [
        'created_at',
        'updated_at',
        'email_verified_at',
        'two_factor_confirmed_at',
        'approved_at',
        'failed_at',
    ];

    private const SOURCE_TIMEZONE = 'Asia/Tokyo';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $inList = $this->columnInList();

        $columns = DB::select(
            <<<SQL
            select table_name, column_name
            from information_schema.columns
            where table_schema = 'public'
              and data_type = 'timestamp without time zone'
              and column_name in ({$inList})
            SQL
        );

        foreach ($columns as $column) {
            $table = $this->quoteIdentifier($column->table_name);
            $name = $this->quoteIdentifier($column->column_name);
            $tz = self::SOURCE_TIMEZONE;

            DB::statement(
                "alter table {$table} alter column {$name} type timestamp with time zone using {$name} at time zone '{$tz}'"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $inList = $this->columnInList();

        $columns = DB::select(
            <<<SQL
            select table_name, column_name
            from information_schema.columns
            where table_schema = 'public'
              and data_type = 'timestamp with time zone'
              and column_name in ({$inList})
            SQL
        );

        foreach ($columns as $column) {
            $table = $this->quoteIdentifier($column->table_name);
            $name = $this->quoteIdentifier($column->column_name);
            $tz = self::SOURCE_TIMEZONE;

            DB::statement(
                "alter table {$table} alter column {$name} type timestamp without time zone using {$name} at time zone '{$tz}'"
            );
        }
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    private function columnInList(): string
    {
        return implode(
            ', ',
            array_map(static fn (string $column): string => "'{$column}'", self::TARGET_COLUMNS)
        );
    }
};

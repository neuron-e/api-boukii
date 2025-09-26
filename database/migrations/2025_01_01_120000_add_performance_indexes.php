<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration that adds performance oriented indexes using defensive checks so
 * it can safely run across environments with slightly different schemas.
 */
class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndex('course_subgroups', ['course_date_id', 'degree_id'], 'idx_course_date_degree');
        $this->addIndex('course_subgroups', ['max_participants', 'created_at'], 'idx_max_participants');

        $this->addIndex('booking_users', ['course_subgroup_id', 'status', 'booking_id'], 'idx_booking_subgroup_status');
        $this->addIndex('booking_users', ['client_id', 'status'], 'idx_client_status');

        $this->addIndex('bookings', ['school_id', 'client_main_id', 'status'], 'idx_school_client_status');
        $this->addIndex('bookings', ['created_at', 'status'], 'idx_created_status');
        $this->addIndex('bookings', ['payment_method_id', 'paid', 'created_at'], 'idx_payment_reports');

        $this->addIndex('course_dates', ['course_id', 'date'], 'idx_course_date');
        $this->addIndex('course_dates', ['date', 'course_id'], 'idx_date_course');

        $this->addIndex('courses', ['school_id', 'course_type', 'sport_id'], 'idx_school_type_sport');
        $this->addIndex('courses', ['active', 'date_start', 'date_end'], 'idx_active_dates');

        $this->addIndex('clients', ['first_name', 'last_name'], 'idx_name_surname');

        $this->addIndex('course_groups', ['course_id', 'degree_id'], 'idx_course_degree');
        $this->addIndex('course_groups', ['age_min', 'age_max'], 'idx_age_range');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndex('course_subgroups', 'idx_course_date_degree');
        $this->dropIndex('course_subgroups', 'idx_max_participants');

        $this->dropIndex('booking_users', 'idx_booking_subgroup_status');
        $this->dropIndex('booking_users', 'idx_client_status');

        $this->dropIndex('bookings', 'idx_school_client_status');
        $this->dropIndex('bookings', 'idx_created_status');
        $this->dropIndex('bookings', 'idx_payment_reports');

        $this->dropIndex('course_dates', 'idx_course_date');
        $this->dropIndex('course_dates', 'idx_date_course');

        $this->dropIndex('courses', 'idx_school_type_sport');
        $this->dropIndex('courses', 'idx_active_dates');

        $this->dropIndex('clients', 'idx_name_surname');

        $this->dropIndex('course_groups', 'idx_course_degree');
        $this->dropIndex('course_groups', 'idx_age_range');
    }

    private function addIndex(string $table, array $columns, string $indexName): void
    {
        if (! $this->columnsExist($table, $columns) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function columnsExist(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $prefixedTable = $connection->getTablePrefix() . $table;

        $result = $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $prefixedTable, $indexName]
        );

        return $result !== null;
    }
}

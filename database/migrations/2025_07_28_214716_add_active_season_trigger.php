<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seasons')) {
            return;
        }
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_update');

            DB::unprepared('CREATE TRIGGER seasons_before_insert
                BEFORE INSERT ON seasons
                FOR EACH ROW
                BEGIN
                    IF NEW.is_active = 1 THEN
                        UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1;
                    END IF;
                END');

            DB::unprepared('CREATE TRIGGER seasons_before_update
                BEFORE UPDATE ON seasons
                FOR EACH ROW
                BEGIN
                    IF NEW.is_active = 1 THEN
                        UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1 AND id != NEW.id;
                    END IF;
                END');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_update');

            DB::unprepared('CREATE TRIGGER seasons_before_insert
                BEFORE INSERT ON seasons
                WHEN NEW.is_active = 1
                BEGIN
                    UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1;
                END;');

            DB::unprepared('CREATE TRIGGER seasons_before_update
                BEFORE UPDATE ON seasons
                WHEN NEW.is_active = 1
                BEGIN
                    UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1 AND id <> NEW.id;
                END;');
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_update');
    }
};

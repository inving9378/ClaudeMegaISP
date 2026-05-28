<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El trigger update_column_is_first_payment fue creado con
     * DEFINER='meganet'@'localhost'. En el servidor de desarrollo ese usuario
     * no existe, lo que causa error 1449 en cada INSERT a payments.
     * Se elimina y recrea sin DEFINER explícito (hereda el usuario actual).
     */
    public function up(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'is_first_payment')) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `update_column_is_first_payment`');
        DB::unprepared('
            CREATE TRIGGER `update_column_is_first_payment`
            BEFORE INSERT ON `payments`
            FOR EACH ROW
            BEGIN
                IF (SELECT COUNT(*) FROM `payments` WHERE `paymentable_id` = NEW.`paymentable_id`) = 0 THEN
                    SET NEW.`is_first_payment` = 1;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `update_column_is_first_payment`');
    }
};

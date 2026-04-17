<?php

use App\Models\ClientMainInformation;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sales = ClientMainInformation::all();
        foreach ($sales as $s) {
            $s->distribute_commission = null;
            $s->save();
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'is_first_payment')) {
                $table->boolean('is_first_payment')->default(false)->after('paymentable_type');
            }
        });

        $payments = Payment::where('created_at', '>=', '2024-06-01')->orderBy('created_at')->get()->groupBy('paymentable_id');
        foreach ($payments as $p) {
            $p[0]->is_first_payment = true;
            $p[0]->save();
        }

       /*  DB::unprepared('
            CREATE TRIGGER update_column_is_first_payment
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                IF (SELECT COUNT(*) FROM payments WHERE paymentable_id = NEW.paymentable_id) = 0 THEN
                    SET NEW.is_first_payment = 1;
                END IF;
            END
        '); */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       // DB::unprepared('DROP TRIGGER IF EXISTS update_column_is_first_payment');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('is_first_payment');
        });
    }
};

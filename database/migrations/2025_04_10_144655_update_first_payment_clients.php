<?php

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Constant\Periodic\Payments;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE payments SET is_first_payment=0");
        DB::statement("UPDATE payments p
            JOIN (
                SELECT p1.paymentable_id, 
                    MIN(p1.id) AS primer_id
                FROM payments p1
                JOIN (
                    SELECT paymentable_id, 
                        MIN(CASE 
                            WHEN date REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' THEN STR_TO_DATE(date, '%d/%m/%Y')
                            WHEN date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN STR_TO_DATE(date, '%Y-%m-%d')
                        END) AS primer_fecha
                    FROM payments
                    GROUP BY paymentable_id
                ) p2 ON p1.paymentable_id = p2.paymentable_id 
                    AND CASE 
                        WHEN p1.date REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' THEN STR_TO_DATE(p1.date, '%d/%m/%Y')
                        WHEN p1.date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN STR_TO_DATE(p1.date, '%Y-%m-%d')
                        END = p2.primer_fecha
                GROUP BY p1.paymentable_id, p2.primer_fecha
            ) AS primeros
            ON p.id = primeros.primer_id
            SET p.is_first_payment = 1");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Payment::where('is_first_payment', true)->update([
            'is_first_payment' => false
        ]);
    }
};

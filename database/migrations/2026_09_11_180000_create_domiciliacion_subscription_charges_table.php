<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domiciliacion_subscription_charges')) {
            Schema::create('domiciliacion_subscription_charges', function (Blueprint $t) {
                $t->id();
                $t->string('openpay_charge_id', 100)->unique();   // idempotencia
                $t->string('openpay_subscription_id', 100)->nullable()->index();
                $t->unsignedBigInteger('client_id')->index();
                $t->decimal('amount', 10, 2);
                $t->string('status', 30)->nullable();
                $t->unsignedBigInteger('payment_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('domiciliacion_subscription_charges');
    }
};

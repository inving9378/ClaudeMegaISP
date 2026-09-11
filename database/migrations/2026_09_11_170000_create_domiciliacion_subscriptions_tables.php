<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domiciliacion_plans')) {
            Schema::create('domiciliacion_plans', function (Blueprint $t) {
                $t->id();
                $t->decimal('amount', 10, 2)->unique();   // una tarifa = un plan de OpenPay
                $t->string('openpay_plan_id', 100);
                $t->string('name')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('domiciliacion_subscriptions')) {
            Schema::create('domiciliacion_subscriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('client_id')->index();
                $t->unsignedBigInteger('client_recurring_card_id')->nullable();
                $t->string('openpay_customer_id', 100);
                $t->string('openpay_card_id', 100);
                $t->unsignedBigInteger('domiciliacion_plan_id')->nullable();
                $t->string('openpay_subscription_id', 100)->unique();
                $t->decimal('amount', 10, 2);
                $t->string('status', 30)->default('active'); // active/trial/past_due/unpaid/cancelled
                $t->date('charge_date')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('domiciliacion_subscriptions');
        Schema::dropIfExists('domiciliacion_plans');
    }
};

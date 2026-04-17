<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('location_id');
            $table->index('partner_id');
            $table->index('state_id');
            $table->index('municipality_id');
            $table->index('colony_id');
        });

        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('billing_configurations', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('type_billing_id');
            $table->index('payment_method_id');
        });

        Schema::table('type_billings', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('reminders_configurations', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('billing_addresses', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->index('balanceable_id');
        });

        Schema::table('method_of_payments', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('routers', function (Blueprint $table) {
            $table->index('location_id');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('colonies', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('network_ips', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('network_id');
        });

        Schema::table('networks', function (Blueprint $table) {
            $table->index('id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['location_id']);
            $table->dropIndex(['partner_id']);
            $table->dropIndex(['state_id']);
            $table->dropIndex(['municipality_id']);
            $table->dropIndex(['colony_id']);
        });

        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('billing_configurations', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['type_billing_id']);
            $table->dropIndex(['payment_method_id']);
        });

        Schema::table('type_billings', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('reminders_configurations', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('billing_addresses', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->dropIndex(['balanceable_id']);
        });

        Schema::table('method_of_payments', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('routers', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('colonies', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('network_ips', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['network_id']);
        });

        Schema::table('networks', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['id']);
        });
    }
};

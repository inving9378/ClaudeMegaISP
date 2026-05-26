<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_consents', function (Blueprint $t) {
            $t->id();
            $t->integer('version_number')->unique();
            $t->longText('content')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_draft')->default(true);
            $t->boolean('require_reacceptance')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->integer('terms_version_accepted')->nullable()->after('terms_ip');
        });
    }

    public function down(): void
    {
        Schema::table('parental_accounts', function (Blueprint $t) {
            $t->dropColumn('terms_version_accepted');
        });
        Schema::dropIfExists('parental_consents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $t) {
            $t->id();
            $t->enum('platform', ['android', 'ios']);
            $t->string('version_name', 32);
            $t->integer('version_code');
            $t->string('file_path', 500);
            $t->bigInteger('file_size')->default(0);
            $t->text('changelog')->nullable();
            $t->boolean('is_mandatory')->default(false);
            $t->integer('min_version_code')->nullable();
            $t->integer('download_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamp('released_at')->nullable();
            $t->timestamps();

            $t->index(['platform', 'active', 'version_code']);
        });

        $apkPath = public_path('downloads/megafamilia-v0_3_0.apk');
        $size = file_exists($apkPath) ? filesize($apkPath) : 0;

        DB::table('app_versions')->insert([
            'platform'      => 'android',
            'version_name'  => '0.3.0',
            'version_code'  => 3,
            'file_path'     => 'downloads/megafamilia-v0_3_0.apk',
            'file_size'     => $size,
            'changelog'     => 'Versión inicial del módulo MegaFamilia',
            'is_mandatory'  => false,
            'active'        => true,
            'released_at'   => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};

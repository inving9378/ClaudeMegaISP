<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990618 — firma de documentos de empleado/vendedor (fase 1: backend).
 * Aditiva (columnas nullable, guard hasColumn): registra la firma capturada (dibujada en pad
 * o subida como imagen) sobre un documento ya generado. No toca el flujo de generación/listado
 * existente (Hijo D2 / item #871).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_employee_documents', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('generated_at');
            }
            if (!Schema::hasColumn('talento_employee_documents', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_path');
            }
            if (!Schema::hasColumn('talento_employee_documents', 'signed_by')) {
                $table->unsignedBigInteger('signed_by')->nullable()->after('signed_at');
            }
            if (!Schema::hasColumn('talento_employee_documents', 'signature_method')) {
                $table->enum('signature_method', ['drawn', 'uploaded', 'digital'])->nullable()->after('signed_by');
            }
        });

        Schema::table('talento_employee_documents', function (Blueprint $table) {
            if (!$this->hasForeign('talento_employee_documents', 'talento_employee_documents_signed_by_foreign')) {
                $table->foreign('signed_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_employee_documents', function (Blueprint $table) {
            if ($this->hasForeign('talento_employee_documents', 'talento_employee_documents_signed_by_foreign')) {
                $table->dropForeign(['signed_by']);
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('talento_employee_documents', 'signature_method') ? 'signature_method' : null,
                Schema::hasColumn('talento_employee_documents', 'signed_by') ? 'signed_by' : null,
                Schema::hasColumn('talento_employee_documents', 'signed_at') ? 'signed_at' : null,
                Schema::hasColumn('talento_employee_documents', 'signature_path') ? 'signature_path' : null,
            ]));
        });
    }

    private function hasForeign(string $table, string $constraintName): bool
    {
        $connection = Schema::getConnection();
        $rows = $connection->select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$connection->getDatabaseName(), $table, $constraintName]
        );

        return count($rows) > 0;
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Ningún item llega mudo a la bandeja»: la columna donde se guarda QUÉ LE FALTA a un item para
 * poder decidirse (`HuecosDelSpec::detectar()`, determinista y sin IA).
 *
 * Columna PROPIA y no dentro de `preguntas` por dos razones, las dos ya documentadas en el módulo:
 *  · `preguntas` son preguntas de DECISIÓN con opciones, y las genera una IA a través de un worker
 *    de cola. Cuando falta cualquiera de los dos, el item se quedaba mudo — el caso que esto cierra.
 *  · Los ids de `preguntas` son POSICIONALES (`q1`, `q2`…): mezclar ahí dentro un segundo origen
 *    de escritura reabre el footgun de pegarle la respuesta vieja a una pregunta nueva.
 *
 * ADITIVA e IDEMPOTENTE. `nullable` a propósito: null = «todavía no se ha medido», que es distinto
 * de `[]` = «se midió y no le falta nada». Un default `[]` haría indistinguibles las dos cosas, que
 * es la firma de la familia de bugs que este módulo ya pagó tres veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'huecos_spec')) {
                $t->json('huecos_spec')->nullable()->after('preguntas');
            }
            if (! Schema::hasColumn('roadmap_items', 'huecos_medidos_at')) {
                $t->timestamp('huecos_medidos_at')->nullable()->after('huecos_spec');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            foreach (['huecos_medidos_at', 'huecos_spec'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};

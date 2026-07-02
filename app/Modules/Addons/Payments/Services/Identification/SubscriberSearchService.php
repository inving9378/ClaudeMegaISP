<?php

namespace App\Modules\Addons\Payments\Services\Identification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 (F3.2) — Búsqueda robusta de cliente por NOMBRE (concepto / titular).
 *
 * Camino PROPUESTA de identificación: cuando no hay referencia MEG, se busca al
 * suscriptor por su nombre. Devuelve candidatos RANKEADOS con sus servicios para
 * desambiguar (homónimos, o un cliente con varios servicios).
 *
 * Diseño anti falso-positivo:
 *  - normaliza (mayúsculas, sin acentos, sin ruido);
 *  - exige coincidencia de AL MENOS un apellido + score suficiente;
 *  - tolera truncamiento del banco ("FLO" ~ "FLORES") por prefijo.
 *
 * Read-only. NO identifica solo por teléfono (se usa apenas como desempate de
 * ranking, nunca para resolver). El resultado por nombre SIEMPRE es "propuesta"
 * (F4 exige confirmación humana).
 */
class SubscriberSearchService
{
    /** Palabras de ruido que no aportan a la identificación. */
    private const STOPWORDS = [
        'DE', 'LA', 'EL', 'LOS', 'LAS', 'DEL', 'Y', 'PAGO', 'PAGOS', 'ABONO',
        'DEPOSITO', 'TRANSFERENCIA', 'SPEI', 'MEG', 'MEGANET', 'INTERNET',
        'MENSUALIDAD', 'SERVICIO',
    ];

    /**
     * Busca candidatos por uno o varios textos de nombre (concepto y/o titular).
     *
     * @param string[] $names   Textos candidatos (ej. [concepto, titular]).
     * @param string|null $phoneHint  Teléfono del remitente (solo desempata ranking).
     * @return array Lista de candidatos: [{client_id, full_name, colonia, address, services[], score, phone_match}]
     */
    public function search(array $names, ?string $phoneHint = null, int $limit = 8): array
    {
        $queryTokens = $this->tokensFrom(implode(' ', array_filter($names)));
        if (count($queryTokens) < 2) {
            return []; // menos de 2 tokens útiles → demasiado ambiguo, no buscamos.
        }

        $phoneDigits = $phoneHint ? preg_replace('/\D/', '', $phoneHint) : null;

        $rows = $this->activeClients();

        $scored = [];
        foreach ($rows as $r) {
            $nameTokens   = $this->tokensFrom($r->name);
            $fatherTokens = $this->tokensFrom($r->father_last_name);
            $motherTokens = $this->tokensFrom($r->mother_last_name);
            $allTokens    = array_values(array_unique(array_merge($nameTokens, $fatherTokens, $motherTokens)));

            $matched = 0;
            foreach ($queryTokens as $q) {
                if ($this->tokenMatchesAny($q, $allTokens)) {
                    $matched++;
                }
            }

            $surnameHit = $this->anyMatch($queryTokens, $fatherTokens) || $this->anyMatch($queryTokens, $motherTokens);

            // Umbral: >=2 tokens y al menos un apellido → precisión sobre recall.
            if ($matched < 2 || !$surnameHit) {
                continue;
            }

            $phoneMatch = false;
            if ($phoneDigits) {
                foreach ([$r->phone, $r->phone2, $r->phone3] as $p) {
                    if ($p && str_ends_with(preg_replace('/\D/', '', $p), substr($phoneDigits, -10))) {
                        $phoneMatch = true;
                        break;
                    }
                }
            }

            $scored[] = [
                'client_id'   => (int) $r->client_id,
                'full_name'   => $this->displayName($r),
                'colony_id'   => $r->colony_id,
                'address'     => $r->address,
                'name_score'  => $matched,
                // match_full = TODOS los tokens del nombre buscado coincidieron
                // (tolerando truncamiento). Es lo que distingue "único" de "varios".
                'match_full'  => $matched === count($queryTokens),
                'score'       => $matched + ($phoneMatch ? 1 : 0),
                'phone_match' => $phoneMatch,
            ];
        }

        // Ranking: match_full primero, luego score, luego phone_match.
        usort($scored, fn ($a, $b) =>
            ($b['match_full'] <=> $a['match_full'])
            ?: ($b['score'] <=> $a['score'])
            ?: ($b['phone_match'] <=> $a['phone_match'])
        );
        $top = array_slice($scored, 0, $limit);

        return $this->attachServicesAndColonia($top);
    }

    /**
     * Clasifica el resultado de search() en la decisión del FSM:
     *  - 'single'   → un único match completo → propuesta de cliente.
     *  - 'multiple' → varios matches completos (homónimos) → desambiguar.
     *  - 'none'     → ningún match completo → volver a preguntar el nombre.
     * Solo cuentan los match_full (nombre completo coincidente); los parciales
     * son demasiado débiles para proponer.
     *
     * @return array {status, client_id, candidates}
     */
    public function classify(array $candidates): array
    {
        $fulls = array_values(array_filter($candidates, fn ($c) => $c['match_full']));

        if (count($fulls) === 1) {
            return ['status' => 'single', 'client_id' => $fulls[0]['client_id'], 'candidates' => $fulls];
        }
        if (count($fulls) > 1) {
            return ['status' => 'multiple', 'client_id' => null, 'candidates' => $fulls];
        }
        return ['status' => 'none', 'client_id' => null, 'candidates' => []];
    }

    // ── Datos ────────────────────────────────────────────────────────────────

    /** Clientes activos (no borrados, no de prueba) con su ficha de nombre. */
    private function activeClients()
    {
        return DB::table('client_main_information as cmi')
            ->join('clients as c', 'c.id', '=', 'cmi.client_id')
            ->whereNull('c.deleted_at')
            ->where(function ($q) {
                $q->whereNull('c.is_test_data')->orWhere('c.is_test_data', 0);
            })
            ->get([
                'cmi.client_id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name',
                'cmi.phone', 'cmi.phone2', 'cmi.phone3', 'cmi.colony_id', 'cmi.address',
            ]);
    }

    /** Adjunta servicios (bundle+custom) y nombre de colonia a cada candidato. */
    private function attachServicesAndColonia(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }
        $ids = array_column($candidates, 'client_id');

        $bundles = DB::table('client_bundle_services')->whereIn('client_id', $ids)
            ->get(['id', 'client_id', 'description'])->groupBy('client_id');
        $customs = DB::table('client_custom_services')->whereIn('client_id', $ids)
            ->get(['id', 'client_id', 'description'])->groupBy('client_id');

        $colonyIds = array_filter(array_column($candidates, 'colony_id'));
        $colonies = [];
        if ($colonyIds && Schema::hasTable('colonies')) {
            $colonies = DB::table('colonies')->whereIn('id', $colonyIds)->pluck('name', 'id')->toArray();
        }

        foreach ($candidates as &$cand) {
            $services = [];
            foreach ($bundles->get($cand['client_id'], collect()) as $b) {
                $services[] = ['type' => 'bundle', 'id' => $b->id, 'description' => $b->description];
            }
            foreach ($customs->get($cand['client_id'], collect()) as $s) {
                $services[] = ['type' => 'custom', 'id' => $s->id, 'description' => $s->description];
            }
            $cand['services'] = $services;
            $cand['colonia']  = $cand['colony_id'] ? ($colonies[$cand['colony_id']] ?? null) : null;
            unset($cand['colony_id']);
        }
        return $candidates;
    }

    // ── Normalización / matching ─────────────────────────────────────────────

    private function tokensFrom(?string $s): array
    {
        if (!$s) {
            return [];
        }
        $norm = $this->normalize($s);
        $tokens = array_filter(explode(' ', $norm), function ($t) {
            return strlen($t) >= 3 && !in_array($t, self::STOPWORDS, true);
        });
        return array_values(array_unique($tokens));
    }

    private function normalize(string $s): string
    {
        $s = mb_strtoupper($s, 'UTF-8');
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U', 'Ñ' => 'N',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /** ¿el token q coincide con alguno de la lista? (igual o prefijo, tolera truncamiento). */
    private function tokenMatchesAny(string $q, array $tokens): bool
    {
        foreach ($tokens as $t) {
            if ($q === $t) {
                return true;
            }
            if (strlen($q) >= 3 && strlen($t) >= 3 && (str_starts_with($t, $q) || str_starts_with($q, $t))) {
                return true;
            }
        }
        return false;
    }

    private function anyMatch(array $queryTokens, array $tokens): bool
    {
        foreach ($queryTokens as $q) {
            if ($this->tokenMatchesAny($q, $tokens)) {
                return true;
            }
        }
        return false;
    }

    private function displayName($r): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$r->name} {$r->father_last_name} {$r->mother_last_name}"));
    }
}

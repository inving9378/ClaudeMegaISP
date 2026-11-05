<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Modules\Addons\CrmEmpresarial\Models\CrmDocumentTemplateVersion;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Generador de documentos DOCX del CRM Empresarial (item #603, épica #554, Fase 4).
 *
 * Plantilla publicada -> datos del expediente -> faltantes (NUNCA se inventa un
 * dato) -> vista previa -> DOCX. Variables `${campo}`; filas repetidas con
 * `${rep.nombre}` (cloneRow sobre representantes) y `${con.nombre}` (contactos);
 * imágenes `${img.xxx}` (setImageValue). El PDF lo hace GenerarPdfDocumentoJob.
 */
class DocumentoDocxService
{
    private const GRUPOS = ['rep' => 'representantes', 'con' => 'contactos', 'sit' => 'sitios'];

    public function versionPublicada(int $templateId): ?CrmDocumentTemplateVersion
    {
        $id = DB::table('document_templates')->where('id', $templateId)->value('version_publicada_id');
        $q = CrmDocumentTemplateVersion::where('template_id', $templateId)->where('estado', 'publicada')->where('formato', 'docx');
        return $id ? (clone $q)->where('id', $id)->first() ?? $q->orderByDesc('version')->first()
                   : $q->orderByDesc('version')->first();
    }

    /** Valores escalares y filas del expediente. Lo que no existe NO aparece. */
    public function datos(CrmExpediente $e): array
    {
        $esc = [];
        foreach ($e->getAttributes() as $k => $v) {
            if ($v !== null && $v !== '' && is_scalar($v)) {
                $esc[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v;
            }
        }
        $filas = [];
        foreach (self::GRUPOS as $pref => $rel) {
            foreach ($e->{$rel}()->get() as $m) {
                $f = [];
                foreach ($m->getAttributes() as $k => $v) {
                    if ($v !== null && $v !== '' && is_scalar($v)) {
                        $f["$pref.$k"] = (string) $v;
                    }
                }
                $filas[$pref][] = $f;
            }
        }
        return ['escalares' => $esc, 'filas' => $filas];
    }

    /** Variables de la plantilla, ya separadas en escalares / de fila / imagen. */
    public function variables(string $docxPath): array
    {
        $vars = (new TemplateProcessor($docxPath))->getVariables();
        $out = ['escalares' => [], 'filas' => [], 'imagenes' => []];
        foreach (array_unique($vars) as $v) {
            $v = explode(':', $v)[0];
            if (str_starts_with($v, 'img.')) {
                $out['imagenes'][] = $v;
            } elseif (preg_match('/^(rep|con|sit)\./', $v)) {
                $out['filas'][] = $v;
            } else {
                $out['escalares'][] = $v;
            }
        }
        return $out;
    }

    /**
     * Vista previa: qué se llenará y qué falta. `ok` sólo si no falta nada.
     * @param array<string,string> $imagenes variable img.* => ruta absoluta
     */
    public function vistaPrevia(CrmDocumentTemplateVersion $v, CrmExpediente $e, array $imagenes = []): array
    {
        $vars = $this->variables($this->rutaAbsoluta($v));
        $d = $this->datos($e);
        $faltantes = [];
        foreach ($vars['escalares'] as $n) {
            if (! isset($d['escalares'][$n])) {
                $faltantes[] = $n;
            }
        }
        foreach ($vars['filas'] as $n) {
            $pref = explode('.', $n)[0];
            if (empty($d['filas'][$pref]) || ! isset($d['filas'][$pref][0][$n])) {
                $faltantes[] = $n;
            }
        }
        foreach ($vars['imagenes'] as $n) {
            if (empty($imagenes[$n]) || ! is_file($imagenes[$n])) {
                $faltantes[] = $n;
            }
        }
        $faltantes = array_values(array_unique($faltantes));
        return [
            'ok' => $faltantes === [],
            'faltantes' => $faltantes,
            'valores' => array_intersect_key($d['escalares'], array_flip($vars['escalares'])),
            'filas' => array_map('count', $d['filas']),
        ];
    }

    /**
     * Genera el DOCX. Con faltantes devuelve ['ok'=>false,'faltantes'=>[...]] y NO
     * escribe nada, salvo $permitirFaltantes (deja la variable en blanco).
     */
    public function generar(CrmDocumentTemplateVersion $v, CrmExpediente $e, array $imagenes = [], bool $permitirFaltantes = false): array
    {
        $prev = $this->vistaPrevia($v, $e, $imagenes);
        if (! $prev['ok'] && ! $permitirFaltantes) {
            return ['ok' => false, 'faltantes' => $prev['faltantes']];
        }
        $vars = $this->variables($this->rutaAbsoluta($v));
        $d = $this->datos($e);
        $tp = new TemplateProcessor($this->rutaAbsoluta($v));

        foreach (self::GRUPOS as $pref => $_) {
            $anclas = array_values(array_filter($vars['filas'], fn ($n) => str_starts_with($n, "$pref.")));
            if (! $anclas) {
                continue;
            }
            $filas = $d['filas'][$pref] ?? [];
            if (! $filas) {
                foreach ($anclas as $a) {
                    $tp->setValue($a, '');
                }
                continue;
            }
            $tp->cloneRow($anclas[0], count($filas));
            foreach ($filas as $i => $fila) {
                foreach ($anclas as $a) {
                    $tp->setValue($a . '#' . ($i + 1), $fila[$a] ?? '');
                }
            }
        }
        foreach ($vars['escalares'] as $n) {
            $tp->setValue($n, $d['escalares'][$n] ?? '');
        }
        foreach ($vars['imagenes'] as $n) {
            if (! empty($imagenes[$n]) && is_file($imagenes[$n])) {
                $tp->setImageValue($n, ['path' => $imagenes[$n], 'width' => 160, 'height' => 60, 'ratio' => true]);
            } else {
                $tp->setValue($n, '');
            }
        }

        $rel = "private/crm-empresarial/documentos/exp{$e->id}-t{$v->template_id}-v{$v->version}-" . now()->format('YmdHis') . '.docx';
        Storage::disk('local')->makeDirectory(dirname($rel));
        $tp->saveAs(Storage::disk('local')->path($rel));

        return ['ok' => true, 'path' => $rel, 'faltantes' => $prev['faltantes'], 'fuentes_ausentes' => $this->fuentesAusentes()];
    }

    /** Carlito/Caladea (métricas de Calibri/Cambria) no instaladas = el PDF cambia de tipografía. */
    public function fuentesAusentes(): array
    {
        $lista = (string) @shell_exec('fc-list 2>/dev/null');
        return array_values(array_filter(['Carlito', 'Caladea'], fn ($f) => stripos($lista, $f) === false));
    }

    private function rutaAbsoluta(CrmDocumentTemplateVersion $v): string
    {
        abort_unless($v->archivo_path && Storage::disk('local')->exists($v->archivo_path), 422, 'La versión no tiene archivo DOCX.');
        return Storage::disk('local')->path($v->archivo_path);
    }
}

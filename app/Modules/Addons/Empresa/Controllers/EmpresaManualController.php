<?php

namespace App\Modules\Addons\Empresa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Empresa\Models\ManualChapter;
use App\Modules\Addons\Empresa\Models\ManualSection;
use App\Modules\Addons\Empresa\Models\ManualSectionVersion;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manual General de la Empresa (#838, Parte B del #795): documento vivo,
 * editable, con contenido en BD (capítulos → secciones → versiones). La
 * pantalla en sí sigue autocontenida (no extiende core-layout::master, ver
 * manual.blade.php) para que su CSS/JS propios no interfieran con el chrome
 * del admin; esta clase expone además la API JSON que esa pantalla consume
 * en modo edición.
 */
class EmpresaManualController extends Controller
{
    public function view()
    {
        $this->authorize('empresa_manual_view');

        return view('addon-empresa::manual', [
            'canEdit' => auth()->user()->can('empresa_manual_edit'),
            'canCreate' => auth()->user()->can('empresa_manual_create'),
            'canDelete' => auth()->user()->can('empresa_manual_delete'),
            'canPublish' => auth()->user()->can('empresa_manual_publish'),
        ]);
    }

    /** GET /empresa/manual/api/data — árbol completo para pintar/editar el documento. */
    public function data(): JsonResponse
    {
        $this->authorize('empresa_manual_view');

        $chapters = ManualChapter::with(['sections.publishedVersion'])->orderBy('order')->get();

        return response()->json([
            'chapters' => $chapters->map(function (ManualChapter $chapter) {
                return [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'order' => $chapter->order,
                    'sections' => $chapter->sections->map(function (ManualSection $section) {
                        return [
                            'id' => $section->id,
                            'title' => $section->title,
                            'order' => $section->order,
                            'content' => $section->content,
                            'published_content' => optional($section->publishedVersion)->content,
                            'has_unpublished_changes' => $section->hasUnpublishedChanges(),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    /** POST /empresa/manual/api/chapters */
    public function storeChapter(Request $request): JsonResponse
    {
        $this->authorize('empresa_manual_create');

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $chapter = ManualChapter::create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug(ManualChapter::class, $data['title']),
            'order' => (int) ManualChapter::max('order') + 1,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['chapter' => $chapter], 201);
    }

    /** PUT /empresa/manual/api/chapters/{id} */
    public function updateChapter(Request $request, int $id): JsonResponse
    {
        $this->authorize('empresa_manual_edit');

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $chapter = ManualChapter::findOrFail($id);
        $chapter->update(['title' => $data['title'], 'updated_by' => auth()->id()]);

        return response()->json(['chapter' => $chapter]);
    }

    /** POST /empresa/manual/api/chapters/{id}/eliminar */
    public function destroyChapter(int $id): JsonResponse
    {
        $this->authorize('empresa_manual_delete');

        $chapter = ManualChapter::findOrFail($id);
        $chapter->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /empresa/manual/api/chapters/reorder — body: {order:[chapterId,...]} */
    public function reorderChapters(Request $request): JsonResponse
    {
        $this->authorize('empresa_manual_edit');

        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        foreach ($data['order'] as $i => $chapterId) {
            ManualChapter::whereKey($chapterId)->update(['order' => $i + 1, 'updated_by' => auth()->id()]);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /empresa/manual/api/sections — body: {chapter_id,title} */
    public function storeSection(Request $request): JsonResponse
    {
        $this->authorize('empresa_manual_create');

        $data = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:empresa_manual_chapters,id'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $section = ManualSection::create([
            'chapter_id' => $data['chapter_id'],
            'title' => $data['title'],
            'slug' => $this->uniqueSectionSlug((int) $data['chapter_id'], $data['title']),
            'order' => (int) ManualSection::where('chapter_id', $data['chapter_id'])->max('order') + 1,
            'content' => '',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['section' => $section], 201);
    }

    /** PUT /empresa/manual/api/sections/{id} — body: {title?, content?}. Editar contenido crea una versión nueva (borrador). */
    public function updateSection(Request $request, int $id): JsonResponse
    {
        $this->authorize('empresa_manual_edit');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
        ]);

        $section = ManualSection::findOrFail($id);

        DB::transaction(function () use ($section, $data) {
            if (array_key_exists('title', $data)) {
                $section->title = $data['title'];
            }

            if (array_key_exists('content', $data) && $data['content'] !== $section->content) {
                $nextVersion = (int) ManualSectionVersion::where('section_id', $section->id)->max('version_number') + 1;
                ManualSectionVersion::create([
                    'section_id' => $section->id,
                    'version_number' => $nextVersion,
                    'content' => $data['content'],
                    'is_published' => false,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]);
                $section->content = $data['content'];
            }

            $section->updated_by = auth()->id();
            $section->save();
        });

        return response()->json(['section' => $section->fresh()]);
    }

    /** POST /empresa/manual/api/sections/{id}/eliminar */
    public function destroySection(int $id): JsonResponse
    {
        $this->authorize('empresa_manual_delete');

        $section = ManualSection::findOrFail($id);
        $section->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /empresa/manual/api/sections/reorder — body: {chapter_id, order:[sectionId,...]} */
    public function reorderSections(Request $request): JsonResponse
    {
        $this->authorize('empresa_manual_edit');

        $data = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:empresa_manual_chapters,id'],
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($data['order'] as $i => $sectionId) {
            ManualSection::whereKey($sectionId)
                ->where('chapter_id', $data['chapter_id'])
                ->update(['order' => $i + 1, 'updated_by' => auth()->id()]);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /empresa/manual/api/sections/{id}/publicar — publica la última versión guardada (el borrador vigente). */
    public function publishSection(int $id): JsonResponse
    {
        $this->authorize('empresa_manual_publish');

        $section = ManualSection::findOrFail($id);

        $latest = ManualSectionVersion::where('section_id', $section->id)
            ->orderByDesc('version_number')
            ->first();

        if (! $latest) {
            return response()->json(['message' => 'La sección no tiene versiones para publicar.'], 422);
        }

        DB::transaction(function () use ($section, $latest) {
            ManualSectionVersion::where('section_id', $section->id)->update(['is_published' => false]);
            $latest->is_published = true;
            $latest->published_at = now();
            $latest->save();

            $section->published_version_id = $latest->id;
            $section->updated_by = auth()->id();
            $section->save();
        });

        return response()->json(['section' => $section->fresh()]);
    }

    /** GET /empresa/manual/pdf — exporta el contenido PUBLICADO (nunca borradores) con membrete de CompanyInformation. */
    public function pdf()
    {
        $this->authorize('empresa_manual_view');

        $chapters = ManualChapter::with(['sections.publishedVersion'])->orderBy('order')->get();
        $company = CompanyInformation::first();

        $pdf = Pdf::loadView('addon-empresa::manual_pdf', [
            'chapters' => $chapters,
            'company' => $company,
        ])->setPaper('letter');

        return $pdf->stream('manual-general-empresa.pdf');
    }

    private function uniqueSlug(string $modelClass, string $title): string
    {
        $base = Str::slug($title) ?: 'capitulo';
        $slug = $base;
        $i = 2;
        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueSectionSlug(int $chapterId, string $title): string
    {
        $base = Str::slug($title) ?: 'seccion';
        $slug = $base;
        $i = 2;
        while (ManualSection::where('chapter_id', $chapterId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}

<?php

namespace App\Modules\Addons\Empresa\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualSection extends BaseModel
{
    use SoftDeletes;

    protected $table = 'empresa_manual_sections';

    protected $fillable = ['chapter_id', 'title', 'slug', 'order', 'content', 'published_version_id', 'visible_roles'];

    protected $casts = [
        'visible_roles' => 'array',
    ];

    /**
     * Roles que siempre ven el manual completo, sin importar visible_roles —
     * necesario para poder editar/curar el árbol entero desde un solo lugar.
     */
    public const ROLES_BYPASS = [
        'super-administrator',
        'Super Administrador',
        'Administrador',
        'DESARROLLADOR',
        'ADMINISTRADOR_COMPLETO',
    ];

    /** Roles asignables a visible_roles — para los que SÍ tiene sentido acotar (los bypass ya ven todo). */
    public const ROLES_ASSIGNABLE = [
        'Almacen',
        'CONTADOR',
        'Mostrador',
        'SUPERVISOR_MOSTRADOR',
        'TECNICO',
        'TECNICO_INSTALADOR',
        'TECNICO_PLANTA',
        'Vendedor',
        'conductor',
    ];

    /**
     * Pseudo-rol: "nadie del staff operativo, solo administración" — distinto de
     * visible_roles vacío/null (que significa "sin restricción, todos"). Sin este
     * sentinel no hay forma de expresar "esta sección es solo para quien administra
     * el sistema" sin dejarla abierta a todo el staff.
     */
    public const ROLE_ADMIN_ONLY = '__admin_only__';

    public function chapter()
    {
        return $this->belongsTo(ManualChapter::class, 'chapter_id');
    }

    /** null/vacío = visible para cualquiera que pueda ver el manual. */
    public function isVisibleFor(?\App\Models\User $user): bool
    {
        if (empty($this->visible_roles)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(self::ROLES_BYPASS)) {
            return true;
        }

        if (in_array(self::ROLE_ADMIN_ONLY, $this->visible_roles, true)) {
            return false;
        }

        return $user->hasAnyRole($this->visible_roles);
    }

    public function versions()
    {
        return $this->hasMany(ManualSectionVersion::class, 'section_id')->orderByDesc('version_number');
    }

    public function publishedVersion()
    {
        return $this->belongsTo(ManualSectionVersion::class, 'published_version_id');
    }

    public function hasUnpublishedChanges(): bool
    {
        if (! $this->published_version_id) {
            return true;
        }

        return $this->content !== optional($this->publishedVersion)->content;
    }
}

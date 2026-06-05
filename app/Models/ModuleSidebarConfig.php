<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleSidebarConfig extends Model
{
    protected $table = 'module_sidebar_config';

    protected $fillable = [
        'module_key', 'show_in_sidebar', 'sidebar_location', 'sidebar_parent',
        'sidebar_section', 'sidebar_position', 'sidebar_icon', 'sidebar_label',
        'config_moved', 'admin_section', 'configuracion_subsection', 'is_core',
    ];

    protected $casts = [
        'show_in_sidebar' => 'boolean',
        'config_moved'    => 'boolean',
        'is_core'         => 'boolean',
        'sidebar_position' => 'integer',
    ];

    public function scopeVisibleInSidebar($q)
    {
        return $q->where('show_in_sidebar', true);
    }

    public function scopeAddon($q)
    {
        return $q->where('is_core', false);
    }

    public function scopeInSection($q, string $section)
    {
        return $q->where('sidebar_section', $section);
    }
}

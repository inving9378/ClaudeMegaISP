<?php

namespace App\Http\Repository;

use App\Models\DocumentationManagerSection;

/**
 * DocumentationManagerSectionRepository
 * 
 * Repositorio para gestionar consultas de secciones de documentación.
 * Sigue el mismo patrón que InventoryStoreRepository.
 * 
 * @package App\Http\Repository
 */
class DocumentationManagerSectionRepository
{
    protected $model;

    /**
     * Constructor del repositorio.
     * 
     * Inicializa la consulta del modelo DocumentationManagerSection.
     */
    public function __construct()
    {
        $this->model = DocumentationManagerSection::query();
    }

    /**
     * Obtener el conteo total de secciones.
     * 
     * @return int
     */
    public function count()
    {
        return $this->model->count();
    }

    /**
     * Obtener una sección por su ID.
     * 
     * @param int $id ID de la sección
     * @return DocumentationManagerSection|null
     */
    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first() ?? null;
    }
}
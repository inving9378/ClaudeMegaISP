<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\InventoryMovement;

class InventoryMovementRepository
{

    protected $model;

    public function __construct()
    {
        $this->model = InventoryMovement::query();
    }

    public function count()
    {
        return $this->model->count();
    }


    public function getModelById($id)
    {
        return $this->model->where('id', $id)->first() ?? null;
    }

    public function getItemsPendingByUser($id)
    {
        return $this->model->where('movementable_to_type', 'App\Models\User')
            ->where('movementable_to_id', $id)
            ->where('type', ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA)
            ->where('status', ComunConstantsController::INVENTORY_MOVEMENT_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }


    public function getItemsPendingByStore($id)
    {
        return $this->model->where('movementable_to_type', 'App\Models\InventoryStore')
            ->where('movementable_to_id', $id)
            ->where('type', ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA)
            ->where('status', ComunConstantsController::INVENTORY_MOVEMENT_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }


    public function getLastActionsByUser($id)
    {
        return $this->model
            ->where(function ($query) use ($id) {
                // Caso 1: movementable_to_type es User y movementable_to_id = $id
                $query->where(function ($q) use ($id) {
                    $q->where('movementable_to_type', 'App\Models\User')
                        ->where('movementable_to_id', $id)
                        ->where('type', ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA);
                })
                    ->orWhere(function ($q) use ($id) {
                        // Caso 2: movementable_from_type es User y movementable_from_id = $id
                        $q->where('movementable_to_type', 'App\Models\Client')
                            ->where('movementable_from_type', 'App\Models\User')
                            ->where('movementable_from_id', $id)
                            ->where('type', ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA);
                    })
                    ->orWhere(function ($q) use ($id) {
                        // Caso 2: movementable_from_type es User y movementable_from_id = $id
                        $q->where('movementable_from_type', 'App\Models\User')
                            ->where('movementable_from_id', $id)
                            ->where('type', ComunConstantsController::INVENTORY_MOVEMENT_TYPE_SALIDA);
                    })
                ;
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    public function create_function($data)
    {
        return $this->model->create($data);
    }
}

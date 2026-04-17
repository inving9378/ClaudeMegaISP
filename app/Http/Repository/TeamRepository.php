<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientMainInformation;
use App\Models\Colony;
use App\Models\Team;
use App\Models\User;

class TeamRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Team::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getTeamsWithUsersGroup()
    {
        // Obtener equipos con sus usuarios
        $teamsWithUsers = Team::with('users')
            ->whereHas('users') // Solo equipos con usuarios asignados
            ->get()
            ->mapWithKeys(function ($team) {
                return [
                    $team->name => $team->users->mapWithKeys(function ($user) {
                        return [$user->id => $user->name]; // Mapeamos el id y el nombre del usuario
                    })->toArray(),
                ];
            })
            ->toArray();

        // Obtener la lista de equipos para el grupo "Equipo"
        $teamsList = Team::whereHas('users') // Solo equipos con usuarios asignados
            ->get()
            ->mapWithKeys(function ($team) {
                return [$team->name => $team->name];
            })
            ->toArray();

        // Agregar usuarios sin equipo al grupo "Otros"
        $usersWithoutTeam = User::doesntHave('teams')->notClientRole() // Usuarios que no pertenecen a ningún equipo
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->id => $user->name]; // Mapeamos el id y el nombre del usuario
            })
            ->toArray();

        if (!empty($usersWithoutTeam)) {
            $teamsWithUsers['Otros'] = $usersWithoutTeam;
            $teamsList['Otros'] = 'Otros';
        }

        // Unimos ambas estructuras
        $finalResult = [
            'Equipo' => $teamsList
        ] + $teamsWithUsers;

        return $finalResult;
    }


    public function getColorByName($name)
    {
        return $this->model->where('name', $name)->first()->color;
    }

    // SETTERS




}

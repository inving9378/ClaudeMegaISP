<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\MikrotikNotification;
use App\Models\Router;
use App\Notifications\StandardNotification;
use Illuminate\Support\Facades\Notification;

class RouterRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Router::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS
    public function getRouterById($routerId)
    {
        return $this->model->find($routerId);
    }

    public function getRouterByTitle($title)
    {
        return $this->model->where('title', $title)->first();
    }
  // SETTERS

    public function sendNotifications($id)
    {
        $router = $this->model->where('id',$id)->first();
        $userRepository = new UserRepository();
        $usersToSend = $userRepository->getUserAdmin();
        return $router->sendNotifications("Alta",$usersToSend);
    }
}

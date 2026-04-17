<?php

namespace App\Http\Controllers;

use App\Http\Traits\IncludeFieldsTrait;
use App\Http\Traits\IncludeLibraryTrait;
use App\Http\Traits\MultipleRelationTrait;
use App\Http\Traits\PermissionTrait;
use App\Http\Traits\SingleRelationTrait;
use App\Http\Traits\UserTrait;
use App\Http\Traits\ValidateFieldByRulesInTableFiledModulesTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, IncludeLibraryTrait, IncludeFieldsTrait, MultipleRelationTrait, SingleRelationTrait, PermissionTrait, UserTrait, ValidateFieldByRulesInTableFiledModulesTrait;
}

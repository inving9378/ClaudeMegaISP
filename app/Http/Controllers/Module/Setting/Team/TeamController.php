<?php

namespace App\Http\Controllers\Module\Setting\Team;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\nomenclature\NomenclatureDatatableHelper;
use App\Http\HelpersModule\module\setting\team\TeamDatatableHelper;
use App\Http\Repository\ClientAdditionalInformationRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Models\BoxZone;
use App\Models\District;
use App\Models\Nomenclature;
use App\Models\Team;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeamController extends CrudModalController
{
    public function __construct(TeamDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\Team';
        $this->data['url'] = 'meganet.module.setting.team';
        $this->data['module'] = 'Team';
    }
}

<?php

namespace App\Jobs;

use App\Http\Controllers\UserColumnDatatableModuleController;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\ColumnDatatableModule;
use App\Models\Module;
use App\Models\User;
use App\Models\UserColumnDatatableModule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PupulateUserColumnsDatatableModuleDefaultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $module;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $module)
    {
        $this->user = $user;
        $this->module = $module;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $array = [
            'estado',
            'id',
            'internet_fees',
            'router',
            'type_billing_id',
            'amount',
            'billing_date',
            'billing_expiration',
            'grace_period'
        ];

        $userColumns = UserColumnDatatableModule::where('user_id', $this->user->id)->get();
        if($userColumns){
            foreach ($userColumns as $column){
                $column->delete();
            }
        }

       $moduleId = Module::where('name',$this->module)->first()->id;

        $columnsVisibles = ColumnDatatableModule::where('module_id', $moduleId)
                ->whereIn('name', $array)
                ->get()->pluck('id')->toArray();

        $columnAll = ColumnDatatableModule::where('module_id', $moduleId)->get();
        $filtereds = $columnAll->except($columnsVisibles);

        foreach ($filtereds as $column) {
            DB::table("user_column_datatable_modules")->updateOrInsert([
                "user_id" => $this->user->id,
                "column_datatable_module_id" => $column->id,
                "active" => 0,
            ]);
        }
    }
}

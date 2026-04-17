<?php


namespace App\Services;

use App\Repositories\TableRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class SimpleService
{
    public function simpleTransaction($fun)
    {
        try{
            DB::beginTransaction();
                $response = $fun();
            DB::commit();

            if(empty($response))
                $response = [
                    'res' => true,
                    'message' => "guardado",
                ];

            return response()->json($response, 200);
        }catch(Exception $e){
            DB::rollBack();
            return $this->catch($e);
        }
    }

    public function catch(Exception $e)
    {
        if(config('app.debug')){
            dd($e);
        }

        return response()->json([
            'res' => false,
            'message' => $this->getMessageException($e),
        ], 490);
    }

    public function getMessageException(Exception $e)
    {
        if($e->getCode() === 5525){
            return $e->getMessage();
        }
        return 'Ha ocurrido un error';
    }
}

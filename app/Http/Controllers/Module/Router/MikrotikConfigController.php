<?php

namespace App\Http\Controllers\Module\Router;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\module\router\MikrotikConfigUpdateRequest;
use App\Models\Router;
class MikrotikConfigController extends Controller
{
      /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(MikrotikConfigUpdateRequest $request, $idRouter)
    {
        $router = Router::find($idRouter);
        $action = $router->mikrotikconfig ? 'update' : 'create';
        return $this->saveSingleRelationIfExist('App\Models\Router', $router, $request, $action);
    }

        /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }
}

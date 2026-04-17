<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\module\perfil\PerfilUpdateRequest;
use App\Services\FileUploadService;

class UserController extends Controller
{
    public function __construct()
    {
        $model = 'User';
        $this->data['url'] = 'meganet.module.perfil';
        $this->data['module'] = 'perfil';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'perfil';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        return view($this->data['url'] . '.perfil', $this->data);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function update(PerfilUpdateRequest $request, $id)
    {
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        $input['password'] =  base64_encode($request->password);
        //TODO EJEMPLO DE COMO GUARDAR UNA IMAGEN
        if ($request->hasFile('photography')) {
            $photography = $request->file('photography');
            $fileUploadService = new FileUploadService();
            $path = 'perfil/' . $id;
            $input['photography'] = $fileUploadService->uploadImage($photography, $path);
        }
        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }

    public function updateImage(Request $request, $id)
    {
        //        $perfil = User::byUserAutenticated();
        //        $file = $request->image;
        //        $extension = $file->getClientOriginalExtension();
        //        $name = Str::slug($file->getClientOriginalName());
        //        $name_to_save = Str::replaceLast($extension, '.'.$extension, $name);
        //        $path = '/storage/perfil/'.$id.'/'.$name_to_save;
        //
        //        $perfil->deleteImagePreview();
        //        $perfil->image()->create([
        //            'name' => $name,
        //            'type' => $extension,
        //            'path' => $path,
        //            'size' => $file->getSize(),
        //            'preview' => true
        //        ]);
        //
        //        $file->storeAs('/public/perfil/'.$id, $name_to_save);
        //
        //        return $path;
    }

    public function getPerfilById($id)
    {
        $model = $this->data['model']::find($id);
        return $model;
    }
}

<?php

namespace App\Http\Controllers\Module\Scheduling\Task;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\HelpersModule\module\scheduling\task\TaskDatatableHelper;
use App\Http\Repository\DefaultValueRepository;
use App\Http\Repository\ListTemplateVerificationRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\scheduling\project\TaskCreateRequest;
use App\Http\Traits\NotificationTrait;
use App\Models\FrequencyEstimatedDedicatedTime;
use App\Models\ListTemplateVerificationTask;
use App\Models\LogActivity;
use App\Models\ObservationTask;
use App\Models\Task;
use App\Services\DocumentTemplateService;
use App\Services\FileUploadService;
use App\Services\FormatDateService;
use App\Services\LogService;
use App\Services\UnreadNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    use NotificationTrait;

    private $helper;

    public function __construct(TaskDatatableHelper $helper)
    {
        $model = 'Task';
        $this->data['url'] = 'meganet.module.scheduling.task';
        $this->data['module'] = 'Task';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['persistentFilters'] = $this->getPersistentFilters(false);
        $this->data['filters'] = $this->getFiltersByAuthUser();
        $this->data['module_id'] = $this->getModuleId();
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getModuleId()
    {
        $moduleRepository = new ModuleRepository();
        return $moduleRepository->getModuleByName('FiltersTaskCalendar')->id ?? null;
    }


    public function showArchived()
    {
        $this->data['url'] = 'meganet.module.scheduling.task_archived';
        $this->data['persistentFilters'] = $this->getPersistentFilters(true);
        $this->data['notifications'] = $this->userNotification();
        $this->data['filters'] = $this->getFiltersByAuthUser();
        $this->data['module_id'] = $this->getModuleId();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getFiltersByAuthUser()
    {
        $filters = [];
        $module_id = $this->getModuleId();
        $defaultValueRepository = new DefaultValueRepository();
        $defaultValues = $defaultValueRepository->getDefaultValueFilteredByAuthUserAndModuleId($module_id);

        foreach ($defaultValues as $key => $value) {
            $array = explode(',', $value);
            $filters[$key] = array_map('trim', $array);
        }
        return $filters;
    }


    public function getPersistentFilters($val)
    {
        $filters['archived'] = [
            $val
        ];

        return $filters; // Devuelve un array directamente
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(TaskCreateRequest $request)
    {
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->except('checks');

        unset($input['checks']);
        unset($input['assigned_to']);

        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($input['description']);
        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }

        $input = $this->getStartAndEndTime($input);

        $model = $this->data['model']::create($input);

        $model->users()->sync($request->assigned_to);

        $this->saveChecksListVerification($request, $model->id);

        $model->sendNotifications();

        return $model;
    }

    public function getStartAndEndTime($input, $isEdit = null)
    {
        $estimatedTime = $input['estimated_time'];

        $estimated = FrequencyEstimatedDedicatedTime::where('id', $estimatedTime)->first();
        if ($estimated && !$isEdit) {
            $arrayHurMinutes = explode(':', $estimated->value);

            $currentHour = Carbon::now()->hour;
            $hour = $arrayHurMinutes[0] + $currentHour;
            $minutes = $arrayHurMinutes[1];
            $inputStartTime = Carbon::parse($input['start_time'])->setTime($currentHour, 0, 0);
            $inputEndTime = Carbon::parse($input['end_time'])->setTime($hour, $minutes, 0);

            $input['start_time'] = $inputStartTime;
            $input['end_time'] = $inputEndTime;
        }

        if ($estimated && $isEdit) {
            $arrayHurMinutes = explode(':', $estimated->value);
            $inputStartTime =  Carbon::parse($input['start_time']);
            $currentHour = $inputStartTime->hour;
            $minutes = $arrayHurMinutes[1];
            $hour = $arrayHurMinutes[0] + $currentHour;
            $input['end_time'] = $inputStartTime->setTime($hour, $minutes, 0);
        }


        return $input;
    }

    public function saveChecksListVerification($request, $id)
    {
        if ($request->checks != null) {
            $listTemplateVerification = ListTemplateVerificationTask::where('task_id', $id)->first();
            if ($listTemplateVerification) {
                $listTemplateVerification->update([
                    'list_template_verification_id' => $request->template_verification,
                    'checks' => $this->getValueToInputChecks($request->checks)
                ]);
            } else {
                ListTemplateVerificationTask::create([
                    'task_id' => $id,
                    'list_template_verification_id' => $request->template_verification,
                    'checks' => $this->getValueToInputChecks($request->checks)
                ]);
            }
        }
    }

    public function getValueToInputChecks($checks)
    {
        $array = [];

        if (!is_array($checks)) {
            $checks = explode(',', $checks);
        }
        foreach ($checks as $key => $value) {
            $array[$key] = $value;
        }
        return json_encode($array);
    }

    public function show(LogActivity $log)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        $this->data['observations'] = ObservationTask::where('task_id', $id)->orderBy('id', 'desc')->get();
        $this->data['archived'] = $this->data['model']::where('id', $id)->first()->archived ?? 0;
        $unreadNotificationService = new UnreadNotificationService();
        $unreadNotificationService->unreadTask($id);
        return view($this->data['url'] . '.edit', $this->data);
    }

    public function getData($id)
    {
        $model = $this->data['model']::find($id);
        $logService = new LogService();
        $logs = $logService->getLogs($model);

        if ($model) {
            $array = [
                'id' => $model->id,
                'status' => $model->status,
                'archived' => $model->archived,
                'archived_at' => $model->archived_at,
                'archived_by' => $model->archived_by,
                'created_at' => (new FormatDateService($model->created_at))->formatDateWithTime(),
                'updated_at' => (new FormatDateService($model->updated_at))->formatDateWithTime(),
                'created_by' => $model->created_by_name,
                'finish_at' => (new FormatDateService($model->finish_at))->formatDateWithTime(),
                'logs' => $logs,
                'files' => $model->files
            ];
            return $array;
        }
        return [];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(TaskCreateRequest $request, $id)
    {
        $model = $this->data['model']::find($id);

        try {
            DB::beginTransaction();
            if ($request->file) {
                $this->saveFilesToTask($request, $id, $model);
            }

            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->except('file', 'checks');

            unset($input['checks']);
            unset($input['assigned_to']);
            unset($input['observation']);
            unset($input['file']);

            $this->saveChecksListVerification($request, $model->id);

            $input = $this->getStartAndEndTime($input, true);

            $usersAssigned = $request->assigned_to;
            if (is_string($usersAssigned)) {
                $usersAssigned = explode(',', $usersAssigned);
            }
            $model->users()->sync($usersAssigned);
            $model->sendNotifications();

            $unreadNotificationService = new UnreadNotificationService();
            $unreadNotificationService->unreadTask($id);
            $modelResponse = $model->update($input);
            DB::commit();
            return $modelResponse;
        } catch (\Throwable $th) {
            DB::rollBack();
            return $th;
        }
    }

    public function getTaskColorByStatus($input)
    {
        return ComunConstantsController::STATUS_TASK_COLOR[$input['status']];
    }

    public function saveFilesToTask($request, $id, $model)
    {
        $directory = 'task/' . $id . '/document';
        $fileUploadService = new FileUploadService();

        $file = $request->file('file'); // asegúrate de usar el mismo name del input

        // Detectar MIME type
        $mimeType = $file->getClientMimeType();

        if (str_starts_with($mimeType, 'video/')) {
            // Es un video
            $properties = $fileUploadService->uploadVideo($file, $directory);
        } else {
            // Es otro tipo de archivo (imagen, PDF, etc.)
            $properties = $fileUploadService->uploadFile($file, $directory);
        }
        $input = [
            'name' => $properties['name'],
            'type' => $properties['type'],
            'path' => $properties['path'],
            'size' => $properties['size'],
            'preview' => 0,
            'fileable_id' => $id,
            'fileable_type' => 'App\Models\\Task',
        ];

        $model->file()->create($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }


    public function getListTemplateVerification($id)
    {
        $data = [];
        $model = $this->data['model']::findOrFail($id);
        $listTemplateVerificationRepository = new ListTemplateVerificationRepository();
        $listTemplateVerification = $listTemplateVerificationRepository->getModelById($model->template_verification);
        $templateVerificationName = $listTemplateVerification->name;

        $checks = $listTemplateVerification->checks;
        $checksChecked = ListTemplateVerificationTask::where('task_id', $id)->first()->checks;
        $data['list_template_verification_name'] = $templateVerificationName;
        $data['checks'] = $checks;
        $data['checksChecked'] = $checksChecked;
        return response()->json($data);
    }

    public function showCalendar()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['module_id'] = $this->getModuleId();
        $this->data['filters'] = $this->getFiltersByAuthUser();
        $this->data['persistentFilters'] = $this->getPersistentFilters(false);
        return view('meganet.module.scheduling.calendar.index', $this->data);
    }

    public function updatetaskToCalenddar(Request $request)
    {
        // Parsear el inicio sin la hora del valor original
        $startDateTime = \Carbon\Carbon::parse($request->data['start']);

        $endDateTime = null;
        if ($request->data['end']) {
            // Si hay un valor de end, aplicamos la misma lógica
            $endDateTime = \Carbon\Carbon::parse($request->data['end']);
        }

        // Buscar la tarea
        $task = $this->data['model']::find($request->data['id']);
        if ($task) {
            // Actualizar los tiempos de la tarea con la hora del request
            $task->update([
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
            ]);
        }

        return $task;
    }

    public function archive($id)
    {
        $this->data['model']::findOrFail($id)->update([
            'archived' => true,
            'archived_at' => now(),
            'archived_by' => auth()->user()->id,
            'finish_at' => now(),
            'status' => 'Done',
        ]);

        $logService = new LogService();
        $logService->log($this->data['model']::find($id), 'Archivado');
        return redirect()->back()->with('message', $this->data['module'] . ' Archivado Correctamente');
    }

    public function unArchive($id)
    {
        $this->data['model']::find($id)->update([
            'archived' => false,
            'archived_by' => null,
        ]);

        $logService = new LogService();
        $logService->log($this->data['model']::find($id), 'Desarchivado');
        return redirect()->back()->with('message', $this->data['module'] . ' Desarchivado Correctamente');
    }

    public function addNote(Request $request, $id)
    {
        $newObservation = [
            'observation' => $request->observation,
            'task_id' => $id,
            'created_by' => auth()->user()->id
        ];

        ObservationTask::create($newObservation);
    }

    public function getNotesByTask($id)
    {
        $observations = ObservationTask::where('task_id', $id)->orderBy('id', 'desc')->get();
        return response()->json([
            'observations' => $observations
        ]);
    }

    public function readNotification($id)
    {
        $user = auth()->user();
        $notification = $user->notifications->firstWhere('id', $id);
        if ($notification) {
            $notification->markAsRead();
            $data = $this->getNotificationAttributes($notification);
            if ($data && $data['task_id']) {
                $task = Task::find($data['task_id']);
                if ($task) {
                    return $this->edit($data['task_id']);
                }
            }
        }
        return redirect()->back()->with('error', 'La tarea a la que hace referencia esta notificación ya no existe');
    }

    public function unreadNotification($id)
    {
        $unreadNotificationService = new UnreadNotificationService();
        $unreadNotificationService->unreadTask($id);
    }
    public function download(Request $request, $taskId)
    {
        try {
            // Buscar la tarea y el archivo
            $model = $this->data['model']::findOrFail($taskId);
            $file = $model->files()->where('id', $request->id_file)->firstOrFail();

            // Extraer ruta limpia (sin /storage/ ni dominio)
            $path = $file->path;
            $relativePath = ltrim(str_replace(
                ['/storage/app/public/', '/storage/app/', '/storage/', 'storage/'],
                '',
                parse_url($path, PHP_URL_PATH)
            ), '/');

            // Resolver ruta absoluta existente
            $absolutePath = $this->resolveStoragePath($relativePath);

            // Detectar tipo MIME
            $mimeType = $file->type;
            if (empty($mimeType)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $absolutePath);
                finfo_close($finfo);
            }

            // Nombre visible
            $filename = $file->name ?? basename($absolutePath);

            // Si es video, se sirve con streaming binario
            if (str_starts_with($mimeType, 'video/')) {
                return $this->streamVideo($absolutePath, $filename, $mimeType);
            }

            // Si no es video → descarga normal
            return response()->download($absolutePath, $filename);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error en la descarga',
                'message' => $e->getMessage(),
                'file' => isset($file) ? $file->path : null,
            ], 500);
        }
    }

    private function resolveStoragePath(string $relativePath): string
    {
        $candidates = [
            // symlink público
            public_path('storage/' . $relativePath),
            // carpeta de storage pública real
            storage_path('app/public/' . $relativePath),
            // carpeta raíz de app
            storage_path('app/' . $relativePath),
            // path absoluto (por si acaso)
            $relativePath
        ];

        foreach ($candidates as $try) {
            if (file_exists($try)) {
                Log::debug('Archivo encontrado', ['path' => $try]);
                return $try;
            }
        }

        Log::error('Archivo no encontrado', ['intentos' => $candidates]);
        abort(404, 'Archivo no encontrado en el servidor');
    }
    private function streamVideo(string $absolutePath, string $filename, string $mimeType)
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($absolutePath));

        flush();

        $fp = fopen($absolutePath, 'rb');
        while (!feof($fp)) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);
        exit;
    }


    public function uploadFile(Request $request, $id)
    {
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if (!$request->hasFile('files')) {
            return response()->json(['error' => 'No files uploaded'], 400);
        }

        $uploadedFiles = $request->file('files');
        $storedFiles = [];
        $fileUploadService = new FileUploadService();
        $directory = 'task/' . $id . '/document';

        foreach ($uploadedFiles as $file) {
            try {
                // Detectar tipo de archivo (video, imagen, PDF, etc.)
                $mimeType = $file->getClientMimeType();

                if (str_starts_with($mimeType, 'video/')) {
                    $properties = $fileUploadService->uploadVideo($file, $directory);
                } else {
                    $properties = $fileUploadService->uploadFile($file, $directory);
                }

                // Crear registro del archivo en la base de datos
                $newFile = $model->files()->create([
                    'name' => $properties['name'],
                    'type' => $properties['type'],
                    'path' => $properties['path'],
                    'size' => $properties['size'],
                    'preview' => 0,
                    'fileable_id' => $id,
                    'fileable_type' => 'App\Models\\Task',
                ]);

                $storedFiles[] = $newFile;

                // Agregar el archivo a la colección de archivos
                $model->files()->save($newFile);
            } catch (\Throwable $e) {
                Log::info('Error uploading file: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Error uploading file: ' . $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Files uploaded successfully',
            'files' => $storedFiles,
        ]);
    }

    public function removeFile(Request $request, $id)
    {
        $model = $this->data['model']::find($id);
        if (!$model) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        $fileId = $request->input('id_file');
        $file = $model->files()->find($fileId);
        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            // --- Normalizar ruta ---
            $path = $file->path;
            $relativePath = ltrim(str_replace(['/storage/', 'storage/'], '', parse_url($path, PHP_URL_PATH)), '/');

            // --- Intentar eliminar en disco "public" ---
            $disk = Storage::disk('public');
            if ($disk->exists($relativePath)) {
                $disk->delete($relativePath);
            } else {
                // --- Si no existe, probar en disco "local" ---
                $localDisk = Storage::disk('local');
                if ($localDisk->exists($relativePath)) {
                    $localDisk->delete($relativePath);
                }
            }

            // --- Eliminar registro sin disparar observers ---
            \App\Models\File::withoutEvents(function () use ($file) {
                $file->delete();
            });

            return response()->json(['message' => 'File removed successfully']);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error removing file',
                'message' => $e->getMessage(),
                'path' => $file->path,
            ], 500);
        }
    }
}

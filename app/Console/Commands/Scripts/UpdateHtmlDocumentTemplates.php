<?php

namespace App\Console\Commands\Scripts;

use App\Models\DocumentTemplate;
use Illuminate\Console\Command;

class UpdateHtmlDocumentTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-html-document-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia la direccion del enlace de imagenes de las plantillas a la url del servidor actual';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $publicPath = public_path();
        $documentTemplates = DocumentTemplate::all();
        foreach ($documentTemplates as $documentTemplate)  {
            $html = $documentTemplate->html;
            $htmlNew = str_replace('/home/MEGANET/public', $publicPath, $html);
            //$htmlNew = str_replace('${data.image_src}', $publicPath, $html);
            $documentTemplate->html = $htmlNew;
            $documentTemplate->save();
        }
        
        foreach ($documentTemplates as $documentTemplate)  {
            $html = $documentTemplate->html;
            $htmlNew = str_replace('${data.image_src}', $publicPath, $html);
            $documentTemplate->html = $htmlNew;
            $documentTemplate->save();
        }
    }
}

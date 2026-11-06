<?php

/*
 * Feature flag del ReleasePublisher atómico (item roadmap #9990674, F3 de la épica #9990668).
 *
 * OFF por default: el flujo actual (DeploymentService::run() ejecutando git_tag/git_push/
 * github_release como pasos independientes, sin compensación) sigue exactamente igual. Al
 * ponerlo en `true`, esos tres pasos se sustituyen por un solo acto atómico
 * (App\Services\Deploy\ReleasePublisherService): si falla a mitad de camino, deshace lo
 * alcanzado (borra el tag remoto, despublica el GitHub Release) en vez de dejar una versión
 * a medias. Apagarlo de nuevo regresa al comportamiento anterior sin desplegar nada.
 */
return [
    'emision_atomica' => (bool) env('RELEASES_EMISION_ATOMICA', false),
];

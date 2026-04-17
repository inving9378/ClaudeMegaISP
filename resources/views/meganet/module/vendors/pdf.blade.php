<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Meganet</title>
    <link rel="stylesheet" href="{{ public_path('css/pdf.css') }}" type="text/css">
</head>
<body>
    <div class="container">
        <div 
            class="card-front" 
            style="background-image: url({{ $front_image_name && $front_image_name->name ? public_path('credencial') . '/' . $front_image_name->name : public_path('images/gafete-front.png') }})"
        >
            <div class="card-body">
                <img 
                    class="image-logo" 
                    src="{{ $logo_image_name && $logo_image_name->name ? public_path('credencial') . '/' . $logo_image_name->name : public_path('images/logo_meganet.jpg') }}" 
                    alt="Logo meganet"
                />
                <div class="container-perfil">
                    <img src="{{ 
                        $seller->photography ? 
                        public_path('perfiles') . '/' . $seller->photography : 
                        public_path('images/perfil.png') }}" 
                        class="image-perfil" 
                        alt="Foto del vendedor"
                    />
                </div>
                <div class="content-text">
                    <h3 class="name-credential">
                        {{ $seller->name }} {{ $seller->father_last_name }} {{ $seller->mother_last_name }}
                    </h3>
                    <h3 class="title-user">Vendedor</h3>
                    <p class="credential-text">
                        Teléfono: {{ $seller->phone}}
                    </p>
                    <p class="credential-text">
                        Correo electrónico: {{ $seller->email }}
                    </p>
                    <p class="credential-text">RFC: {{ $seller->rfc }}</p>
                </div>
            </div>
        </div>

        <div 
            class="card-front" 
            style="background-image: url({{ $back_image_name && $back_image_name->name ? public_path('credencial') . '/' . $back_image_name->name : public_path('images/gafete-back.png') }})"
        >
            <div class="card-body text-black">
                <img 
                    class="image-logo" 
                    src="{{ $logo_image_name && $logo_image_name->name ? public_path('credencial') . '/' . $logo_image_name->name : public_path('images/logo_meganet.jpg') }}" 
                    alt="Logo meganet"
                />
                <div class="content-text">
                    <h3>Dirección de la empresa:</h3>
                    <p class="text-back">
                        Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc. Ex Hacienda Santa Ines Nextlalpan Edo de Mexico, CP 55796.
                    </p>

                    <p class="signature">________________________</p>
                    <h3>Firma</h3>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
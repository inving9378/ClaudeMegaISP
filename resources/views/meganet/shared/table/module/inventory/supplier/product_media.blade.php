<td>
    @php
        $hasImage = isset($url_image) && $url_image;
    @endphp
    @if ($hasImage)
        <div style="max-width: 50px" class="image-zoom-container" id-item="{{ $id }}">
            <img src="{{ asset('storage/' . $url_image) }}" alt="{{ 'inventory_item' . $id }}"
                class="img-fluid thumbnail-image inventory_item_image"
                data-full-image="{{ asset('storage/' . $url_image) }}"
                style="cursor: zoom-in;">
            <div class="image-zoom-preview">
                <img src="{{ asset('storage/' . $url_image) }}" alt="">
            </div>
        </div>
    @else
        <div style="max-width: 50px" id-item="{{ $id }}">
            <img src="{{ asset('images/icono_add_rojo.png') }}" alt="{{ 'inventory_item' . $id }}"
                class="img-fluid inventory_item_image">
        </div>
    @endif
</td>

<style>
    .thumbnail-image {
        transition: transform 0.2s;
    }
    .thumbnail-image:hover {
        transform: scale(1.2);
    }
    .image-zoom-container {
        position: relative;
    }
    .image-zoom-preview {
        display: none;
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        background: #fff;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .image-zoom-container:hover .image-zoom-preview {
        display: block;
    }
    .image-zoom-preview img {
        max-width: 250px;
        max-height: 250px;
        object-fit: contain;
    }
</style>
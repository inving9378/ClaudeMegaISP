<td>
    @if (isset($url_image))
        <div style="max-width: 50px" id-item="{{ $id }}">
            <img src="{{ asset('storage/' . $url_image) }}" alt="{{ 'inventory_item' . $id }}"
                class="img-fluid thumbnail-image inventory_item_image"
                data-zoom-src="{{ asset('storage/' . $url_image) }}" style="cursor: zoom-in;">
        </div>
    @else
        <div style="max-width: 50px" id-item="{{ $id }}">
            <img src="{{ asset('images/icono_add_rojo.png') }}" alt="{{ 'inventory_item' . $id }}"
                class="img-fluid inventory_item_image" data-zoom-src="{{ asset('storage/' . $url_image) }}">
        </div>
    @endif



</td>

<style>
    .thumbnail-image {
        transition: transform 0.2s;
    }

    .thumbnail-image:hover {
        transform: scale(2);
        position: absolute;
        z-index: 99999;
        top: 0;
    }
</style>

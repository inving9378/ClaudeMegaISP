<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $form->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 20px; }
        .form-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,.08); padding: 32px; width: 100%; max-width: 480px; }
        .form-title { font-size: 1.4rem; font-weight: 600; color: #1a1a1a; margin-bottom: 8px; }
        .form-desc { color: #666; font-size: .9rem; margin-bottom: 24px; }
        .field-group { margin-bottom: 18px; }
        label { display: block; font-size: .85rem; font-weight: 500; color: #333; margin-bottom: 6px; }
        label .req { color: #e53e3e; margin-left: 2px; }
        input, select, textarea { width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: .95rem; transition: border .2s; outline: none; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary, #1976D2); }
        textarea { resize: vertical; min-height: 80px; }
        .btn-submit { display: block; width: 100%; padding: 14px; background: var(--btn-color, #1976D2); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: opacity .2s; margin-top: 8px; }
        .btn-submit:hover { opacity: .9; }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; }
        .thank-you { text-align: center; padding: 40px 20px; }
        .thank-you h2 { font-size: 1.5rem; color: #2d6a2d; margin-bottom: 12px; }
        .error-msg { color: #e53e3e; font-size: .8rem; margin-top: 4px; }
        .global-error { background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 12px; margin-bottom: 16px; color: #c53030; font-size: .9rem; }
    </style>
    <script>
        var __formSlug = "{{ $form->slug }}";
        var __submitUrl = "{{ url('/public/marketing/lead-form/' . $form->slug . '/submit') }}";
        var __styling = @json($form->styling ?? []);
        if (__styling.primary_color) document.documentElement.style.setProperty('--primary', __styling.primary_color);
        if (__styling.button_color) document.documentElement.style.setProperty('--btn-color', __styling.button_color);
    </script>
</head>
<body>
<div class="form-card" id="form-container">
    <div class="form-title">{{ $form->name }}</div>
    @if($form->description)
        <div class="form-desc">{{ $form->description }}</div>
    @endif

    <form id="lead-form" novalidate>
        @csrf
        <div id="global-error" class="global-error" style="display:none;"></div>

        @foreach($form->fields_schema as $field)
            <div class="field-group">
                <label for="field_{{ $field['key'] }}">
                    {{ $field['label'] }}
                    @if($field['required'] ?? false)<span class="req">*</span>@endif
                </label>

                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea id="field_{{ $field['key'] }}" name="{{ $field['key'] }}"
                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                        @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset
                    ></textarea>
                @elseif(($field['type'] ?? 'text') === 'select')
                    <select id="field_{{ $field['key'] }}" name="{{ $field['key'] }}"
                        {{ ($field['required'] ?? false) ? 'required' : '' }}>
                        <option value="">Selecciona...</option>
                        @foreach($field['options'] ?? [] as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="{{ $field['type'] ?? 'text' }}"
                        id="field_{{ $field['key'] }}" name="{{ $field['key'] }}"
                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                        @isset($field['min']) minlength="{{ $field['min'] }}" @endisset
                        @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset
                    >
                @endif
                <div class="error-msg" id="err_{{ $field['key'] }}" style="display:none;"></div>
            </div>
        @endforeach

        <button type="submit" class="btn-submit" id="btn-submit">
            {{ $form->styling['button_text'] ?? 'Enviar' }}
        </button>
    </form>

    <div id="thank-you" class="thank-you" style="display:none;">
        <h2>✅ ¡Gracias!</h2>
        <p id="thank-you-msg">{{ $form->thank_you_message ?? 'Nos pondremos en contacto pronto.' }}</p>
    </div>
</div>

<script>
document.getElementById('lead-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    document.getElementById('global-error').style.display = 'none';

    // Clear previous errors
    document.querySelectorAll('.error-msg').forEach(function(el) {
        el.style.display = 'none'; el.textContent = '';
    });

    var formData = {};
    new FormData(this).forEach(function(v, k) { if (k !== '_token') formData[k] = v; });

    try {
        var resp = await fetch(__submitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        var data = await resp.json();

        if (data.success) {
            document.getElementById('lead-form').style.display = 'none';
            document.getElementById('thank-you').style.display = 'block';
            if (data.thank_you_message) document.getElementById('thank-you-msg').textContent = data.thank_you_message;
            if (data.redirect_url) setTimeout(function() { window.location.href = data.redirect_url; }, 2000);
            // Notify parent iframe
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'megaisp-lead-captured', slug: __formSlug }, '*');
            }
        } else {
            if (data.errors) {
                Object.entries(data.errors).forEach(function(entry) {
                    var el = document.getElementById('err_' + entry[0]);
                    if (el) { el.textContent = entry[1]; el.style.display = 'block'; }
                    else {
                        var ge = document.getElementById('global-error');
                        ge.textContent = entry[1]; ge.style.display = 'block';
                    }
                });
            }
            btn.disabled = false; btn.textContent = '{{ $form->styling["button_text"] ?? "Enviar" }}';
        }
    } catch(err) {
        var ge = document.getElementById('global-error');
        ge.textContent = 'Error al enviar. Por favor intenta de nuevo.'; ge.style.display = 'block';
        btn.disabled = false; btn.textContent = '{{ $form->styling["button_text"] ?? "Enviar" }}';
    }
});

// Auto-resize for iframe embedding
function notifyResize() {
    if (window.parent !== window) {
        window.parent.postMessage({
            type: 'megaisp-resize',
            slug: __formSlug,
            height: document.body.scrollHeight
        }, '*');
    }
}
window.addEventListener('load', notifyResize);
new MutationObserver(notifyResize).observe(document.body, { childList: true, subtree: true });
</script>
</body>
</html>

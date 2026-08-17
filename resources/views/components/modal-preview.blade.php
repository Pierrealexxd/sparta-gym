@props([])

{{-- Modal de previsualización de secciones de la landing. Se incluye una
     sola vez en layouts/panel.blade.php. Cualquier botón "Previsualizar"
     abre este modal despachando un CustomEvent:

       window.dispatchEvent(new CustomEvent('abrir-preview', {
           detail: { seccion: 'preguntas' }
       }))

     El componente renderiza un iframe que apunta a la ruta admin.preview,
     que devuelve la misma Blade view + CSS que la página pública. --}}
<div class="modal__fondo preview-modal"
     x-data="{
         abierto: false,
         seccion: '',
         cargando: true,
         abrir(nombre) {
             this.seccion = nombre;
             this.cargando = true;
             this.abierto = true;
         },
         cerrar() {
             this.abierto = false;
             this.seccion = '';
         }
     }"
     x-show="abierto" x-cloak
     @abrir-preview.window="abrir($event.detail.seccion)"
     @keydown.escape.window="cerrar()">
    <div class="preview-modal__caja" @click.outside="cerrar()">
        <header class="preview-modal__cabecera">
            <h3 class="preview-modal__titulo" x-text="seccion ? 'Previsualizar: ' + seccion.charAt(0).toUpperCase() + seccion.slice(1) : ''"></h3>
            <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar">
                <x-icono nombre="cerrar" />
            </button>
        </header>
        <div class="preview-modal__cuerpo">
            <div class="preview-modal__cargando" x-show="cargando" x-cloak>
                <span class="preview-modal__spinner"></span>
                Cargando previsualización…
            </div>
            <iframe class="preview-modal__iframe"
                    :src="seccion ? '{{ route('admin.preview', '__SECCION__') }}'.replace('__SECCION__', seccion) : ''"
                    x-show="seccion"
                    @load="cargando = false"
                    loading="lazy"></iframe>
        </div>
    </div>
</div>

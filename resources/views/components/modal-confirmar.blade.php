@props([])

{{-- Confirmación única por página, alimentada por el store Alpine 'confirmar'.
     Cualquier acción destructiva del panel la abre con:
     $store.confirmar.abrir({ accion, metodo, titulo, mensaje, etiqueta, ids }) --}}
<div class="modal__fondo" x-data x-show="$store.confirmar.abierto" x-cloak
     @keydown.escape.window="$store.confirmar.cerrar()">
    <div class="tarjeta modal__caja modal-confirmar" @click.outside="$store.confirmar.cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)" x-text="$store.confirmar.titulo">Confirmar acción</h3>
            <button class="modal__cerrar" type="button" @click="$store.confirmar.cerrar()" aria-label="Cerrar">
                <x-icono nombre="cerrar" />
            </button>
        </div>

        <p class="modal-confirmar__mensaje" x-text="$store.confirmar.mensaje"></p>

        <form method="POST" :action="$store.confirmar.accion" @submit="$store.confirmar.cerrar()">
            @csrf
            <input type="hidden" name="_method" :value="$store.confirmar.metodo">
            <template x-for="id in $store.confirmar.ids" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>

            <div class="modal-confirmar__acciones">
                <button class="btn btn--vidrio" type="button" @click="$store.confirmar.cerrar()">Cancelar</button>
                <button class="btn btn--fuego" type="submit" x-text="$store.confirmar.etiqueta">Confirmar</button>
            </div>
        </form>
    </div>
</div>

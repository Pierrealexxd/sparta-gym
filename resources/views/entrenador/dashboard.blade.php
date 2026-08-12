@extends('layouts.panel')

@section('titulo', 'Resumen')

@section('contenido')
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="usuarios" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['clientesActivos'] }}">0</span></b>
            <span class="kpi__etiqueta">Clientes a tu cargo</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="lista" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['rutinasMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Rutinas creadas este mes</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="agregar" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['inscripcionesMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Inscripciones este mes</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="caja" /></span>
            <b class="kpi__valor">S/ <span data-contador="{{ $kpis['ventasMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Vendido este mes</span>
        </article>
    </div>
@endsection

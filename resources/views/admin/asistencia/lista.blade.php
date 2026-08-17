@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Marcaciones laborales de los entrenadores — por QR, con ubicación')

{{-- Página de la vista Lista como ruta directa (admin.asistencia.lista):
     para compartir un enlace ya filtrado, o entrar sin pasar por el
     calendario. El toggle Lista/Calendario del día a día vive en
     admin.asistencia.calendario (<x-alterna-vista> con ambos slots ya
     cargados) — acá no se repite el calendario porque esta ruta no calcula
     $porDia; el enlace "Ver calendario" de abajo lleva a la vista completa. --}}
@section('contenido')
    @include('admin.asistencia._pestanas')

    <form class="panel__toolbar" method="GET">
        <select class="campo__control" name="entrenador" style="max-width:220px">
            <option value="">Todos los entrenadores</option>
            @foreach ($entrenadores as $e)
                <option value="{{ $e->id }}" @selected($entrenador === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <select class="campo__control" name="metodo" style="max-width:160px">
            <option value="">Todos los métodos</option>
            <option value="manual" @selected($metodo === 'manual')>Manual</option>
            <option value="qr" @selected($metodo === 'qr')>QR</option>
        </select>
        <button class="btn btn--vidrio" type="submit">Filtrar</button>
        <a class="btn btn--vidrio" href="{{ route('admin.asistencia.calendario') }}">
            <x-icono nombre="panel" /> Ver calendario
        </a>
    </form>

    @include('admin.asistencia._lista')

    @include('admin.asistencia._detalle-marcacion')
@endsection

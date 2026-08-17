@extends('layouts.public')

@section('contenido')
    @include('landing.sections.hero')
    @include('landing.sections.historia')
    @include('landing.sections.beneficios')
    @include('landing.sections.ejercicios')
    @include('landing.sections.programas')
    @include('landing.sections.guias')
    @include('landing.sections.planes')
    @include('landing.sections.testimonios')
    @include('landing.sections.preguntas')
    @include('landing.sections.contacto')
@endsection

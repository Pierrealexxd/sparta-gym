<section class="seccion" id="preguntas">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Preguntas</span>
            <h2>Lo que suelen<br>preguntarnos</h2>
        </div>

        <div class="faq" data-revelar>
            @foreach ($faqs as $faq)
                <div class="faq__item" data-faq>
                    <button class="faq__pregunta" type="button"
                            aria-expanded="false" aria-controls="faq-{{ $faq->id }}">
                        <span>{{ $faq->question }}</span>
                        <span class="faq__signo" aria-hidden="true"></span>
                    </button>
                    <div class="faq__respuesta" id="faq-{{ $faq->id }}">
                        <p>{{ $faq->answer }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

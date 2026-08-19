<section class="seccion" id="historia">
    <div class="contenedor historia">
        <div class="historia__texto" data-revelar>
            <span class="eyebrow">La historia</span>
            <h2>Empezamos con<br><span class="fuego">7 máquinas</span><br>y mucha actitud</h2>
            <p>
                El 4 de diciembre de 2017 abrimos con 7 máquinas, mucha actitud
                y unas enormes ganas de ayudar a la gente a cambiar sus vidas.
                Desde el primer día entendimos que un gimnasio no se trata
                solamente de pesas y máquinas, sino de personas, disciplina,
                esfuerzo y metas.
            </p>
            <p>
                Hoy somos {{ $cifras['clientes'] }} clientes y {{ $cifras['entrenadores'] }} entrenadores.
                Desde 7 máquinas hasta una gran familia. Seguimos con la misma esencia.
            </p>
        </div>

        {{-- Aquí la numeración sí informa: es una línea temporal real. --}}
        <div class="historia__hitos" data-revelar data-revelar-grupo>
            <article class="historia__hito">
                <b>2017</b>
                <div>
                    <h4>Nacimos</h4>
                    <p>4 de diciembre. 7 máquinas, mucha actitud y un sueño por crecer.</p>
                </div>
            </article>
            <article class="historia__hito">
                <b>2019</b>
                <div>
                    <h4>Crecimos</h4>
                    <p>Más espacio, más máquinas y los primeros entrenadores a tiempo completo.</p>
                </div>
            </article>
            <article class="historia__hito">
                <b>2023</b>
                <div>
                    <h4>Seguimiento real</h4>
                    <p>Cada socio con su rutina, sus medidas y su progreso registrado. Se acabó entrenar a ciegas.</p>
                </div>
            </article>
            <article class="historia__hito">
                <b>Hoy</b>
                <div>
                    <h4>La misma esencia</h4>
                    <p>Disciplina, constancia y actitud. Si llevas dos semanas sin venir, alguien te escribe.</p>
                </div>
            </article>
        </div>
    </div>
</section>

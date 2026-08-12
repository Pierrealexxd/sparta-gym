<section class="seccion" id="historia">
    <div class="contenedor historia">
        <div class="historia__texto" data-revelar>
            <span class="eyebrow">La historia</span>
            <h2>Empezamos con<br><span class="fuego">doce barras</span><br>y un local vacío</h2>
            <p>
                En 2019 abrimos con lo mínimo: hierro, espejos y la idea de que
                un gimnasio se mide por cuánta gente vuelve, no por cuánta se apunta.
            </p>
            <p>
                Hoy somos {{ $cifras['clientes'] }} clientes y {{ $cifras['entrenadores'] }} entrenadores.
                Seguimos midiendo lo mismo.
            </p>
        </div>

        {{-- Aquí la numeración sí informa: es una línea temporal real. --}}
        <div class="historia__hitos" data-revelar data-revelar-grupo>
            <article class="historia__hito">
                <b>2019</b>
                <div>
                    <h4>Abrimos</h4>
                    <p>Un local de 200 m² en {{ $gym->city }}. Doce barras, cuatro racks y ninguna máquina.</p>
                </div>
            </article>
            <article class="historia__hito">
                <b>2021</b>
                <div>
                    <h4>Doblamos el espacio</h4>
                    <p>Sala de máquinas, zona funcional y los primeros entrenadores a tiempo completo.</p>
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
                    <h4>La misma regla</h4>
                    <p>Si llevas dos semanas sin venir, alguien te escribe. No es marketing: es el trato.</p>
                </div>
            </article>
        </div>
    </div>
</section>

(function () {
    document.querySelectorAll('form[data-live-search]').forEach(function (form) {
        var grupo = form.dataset.liveSearch;
        var tablaSelector = 'table[data-live-search-results="' + grupo + '"]';
        var estado = document.createElement('span');
        estado.className = 'form-text text-danger d-block';
        estado.setAttribute('role', 'status');
        estado.hidden = true;
        form.insertAdjacentElement('afterend', estado);

        var temporizador;
        var solicitudActual = 0;

        function buscar() {
            var numeroSolicitud = ++solicitudActual;
            var parametros = new URLSearchParams(new FormData(form));
            var destino = new URL(window.location.href);
            destino.search = parametros.toString();

            estado.hidden = false;
            estado.className = 'form-text text-muted d-block';
            estado.textContent = 'Buscando registros…';
            fetch(destino.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (respuesta) {
                if (!respuesta.ok) throw new Error('La búsqueda respondió con estado ' + respuesta.status);
                return respuesta.text();
            }).then(function (html) {
                if (numeroSolicitud !== solicitudActual) return;
                var documento = new DOMParser().parseFromString(html, 'text/html');
                var tablaNueva = documento.querySelector(tablaSelector);
                var tablaActual = document.querySelector(tablaSelector);
                var cuerpoNuevo = tablaNueva && tablaNueva.querySelector('tbody');
                var cuerpoActual = tablaActual && tablaActual.querySelector('tbody');
                if (!cuerpoNuevo || !cuerpoActual) throw new Error('No se encontró la tabla de resultados.');

                cuerpoActual.replaceWith(document.importNode(cuerpoNuevo, true));
                window.history.replaceState(window.history.state, '', destino.pathname + destino.search + destino.hash);
                estado.hidden = true;
                estado.textContent = '';
                document.dispatchEvent(new CustomEvent('ag:live-search-updated'));
            }).catch(function (error) {
                if (numeroSolicitud !== solicitudActual) return;
                console.error('Error en la búsqueda en vivo:', error);
                estado.hidden = false;
                estado.className = 'form-text text-danger d-block';
                estado.textContent = 'No se pudo actualizar la búsqueda. Presiona Enter para volver a intentar.';
            });
        }

        function programarBusqueda() {
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(buscar, 180);
        }

        var campoBusqueda = form.querySelector('[name="q"]');
        if (campoBusqueda) campoBusqueda.addEventListener('input', programarBusqueda);
        form.querySelectorAll('select, input[type="checkbox"]').forEach(function (control) {
            control.addEventListener('change', programarBusqueda);
        });
        form.addEventListener('submit', function (evento) {
            evento.preventDefault();
            window.clearTimeout(temporizador);
            buscar();
        });
    });
})();

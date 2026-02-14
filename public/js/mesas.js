document.addEventListener('alpine:init', () => {

    /* ================================
       STORE GLOBAL
    ================================== */
    Alpine.store('modalPdv', {
        open: false,
        mesaId: null,
        pedidoId: null,
        personas: 1,
        tenant: window.APP_TENANT ?? null,
    });

    /* ================================
       COMPONENTE MESAS
    ================================== */
    Alpine.data('mesasLogic', () => ({

        /* === ESTADO === */
        menuOpen: false,
        menuPos: { x: 0, y: 0 },
        activeTable: { id: null, orderId: null, status: 'free' },

        /* === AUXILIARES === */
        pressing: false,
        pressTimer: null,
        spinnerTimer: null,
        touchPos: { x: 0, y: 0 },
        ignoreNextClick: false,

        /* ================================= */
        init() {
            console.log('Mesas Logic Unificado Iniciado');

            // Cerrar menú al hacer scroll
            window.addEventListener('scroll', () => {
                this.menuOpen = false;
            });

            // Cerrar menú al hacer click fuera
            document.addEventListener('click', () => {
                this.menuOpen = false;
            });

            // Liberar bloqueo cuando se cierra menú
            this.$watch('menuOpen', (value) => {
                if (value === false) {
                    setTimeout(() => {
                        this.ignoreNextClick = false;
                    }, 100);
                }
            });
        },

        /* ================================= */
        openMenu(x, y, tableData) {

            this.menuOpen = false;

            // Evitar que el menú se salga de pantalla
            if (x + 180 > window.innerWidth) {
                x = window.innerWidth - 190;
            }

            if (y + 200 > window.innerHeight) {
                y = window.innerHeight - 210;
            }

            this.menuPos = { x, y };
            this.activeTable = tableData;
            this.ignoreNextClick = true;

            this.$nextTick(() => {
                this.menuOpen = true;
            });
        },

        /* ================================= */
        startPress(e, tableData) {

            if (e.touches && e.touches.length !== 1) return;

            const touch = e.touches ? e.touches[0] : e;

            this.touchPos = {
                x: touch.clientX,
                y: touch.clientY
            };

            // Spinner visual (250ms)
            this.spinnerTimer = setTimeout(() => {
                this.pressing = true;
            }, 250);

            // Acción real (750ms)
            this.pressTimer = setTimeout(() => {
                this.openMenu(this.touchPos.x, this.touchPos.y, tableData);
                this.pressing = false;

                if (navigator.vibrate) {
                    navigator.vibrate(50);
                }

            }, 750);
        },

        /* ================================= */
        cancelPress() {

            if (this.pressing) {
                this.ignoreNextClick = true;

                setTimeout(() => {
                    this.ignoreNextClick = false;
                }, 100);
            }

            clearTimeout(this.spinnerTimer);
            clearTimeout(this.pressTimer);

            this.pressing = false;
        },

        /* ================================= */
        handleCardClick(tableData) {

            if (this.menuOpen) return;
            if (this.ignoreNextClick) return;

            // Si está ocupada → ir a orden
            if (tableData.status !== 'free' && tableData.orderId) {

                // window.location = `/app/orden-mesa/${tableData.id}/${tableData.orderId}`;
                    Livewire.navigate(`/app/orden-mesa/${tableData.id}/${tableData.orderId}`);

                return;
            }

            // Si está libre → abrir modal
            this.$store.modalPdv.mesaId = tableData.id;
            this.$store.modalPdv.open = true;
        }

    }));
});

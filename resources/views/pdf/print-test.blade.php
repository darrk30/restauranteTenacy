<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Ticket...</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="bg-white p-8 rounded-lg shadow-md text-center">
        <h2 class="text-2xl font-bold mb-4">Enviando a la impresora...</h2>
        <p class="text-gray-600">Procesando el ticket de la venta #{{ $id_venta }}</p>

        <a href="/admin" class="mt-6 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            Volver al sistema
        </a>
    </div>

    <script>
        // Enviamos el mensaje al programa local de Electron
        fetch('http://localhost:3030/recibir-orden', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'imprimir_ticket',
                    id_venta: '1045',
                    total: '150.50'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log("¡Éxito! El programa local respondió:", data);
            })
            .catch(error => {
                console.error("Error: ¿Está abierto el programa de escritorio?", error);
                alert("Abre el programa de impresiones en tu PC.");
            });
    </script>
</body>

</html>

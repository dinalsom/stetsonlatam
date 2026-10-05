<?php
// pago_exitoso.php

// Recuperamos el ID del pedido desde la URL
$pedido_id = isset($_GET['pedido_id']) ? htmlspecialchars($_GET['pedido_id']) : 'desconocido';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>¡Pago Exitoso! | Stetson LATAM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    </head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
<?php include_once __DIR__ . '/gtm.php'; ?>
    <div class="text-center p-10 bg-white rounded-lg shadow-lg">
        <svg class="mx-auto mb-4 w-16 h-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">¡Gracias por tu compra!</h1>
        <p class="text-gray-600 mb-6">Tu pago ha sido procesado exitosamente.</p>
        <p class="text-gray-700">Tu número de pedido es: <strong class="text-indigo-600">#<?php echo $pedido_id; ?></strong></p>
        <p class="text-gray-500 text-sm mt-2">Pronto recibirás un correo de confirmación.</p>
        <a href="/" class="mt-8 inline-block bg-[#e68019] text-white font-bold py-2 px-4 rounded hover:bg-opacity-90">
            Volver a la Tienda
        </a>
    </div>
</body>
</html>
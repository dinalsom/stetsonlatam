<?php
// pago_pendiente.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Pendiente | Stetson LATAM</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
<?php include_once __DIR__ . '/gtm.php'; ?>
    <div class="text-center p-10 bg-white rounded-lg shadow-lg">
        <svg class="mx-auto mb-4 w-16 h-16 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tu pago está pendiente</h1>
        <p class="text-gray-600 mb-6">Estamos esperando la confirmación de tu pago. Recibirás una notificación cuando sea aprobado.</p>
        <a href="/" class="mt-8 inline-block bg-[#e68019] text-white font-bold py-2 px-4 rounded hover:bg-opacity-90">
            Volver a la Tienda
        </a>
    </div>
</body>
</html>
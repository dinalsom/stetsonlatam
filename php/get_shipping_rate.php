<?php
// php/get_shipping_rate.php
require_once 'conexion.php';
header('Content-Type: application/json');

require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/shipping_config.php';

$api_key        = CURRENCY_API_KEY;
$base_currency  = CURRENCY_BASE;
$target_currency = CURRENCY_TARGET;

$pais = trim($_GET['pais'] ?? PAIS_LOCAL);

// Envío internacional: tarifa plana por zona, ya expresada en dólares.
// No pasa por la tabla de departamentos ni por la conversión de moneda.
if (!es_envio_nacional($pais)) {
   // País fuera de los territorios habilitados: no se cotiza ni se vende.
   if (!pais_tiene_envio($pais)) {
      echo json_encode([
         'success' => false,
         'message' => 'Por ahora no realizamos envíos a ese país.'
      ]);
      exit;
   }

   echo json_encode([
      'success' => true,
      'price'   => tarifa_envio_internacional($pais),
      'label'   => 'Envío internacional a ' . $pais
   ]);
   exit;
}

// Normalizamos la entrada para mejorar las coincidencias (ej. "Bogota D.C." -> "Bogota")
$department = trim($_GET['departamento'] ?? '');
if (empty($department)) {
   echo json_encode(['success' => false, 'message' => 'Departamento no proporcionado.']);
   exit;
}

// Lógica para el caso especial "Resto del mundo"
if (strtolower($department) === 'resto del mundo') {
   echo json_encode(['success' => true, 'requires_quote' => true, 'message' => 'Se requiere cotización de envío.']);
   exit;
}

// 1. Obtenemos el precio en la moneda base (COP) de la base de datos
$stmt = $conn->prepare("SELECT price FROM shipping_rates WHERE departamento = ?");
$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();
$rate_cop = $result->fetch_assoc();
$stmt->close();

if ($rate_cop) {
   $price_in_cop = (float)$rate_cop['price'];

   // 2. Obtenemos la tasa de conversión
   $conversion_rate = get_conversion_rate($api_key, $base_currency, $target_currency);

   if ($conversion_rate !== null) {
      // 3. Calculamos el precio final en la moneda objetivo (USD)
      $price_in_usd = $price_in_cop * $conversion_rate;
      echo json_encode(['success' => true, 'price' => round($price_in_usd, 2)]);
   } else {
      // Si el API de conversión falla, podrías devolver un error
      echo json_encode(['success' => false, 'message' => 'No se pudo obtener la tasa de cambio.']);
   }
} else {
   echo json_encode(['success' => false, 'message' => 'No hay envíos disponibles para esta ubicación.']);
}

$conn->close();

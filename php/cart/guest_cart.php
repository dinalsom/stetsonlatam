<?php
// guest_cart.php
// Devuelve el detalle de un carrito de invitado (sin sesión iniciada).
//
// El carrito del invitado vive en el navegador (localStorage) y solo guarda
// identificadores: producto, color, talla y cantidad. Este endpoint los
// traduce a nombre, precio, imagen y stock leyendo la base de datos, para
// que el precio mostrado siempre sea el real y no uno guardado en el
// navegador que el visitante pudiera manipular.
//
// Solo lee. No escribe nada ni requiere autenticación.

require_once '../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

// Límite defensivo: un carrito legítimo no tiene cientos de líneas.
if (count($items) > 50) {
    $items = array_slice($items, 0, 50);
}

$carrito = [];

try {
    $stmt = $conn->prepare("
        SELECT
            p.id   AS producto_id,
            p.name,
            p.price,
            p.image,
            co.id  AS color_id,
            co.name AS color_name,
            co.hex,
            s.id   AS size_id,
            s.name AS size_name,
            pv.stock
        FROM productos p
        LEFT JOIN colors co ON co.id = ?
        LEFT JOIN sizes  s  ON s.id  = ?
        LEFT JOIN product_variants pv
               ON pv.product_id = p.id AND pv.color_id = ? AND pv.size_id = ?
        WHERE p.id = ?
    ");

    foreach ($items as $item) {
        $producto_id = isset($item['producto_id']) ? (int)$item['producto_id'] : 0;
        $color_id    = isset($item['color_id'])    ? (int)$item['color_id']    : 0;
        $size_id     = isset($item['size_id'])     ? (int)$item['size_id']     : 0;
        $quantity    = isset($item['quantity'])    ? (int)$item['quantity']    : 0;

        if ($producto_id <= 0 || $color_id <= 0 || $size_id <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt->bind_param("iiiii", $color_id, $size_id, $color_id, $size_id, $producto_id);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        // El producto ya no existe: se omite en lugar de romper el carrito.
        if (!$fila) {
            continue;
        }

        $stock = (int)$fila['stock'];
        if ($stock <= 0) {
            continue; // Variante agotada: no se muestra.
        }

        // Nunca devolvemos más unidades de las que hay en bodega.
        $fila['quantity'] = min($quantity, $stock);

        // Identificador sintético: el carrito de invitado no tiene filas en
        // la base de datos, así que la línea se identifica por su variante.
        $fila['cart_item_id'] = $producto_id . '-' . $color_id . '-' . $size_id;

        $carrito[] = $fila;
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'cart' => $carrito]);
} catch (Exception $e) {
    error_log('Error en guest_cart.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo cargar el carrito.']);
}

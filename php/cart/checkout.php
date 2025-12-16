<?php
// php/cart/checkout.php (VERSIÓN ENFOCADA EN MERCADO PAGO)

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Asegúrate que la ruta a autoload.php y conexion.php sea correcta
require '../conexion.php';
require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// (Aquí va tu función getAuthorizationHeader()... es la misma que ya tienes)
function getAuthorizationHeader()
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER["HTTP_AUTHORIZATION"]);
    }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
    }
    if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            return trim($requestHeaders['Authorization']);
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_started = false;
    try {
        // 2. CONFIGURA TUS CREDENCIALES DE MERCADO PAGO (SANDBOX PARA PRUEBAS)
        $access_token = "APP_USR-7493389823882807-112515-74cf048ad84435669297aeae24865865-12742422";

        // (Aquí va tu código para validar el JWT y obtener el user_id... es el mismo que ya tienes)
        $authHeader = getAuthorizationHeader();
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new Exception('Token no proporcionado.');
        }
        $jwt = trim(str_replace('Bearer', '', $authHeader));
        $secret_key = "StetsonLatam1977";
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        $user_id = $decoded->data->id;

        if (isset($_POST['save_address']) && $_POST['save_address'] === 'true') {
            $stmt_save_addr = $conn->prepare(
                "INSERT INTO user_addresses (user_id, street_address, city, state, postal_code, country) VALUES (?, ?, ?, ?, ?, ?)"
            );
            // Tomamos los datos del formulario POST
            $stmt_save_addr->bind_param(
                "isssss",
                $user_id,
                $_POST['direccion'],
                $_POST['ciudad'],
                $_POST['estado'],
                $_POST['zip'],
                $_POST['pais']
            );
            $stmt_save_addr->execute();
            $stmt_save_addr->close();
        }

        $conn->begin_transaction();
        $transaction_started = true;
        $stmt_cart = $conn->prepare("
            SELECT c.*, p.name AS nombre, p.price AS precio, col.name AS color_nombre, s.name AS size_nombre 
            FROM cart c 
            JOIN productos p ON c.producto_id = p.id 
            LEFT JOIN colors col ON c.color_id = col.id 
            LEFT JOIN sizes s ON c.size_id = s.id 
            WHERE c.users_id = ?
        ");
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $items_carrito = $stmt_cart->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_cart->close();
        if (count($items_carrito) === 0) throw new Exception("Tu carrito está vacío.");
        $cart_total = 0;
        foreach ($items_carrito as $item) {
            $cart_total += $item['precio'] * $item['quantity'];
        }

        // 2. Calcular el costo de envío (verificándolo en el backend por seguridad)
        $shipping_cost = 0;
        $department = $_POST['estado'] ?? ''; // 'estado' es el name de tu campo de departamento

        if (!empty($department)) {
            $stmt_rate = $conn->prepare("SELECT price FROM shipping_rates WHERE departamento = ?");
            $stmt_rate->bind_param("s", $department);
            $stmt_rate->execute();
            $rate_result = $stmt_rate->get_result()->fetch_assoc();
            if ($rate_result) {
                $shipping_cost = (float)$rate_result['price'];
            }
            $stmt_rate->close();
        }

        // 3. Calcular el total final
        $total_final = $cart_total + $shipping_cost;

        // 3. CREAMOS EL PEDIDO EN NUESTRA BASE DE DATOS con estado 'Pendiente de Pago'
        $metodo_pago = $_POST['metodo'] ?? '';
        $stmt_order = $conn->prepare("INSERT INTO pedidos (user_id, total, estado, nombre_cliente, email_cliente, pais, ciudad, direccion, telefono, metodo_pago) VALUES (?, ?, 'PendienteDePago', ?, ?, ?, ?, ?, ?, ?)");
        $stmt_order->bind_param("idsssssss", $user_id, $total_final, $_POST['nombre'], $_POST['email'], $_POST['pais'], $_POST['ciudad'], $_POST['direccion'], $_POST['telefono'], $metodo_pago);
        $stmt_order->execute();
        $pedido_id = $conn->insert_id;
        $stmt_order->close();
        $stmt_detail = $conn->prepare("
            INSERT INTO pedido_detalle 
            (pedido_id, producto_id, nombre_producto, precio, cantidad, color_id, color_nombre, size_id, size_nombre) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items_carrito as $item) {
            $stmt_detail->bind_param(
                "iisdiisis",
                $pedido_id,
                $item['producto_id'],
                $item['nombre'],
                $item['precio'],
                $item['quantity'],
                $item['color_id'],
                $item['color_nombre'],
                $item['size_id'],
                $item['size_nombre']
            );
            $stmt_detail->execute();
        }

        // 4. CREAMOS LA PREFERENCIA DE PAGO (MANUALMENTE)

        // 4.1. Construimos el array de items
        $mp_items = [];
        foreach ($items_carrito as $item) {
            $mp_items[] = [
                'title' => $item['nombre'],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['precio'],
                'currency_id' => 'USD'
            ];
        }

        // Añadimos el envío como un ítem más en el desglose de Mercado Pago
        if ($shipping_cost > 0) {
            $mp_items[] = [
                'title' => 'Costo de Envío',
                'quantity' => 1,
                'unit_price' => $shipping_cost,
                'currency_id' => 'COP'
            ];
        }


        // Dividimos el nombre completo en nombre y apellido para el payer
        $nombre_completo = explode(' ', $_POST['nombre'], 2);
        $first_name = $nombre_completo[0];
        $last_name = $nombre_completo[1] ?? '';
        // 4.2. Construimos el cuerpo completo de la petición (el "payload")
        $preference_data = [
            'payer' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $_POST['email'],
                'identification' => [
                    'type' => $_POST['docType'],
                    'number' => $_POST['docNumber']
                ]
            ],
            'items' => $mp_items,
            'back_urls' => [
                'success' => 'https://stetsonlatam.com/pago/exitoso/' . $pedido_id,
                'failure' => 'https://stetsonlatam.com/pago/fallido',
                'pending' => 'https://stetsonlatam.com/pago/pendiente'
            ],
            'auto_return' => 'approved',
            'notification_url' => 'https://stetsonlatam.com/php/webhook_mercado_pago',
            'external_reference' => (string)$pedido_id
        ];
        //Prueba

        // 4.3. Convertimos el array a formato JSON
        $preference_payload = json_encode($preference_data);

        $log_file_checkout = __DIR__ . '/checkout_log.txt';
        file_put_contents($log_file_checkout, "Payload a enviar a MP: \n" . $preference_payload . "\n\n", FILE_APPEND);

        // 4.4. Configuramos y ejecutamos la petición cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences', // URL del API
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',   // Método de la petición
            CURLOPT_POSTFIELDS => $preference_payload, // El JSON que enviamos
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token, // La autorización es clave
                'Content-Type: application/json'          // Indicamos que enviamos JSON
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception("Error en la comunicación con Mercado Pago: " . $err);
        }

        // 4.5. Procesamos la respuesta de Mercado Pago
        $preference_result = json_decode($response, true);

        if (!isset($preference_result['init_point'])) {
            // Si MP no nos dio un link de pago, algo salió mal. 
            $error_message = $preference_result['message'] ?? 'Respuesta inválida de Mercado Pago.';
            throw new Exception($error_message);
        }

        // Si todo salió bien, confirmamos la creación de nuestro pedido
        $conn->commit();
        $transaction_started = false;

        // 5. ENVIAMOS LA URL DE PAGO AL FRONTEND
        echo json_encode(['redirect_url' => $preference_result['init_point']]);
    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    } finally {
        if (isset($conn) && $conn->ping()) $conn->close();
    }
    exit;
}

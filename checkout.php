    <?php
    // checkout.php (CÓDIGO COMPLETO Y ADAPTADO A TU BASE DE DATOS)

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    require_once 'php/conexion.php';

    // --- OBTENER DATOS DEL USUARIO AL CARGAR LA PÁGINA ---
    $user_id = $_SESSION['user_id'] ?? null;
    $saved_addresses = [];
    $saved_payment_methods = [];
    $user_email = '';
    $user_name = '';

    if ($user_id) {
        // 1. Obtener datos básicos del usuario (nombre, email)
        $stmt_user = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        if ($stmt_user->execute()) {
            $result_user = $stmt_user->get_result()->fetch_assoc();
            if ($result_user) {
                $user_name = $result_user['name'];
                $user_email = $result_user['email'];
            }
        }
        $stmt_user->close();

        // 2. Leer las direcciones guardadas del usuario
        $stmt_addr = $conn->prepare("SELECT id, street_address, city, state, postal_code, country FROM user_addresses WHERE user_id = ?");
        $stmt_addr->bind_param("i", $user_id);
        $stmt_addr->execute();
        $result_addr = $stmt_addr->get_result();
        while ($row = $result_addr->fetch_assoc()) {
            $saved_addresses[] = $row; // Llenamos el array
        }
        $stmt_addr->close();

        // ADAPTADO: Leer las columnas correctas de tu tabla user_payment_methods
        $stmt_pay = $conn->prepare("SELECT id, card_type, last_four_digits, expiry_date, is_default FROM user_payment_methods WHERE user_id = ?");
        $stmt_pay->bind_param("i", $user_id);
        $stmt_pay->execute();
        $result_pay = $stmt_pay->get_result();
        while ($row = $result_pay->fetch_assoc()) {
            $saved_payment_methods[] = $row;
        }
        $stmt_pay->close();
    }

    $cart_subtotal = 0;
    if ($user_id) {
        $stmt_cart = $conn->prepare("SELECT SUM(p.price * c.quantity) as subtotal FROM cart c JOIN productos p ON c.producto_id = p.id WHERE c.users_id = ?");
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $result = $stmt_cart->get_result()->fetch_assoc();
        $cart_subtotal = $result['subtotal'] ?? 0;
        $stmt_cart->close();
    }

    $conn->close();
    ?>
    <html>

    <head>
        <title>Finalizar compra | Stetson LATAM</title>
        <link rel="icon" href="img/logo.webp" type="image/x-icon">
        <link href="css/index.css?v=<?php echo time(); ?>" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .checkout-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 40px;
                max-width: 1280px;
                margin: auto;
                padding: 20px;
            }

            .order-summary {
                background-color: #f7f7f7;
                padding: 2rem;
                border-radius: 8px;
                height: fit-content;
            }

            .summary-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 1rem;
            }

            .summary-total {
                display: flex;
                justify-content: space-between;
                font-weight: bold;
                font-size: 1.25rem;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid #ddd;
            }

            .form-input {
                width: 100%;
                border: 1px solid #ccc;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
            }

            .checkout-button {
                display: block;
                width: 100%;
                background-color: #3F1E1F;
                color: #f1eeea;
                text-align: center;
                padding: 1rem;
                border-radius: 5px;
                font-weight: bold;
                margin-top: 1.5rem;
                cursor: pointer;
            }
        </style>
    </head>


    <body>
        <script>
            // El checkout necesita cuenta: es donde se guardan el pedido, la
            // dirección y el pago. Un invitado que llegue aquí directamente
            // vuelve al carrito, donde se le ofrece crear la cuenta sin perder
            // lo que ya había añadido.
            if (!localStorage.getItem('jwt')) {
                window.location.replace('/cart');
            }
        </script>

        <?php include 'header.php'; ?>

        <main class="checkout-grid">

            <div>
                <div class="flex flex-wrap gap-2 p-4 border-b mb-6">
                    <a class="text-[#887563]" href="cart">Carrito</a>
                    <span>/</span>
                    <span>Finalizar compra</span>
                </div>

                <form id="checkout-form" method="POST" action="php/cart/checkout">
                    <h2 class="text-2xl font-bold mb-6">Información de Envío</h2>

                    <?php if (!empty($saved_addresses)): ?>
                        <div class="mb-4">
                            <label class="block mb-1">Elige una dirección guardada</label>
                            <select name="address_id" id="address-select" class="form-input">
                                <option value="new">-- Usar una nueva dirección --</option>
                                <?php foreach ($saved_addresses as $addr): ?>
                                    <option value='<?php echo json_encode($addr); ?>'><?php echo htmlspecialchars($addr['street_address'] . ', ' . $addr['city']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <input name="nombre" class="form-input" placeholder="Nombre completo" value="<?php echo htmlspecialchars($user_name); ?>" required>
                    <div class="flex gap-4">
                        <label class="flex flex-col" style="width: 33.33%;">
                            <p class="text-sm font-medium text-gray-700 mb-1">Tipo Documento</p>
                            <select name="docType" class="form-input" required>
                                <option value="CC">CC</option>
                                <option value="CE">CE</option>
                                <option value="NIT">NIT</option>
                                <option value="PAS">PAS</option>
                            </select>
                        </label>
                        <label class="flex flex-col flex-1">
                            <p class="text-sm font-medium text-gray-700 mb-1">Número de Documento</p>
                            <input name="docNumber" placeholder="Tu número de documento" class="form-input" required />
                        </label>
                    </div>
                    <input type="email" name="email" class="form-input" placeholder="Email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                    <input name="direccion" class="form-input" placeholder="Dirección" required>
                    <div class="grid grid-cols-2 gap-4">
                        <input name="ciudad" class="form-input" placeholder="Ciudad" required>
                        <input name="estado" class="form-input" placeholder="Departamento" required>
                    </div>
                    <input name="zip" class="form-input" placeholder="Código Postal" required>
                    <input name="pais" class="form-input" placeholder="País" value="Colombia" required>
                    <input name="telefono" class="form-input" placeholder="Teléfono" required>

                    <div class="flex max-w-[480px] items-center gap-4 px-4 py-3">
                        <input type="checkbox" id="save-address" name="save_address" value="true" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="save-address" class="block text-sm text-gray-900">Guardar esta dirección para futuras compras</label>
                    </div>

                    <div id="shipping-options-container" class="mt-6" style="display: none;">
                        <h3 class="text-lg font-bold mb-2">Método de Envío</h3>
                        <div id="shipping-options-list"></div>
                    </div>
                    <div id="shipping-error" class="text-red-500 font-medium mt-2"></div>

                    <hr class="my-8">
                    <h2 class="text-2xl font-bold mb-6">Método de Pago</h2>
                    <select name="metodo" class="form-input" required>
                        <option value="mercadopago">Mercado Pago</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                    </select>
                    <button type="submit" class="checkout-button">Continuar al Pago</button>
                </form>
            </div>

            <aside class="order-summary">
                <h2 class="text-xl">Resumen del pedido</h2>
                <div class="summary-row"><span>Subtotal</span><span id="summary-subtotal">$0.00</span></div>
                <div class="summary-row"><span>Envío</span><span id="summary-shipping">--</span></div>
                <div class="summary-total"><span>Total</span><span id="summary-total">$0.00</span></div>
            </aside>

        </main>

        <?php include 'footer.php'; ?>
        <?php include 'modal.php'; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // --- SELECCIÓN DE ELEMENTOS ---
                const form = document.getElementById('checkout-form');
                const addressSelect = document.getElementById('address-select');
                const departmentInput = document.querySelector('input[name="estado"]');

                const shippingCostEl = document.getElementById('summary-shipping');
                const totalEl = document.getElementById('summary-total');
                const subtotalEl = document.getElementById('summary-subtotal');

                const shippingContainer = document.getElementById('shipping-options-container');
                const shippingList = document.getElementById('shipping-options-list');
                const shippingError = document.getElementById('shipping-error');

                let currentShippingCost = 0;
                // Simulación del subtotal del carrito. En una implementación real,
                // este valor vendría de otra llamada a la API o se pasaría desde la página anterior.
                let cartSubtotal = <?php echo $cart_subtotal; ?>;

                // --- FUNCIONES AUXILIARES ---
                function formatCurrency(value) {
                    return `$${parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
                }

                function updateTotal() {
                    const newTotal = cartSubtotal + currentShippingCost;
                    subtotalEl.textContent = formatCurrency(cartSubtotal);
                    shippingCostEl.textContent = currentShippingCost > 0 ? formatCurrency(currentShippingCost) : '--';
                    totalEl.textContent = formatCurrency(newTotal);
                }

                // --- FUNCIÓN PRINCIPAL PARA OBTENER TARIFA DE ENVÍO ---
                async function getShippingRate() {
                    const department = departmentInput.value;

                    if (department.trim().length < 3) {
                        shippingContainer.style.display = 'none';
                        shippingError.textContent = '';
                        currentShippingCost = 0;
                        updateTotal();
                        return;
                    }

                    shippingContainer.style.display = 'block';
                    shippingList.innerHTML = 'Calculando...';
                    shippingError.textContent = '';

                    try {
                        const res = await fetch(`/php/shipping-rate?departamento=${encodeURIComponent(department)}`);
                        const data = await res.json();

                        if (data.success) {
                            if (data.requires_quote) {
                                shippingError.textContent = data.message;
                                shippingContainer.style.display = 'none';
                                currentShippingCost = 0;
                            } else {
                                currentShippingCost = parseFloat(data.price);
                                shippingList.innerHTML = `
                            <div class="flex items-center justify-between border p-3 rounded-lg bg-gray-50">
                                <div>
                                    <input type="radio" id="shipping_rate_std" name="shipping_option" value="${currentShippingCost}" checked class="h-4 w-4 text-indigo-600 border-gray-300">
                                    <label for="shipping_rate_std" class="ml-3 font-medium text-gray-700">Envío Estándar</label>
                                </div>
                                <span class="font-bold text-gray-900">${formatCurrency(currentShippingCost)}</span>
                            </div>`;
                            }
                        } else {
                            shippingError.textContent = data.message;
                            shippingContainer.style.display = 'none';
                            currentShippingCost = 0;
                        }
                    } catch (error) {
                        shippingError.textContent = 'Error al calcular el envío.';
                        shippingContainer.style.display = 'none';
                        currentShippingCost = 0;
                    }
                    updateTotal();
                }

                // --- ASIGNACIÓN DE EVENTOS ---

                // 1. Calcular envío cuando se llene el campo "Departamento"
                if (departmentInput) {
                    departmentInput.addEventListener('blur', getShippingRate);
                }

                // 2. Autocompletar formulario si se elige una dirección guardada
                if (addressSelect) {
                    addressSelect.addEventListener('change', function() {
                        // Limpia todos los campos de dirección antes de llenarlos
                        ['direccion', 'ciudad', 'estado', 'zip', 'pais'].forEach(name => {
                            const field = form.querySelector(`[name="${name}"]`);
                            if (field) field.value = '';
                        });

                        if (this.value !== 'new') {
                            const selectedAddr = JSON.parse(this.value);
                            form.direccion.value = selectedAddr.street_address || '';
                            form.ciudad.value = selectedAddr.city || '';
                            form.estado.value = selectedAddr.state || '';
                            form.zip.value = selectedAddr.postal_code || '';
                            form.pais.value = selectedAddr.country || '';

                            // Disparamos el evento 'blur' en el campo de departamento para que recalcule el envío
                            if (departmentInput) {
                                departmentInput.dispatchEvent(new Event('blur'));
                            }
                        }
                    });
                }

                // 3. Lógica de envío del formulario
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const jwt = localStorage.getItem('jwt');
                    if (!jwt) {
                        Swal.fire('Error', 'Debes iniciar sesión para completar la compra.', 'error');
                        return;
                    }

                    const formData = new FormData(form);

                    try {
                        // Ahora el 'await' es válido
                        const res = await fetch('/php/cart/checkout', {
                            method: 'POST',
                            headers: {
                                'Authorization': 'Bearer ' + jwt
                            },
                            body: formData
                        });

                        if (!res.ok) {
                            throw new Error(`Error del servidor: ${res.status}`);
                        }

                        const data = await res.json();

                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            Swal.fire('Error', data.message || 'Ocurrió un error desde el backend.', 'error');
                        }
                    } catch (err) {
                        console.error('Error en la petición FETCH:', err);
                        Swal.fire('Error', 'No se pudo procesar tu solicitud. Revisa la consola para más detalles.', 'error');
                    }
                });
                updateTotal();
            });
        </script>

    </body>

    </html>
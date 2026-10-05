// Carrito de Stetson LATAM
//
// Funciona en dos modos:
//   - Con sesión iniciada: el carrito vive en la base de datos (igual que antes).
//   - Sin sesión (invitado): el carrito vive en el navegador (localStorage).
//
// Cuando el invitado inicia sesión, su carrito se fusiona automáticamente con
// el de su cuenta y se borra del navegador. La cuenta sigue siendo necesaria
// para pagar, pero ya no para empezar a comprar.

const GUEST_CART_KEY = 'guest_cart';

// Variable para mantener el estado actual del carrito
let currentCartItems = [];

// ---------------------------------------------------------------------------
// Carrito de invitado (navegador)
// ---------------------------------------------------------------------------

function getGuestCart() {
  try {
    const raw = localStorage.getItem(GUEST_CART_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    // Si el contenido quedó corrupto, empezamos de cero en vez de romper la página.
    console.warn('Carrito de invitado ilegible, se reinicia:', error);
    return [];
  }
}

function saveGuestCart(items) {
  try {
    localStorage.setItem(GUEST_CART_KEY, JSON.stringify(items));
  } catch (error) {
    // Modo incógnito o almacenamiento lleno.
    console.warn('No se pudo guardar el carrito de invitado:', error);
  }
  updateCartBadge();
}

function guestKey(item) {
  return `${item.producto_id}-${item.color_id}-${item.size_id}`;
}

function guestCartCount() {
  return getGuestCart().reduce((total, item) => total + (parseInt(item.quantity) || 0), 0);
}

// Muestra el número de artículos sobre el ícono del carrito, si existe.
function updateCartBadge() {
  const badge = document.getElementById('cart-count');
  if (!badge) return;

  const count = localStorage.getItem('jwt') ? null : guestCartCount();
  if (count === null) return;

  badge.textContent = count > 0 ? count : '';
  badge.style.display = count > 0 ? 'inline-block' : 'none';
}

// ---------------------------------------------------------------------------
// Fusión del carrito de invitado al iniciar sesión
// ---------------------------------------------------------------------------

async function mergeGuestCart() {
  const jwt = localStorage.getItem('jwt');
  const guestItems = getGuestCart();

  if (!jwt || guestItems.length === 0) return false;

  for (const item of guestItems) {
    try {
      await fetch('/php/cart/add_to_cart', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + jwt,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          producto_id: item.producto_id,
          quantity: item.quantity,
          color_id: item.color_id,
          size_id: item.size_id
        })
      });
    } catch (error) {
      // Si una línea falla (por stock, por ejemplo), seguimos con las demás.
      console.error('No se pudo fusionar un artículo del carrito:', error);
    }
  }

  localStorage.removeItem(GUEST_CART_KEY);
  updateCartBadge();
  return true;
}

// ---------------------------------------------------------------------------
// Arranque
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', async () => {
  const jwt = localStorage.getItem('jwt');

  // Si el visitante acaba de iniciar sesión y traía carrito, lo pasamos a su cuenta.
  if (jwt) {
    await mergeGuestCart();
  }

  updateCartBadge();

  if (document.getElementById('cart-items-container')) {
    loadCart();
  }

  // Listener para el botón de proceder al pago
  const checkoutBtn = document.getElementById('checkout-btn');
  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', (e) => {
      e.preventDefault();

      if (currentCartItems.length === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'Carrito vacío',
          text: 'Debes añadir al menos un artículo para proceder al pago.'
        });
        return;
      }

      // El pago sí requiere cuenta: es donde se guardan el pedido y la dirección.
      if (!localStorage.getItem('jwt')) {
        Swal.fire({
          icon: 'info',
          title: 'Un último paso',
          text: 'Para finalizar tu compra necesitas una cuenta. Tu carrito se conserva.',
          showCancelButton: true,
          confirmButtonText: 'Crear cuenta o entrar',
          cancelButtonText: 'Seguir viendo',
          confirmButtonColor: '#3f1e1f',
          cancelButtonColor: '#6b7280'
        }).then((result) => {
          if (result.isConfirmed && typeof openAuthModal === 'function') {
            openAuthModal(true);
          }
        });
        return;
      }

      window.location.href = checkoutBtn.href;
    });
  }
});

// ---------------------------------------------------------------------------
// Añadir al carrito
// ---------------------------------------------------------------------------

function addToCart(productData) {
  const jwt = localStorage.getItem('jwt');

  if (!jwt) {
    addToGuestCart(productData);
    return;
  }

  fetch('/php/cart/add_to_cart', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + jwt,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      producto_id: productData.id,
      quantity: productData.quantity,
      color_id: productData.color,
      size_id: productData.size
    })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        notifyAdded();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
      }
    })
    .catch(() => {
      Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.' });
    });
}

function addToGuestCart(productData) {
  const items = getGuestCart();

  const nuevo = {
    producto_id: parseInt(productData.id),
    color_id: parseInt(productData.color),
    size_id: parseInt(productData.size),
    quantity: parseInt(productData.quantity) || 1
  };

  if (!nuevo.producto_id || !nuevo.color_id || !nuevo.size_id) {
    Swal.fire({ icon: 'warning', text: 'Seleccione color y talla.' });
    return;
  }

  const existente = items.find(item => guestKey(item) === guestKey(nuevo));
  if (existente) {
    existente.quantity += nuevo.quantity;
  } else {
    items.push(nuevo);
  }

  saveGuestCart(items);
  notifyAdded();

  if (document.getElementById('cart-items-container')) {
    loadCart();
  }
}

function notifyAdded() {
  Swal.fire({
    icon: 'success',
    title: '¡Añadido al carrito!',
    showConfirmButton: false,
    timer: 1500
  });
}

// ---------------------------------------------------------------------------
// Cargar y dibujar el carrito
// ---------------------------------------------------------------------------

async function loadCart() {
  const jwt = localStorage.getItem('jwt');
  const container = document.getElementById('cart-items-container');
  if (!container) return;

  try {
    let data;

    if (jwt) {
      const res = await fetch('/php/cart/get_cart', {
        method: 'GET',
        headers: { 'Authorization': 'Bearer ' + jwt }
      });
      data = await res.json();
    } else {
      const items = getGuestCart();
      if (items.length === 0) {
        renderCart([]);
        return;
      }
      const res = await fetch('/php/cart/guest_cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items })
      });
      data = await res.json();
    }

    if (data.success) {
      renderCart(data.cart);
    } else {
      container.innerHTML = `<p class="empty-cart">${data.message || 'No se pudo cargar el carrito.'}</p>`;
    }
  } catch (error) {
    console.error('Error al cargar el carrito:', error);
    container.innerHTML = `<p class="empty-cart">Error al cargar el carrito.</p>`;
  }
}

// Dibuja los artículos en el HTML
function renderCart(items) {
  const container = document.getElementById('cart-items-container');
  const summarySubtotal = document.getElementById('summary-subtotal');
  const summaryTotal = document.getElementById('summary-total');

  currentCartItems = items || [];

  container.innerHTML = '';
  let subtotal = 0;

  if (currentCartItems.length === 0) {
    container.innerHTML = '<p class="empty-cart">Tu carrito está vacío.</p>';
  } else {
    currentCartItems.forEach(item => {
      const itemTotal = item.price * item.quantity;
      subtotal += itemTotal;

      const itemElement = document.createElement('div');
      itemElement.className = 'cart-item';
      itemElement.innerHTML = `
                <img src="${item.image}" alt="${item.name}" class="item-image">
                <div class="item-details">
                    <h3>${item.name}</h3>
                    ${item.size_name ? `<p>Talla: ${item.size_name}</p>` : ''}
                    ${item.color_name ? `<p>Color: ${item.color_name}</p>` : ''}
                </div>
                <div class="item-quantity" data-stock="${item.stock}">
                    <button class="qty-btn" data-action="decrease" data-id="${item.cart_item_id}">-</button>
                    <input type="text" value="${item.quantity}" readonly>
                    <button class="qty-btn" data-action="increase" data-id="${item.cart_item_id}">+</button>
                </div>
                <div class="item-total">$${itemTotal.toFixed(2)}</div>
                <button class="item-remove" data-id="${item.cart_item_id}"><i class="fas fa-trash-alt"></i></button>
            `;
      container.appendChild(itemElement);
    });
  }

  summarySubtotal.textContent = `$${subtotal.toFixed(2)}`;
  summaryTotal.textContent = `$${subtotal.toFixed(2)}`;
}

// Envía datos (POST) a la API del carrito
async function postToCartAPI(endpoint, body) {
  const jwt = localStorage.getItem('jwt');
  if (!jwt) return { success: false, message: 'Not logged in' };

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + jwt },
      body: JSON.stringify(body)
    });

    if (res.status === 204 || !res.headers.get('content-length') || res.headers.get('content-length') === '0') {
      return { success: true };
    }

    const data = await res.json();
    return data;
  } catch (error) {
    console.error(`Error en postToCartAPI para ${endpoint}:`, error);
    return { success: false, message: 'Error de comunicación con el servidor.' };
  }
}

// Cambia la cantidad de una línea del carrito de invitado.
function setGuestQuantity(cartItemId, newQty) {
  const items = getGuestCart();
  const item = items.find(i => guestKey(i) === cartItemId);
  if (!item) return;

  item.quantity = newQty;
  saveGuestCart(items);
}

function removeFromGuestCart(cartItemId) {
  const items = getGuestCart().filter(i => guestKey(i) !== cartItemId);
  saveGuestCart(items);
}

// ---------------------------------------------------------------------------
// Botones de cantidad y de eliminar
// ---------------------------------------------------------------------------

document.getElementById('cart-items-container')?.addEventListener('click', async e => {
  const jwt = localStorage.getItem('jwt');
  const esInvitado = !jwt;

  const quantityButton = e.target.closest('.qty-btn');

  // --- BOTONES DE CANTIDAD (+ y -) ---
  if (quantityButton) {
    const cart_item_id = quantityButton.dataset.id;
    const action = quantityButton.dataset.action;
    const quantityContainer = quantityButton.parentElement;
    const input = quantityContainer.querySelector('input');
    const stock = parseInt(quantityContainer.dataset.stock);
    const currentQty = parseInt(input.value);
    let newQty;

    if (action === 'increase') {
      if (currentQty >= stock) {
        Swal.fire({ icon: 'warning', title: 'Stock máximo alcanzado', text: `Solo hay ${stock} unidades disponibles.` });
        return;
      }
      newQty = currentQty + 1;
    } else {
      newQty = currentQty - 1;
    }

    if (newQty < 1) {
      const itemElement = quantityButton.closest('.cart-item');
      const associatedRemoveButton = itemElement.querySelector('.item-remove');
      if (associatedRemoveButton) {
        associatedRemoveButton.click();
      }
      return;
    }

    input.value = newQty;

    if (esInvitado) {
      setGuestQuantity(cart_item_id, newQty);
      loadCart();
      return;
    }

    const result = await postToCartAPI('/php/cart/update_cart', { cart_item_id: cart_item_id, cantidad: newQty });
    if (result && result.success) {
      loadCart();
    } else {
      input.value = currentQty;
      Swal.fire('Error', (result && result.message) || 'No se pudo actualizar la cantidad.', 'error');
    }
  }

  // --- BOTÓN DE ELIMINAR ---
  if (e.target.closest('.item-remove')) {
    const cart_item_id = e.target.closest('.item-remove').dataset.id;

    Swal.fire({
      title: '¿Eliminar artículo?',
      text: '¿Estás seguro de que deseas eliminar este artículo de tu carrito?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3f1e1f',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Sí, eliminarlo'
    }).then(async (result) => {
      if (!result.isConfirmed) return;

      if (esInvitado) {
        removeFromGuestCart(cart_item_id);
      } else {
        await postToCartAPI('/php/cart/remove_from_cart', { cart_item_id: cart_item_id });
      }
      loadCart();
    });
  }
});

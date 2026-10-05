<?php
// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---
require 'php/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
  // Esta es la consulta que ya tienes
  $stmt = $conn->prepare("SELECT id, nombre, categoria_padre_id FROM categorias ORDER BY nombre");
  $stmt->execute();

  $result = $stmt->get_result();
  $categorias_flat = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) { // Cambiado a Exception general para MySQLi
  error_log("Error al consultar categorías: " . $e->getMessage());
  $categorias_flat = [];
}


// --- 3. CONSTRUIR EL ÁRBOL JERÁRQUICO COMPLETO ---
$categorias_tree = [];
$hat_collections = [];
$cap_collections = [];

if (!empty($categorias_flat)) {
  $categorias_by_id = [];
  foreach ($categorias_flat as $cat) {
    $categorias_by_id[$cat['id']] = $cat;
    $categorias_by_id[$cat['id']]['children'] = [];
  }

  foreach ($categorias_by_id as $id => &$cat) {
    if ($cat['categoria_padre_id'] !== null && isset($categorias_by_id[$cat['categoria_padre_id']])) {
      $categorias_by_id[$cat['categoria_padre_id']]['children'][] = &$cat;
    } else if ($cat['categoria_padre_id'] === null) {
      $categorias_tree[] = &$cat;
    }
  }
  unset($cat);

  // --- 4. SEPARAR COLECCIONES DE HATS Y CAPS ---
  foreach ($categorias_tree as $categoria_principal) {
    // Usamos strtolower y trim para una comparación robusta
    if (trim(strtolower($categoria_principal['nombre'])) === 'cachuchas') {
      // Si es la colección de cachuchas, la guardamos para el menú de Caps
      $cap_collections = $categoria_principal['children'];
    } else {
      // Todas las demás colecciones van al menú de Hats
      $hat_collections[] = $categoria_principal;
    }
  }
}
?>

<?php include_once __DIR__ . '/gtm.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<header
  class="flex items-center justify-center whitespace-nowrap border-b border-solid border-b-[#f3f2f2] px-10 py-5 h-20">
  <div class="flex-1 flex justify-start">
    <a href="https://stetsonlatam.com/" aria-label="Stetson Latam">
      <img src="img/logo.svg" alt="Logo de Stetson Latam" class="h-5 w-auto">
    </a>
  </div>

  <nav class="hidden md:flex justify-center desktop-nav">
    <div class="flex items-center gap-9 h-full">
      <div class="nav-item">
        <a href="hats" class="text-[#3c3737] text-sm font-bold uppercase leading-normal">Sombreros</a>
        <?php if (!empty($hat_collections)): ?>
          <div class="mega-menu">
            <div class="mega-menu-content">
              <?php foreach ($hat_collections as $collection): ?>
                <div class="mega-menu-column">
                  <a href="categoria<?php echo $collection['id']; ?>"
                    class="column-title"><?php echo htmlspecialchars($collection['nombre']); ?></a>
                  <?php if (!empty($collection['children'])): ?>
                    <ul>
                      <?php foreach ($collection['children'] as $subcat): ?>
                        <li><a
                            href="categoria<?php echo $subcat['id']; ?>"><?php echo htmlspecialchars($subcat['nombre']); ?></a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="nav-item">
        <a href="caps" class="text-[#3c3737] text-sm font-bold uppercase leading-normal">Cachuchas</a>
        <?php if (!empty($cap_collections)): ?>
          <div class="mega-menu">
            <div class="mega-menu-content">
              <?php foreach ($cap_collections as $collection): ?>
                <div class="mega-menu-column">
                  <a href="categoria<?php echo $collection['id']; ?>"
                    class="column-title"><?php echo htmlspecialchars($collection['nombre']); ?></a>
                  <?php if (!empty($collection['children'])): ?>
                    <ul>
                      <?php foreach ($collection['children'] as $subcat): ?>
                        <li><a
                            href="categoria<?php echo $subcat['id']; ?>"><?php echo htmlspecialchars($subcat['nombre']); ?></a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <div class="flex-1 flex justify-end items-center gap-4">
    <div class="hidden md:block desktop-search">
      <label class="relative flex items-center">
        <div class="absolute left-3 text-[#3c3737]">
          <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" fill="currentColor" viewBox="0 0 256 256">
            <path
              d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z">
            </path>
          </svg>
        </div>
        <input id="search-input" name="q" placeholder="Search..." autocomplete="off"
          class="w-full h-10 pl-10 pr-4 placeholder-[#3c3737] bg-[#f1eeea] rounded-lg border-2 border-transparent focus:border-transparent focus:ring-2 focus:ring-[#3f1e1f]" />
        <div id="search-results"
          class="absolute top-full mt-1 left-0 w-full bg-white border rounded-lg shadow-lg hidden z-50 max-h-80 overflow-y-auto">
        </div>
      </label>
    </div>
    <div class="flex gap-2">
      <button id="logout-btn" style="display:none;"
        class="flex max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 bg-[#f1eeea] text-[#3c3737] gap-2 text-sm font-bold leading-normal tracking-[0.015em] min-w-0 px-2.5">
        <div class="text-[#3c3737]" data-icon="SignOut" data-size="24px" data-weight="regular">
          <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" fill="currentColor" viewBox="0 0 256 256">
            <path
              d="M216,128a8,8,0,0,1-8,8H104v16a8,8,0,0,1-13.66,5.66l-32-32a8,8,0,0,1,0-11.32l32-32A8,8,0,0,1,104,104v16h104A8,8,0,0,1,216,128ZM128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Z">
            </path>
          </svg>
        </div>
      </button>
      <button id="user-btn" class="p-2 h-10 w-10 flex items-center justify-center bg-[#f1eeea] rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" fill="currentColor" viewBox="0 0 256 256">
          <path
            d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z">
          </path>
        </svg>
      </button>
      <button id="cart-btn" class="p-2 h-10 w-10 flex items-center justify-center bg-[#f1eeea] rounded-lg relative">
        <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" fill="currentColor" viewBox="0 0 256 256">
          <path
            d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,160H40V56H216V200ZM176,88a48,48,0,0,1-96,0,8,8,0,0,1,16,0,32,32,0,0,0,64,0,8,8,0,0,1,16,0Z">
          </path>
        </svg>
        <span id="cart-count"
          style="display:none; position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; padding:0 5px; border-radius:9px; background:#3f1e1f; color:#fff; font-size:11px; line-height:18px; font-weight:700; text-align:center;"></span>
      </button>
      <script>
        // Contador del carrito de invitado. Va aquí, y no en cart.js, porque
        // la cabecera se muestra en todas las páginas y cart.js solo se carga
        // en las de producto y carrito.
        (function() {
          try {
            if (localStorage.getItem('jwt')) return;
            var items = JSON.parse(localStorage.getItem('guest_cart') || '[]');
            if (!Array.isArray(items)) return;
            var total = items.reduce(function(suma, item) {
              return suma + (parseInt(item.quantity) || 0);
            }, 0);
            var badge = document.getElementById('cart-count');
            if (badge && total > 0) {
              badge.textContent = total;
              badge.style.display = 'inline-block';
            }
          } catch (e) {
            /* Sin almacenamiento disponible: el contador simplemente no se muestra. */
          }
        })();
      </script>
      <a id="wishlist-link" href="/wishlist" title="Mi Lista de Deseos" class="p-2 h-10 w-10 flex items-center justify-center bg-[#f1eeea] rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" fill="currentColor" viewBox="0 0 256 256">
          <path d="M128,216S28,160,28,92A52,52,0,0,1,80,40a52,52,0,0,1,48,48,52,52,0,0,1,48-48,52,52,0,0,1,52,52C228,160,128,216,128,216ZM80,56A36,36,0,0,0,44,92c0,42.59,62.26,90.46,84,103.41,21.74-12.95,84-60.82,84-103.41A36,36,0,0,0,176,56a35.82,35.82,0,0,0-30.33,18.49,8,8,0,0,1-13.34,0A35.82,35.82,0,0,0,80,56Z"></path>
        </svg>
      </a>
      <div id="notification-wrapper" class="notification-wrapper" style="display:none;">
        <button id="notifications-btn" class="p-2 h-10 w-10 flex items-center justify-center bg-[#f1eeea] rounded-lg">
          <i class="far fa-bell"></i>
          <span id="unread-count" class="unread-badge" style="display:none;"></span>
        </button>
        <div id="notifications-panel" style="display:none;">
        </div>
      </div>
      <div class="md:hidden mobile-menu">
        <button id="hamburger-btn" class="p-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 256 256">
            <path
              d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,88H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z">
            </path>
          </svg>
        </button>
      </div>
      <a href="https://stetsonlatam.com/">
        <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10"
          style='background-image: url("img/logo.webp");'></div>
      </a>
    </div>
  </div>
</header>

<div id="mobile-nav-panel" class="mobile-nav-panel">
  <div class="mobile-nav-header">
    <span class="font-bold">MENU</span>
    <button id="close-nav-btn" class="text-3xl font-bold">&times;</button>
  </div>
  <nav class="mobile-nav-links">
    <a href="hats">Hats</a>
    <a href="caps">Caps</a>
  </nav>
</div>

<style>
  .notification-wrapper {
    position: relative;
  }

  .unread-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: red;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  #notifications-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 300px;
    background: white;
    border: 1px solid #eee;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
  }
</style>

<script>
  const BASE_URL = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>";
</script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search-input');
    const resultsBox = document.getElementById('search-results');
    let controller = null;

    // Mostrar resultados al escribir
    input.addEventListener('input', function() {
      clearTimeout(window.searchTimer);
      window.searchTimer = setTimeout(() => doSearch(input.value), 300);
    });

    // Mostrar resultados al presionar Enter
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        doSearch(input.value);
        resultsBox.classList.remove('hidden');
      }
    });

    // Ocultar resultados si se hace clic fuera
    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !resultsBox.contains(e.target)) {
        resultsBox.classList.add('hidden');
      }
    });

    async function doSearch(q) {
      console.log('Buscando:', q); // Depuración
      if (controller) controller.abort();
      controller = new AbortController();
      if (q.trim() === '') {
        resultsBox.classList.add('hidden');
        return;
      }
      try {
        const res = await fetch(BASE_URL + '/search/' + encodeURIComponent(q), {
          signal: controller.signal
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if ((!data.productos || !data.productos.length) && (!data.categorias || !data.categorias.length)) {
          resultsBox.innerHTML = "<p class='p-2 text-gray-500'>No se han encontrado resultados.</p>";
        } else {
          let html = '';
          if (data.productos && data.productos.length) {
            html += "<h4 class='px-2 py-1 font-bold text-sm text-gray-600'>Productos</h4>";
            data.productos.forEach(p => {
              html += `<a href="${p.url}" class="flex items-center gap-2 p-2 hover:bg-gray-100"><img src="${p.image}" class="w-10 h-10 object-contain rounded"><span>${p.title}</span></a>`;
            });
          }
          if (data.categorias && data.categorias.length) {
            html += "<h4 class='px-2 py-1 font-bold text-sm text-gray-600'>Categorías</h4>";
            data.categorias.forEach(c => {
              html += `<a href="${c.url}" class="block p-2 hover:bg-gray-100"><span>${c.title}</span></a>`;
            });
          }
          resultsBox.innerHTML = html;
        }
        resultsBox.classList.remove('hidden');
      } catch (err) {
        if (err.name !== 'AbortError') {
          console.error(err);
          resultsBox.innerHTML = "<p class='p-2 text-red-500'>Error al realizar la búsqueda.</p>";
          resultsBox.classList.remove('hidden');
        }
      }
    }
  });
</script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
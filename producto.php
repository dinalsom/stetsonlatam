<?php
// producto.php (CÓDIGO FINAL ORGANIZADO)

require_once 'php/conexion.php';
session_start();

$product_id = $_GET['id'] ?? null;
if (!$product_id || !filter_var($product_id, FILTER_VALIDATE_INT)) {
  exit('Producto no válido');
}

try {
  // Detalles del producto
  $stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
  $stmt->bind_param("i", $product_id);
  $stmt->execute();
  $producto = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$producto) {
    exit('Producto no encontrado');
  }

  // Tallas disponibles
  $stmt_sizes = $conn->prepare("SELECT s.id, s.name FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ?");
  $stmt_sizes->bind_param("i", $product_id);
  $stmt_sizes->execute();
  $sizes = $stmt_sizes->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt_sizes->close();

  // Colores disponibles
  $stmt_colors = $conn->prepare("SELECT c.id, c.name, c.hex FROM product_colors pc JOIN colors c ON pc.color_id = c.id WHERE pc.product_id = ?");
  $stmt_colors->bind_param("i", $product_id);
  $stmt_colors->execute();
  $colors = $stmt_colors->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt_colors->close();

  // OBTENER VARIANTES DE STOCK
  $stmt_variants = $conn->prepare("SELECT color_id, size_id, stock FROM product_variants WHERE product_id = ?");
  $stmt_variants->bind_param("i", $product_id);
  $stmt_variants->execute();
  $variants_stock = $stmt_variants->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt_variants->close();

  $stmt_images = $conn->prepare("SELECT color_id, image_url FROM product_images WHERE product_id = ? ORDER BY id");
  $stmt_images->bind_param("i", $product_id);
  $stmt_images->execute();
  $images_result = $stmt_images->get_result();

  $images_by_color = [];
  while ($row = $images_result->fetch_assoc()) {
    // Usamos 'default' como clave para imágenes sin color específico (NULL)
    $key = $row['color_id'] ?? 'default';
    // Agrupamos las URLs de las imágenes por su clave (color_id o 'default')
    $images_by_color[$key][] = $row['image_url'];
  }
  $stmt_images->close();

  $user_id = $_SESSION['user_id'] ?? null;
  if ($user_id !== null) {
    $stmt_visit = $conn->prepare("INSERT INTO user_visits (user_id, product_id, visited_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE visited_at = NOW()");
    $stmt_visit->bind_param("ii", $user_id, $product_id);
  } else {
    // No se guarda visita para usuarios no logueados en DB, se maneja por JS
  }
  if (isset($stmt_visit)) {
    $stmt_visit->execute();
    $stmt_visit->close();
  }
} catch (Exception $e) {
  error_log("Error al cargar producto: " . $e->getMessage());
  exit('Error al cargar la página del producto.');
}

$page_title = htmlspecialchars($producto['name']) . ' | Stetson LATAM';
$meta_description = htmlspecialchars(substr(strip_tags($producto['description']), 0, 155)) . '...';
$canonical_url = "https://www.stetsonlatam.com/producto/" . $product_id;
$whatsapp_number = "573176437238";
$wholesale_whatsapp_text = rawurlencode(
  "Hola, quiero información de compra al por mayor (mínimo 8 unidades) del producto: " . $producto['name']
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo $page_title; ?></title>
  <link rel="icon" href="/img/logo.webp" type="image/x-icon">
  <link href="/css/index.css?v=<?php echo time(); ?>" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
  <link href="/css/producto.css?v=<?php echo time(); ?>" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/drift-zoom/dist/drift-basic.min.css">
  <script src="https://unpkg.com/drift-zoom/dist/Drift.min.js" defer></script>
  <meta name="description" content="<?php echo $meta_description; ?>">
  <link rel="canonical" href="<?php echo $canonical_url; ?>" />
  <meta property="og:title" content="<?php echo $page_title; ?>" />
  <meta property="og:description" content="<?php echo $meta_description; ?>" />
  <meta property="og:url" content="<?php echo $canonical_url; ?>" />
  <meta property="og:image" content="https://www.stetsonlatam.com/<?php echo htmlspecialchars($producto['image']); ?>" />
  <meta property="og:type" content="product" />
  <style>
    .wholesale-info-box {
      margin: 12px 0 18px;
      padding: 12px 14px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: #f8f9fa;
      border: 1px solid #ececec;
      border-left: 4px solid #3c3737;
      border-radius: 8px;
    }

    .wholesale-info-icon {
      font-size: 1.1rem;
      line-height: 1;
      color: #25D366;
      margin-top: 2px;
    }

    .wholesale-info-content {
      flex: 1;
    }

    .wholesale-info-title {
      margin: 0 0 4px 0;
      font-size: 0.95rem;
      font-weight: 700;
      color: #3c3737;
    }

    .wholesale-info-text {
      margin: 0;
      font-size: 0.9rem;
      line-height: 1.45;
      color: #555;
    }

    .wholesale-info-text strong {
      color: #3c3737;
    }

    .wholesale-info-link {
      color: #3c3737;
      font-weight: 600;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .wholesale-info-link:hover {
      opacity: 0.85;
    }

    @media (max-width: 768px) {
      .wholesale-info-box {
        padding: 10px 12px;
        gap: 8px;
      }

      .wholesale-info-title {
        font-size: 0.9rem;
      }

      .wholesale-info-text {
        font-size: 0.85rem;
      }
    }
    .reviews-section {
      max-width: 800px;
      margin: 40px auto;
      padding: 20px;
      border-top: 1px solid #eee
    }

    .review-item {
      border-bottom: 1px solid #eee;
      padding: 15px 0
    }

    .review-item:last-child {
      border-bottom: none
    }

    .admin-reply {
      background-color: #f8f9fa;
      border-left: 3px solid #3c3737;
      padding: 10px;
      margin-top: 10px;
      font-style: italic
    }

    #review-form {
      margin-top: 20px
    }

    #review-form textarea {
      width: 100%;
      min-height: 100px;
      margin: 10px 0;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 5px
    }

    #review-form button {
      padding: 10px 20px;
      cursor: pointer
    }

    .star-rating {
      display: flex;
      flex-direction: row-reverse;
      justify-content: flex-end
    }

    .star-rating input {
      display: none
    }

    .star-rating label {
      font-size: 2em;
      color: #ddd;
      cursor: pointer;
      transition: color .2s
    }

    .star-rating label:before {
      content: '★'
    }

    .star-rating input:checked~label,
    .star-rating label:hover,
    .star-rating label:hover~label {
      color: #f2b600
    }

    #ratings-summary {
      margin-bottom: 20px
    }

    #ratings-summary .bar-container {
      display: flex;
      align-items: center;
      margin-bottom: 5px;
      gap: 10px;
      font-size: .9em
    }

    #ratings-summary .bar-wrapper {
      flex-grow: 1;
      background-color: #e9ecef;
      border-radius: 5px
    }

    #ratings-summary .bar {
      background-color: #f2b600;
      height: 10px;
      border-radius: 5px
    }

    .recently-viewed-section {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .recently-viewed-section h2 {
      font-size: 1.5em;
      font-weight: bold;
      text-align: center;
      margin-bottom: 20px;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 20px;
    }

    .product-card {
      border: 1px solid #eee;
      text-align: center;
    }

    .product-card img {
      width: 100%;
      aspect-ratio: 1/1;
      object-fit: cover;
    }

    .product-card .info {
      padding: 10px;
    }

    /* --- ESTILOS PARA LA NUEVA GALERÍA AVANZADA --- */
    .product-gallery-advanced {
      display: flex;
      gap: 15px;
      /* Espacio entre miniaturas e imagen principal */
    }

    .thumbnail-container-vertical {
      display: flex;
      flex-direction: column;
      /* Apila las imágenes verticalmente */
      gap: 10px;
      width: 80px;
      /* Ancho de la columna de miniaturas */
      flex-shrink: 0;
      max-height: 500px;
      /* Altura máxima, ajusta según tu diseño */
      overflow-y: auto;
      /* Scroll si hay muchas imágenes */
    }

    .thumbnail-image {
      width: 100%;
      height: auto;
      aspect-ratio: 1 / 1;
      object-fit: cover;
      border: 2px solid transparent;
      border-radius: 5px;
      cursor: pointer;
      transition: border-color 0.2s;
    }

    .thumbnail-image:hover {
      border-color: #ccc;
    }

    .thumbnail-image.active {
      border-color: #3c3737;
      /* Borde para la miniatura activa */
    }

    .main-image-zoom-container {
      flex-grow: 1;
      /* Ocupa el resto del espacio */
      position: relative;
      /* Necesario para la librería de zoom */
    }

    #main-product-image {
      width: 100%;
      height: auto;
      aspect-ratio: 1 / 1;
      object-fit: cover;
      border-radius: 5px;
    }

    /* Estilos para la ventana de zoom (puedes personalizarlos) */
    .drift-zoom-pane {
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
    }
  </style>
</head>

<body>
  <div class="page-wrapper">
    <div class="content-container">
      <?php include 'header.php'; ?>

      <main class="product-main">
        <div class="product-container">
          <div class="product-gallery-advanced">
            <div class="thumbnail-container-vertical" id="thumbnail-container">
            </div>

            <div class="main-image-zoom-container">
              <img id="main-product-image" src=""
                data-zoom="" alt="<?php echo htmlspecialchars($producto['name']); ?>">
            </div>
          </div>
          <div class="product-details">
            <h1 class="product-title"><?php echo htmlspecialchars($producto['name']); ?></h1>
            <p class="product-price">$<?php echo number_format($producto['price'], 2); ?></p>
            <div class="wholesale-info-box">
              <div class="wholesale-info-icon">
                <i class="fab fa-whatsapp"></i>
              </div>
              <div class="wholesale-info-content">
                <p class="wholesale-info-title">Compra al detal y atención mayorista</p>
                <p class="wholesale-info-text">
                  Este valor corresponde al <strong>precio al detal</strong>. Si deseas comprar
                  <strong>8 unidades o más</strong> (precio mayorista),
                  <a class="wholesale-info-link"
                    href="https://wa.me/<?php echo $whatsapp_number; ?>?text=<?php echo $wholesale_whatsapp_text; ?>"
                    target="_blank"
                    rel="noopener noreferrer">escríbenos por WhatsApp</a>
                  y te compartimos la información completa.
                </p>
              </div>
            </div>
            <?php if (!empty($colors)): ?>
              <div class="options-group">
                <h3 class="options-label">Color</h3>
                <div class="options-selector">
                  <?php foreach ($colors as $color): ?>
                    <button class="color-btn" style="background-color: <?php echo $color['hex']; ?>;" title="<?php echo $color['name']; ?>" data-color-id="<?php echo $color['id']; ?>"></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($sizes)): ?>
              <div class="options-group">
                <h3 class="options-label">Talla</h3>
                <div class="options-selector">
                  <?php foreach ($sizes as $size): ?>
                    <button class="size-btn" data-size-id="<?php echo $size['id']; ?>"><?php echo $size['name']; ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="options-group">
              <h3 class="options-label">Cantidad</h3>
              <div class="quantity-selector">
                <button type="button" class="qty-btn minus">-</button>
                <input type="text" id="quantity" value="1" readonly>
                <button type="button" class="qty-btn plus">+</button>
              </div>
            </div>

            <div class="actions-container" style="display: flex; align-items: center; margin-top: 15px;">
              <button class="add-to-cart-btn">Comprar por WhatsApp</button>
              <button id="wishlist-btn" style="background:none; border:none; cursor:pointer; font-size: 1.5em; color: #3c3737; margin-left: 15px;">
                <i class="far fa-heart"></i>
              </button>
            </div>

            <div class="description-group">
              <div class="description-content">
                <p><?php echo nl2br(htmlspecialchars($producto['description'])); ?></p>
              </div>
            </div>
          </div>
        </div>
      </main>

      <section class="reviews-section">
        <h2>Opiniones de Clientes</h2>
        <div id="ratings-summary"></div>
        <div id="reviews-container">
          <p>Cargando reseñas...</p>
        </div>
        <div id="review-form-container" style="display: none;">
          <h3>Deja tu opinión</h3>
          <form id="review-form">
            <div class="star-rating">
              <input type="radio" id="star5" name="rating" value="5" required /><label for="star5" title="5 estrellas"></label>
              <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 estrellas"></label>
              <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 estrellas"></label>
              <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 estrellas"></label>
              <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 estrella"></label>
            </div>
            <textarea name="comment" placeholder="Escribe tu reseña aquí..." required></textarea>
            <button type="submit">Enviar Reseña</button>
          </form>
        </div>
      </section>

      <section class="recently-viewed-section">
        <h2>Vistos Recientemente</h2>
        <div id="recently-viewed-container" class="product-grid">
        </div>
      </section>

      <?php include 'footer.php'; ?>
    </div>
  </div>
  <?php include 'modal.php'; ?>
  <script src="/js/cart.js?v=<?php echo time(); ?>"></script>

  <script>
    const productVariants = <?php echo json_encode($variants_stock); ?>;
    const productId = <?php echo $producto['id']; ?>;
    const imagesByColor = <?php echo json_encode($images_by_color); ?>;
    const defaultImage = '/<?php echo htmlspecialchars($producto['image']); ?>';

    document.addEventListener('DOMContentLoaded', function() {
      const jwt = localStorage.getItem('jwt');

      const mainImage = document.getElementById('main-product-image');
      const thumbnailContainer = document.getElementById('thumbnail-container');
      let driftZoom = null; // Variable para guardar la instancia del zoom

      function updateImageGallery(colorId) {
        const genericImages = imagesByColor['default'] || [];
        const colorImages = imagesByColor[colorId] || [];
        let imagesToShow = [];

        if (colorImages.length > 0) {
          // ESCENARIO 1: SÍ hay imágenes para el color seleccionado.
          // Combinamos las imágenes de ese color con las genéricas.
          imagesToShow = [...colorImages, ...genericImages];
        } else {
          // ESCENARIO 2: NO hay imágenes para el color seleccionado.
          // Usamos la imagen principal del producto y la combinamos con las genéricas.
          // El `substring(1)` es para quitar la barra "/" inicial de la variable defaultImage.
          imagesToShow = [defaultImage.substring(1), ...genericImages];
        }

        // Usamos 'Set' para asegurarnos de que no haya imágenes duplicadas en la galería
        imagesToShow = [...new Set(imagesToShow)];

        thumbnailContainer.innerHTML = '';

        if (imagesToShow.length > 0) {
          imagesToShow.forEach((imgUrl, index) => {
            const thumb = document.createElement('img');
            thumb.src = '/' + imgUrl;
            thumb.className = 'thumbnail-image';
            thumbnailContainer.appendChild(thumb);

            thumb.addEventListener('click', () => {
              setMainImage('/' + imgUrl);
              document.querySelectorAll('.thumbnail-image').forEach(t => t.classList.remove('active'));
              thumb.classList.add('active');
            });

            if (index === 0) {
              setMainImage('/' + imgUrl);
              thumb.classList.add('active');
            }
          });
        } else {
          setMainImage(defaultImage);
        }
      }

      function setMainImage(imageUrl) {
        // Si ya existe una instancia de zoom, la destruimos antes de crear una nueva
        if (driftZoom) {
          driftZoom.destroy();
        }

        mainImage.src = imageUrl;
        mainImage.dataset.zoom = imageUrl; // Actualizamos el atributo data-zoom para la nueva imagen

        // Creamos una nueva instancia de Drift para la imagen principal
        driftZoom = new Drift(mainImage, {
          paneContainer: mainImage.parentElement, // El zoom aparecerá junto a la imagen
          inlinePane: 768, // Si la pantalla es menor a 768px, el zoom es sobre la imagen
          hoverBoundingBox: true
        });
      }

      // --- Lógica de selección de variantes ---
      let selectedColorId = null;
      let selectedSizeId = null;
      let availableStock = 0;
      const colorBtns = document.querySelectorAll('.color-btn');
      const sizeBtns = document.querySelectorAll('.size-btn');
      const qtyInput = document.getElementById('quantity');
      const plusBtn = document.querySelector('.qty-btn.plus');
      const minusBtn = document.querySelector('.qty-btn.minus');
      const addToCartBtn = document.querySelector('.add-to-cart-btn');

      function updateStock() {
        if (selectedColorId && selectedSizeId) {
          const variant = productVariants.find(v => v.color_id == selectedColorId && v.size_id == selectedSizeId);
          availableStock = variant ? variant.stock : 0;
          if (parseInt(qtyInput.value) > availableStock) {
            qtyInput.value = availableStock > 0 ? availableStock : 1;
          }
        }
        addToCartBtn.disabled = availableStock <= 0;
        addToCartBtn.textContent = availableStock <= 0 ? "Sin Stock" : "Comprar por WhatsApp";
      }
      colorBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          colorBtns.forEach(b => b.classList.remove('selected'));
          this.classList.add('selected');
          selectedColorId = this.dataset.colorId;
          updateImageGallery(selectedColorId);
          updateStock();
        });
      });
      // Carga inicial de la galería
      if (colorBtns.length > 0) {
        // Simulamos un clic en el primer color disponible para cargar sus imágenes
        colorBtns[0].click();
      } else {
        // Si no hay colores, cargamos las imágenes por defecto
        updateImageGallery('default');
      }
      sizeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          sizeBtns.forEach(b => b.classList.remove('selected'));
          this.classList.add('selected');
          selectedSizeId = this.dataset.sizeId;
          updateStock();
        });
      });
      plusBtn.addEventListener('click', () => {
        let currentValue = parseInt(qtyInput.value);
        if (availableStock > 0 && currentValue < availableStock) {
          qtyInput.value = currentValue + 1;
        }
      });
      minusBtn.addEventListener('click', () => {
        let value = parseInt(qtyInput.value);
        if (value > 1) {
          qtyInput.value = value - 1;
        }
      });
      addToCartBtn.addEventListener('click', function() {
        // Validar selección de variantes
        if ((colorBtns.length > 0 && !selectedColorId) || (sizeBtns.length > 0 && !selectedSizeId)) {
          Swal.fire({
            icon: 'warning',
            text: 'Seleccione color y talla.'
          });
          return;
        }

        // Validar stock
        if (parseInt(qtyInput.value) > availableStock) {
          Swal.fire({
            icon: 'error',
            text: `Solo quedan ${availableStock} unidades en stock.`
          });
          return;
        }

        if (availableStock <= 0) {
          Swal.fire({
            icon: 'error',
            text: 'Este producto no tiene stock disponible.'
          });
          return;
        }

        // ====== DATOS PARA EL MENSAJE ======
        const quantity = parseInt(qtyInput.value);

        // Obtener nombre del color seleccionado
        let selectedColorName = 'No aplica';
        if (selectedColorId) {
          const selectedColorBtn = document.querySelector(`.color-btn[data-color-id="${selectedColorId}"]`);
          selectedColorName = selectedColorBtn ? selectedColorBtn.getAttribute('title') : 'No especificado';
        }

        // Obtener nombre de la talla seleccionada
        let selectedSizeName = 'No aplica';
        if (selectedSizeId) {
          const selectedSizeBtn = document.querySelector(`.size-btn[data-size-id="${selectedSizeId}"]`);
          selectedSizeName = selectedSizeBtn ? selectedSizeBtn.textContent.trim() : 'No especificada';
        }

        const productName = <?php echo json_encode($producto['name']); ?>;
        const productPrice = <?php echo json_encode(number_format($producto['price'], 2)); ?>;
        const productUrl = window.location.href;

        // ⚠️ CAMBIA ESTE NÚMERO por el WhatsApp real del negocio (con código país, sin + ni espacios)
        const whatsappNumber = <?php echo json_encode($whatsapp_number); ?>;

        const message = `Hola, quiero comprar este producto:%0A%0A` +
          `🧢 Producto: ${productName}%0A` +
          `🎨 Color: ${selectedColorName}%0A` +
          `📏 Talla: ${selectedSizeName}%0A` +
          `🔢 Cantidad: ${quantity}%0A` +
          `💲 Precio: $${productPrice}%0A%0A` +
          `🔗 Link: ${productUrl}`;

        const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${message}`;

        // Redirigir a WhatsApp
        window.open(whatsappUrl, '_blank');
      });

      const initialColorId = colorBtns.length > 0 ? colorBtns[0].dataset.colorId : 'default';
      if (colorBtns.length > 0) {
        colorBtns[0].click(); // Simulamos un clic en el primer color para iniciar todo
      } else {
        updateImageGallery('default'); // Si no hay colores, cargamos las default
      }

      // --- Lógica para Reseñas ---
      const reviewFormContainer = document.getElementById('review-form-container');
      if (jwt && reviewFormContainer) {
        reviewFormContainer.style.display = 'block';
      }
      fetchReviews(productId);
      document.getElementById('review-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const rating = this.querySelector('input[name="rating"]:checked');
        const comment = this.querySelector('textarea[name="comment"]').value;
        if (!rating) {
          Swal.fire('Error', 'Por favor, selecciona una calificación de estrellas.', 'error');
          return;
        }
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('rating', rating.value);
        formData.append('comment', comment);
        fetch('/php/reviews/add_review', {
            method: 'POST',
            headers: {
              'Authorization': 'Bearer ' + jwt
            },
            body: formData
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              Swal.fire('¡Éxito!', data.message, 'success');
              fetchReviews(productId);
              this.reset();
            } else {
              Swal.fire('Error', data.message, 'error');
            }
          })
          .catch(err => Swal.fire('Error', 'Ocurrió un problema de conexión.', 'error'));
      });

      // --- Lógica para Wishlist ---
      const wishlistBtn = document.getElementById('wishlist-btn');
      if (wishlistBtn) {
        const heartIcon = wishlistBtn.querySelector('i');

        function updateWishlistIcon(inWishlist) {
          if (inWishlist) {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            heartIcon.style.color = '#d9534f';
          } else {
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            heartIcon.style.color = '#3c3737';
          }
        }

        if (jwt) {
          wishlistBtn.style.display = 'inline-block';
          fetch(`/php/user/get_wishlist_status?product_id=${productId}`, {
              headers: {
                'Authorization': 'Bearer ' + jwt
              }
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                updateWishlistIcon(data.inWishlist);
              }
            });
        } else {
          wishlistBtn.style.display = 'none';
        }

        wishlistBtn.addEventListener('click', () => {
          if (!jwt) return;
          fetch('/php/user/toggle_wishlist', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + jwt
              },
              body: JSON.stringify({
                product_id: productId
              })
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                const message = data.status === 'added' ? 'Producto añadido a tu wishlist.' : 'Producto eliminado de tu wishlist.';
                const icon = data.status === 'added' ? 'success' : 'info';
                updateWishlistIcon(data.status === 'added');
                Swal.fire({
                  icon: icon,
                  title: data.status === 'added' ? '¡Añadido!' : 'Eliminado',
                  text: message,
                  timer: 1500,
                  showConfirmButton: false
                });
              }
            });
        });
      }

      // --- NUEVO: Lógica para Productos Recientemente Vistos ---
      // --- LÓGICA PARA PRODUCTOS VISTOS RECIENTEMENTE (AHORA DENTRO DEL LISTENER) ---
      // 1. Guardar el producto actual en el historial
      const productDataForHistory = {
        id: productId,
        name: '<?php echo addslashes(htmlspecialchars($producto['name'])); ?>',
        image: '<?php echo htmlspecialchars($producto['image']); ?>',
        url: `/producto${productId}`
      };
      let recentlyViewed = JSON.parse(localStorage.getItem('recentlyViewed')) || [];
      recentlyViewed = recentlyViewed.filter(item => item.id !== productId);
      recentlyViewed.unshift(productDataForHistory);
      if (recentlyViewed.length > 5) {
        recentlyViewed.pop();
      }
      localStorage.setItem('recentlyViewed', JSON.stringify(recentlyViewed));

      // 2. Cargar y mostrar la lista de productos vistos
      loadRecentlyViewed();
    });

    async function loadRecentlyViewed() {
      const container = document.getElementById('recently-viewed-container');
      if (!container) return;

      const jwt = localStorage.getItem('jwt');
      let fetchUrl = '/php/user/get_recently_viewed';

      // Si el usuario NO está logueado, enviamos los IDs desde localStorage
      if (!jwt) {
        const recentlyViewedLocal = JSON.parse(localStorage.getItem('recentlyViewed')) || [];
        const productIds = recentlyViewedLocal.map(item => item.id);

        if (productIds.length > 1) { // Solo si hay otros productos vistos
          fetchUrl += `?ids=${JSON.stringify(productIds)}`;
        } else {
          container.parentElement.style.display = 'none'; // Ocultar la sección entera
          return;
        }
      }

      try {
        const res = await fetch(fetchUrl, {
          headers: jwt ? {
            'Authorization': 'Bearer ' + jwt
          } : {}
        });
        const data = await res.json();

        if (data.success && data.products.length > 0) {
          container.innerHTML = '';
          let itemsToShow = 0;
          data.products.forEach(product => {
            // No mostrar el producto que ya se está viendo en la página actual
            if (product.id !== productId) {
              itemsToShow++;
              const productCard = document.createElement('div');
              productCard.className = 'product-card';
              productCard.innerHTML = `
                                <a href="/producto${product.id}">
                                    <img src="/${product.image}" alt="${product.name}" loading="lazy">
                                </a>
                                <div class="info">
                                    <h3>${product.name}</h3>
                                </div>`;
              container.appendChild(productCard);
            }
          });
          // Si después de filtrar solo queda el producto actual, ocultar la sección
          if (itemsToShow === 0) {
            container.parentElement.style.display = 'none';
          }

        } else {
          container.parentElement.style.display = 'none';
        }
      } catch (error) {
        console.error("Error al cargar productos vistos recientemente:", error);
        container.parentElement.style.display = 'none';
      }
    }

    async function fetchReviews(productId) {
      try {
        const res = await fetch(`/php/reviews/get_reviews?id=${productId}`);
        const data = await res.json();
        if (data.success) {
          renderReviews(data.reviews);
        }
      } catch (error) {
        console.error("Error al cargar reseñas:", error);
      }
    }

    function renderReviews(reviews) {
      const container = document.getElementById('reviews-container');
      const summaryContainer = document.getElementById('ratings-summary');
      container.innerHTML = '';
      summaryContainer.innerHTML = '';
      if (reviews.length === 0) {
        container.innerHTML = '<p>Este producto aún no tiene reseñas. ¡Sé el primero!</p>';
        return;
      }
      const ratingCounts = {
        5: 0,
        4: 0,
        3: 0,
        2: 0,
        1: 0
      };
      reviews.forEach(review => ratingCounts[review.rating]++);
      for (let i = 5; i >= 1; i--) {
        const percentage = (reviews.length > 0) ? (ratingCounts[i] / reviews.length) * 100 : 0;
        summaryContainer.innerHTML += `<div class="bar-container"><span>${i} ★</span><div class="bar-wrapper"><div class="bar" style="width: ${percentage}%;"></div></div><span>(${ratingCounts[i]})</span></div>`;
      }
      reviews.forEach(review => {
        const reviewElement = document.createElement('div');
        reviewElement.className = 'review-item';
        reviewElement.innerHTML = `<h4>${review.user_name}</h4><p style="color: #f2b600;">${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</p><p>${review.comment}</p><small>${new Date(review.created_at).toLocaleDateString()}</small>${review.reply_text ? `<div class="admin-reply"><strong>Respuesta de la tienda:</strong><p>${review.reply_text}</p></div>` : ''}`;
        container.appendChild(reviewElement);
      });
    }
  </script>
</body>

</html>
<?php
if (isset($conn) && $conn->ping()) {
  $conn->close();
}
?>
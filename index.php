<?php
require_once 'php/conexion.php';
require_once 'php/minifier.php';
session_start();

try {
    // 1. OBTENER COLECCIONES DESTACADAS (las que no son "Best Sellers", etc.)
    $sql_collections = "SELECT id, nombre, imagen_banner FROM categorias 
                        WHERE categoria_padre_id IS NULL 
                        AND nombre NOT IN ('Best Sellers', 'New Arrivals', 'Accesorios')
                        LIMIT 4"; // Mostramos hasta 4 colecciones
    $result_collections = $conn->query($sql_collections);
    $collections = $result_collections->fetch_all(MYSQLI_ASSOC);

    // 2. OBTENER PRODUCTOS DESTACADOS (is_featured = 1)
    $sql_featured = "SELECT id, name, description, image, price FROM productos WHERE is_featured = 1 LIMIT 4";
    $result_featured = $conn->query($sql_featured);
    $featured_products = $result_featured->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error en la página de inicio: " . $e->getMessage());
    $collections = [];
    $featured_products = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Stetson LATAM - Sombreros Stetson originales para toda Latinoamérica</title>
    <meta name="description" content="En Stetson LATAM, en alianza con DINALSOM, encuentra sombreros legendarios con envíos a toda Latinoamérica. Compra en línea con confianza." />
    <meta charset="UTF-8">
    <link rel="icon" href="img/logo.webp" type="image/x-icon">
    <link href="css/index.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="css/categoria.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include "header.php"; ?>

    <main>
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Estilo auténtico, legado atemporal</h1>
                <h2 class="hero-subtitle">Explora nuestra colección curada de sombreros premium, elaborados con el espíritu del Oeste Americano.</h2>
                <a href="hats" class="hero-button">COMPRA AHORA</a>
            </div>
        </section>

        <section class="home-section">
            <h2 class="section-title">Compra por Categoría</h2>
            <div class="collections-grid">
                <?php foreach ($collections as $collection): ?>
                    <a href="categoria<?php echo $collection['id']; ?>" class="collection-card">
                        <img src="<?php echo htmlspecialchars(!empty($collection['imagen_banner']) ? $collection['imagen_banner'] : 'img/default.jpg'); ?>" alt="<?php echo htmlspecialchars($collection['nombre']); ?>">
                        <div class="collection-card-overlay">
                            <h3><?php echo htmlspecialchars($collection['nombre']); ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="home-section">
            <h2 class="section-title">Productos Destacados</h2>
            <div class="product-grid">
                <?php foreach ($featured_products as $producto): ?>
                    <a href="producto<?php echo $producto['id']; ?>" class="product-card">
                        <div class="product-card-image" style="background-image: url('<?php echo htmlspecialchars($producto['image']); ?>');"></div>
                        <div class="product-card-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($producto['name']); ?></h3>
                            <p class="product-price">$<?php echo number_format($producto['price'], 2); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="home-section story-section">
            <div class="story-image"></div>
            <div class="story-content">
                <h2 class="section-title">Un legado de artesanía</h2>
                <p>Fundada en 1865, Stetson nació del espíritu del Oeste y creció para convertirse en un ícono americano. El gesto amistoso de John B. Stetson hacia un viajero extraviado en la nueva frontera dio origen al primer "sombrero de vaquero" del mundo. Desde entonces, Stetson ha sido un líder global en sombreros, una marca de confianza en todo el mundo, y se ha convertido en un accesorio esencial para los entusiastas del Oeste en todas partes.</p>
                <a href="aboutUs" class="story-button">NUESTRA HISTORIA</a>
            </div>
        </section>

        <section id="signup-section" class="home-section signup-section">
            <h2 class="section-title">Únete a la comunidad Stetson</h2>
            <p>Mantente al día con las últimas noticias, ofertas exclusivas y lanzamientos de nuevos productos.</p>
            <button id="open-user-modal" class="signup-button">REGÍSTRATE</button>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'modal.php'; ?>
    <!--
      La etiqueta de GA4 (G-8V3PPJ187J) estaba aquí en duro y además se dispara
      desde Google Tag Manager, lo que contaba dos veces cada visita al inicio.
      Ahora GA4 se configura solo desde GTM, que se carga en header.php y por lo
      tanto en todas las páginas del sitio.
    -->
</body>

</html>

</html>
<?php
// php/shipping_config.php
//
// Configuración de envíos internacionales.
//
// ⚠️ TARIFA PROVISIONAL. El valor de abajo es un estimado para poder activar la
// venta internacional, NO una tarifa cotizada. Está calculado sobre el peso
// volumétrico de una caja de sombrero (40×40×25 cm ≈ 8 kg facturables) a
// precios de carga aérea consolidada, con margen para cubrir el peor caso.
//
// Apenas el cliente consiga las cotizaciones reales, se reemplazan los valores
// de este archivo. No hay que tocar nada más del código.
//
// Los aranceles e impuestos de importación NO están incluidos: los paga el
// destinatario al recibir. Eso debe quedar advertido en el checkout.

if (!defined('ENVIO_INTERNACIONAL_USD')) {

    // Tarifa plana por zona, en dólares.
    //
    // ⚠️ TERRITORIO: esta lista es también la lista de países a los que se
    // puede vender. México, Estados Unidos, Canadá y Puerto Rico quedan FUERA
    // a propósito: Stetson tiene distribución propia en esos mercados y
    // Dinalsom no vende allí. No agregarlos sin confirmarlo con el cliente.
    define('ENVIO_ZONAS', [
        // Zona 1 — Vecinos
        'Ecuador'              => 75.00,
        'Panamá'               => 75.00,
        'Perú'                 => 75.00,
        'Venezuela'            => 75.00,

        // Zona 2 — Centroamérica y Caribe
        'Costa Rica'           => 85.00,
        'Guatemala'            => 85.00,
        'El Salvador'          => 85.00,
        'Honduras'             => 85.00,
        'Nicaragua'            => 85.00,
        'República Dominicana' => 85.00,

        // Zona 3 — Cono Sur
        'Bolivia'              => 95.00,
        'Chile'                => 95.00,
        'Paraguay'             => 95.00,
        'Uruguay'              => 95.00,
        'Argentina'            => 95.00,
        'Brasil'               => 110.00,
    ]);

    // País de origen: usa la tabla de departamentos y tarifas en COP.
    define('PAIS_LOCAL', 'Colombia');
}

if (!function_exists('pais_tiene_envio')) {
    /**
     * ¿Se puede vender y despachar a este país?
     *
     * Colombia siempre sí. Fuera de Colombia, solo los países de ENVIO_ZONAS:
     * los demás quedan excluidos por acuerdo de territorio con la marca.
     */
    function pais_tiene_envio(string $pais): bool
    {
        $pais = trim($pais);
        return es_envio_nacional($pais) || isset(ENVIO_ZONAS[$pais]);
    }
}

if (!function_exists('tarifa_envio_internacional')) {
    /**
     * Tarifa de envío en USD para un país fuera de Colombia.
     * Lanza excepción si el país no está habilitado, para que nunca se cree
     * un pedido hacia un destino al que no se puede despachar.
     */
    function tarifa_envio_internacional(string $pais): float
    {
        $pais = trim($pais);
        $zonas = ENVIO_ZONAS;

        if (!isset($zonas[$pais])) {
            throw new Exception('Por ahora no realizamos envíos a ese país. Escríbenos y te contamos dónde conseguir tu sombrero.');
        }

        return (float)$zonas[$pais];
    }
}

if (!function_exists('es_envio_nacional')) {
    function es_envio_nacional(string $pais): bool
    {
        return trim($pais) === '' || strcasecmp(trim($pais), PAIS_LOCAL) === 0;
    }
}

if (!function_exists('paises_con_envio')) {
    /**
     * Lista de países a los que se vende, para el selector del checkout.
     * Colombia va primero por ser el mercado principal.
     */
    function paises_con_envio(): array
    {
        $paises = array_keys(ENVIO_ZONAS);
        sort($paises);

        // Sin opción "Otro país": si no está en la lista, no se vende allí.
        return array_merge([PAIS_LOCAL], $paises);
    }
}

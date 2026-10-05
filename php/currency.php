<?php
// php/currency.php
//
// Conversión de moneda compartida.
//
// Las tarifas de envío están guardadas en COP en la tabla `shipping_rates`,
// pero el catálogo y el cobro en Mercado Pago están en USD. Antes, la pantalla
// del checkout convertía la tarifa a dólares y el backend cobraba el número en
// pesos tal cual, así que lo mostrado y lo cobrado no coincidían.
//
// Este archivo centraliza la conversión para que ambos usen exactamente la
// misma tasa y el mismo caché.

if (!defined('CURRENCY_API_KEY')) {
    define('CURRENCY_API_KEY', 'fb6bafe141d0995b2110e976');
    define('CURRENCY_BASE', 'COP');   // Moneda en la que están guardadas las tarifas
    define('CURRENCY_TARGET', 'USD'); // Moneda en la que se cobra
}

if (!function_exists('get_conversion_rate')) {
    /**
     * Tasa de conversión entre dos monedas, con caché en disco de 12 horas.
     * Devuelve null si no se pudo obtener.
     */
    function get_conversion_rate($api_key, $base, $target)
    {
        $cache_file = __DIR__ . '/currency_cache.json';
        $cache_lifetime = 3600 * 12;

        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_lifetime) {
            $cache = json_decode(file_get_contents($cache_file), true);
            $rate = $cache['conversion_rates'][$target] ?? null;
            if ($rate !== null) {
                return $rate;
            }
        }

        $url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$base}";
        $response = @file_get_contents($url);

        if ($response === false) {
            // Si el API falla pero hay un caché viejo, es mejor una tasa de ayer
            // que ninguna: evita que se caiga el checkout por una red lenta.
            if (file_exists($cache_file)) {
                $cache = json_decode(file_get_contents($cache_file), true);
                return $cache['conversion_rates'][$target] ?? null;
            }
            return null;
        }

        $data = json_decode($response, true);
        if ($data && isset($data['result']) && $data['result'] === 'success') {
            file_put_contents($cache_file, $response);
            return $data['conversion_rates'][$target] ?? null;
        }

        return null;
    }
}

if (!function_exists('convertir_envio_a_moneda_de_cobro')) {
    /**
     * Convierte una tarifa de envío guardada en COP a la moneda de cobro.
     * Lanza una excepción si no hay tasa disponible: es preferible detener el
     * pedido a cobrarle al cliente un número en la moneda equivocada.
     */
    function convertir_envio_a_moneda_de_cobro(float $monto_cop): float
    {
        if ($monto_cop <= 0) {
            return 0.0;
        }

        $tasa = get_conversion_rate(CURRENCY_API_KEY, CURRENCY_BASE, CURRENCY_TARGET);

        if ($tasa === null) {
            throw new Exception('No pudimos calcular el costo de envío en este momento. Intenta de nuevo en unos minutos.');
        }

        return round($monto_cop * $tasa, 2);
    }
}

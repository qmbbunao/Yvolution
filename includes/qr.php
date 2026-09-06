<?php
/**
 * QR Server API — free, no key required.
 * Generates a QR code image URL that encodes an order tracking link.
 */
function generate_order_qr_url(string $orderCode): string
{
    $config = (require BASE_PATH . '/config/api_keys.php')['qr_server'];
    $trackingUrl = 'http://localhost' . BASE_URL . '/customer/orders/track.php?code=' . urlencode($orderCode);

    $query = http_build_query([
        'size' => $config['size'],
        'data' => $trackingUrl,
    ]);

    return $config['base_url'] . '?' . $query;
}

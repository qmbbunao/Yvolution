<?php
/**
 * OpenStreetMap Nominatim — free geocoding, no key required.
 * Respect their usage policy: max 1 request/sec, custom User-Agent required.
 */
function geocode_address(string $address): ?array
{
    $config = (require BASE_PATH . '/config/api_keys.php')['nominatim'];

    $query = http_build_query([
        'q'      => $address,
        'format' => 'json',
        'limit'  => 1,
        'countrycodes' => 'ph',
    ]);

    $ch = curl_init($config['base_url'] . '?' . $query);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: ' . $config['user_agent']],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);

    if (empty($data[0])) {
        return null;
    }

    return [
        'lat' => (float) $data[0]['lat'],
        'lng' => (float) $data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? $address,
    ];
}

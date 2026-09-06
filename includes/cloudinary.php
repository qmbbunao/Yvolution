<?php
/**
 * Minimal Cloudinary client — signed upload via cURL, no SDK/Composer needed.
 * Usage:
 *   $cloud = new CloudinaryUploader();
 *   $result = $cloud->upload($_FILES['design']['tmp_name'], 'yvolution/designs');
 *   // $result['secure_url'], $result['public_id']
 */
class CloudinaryUploader
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $config = (require BASE_PATH . '/config/api_keys.php')['cloudinary'];
        $this->cloudName = $config['cloud_name'];
        $this->apiKey    = $config['api_key'];
        $this->apiSecret = $config['api_secret'];
    }

    /**
     * Upload a local file (e.g. $_FILES[...]['tmp_name']) to Cloudinary.
     * Returns ['secure_url' => ..., 'public_id' => ...] or throws RuntimeException.
     */
    public function upload(string $filePath, string $folder = 'yvolution'): array
    {
        if (!is_uploaded_file($filePath) && !file_exists($filePath)) {
            throw new RuntimeException('File not found for upload.');
        }

        $timestamp = time();
        $paramsToSign = [
            'folder'    => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);
        $signatureBase = '';
        foreach ($paramsToSign as $key => $value) {
            $signatureBase .= "{$key}={$value}&";
        }
        $signatureBase = rtrim($signatureBase, '&') . $this->apiSecret;
        $signature = sha1($signatureBase);

        $postFields = [
            'file'      => new CURLFile($filePath),
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ];

        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/auto/upload";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Cloudinary upload failed: ' . $curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || empty($data['secure_url'])) {
            $message = $data['error']['message'] ?? 'Unknown Cloudinary error';
            throw new RuntimeException('Cloudinary upload failed: ' . $message);
        }

        return [
            'secure_url' => $data['secure_url'],
            'public_id'  => $data['public_id'],
        ];
    }

    /** Delete an asset from Cloudinary by its public_id (used when replacing/removing files). */
    public function destroy(string $publicId): bool
    {
        $timestamp = time();
        $signatureBase = "public_id={$publicId}&timestamp={$timestamp}{$this->apiSecret}";
        $signature = sha1($signatureBase);

        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'public_id' => $publicId,
                'api_key'   => $this->apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        return ($data['result'] ?? '') === 'ok';
    }
}

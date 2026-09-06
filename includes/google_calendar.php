<?php
/**
 * Minimal Google Calendar API client — OAuth2 + event CRUD via cURL, no SDK.
 * Tokens are stored in the `api_settings` table (api_name = 'google_calendar').
 *
 * Setup (one-time, done by Super Admin):
 *   1. Create OAuth credentials in Google Cloud Console (OAuth client ID, "Web application").
 *   2. Add the redirect URI from config/api_keys.php as an Authorized redirect URI.
 *   3. Fill in client_id/client_secret in config/api_keys.php under 'google_calendar'.
 *   4. Go to Super Admin > API Settings and click "Connect Google Calendar".
 */
class GoogleCalendarClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $calendarId;
    private PDO $pdo;

    public function __construct()
    {
        $config = (require BASE_PATH . '/config/api_keys.php')['google_calendar'];
        $this->clientId     = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri  = $config['redirect_uri'];
        $this->calendarId   = $config['calendar_id'] ?: 'primary';
        $this->pdo = Database::connect();
    }

    /** Step 1: build the URL to send the Super Admin to for consent. */
    public function getAuthUrl(): string
    {
        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar.events',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /** Step 2: exchange the ?code=... from Google's redirect for tokens, and store them. */
    public function exchangeCode(string $code): bool
    {
        $response = $this->postForm('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri,
        ]);

        if (empty($response['access_token'])) {
            return false;
        }

        $this->saveTokens([
            'access_token'  => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? ($this->getStoredTokens()['refresh_token'] ?? null),
            'expires_at'    => time() + (int) ($response['expires_in'] ?? 3600),
        ]);

        return true;
    }

    public function isConnected(): bool
    {
        $tokens = $this->getStoredTokens();
        return !empty($tokens['refresh_token']);
    }

    public function disconnect(): void
    {
        $this->pdo->prepare("DELETE FROM api_settings WHERE api_name = 'google_calendar'")->execute();
    }

    /** Returns a valid access token, refreshing it first if it's expired. */
    private function getValidAccessToken(): ?string
    {
        $tokens = $this->getStoredTokens();
        if (empty($tokens['refresh_token'])) {
            return null;
        }

        if (empty($tokens['access_token']) || ($tokens['expires_at'] ?? 0) < time() + 60) {
            $refreshed = $this->postForm('https://oauth2.googleapis.com/token', [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $tokens['refresh_token'],
                'grant_type'    => 'refresh_token',
            ]);

            if (empty($refreshed['access_token'])) {
                return null;
            }

            $tokens['access_token'] = $refreshed['access_token'];
            $tokens['expires_at'] = time() + (int) ($refreshed['expires_in'] ?? 3600);
            $this->saveTokens($tokens);
        }

        return $tokens['access_token'];
    }

    /**
     * Create a calendar event for a production milestone.
     * $date is a 'Y-m-d' string; the event is created as an all-day entry.
     * Returns the Google event ID, or null on failure.
     */
    public function createEvent(string $summary, string $description, string $date): ?string
    {
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            return null;
        }

        $nextDay = date('Y-m-d', strtotime($date . ' +1 day'));

        $payload = [
            'summary'     => $summary,
            'description' => $description,
            'start'       => ['date' => $date],
            'end'         => ['date' => $nextDay],
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($this->calendarId) . '/events';
        $result = $this->request('POST', $url, $accessToken, $payload);

        return $result['id'] ?? null;
    }

    public function deleteEvent(string $eventId): bool
    {
        $accessToken = $this->getValidAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($this->calendarId) . '/events/' . urlencode($eventId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return in_array($httpCode, [200, 204, 404], true); // 404 = already gone, treat as success
    }

    // ---------------------------------------------------------------

    private function getStoredTokens(): ?array
    {
        $stmt = $this->pdo->prepare("SELECT config_json FROM api_settings WHERE api_name = 'google_calendar'");
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return json_decode($row['config_json'], true) ?: null;
    }

    private function saveTokens(array $tokens): void
    {
        $json = json_encode($tokens);
        $existing = $this->pdo->prepare("SELECT setting_id FROM api_settings WHERE api_name = 'google_calendar'");
        $existing->execute();

        if ($row = $existing->fetch()) {
            $this->pdo->prepare("UPDATE api_settings SET config_json = ?, status = 'active' WHERE setting_id = ?")
                ->execute([$json, $row['setting_id']]);
        } else {
            $this->pdo->prepare("INSERT INTO api_settings (api_name, config_json, status) VALUES ('google_calendar', ?, 'active')")
                ->execute([$json]);
        }
    }

    private function postForm(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $response, true) ?: [];
    }

    private function request(string $method, string $url, string $accessToken, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $response, true) ?: [];
    }
}

<?php
/**
 * WebPanel - Navidrome API Client
 */
class NavidromeAPI {
    private $url;
    private $adminUser;
    private $adminPass;
    private $token;

    public function __construct() {
        $this->url = rtrim(wp_setting('navidrome_url'), '/');
        $this->adminUser = wp_setting('navidrome_admin_user');
        $this->adminPass = wp_setting('navidrome_admin_pass');
    }

    private function authenticate() {
        if ($this->token) return;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->url}/auth/login",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'username' => $this->adminUser,
                'password' => $this->adminPass
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Navidrome auth error: $error");

        $decoded = json_decode($response, true);
        if (!isset($decoded['token'])) throw new Exception("Navidrome: Authentication failed");

        $this->token = $decoded['token'];
    }

    private function request($method, $path, $data = null) {
        $this->authenticate();

        $ch = curl_init();
        $url = "{$this->url}/api$path";
        $headers = [
            'Content-Type: application/json',
            "x-nd-authorization: Bearer {$this->token}"
        ];

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ];

        switch (strtoupper($method)) {
            case 'GET':
                if ($data) $opts[CURLOPT_URL] .= '?' . http_build_query($data);
                break;
            case 'POST':
                $opts[CURLOPT_POST] = true;
                if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'PUT':
                $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data) $opts[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'DELETE':
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Navidrome API error: $error");
        if ($httpCode >= 400) throw new Exception("Navidrome API HTTP $httpCode: $response");

        return json_decode($response, true);
    }

    public function createUser($username, $password, $isAdmin = false) {
        return $this->request('POST', '/user', [
            'userName' => $username,
            'name' => $username,
            'password' => $password,
            'isAdmin' => $isAdmin
        ]);
    }

    public function getUser($userId) {
        return $this->request('GET', "/user/$userId");
    }

    public function getUsers() {
        return $this->request('GET', '/user', ['_end' => 1000, '_start' => 0, '_order' => 'ASC', '_sort' => 'userName']);
    }

    public function updateUser($userId, $data) {
        return $this->request('PUT', "/user/$userId", $data);
    }

    public function deleteUser($userId) {
        return $this->request('DELETE', "/user/$userId");
    }

    public function changePassword($userId, $newPassword) {
        // Fetch existing user data first — PUT replaces the entire resource
        $user = $this->getUser($userId);
        $user['password'] = $newPassword;
        // Remove read-only fields that Navidrome doesn't accept on update
        unset($user['lastLoginAt'], $user['lastAccessAt'], $user['createdAt'], $user['updatedAt'], $user['currentlyPlaying']);
        return $this->request('PUT', "/user/$userId", $user);
    }

    public function findUserByName($username) {
        $users = $this->getUsers();
        if (is_array($users)) {
            foreach ($users as $user) {
                if (strtolower($user['userName'] ?? '') === strtolower($username)) {
                    return $user;
                }
            }
        }
        return null;
    }

    public function provisionUser($baseUsername, $customPassword = null) {
        $username = preg_replace('/[^a-z0-9._]/', '', strtolower($baseUsername));
        $password = $customPassword ?: wp_generate_password(12);

        // Check if user already exists in Navidrome
        $existing = $this->findUserByName($username);
        if ($existing) {
            // Reset password and return existing user
            $this->changePassword($existing['id'], $password);
            return [
                'username' => $username,
                'password' => $password,
                'user_id' => $existing['id']
            ];
        }

        $result = $this->createUser($username, $password, false);

        return [
            'username' => $username,
            'password' => $password,
            'user_id' => $result['id'] ?? null
        ];
    }

    public function suspendUser($userId) {
        // Navidrome doesn't have suspend, we change password to random
        $tempPass = wp_generate_token(32);
        return $this->changePassword($userId, $tempPass);
    }

    public function isConfigured() {
        return !empty($this->url) && !empty($this->adminUser) && !empty($this->adminPass);
    }
}

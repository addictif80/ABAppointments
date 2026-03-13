<?php
/**
 * WebPanel - CyberPanel API Client
 */
class CyberPanelAPI {
    private $url;
    private $adminUser;
    private $adminPass;

    public function __construct() {
        $this->url = rtrim(wp_setting('cyberpanel_api_url') ?: wp_setting('cyberpanel_url'), '/');
        $this->adminUser = wp_setting('cyberpanel_admin_user', 'admin');
        $this->adminPass = wp_setting('cyberpanel_admin_pass');
    }

    private function request($endpoint, $data = []) {
        $data['adminUser'] = $this->adminUser;
        $data['adminPass'] = $this->adminPass;

        $url = "{$this->url}/api/$endpoint";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) throw new Exception("CyberPanel API error ($url): $error");
        if ($httpCode === 404) throw new Exception("CyberPanel API: endpoint not found ($url). Verifiez que l'URL API pointe vers CyberPanel (port 8090).");
        if ($httpCode >= 500) throw new Exception("CyberPanel API: erreur serveur (HTTP $httpCode) sur $url");

        $decoded = json_decode($response, true);
        if (!$decoded) throw new Exception("CyberPanel API: reponse invalide (HTTP $httpCode) depuis $url: " . substr($response, 0, 200));

        if (isset($decoded['status']) && $decoded['status'] === 0) {
            throw new Exception("CyberPanel: " . ($decoded['error_message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    public function createUser($firstName, $lastName, $email, $username, $password, $websitesLimit = 1, $securityLevel = 'HIGH') {
        return $this->request('submitUserCreation', [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'userName' => $username,
            'password' => $password,
            'websitesLimit' => $websitesLimit,
            'selectedACL' => 'user',
            'securityLevel' => $securityLevel
        ]);
    }

    public function deleteUser($username) {
        return $this->request('submitUserDeletion', [
            'accountUsername' => $username
        ]);
    }

    public function createWebsite($domain, $email, $package, $username, $password, $phpVersion = '8.2') {
        return $this->request('createWebsite', [
            'domainName' => $domain,
            'ownerEmail' => $email,
            'packageName' => $package,
            'websiteOwner' => $username,
            'ownerPassword' => $password,
            'phpSelection' => "PHP $phpVersion",
            'ssl' => 1
        ]);
    }

    public function deleteWebsite($domain) {
        return $this->request('deleteWebsite', [
            'domainName' => $domain
        ]);
    }

    public function suspendWebsite($domain) {
        return $this->request('submitWebsiteStatus', [
            'websiteName' => $domain,
            'state' => 'Suspend'
        ]);
    }

    public function unsuspendWebsite($domain) {
        return $this->request('submitWebsiteStatus', [
            'websiteName' => $domain,
            'state' => 'Un-Suspend'
        ]);
    }

    public function changePackage($domain, $package) {
        return $this->request('changePackageAPI', [
            'websiteName' => $domain,
            'packageName' => $package
        ]);
    }

    public function listWebsites() {
        return $this->request('fetchWebsites', [
            'page' => 1
        ]);
    }

    public function getWebsiteDetails($domain) {
        return $this->request('fetchWebsiteDataJSON', [
            'domainName' => $domain
        ]);
    }

    public function createDatabase($domain, $dbName, $dbUser, $dbPass) {
        return $this->request('submitDBCreation', [
            'databaseWebsite' => $domain,
            'dbName' => $dbName,
            'dbUsername' => $dbUser,
            'dbPassword' => $dbPass
        ]);
    }

    public function createEmailAccount($domain, $username, $password) {
        return $this->request('submitEmailCreation', [
            'domainName' => $domain,
            'userName' => $username,
            'password' => $password
        ]);
    }

    public function issueSSL($domain) {
        return $this->request('issueSSL', [
            'domainName' => $domain
        ]);
    }

    public function changePHP($domain, $phpVersion) {
        return $this->request('changePHP', [
            'childDomain' => $domain,
            'phpSelection' => "PHP $phpVersion"
        ]);
    }

    public function createPackage($name, $diskSpace, $bandwidth, $emailAccounts, $databases, $domains) {
        return $this->request('submitPackage', [
            'packageName' => $name,
            'diskSpace' => $diskSpace,
            'bandwidth' => $bandwidth,
            'emailAccounts' => $emailAccounts,
            'dataBases' => $databases,
            'allowedDomains' => $domains
        ]);
    }

    public function getPackages() {
        return $this->request('fetchPackages');
    }

    public function provisionHosting($domain, $email, $package, $diskMb, $bandwidthMb, $emailAccounts, $databases) {
        $username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $username = substr($username, 0, 16);
        if (strlen($username) < 3) {
            $username = 'user' . $username;
        }
        $password = wp_generate_password(16);

        // Create or use existing package
        $packageName = 'wp_' . $package;
        try {
            $this->createPackage($packageName, (int)($diskMb / 1024) ?: 1, (int)($bandwidthMb / 1024) ?: 10, $emailAccounts, $databases, 1);
        } catch (Exception $e) {
            // Package might already exist
        }

        // Create CyberPanel user account first
        try {
            $this->createUser('Client', $username, $email, $username, $password);
        } catch (Exception $e) {
            // User might already exist — only ignore that specific case
            if (strpos($e->getMessage(), 'already exist') === false) {
                throw $e;
            }
        }

        // Now create the website under that user
        $this->createWebsite($domain, $email, $packageName, $username, $password);

        return [
            'username' => $username,
            'password' => $password,
            'package' => $packageName
        ];
    }

    public function verifyConnection() {
        return $this->request('verifyConn');
    }

    public function isConfigured() {
        return !empty($this->url) && !empty($this->adminPass);
    }
}

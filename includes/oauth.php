<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/* Google social sign-in (OAuth 2.0). */

function oauth_providers(): array {
    return [
        'google' => [
            'label'         => 'Google',
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'scope'         => 'openid email profile',
        ],
    ];
}

function oauth_provider(string $name): ?array {
    return oauth_providers()[$name] ?? null;
}

function oauth_configured(string $name): bool {
    $p = oauth_provider($name);
    return $p !== null && $p['client_id'] !== '' && $p['client_secret'] !== '';
}

/** Absolute callback URL; must match the provider console exactly. */
function oauth_callback_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url('oauth/callback.php');
}

/** JSON request helper. Throws RuntimeException with the provider's message. */
function oauth_http(string $url, ?array $post = null, ?string $bearer = null): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $body   = curl_exec($ch);
    $errno  = curl_errno($ch);
    $error  = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException('Could not reach ' . parse_url($url, PHP_URL_HOST) . ' (' . $error . ').');
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Unexpected response from the sign-in provider.');
    }
    if ($status >= 400) {
        $msg = $data['error_description'] ?? $data['error']['message'] ?? $data['error'] ?? ('HTTP ' . $status);
        if (is_array($msg)) $msg = json_encode($msg);
        throw new RuntimeException((string)$msg);
    }
    return $data;
}

/** Exchange the authorization code and normalize the provider profile. */
function oauth_fetch_profile(string $name, array $provider, string $code): array {
    $redirect = oauth_callback_url();

    if ($name === 'google') {
        $token = oauth_http($provider['token_url'], [
            'code'          => $code,
            'client_id'     => $provider['client_id'],
            'client_secret' => $provider['client_secret'],
            'redirect_uri'  => $redirect,
            'grant_type'    => 'authorization_code',
        ]);
        if (empty($token['access_token'])) {
            throw new RuntimeException('Google did not return an access token.');
        }
        $info = oauth_http('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);
        return [
            'id'             => (string)($info['sub'] ?? ''),
            'email'          => (string)($info['email'] ?? ''),
            'email_verified' => !empty($info['email_verified']),
            'name'           => (string)($info['name'] ?? ''),
        ];
    }

    throw new RuntimeException('Unsupported sign-in provider.');
}

/**
 * Resolve the local user for a provider profile: existing link, then verified
 * email match, then a new account. Throws RuntimeException when it can't.
 */
function oauth_login_or_register(string $name, array $profile): array {
    $provider = oauth_provider($name);
    if ($profile['id'] === '') {
        throw new RuntimeException('The provider did not return an account id.');
    }

    $linked = q(
        'SELECT u.* FROM user_oauth o JOIN users u ON u.id = o.user_id WHERE o.provider = ? AND o.provider_user_id = ?',
        [$name, $profile['id']]
    )->fetch();
    if ($linked) {
        if ($linked['status'] !== 'active') {
            throw new RuntimeException('This account has been deactivated. Contact an administrator.');
        }
        if ($linked['email_verified_at'] === null) {
            q('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [(int)$linked['id']]);
        }
        return $linked;
    }

    $email = strtolower(trim($profile['email']));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('No email address was shared by ' . $provider['label'] . '. Allow email access and try again.');
    }
    if (empty($profile['email_verified'])) {
        throw new RuntimeException('Your ' . $provider['label'] . ' email address is not verified.');
    }

    $user = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
    if ($user) {
        if ($user['status'] !== 'active') {
            throw new RuntimeException('This account has been deactivated. Contact an administrator.');
        }
        if ($user['email_verified_at'] === null) {
            q('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [(int)$user['id']]);
        }
        oauth_link((int)$user['id'], $name, $profile['id']);
        return $user;
    }

    $fullName = trim($profile['name']) !== ''
        ? mb_substr(trim($profile['name']), 0, 100)
        : ucfirst(strstr($email, '@', true) ?: 'Student');
    q(
        'INSERT INTO users (full_name, email, password_hash, email_verified_at) VALUES (?, ?, ?, NOW())',
        [$fullName, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]
    );
    $id = (int)db()->lastInsertId();
    oauth_link($id, $name, $profile['id']);
    return q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
}

function oauth_link(int $userId, string $provider, string $providerUserId): void {
    try {
        q('INSERT INTO user_oauth (user_id, provider, provider_user_id) VALUES (?, ?, ?)', [$userId, $provider, $providerUserId]);
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') throw $ex;   // already linked by a parallel request
    }
}

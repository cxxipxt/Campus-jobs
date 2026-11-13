<?php
declare(strict_types=1);

/**
 * Student OAuth configuration.
 *
 * This file only prepares and validates provider configuration.
 * It does not start authentication until all required settings exist.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Read an environment variable.
 */
function student_auth_env(string $key): string
{
    $value = getenv($key);
    return $value === false ? '' : trim($value);
}

/**
 * Validate a redirect URI.
 *
 * HTTPS is required in production.
 * HTTP is allowed only for local development.
 */
function student_auth_valid_redirect(string $uri): bool
{
    if ($uri === '') {
        return false;
    }

    $parts = parse_url($uri);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);

    if ($scheme === 'https') {
        return true;
    }

    return $scheme === 'http'
        && in_array($host, ['127.0.0.1', 'localhost'], true);
}

/**
 * Get validated OAuth configuration for a provider.
 *
 * Supported providers: microsoft, google
 *
 * @throws InvalidArgumentException
 */
function student_auth_config(string $provider): array
{
    $provider = strtolower($provider);

    if (!in_array($provider, ['microsoft', 'google'], true)) {
        throw new InvalidArgumentException('Unsupported login provider');
    }

    if ($provider === 'microsoft') {
        $tenantId = student_auth_env('MICROSOFT_TENANT_ID');
        $clientId = student_auth_env('MICROSOFT_CLIENT_ID');
        $clientSecret = student_auth_env('MICROSOFT_CLIENT_SECRET');
        $redirectUri = student_auth_env('MICROSOFT_REDIRECT_URI');

        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $tenantId
        )) {
            throw new InvalidArgumentException('Microsoft Tenant ID is missing or invalid');
        }

        $authority = 'https://login.microsoftonline.com/' . $tenantId . '/v2.0';

        $config = [
            'provider' => 'microsoft',
            'provider_url' => $authority,
            'issuer' => $authority,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ];
    } else {
        $clientId = student_auth_env('GOOGLE_CLIENT_ID');
        $clientSecret = student_auth_env('GOOGLE_CLIENT_SECRET');
        $redirectUri = student_auth_env('GOOGLE_REDIRECT_URI');
        $hostedDomain = strtolower(student_auth_env('GOOGLE_HOSTED_DOMAIN'));

        $config = [
            'provider' => 'google',
            'provider_url' => 'https://accounts.google.com',
            'issuer' => 'https://accounts.google.com',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'hosted_domain' => $hostedDomain,
        ];

        if ($hostedDomain === '') {
            throw new InvalidArgumentException('Google Hosted Domain is not configured');
        }
    }

    if ($config['client_id'] === '' || $config['client_secret'] === '') {
        throw new InvalidArgumentException('OAuth client credentials are not configured');
    }

    if (!student_auth_valid_redirect($config['redirect_uri'])) {
        throw new InvalidArgumentException('OAuth redirect URI is missing or invalid');
    }

    return $config;
}

/**
 * Check whether a provider is fully configured.
 *
 * Returns false if any required setting is missing or invalid.
 */
function student_auth_ready(string $provider): bool
{
    try {
        student_auth_config($provider);
        return true;
    } catch (Throwable $error) {
        return false;
    }
}
/**
 * OpenID Connect client for Campus-jobs.
 *
 * Microsoft supports PKCE S256, even when its
 * discovery metadata omits the supported-methods field.
 */
class CampusJobsOpenIDConnectClient extends \Jumbojett\OpenIDConnectClient
{
    protected function getWellKnownConfigValue(string $param, $default = null)
    {
        if ($param === 'code_challenge_methods_supported') {
            return ['S256'];
        }

        return parent::getWellKnownConfigValue($param, $default);
    }

    public function expectedState(): string
    {
        return $this->getState();
    }

    public function expectedNonce(): string
    {
        return $this->getNonce();
    }

    public function clearCodeVerifier(): void
    {
    $this->unsetCodeVerifier();
    }
    public function clearOAuthAttempt(): void
    {
    $this->unsetState();
    $this->unsetNonce();
    $this->unsetCodeVerifier();
    }
}

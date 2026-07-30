<?php

namespace BradTipper\RestfulServer\Api;

use BradTipper\RestfulServer\Auth\AuthSession;
use BradTipper\RestfulServer\Auth\JwtCodec;
use BradTipper\RestfulServer\Auth\PasswordStrength;
use SilverStripe\Control\Director;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Security;
use BradTipper\RestfulServer\Security\RestfulApiMemberGroup;
use Psr\Log\LoggerInterface;

class AuthController extends ApiController implements SupportsAuth
{
    private const REFRESH_COOKIE = 'restful_refresh';

    private static array $allowed_actions = [
        'login',
        'logout',
        'requestReset',
        'resetPassword',
        'refresh',
        'identity',
        'passwordStrength',
    ];

    private static array $authenticated_actions = [
        'logout',
    ];

    /**
     * POST /api/auth/login
     * Accepts { email, password } and returns JWT access token + refresh cookie.
     */
    public function login(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }

        $data = $this->requestData($request);
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $member = (new MemberAuthenticator())->authenticate([
            'Email' => $email,
            'Password' => $password,
        ], $request);

        if (!$member) {
            return $this->respondError('Email/password sign-in failed.', 401);
        }
        if (!RestfulApiMemberGroup::isApiUser($member)) {
            return $this->respondError('Account is not authorized for API access.', 403);
        }

        return $this->issueTokens($member, $request);
    }

    /**
     * POST /api/auth/logout
     * Revokes the current session.
     */
    public function logout(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }

        $member = $this->currentMember();
        $data = $this->requestData($request);
        $sessionUuid = (string) ($data['sessionUuid'] ?? '');
        $session = AuthSession::get()->filter([
            'UUID' => $sessionUuid,
            'MemberID' => $member->ID,
        ])->first();

        if ($session) {
            $session->RevokedAt = date('Y-m-d H:i:s');
            $session->write();
        }

        Injector::inst()->get(IdentityStore::class)->logOut($request);
        Security::setCurrentUser(null);
        Cookie::force_expiry(
            self::REFRESH_COOKIE,
            '/api/auth',
            null,
            Director::is_https($request),
            true,
            Cookie::SAMESITE_LAX
        );

        return $this->respond(['ok' => true]);
    }

    /**
     * POST /api/auth/request-reset
     * Sends a password reset email with a one-time link.
     */
    public function requestReset(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }

        $data = $this->requestData($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $member = Member::get()->filter('Email', $email)->first();

        if ($member) {
            $token = $member->generateAutologinTokenAndStoreHash();
            $resetUrl = Director::absoluteURL('/reset-password?email=' . rawurlencode($member->Email) . '&token=' . rawurlencode($token));
            $from = Environment::getEnv('RESTFUL_EMAIL_FROM') ?: 'no-reply@example.com';

            try {
                Email::create($from)
                    ->setTo($member->Email)
                    ->setSubject('Reset your password')
                    ->text("Use this link to choose a new password:\n\n{$resetUrl}\n\nThis link expires soon.")
                    ->send();
            } catch (\Throwable $error) {
                Injector::inst()->get(LoggerInterface::class)->error('Password reset email failed: ' . $error->getMessage());
            }
        }

        // Always return ok to prevent email enumeration
        return $this->respond(['ok' => true]);
    }

    /**
     * POST /api/auth/reset-password
     * Accepts { email, token, password } to complete a password reset.
     */
    public function resetPassword(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }

        $data = $this->requestData($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $member = Member::get()->filter('Email', $email)->first();

        if (!$member || strlen($password) < 12 || !$member->validateAutoLoginToken($token)) {
            return $this->respondError('The reset link is invalid or expired', 422);
        }

        $passwordResult = $member->changePassword($password, false);
        if (!$passwordResult->isValid()) {
            return $this->respondError('Choose a stronger password', 422);
        }

        $member->AutoLoginHash = null;
        $member->AutoLoginExpired = null;
        $member->write();

        // Revoke all existing sessions for this member
        AuthSession::get()->filter(['MemberID' => $member->ID, 'RevokedAt' => null])->each(function (AuthSession $session): void {
            $session->RevokedAt = date('Y-m-d H:i:s');
            $session->write();
        });

        return $this->respond(['ok' => true]);
    }

    /**
     * POST /api/auth/refresh
     * Exchange a valid refresh token for a new access token.
     * Browser refresh tokens come only from the HttpOnly cookie. Native
     * clients explicitly opt into a body transport and receive the rotated
     * token for storage in platform secure storage.
     */
    public function refresh(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }

        $data = $this->requestData($request);
        $nativeTransport = $this->usesNativeRefreshTransport($request);
        $refreshToken = $nativeTransport
            ? (string) ($data['refreshToken'] ?? '')
            : (string) Cookie::get(self::REFRESH_COOKIE);
        $sessionUuid = (string) ($data['sessionUuid'] ?? '');
        $session = AuthSession::get()->filter('UUID', $sessionUuid)->first();

        if (
            !$session
            || $session->RevokedAt
            || ($session->ExpiresAt && strtotime((string) $session->ExpiresAt) < time())
        ) {
            return $this->respondError('Session expired or revoked', 401);
        }

        if (!password_verify($refreshToken, (string) $session->RefreshTokenHash)) {
            return $this->respondError('Invalid refresh token', 401);
        }

        $member = $session->Member();
        if (
            !$member->exists()
            || !$member->UUID
            || !RestfulApiMemberGroup::isApiUser($member)
        ) {
            AuthSession::revokeAllForMember((int) $session->MemberID);
            return $this->respondError('Session expired or revoked', 401);
        }

        // Rotate before issuing another access token. Replaying the old value
        // now fails password_verify().
        $newRefreshToken = bin2hex(random_bytes(32));
        $session->RefreshTokenHash = password_hash($newRefreshToken, PASSWORD_DEFAULT);
        $session->write();

        $jwt = JwtCodec::singleton();
        $accessToken = $jwt->encode([
            'sub' => $member->UUID,
            'sid' => $session->UUID,
            'iat' => time(),
            'exp' => time() + $jwt->expiry(),
        ]);

        if (!$nativeTransport) {
            $this->setRefreshCookie($request, $newRefreshToken);
        }

        $payload = ['accessToken' => $accessToken];
        if ($nativeTransport) {
            $payload['refreshToken'] = $newRefreshToken;
        }
        return $this->respond($payload);
    }

    /**
     * POST /api/auth/password-strength
     * Accepts { password } and returns entropy score and feedback.
     * No authentication required — safe to call from registration forms.
     */
    public function passwordStrength(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->methodNotAllowed('POST');
        }
        $data = $this->requestData($request);
        $password = (string) ($data['password'] ?? '');
        return $this->respond(['strength' => PasswordStrength::evaluate($password)]);
    }

    /**
     * GET /api/auth/identity
     * Returns the current authenticated user's non-sensitive details.
     */
    public function identity(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isGET()) {
            return $this->methodNotAllowed('GET');
        }

        try {
            $member = $this->currentMember();
        } catch (AuthException) {
            return $this->respond(['authenticated' => false]);
        }

        return $this->respond([
            'authenticated' => true,
            'member' => [
                'uuid' => $member->UUID,
                'firstName' => $member->FirstName,
                'surname' => $member->Surname,
                'email' => $member->Email,
            ],
        ]);
    }

    private function issueTokens(Member $member, HTTPRequest $request, ?string $label = null): HTTPResponse
    {
        Injector::inst()->get(IdentityStore::class)->logIn($member, false, $request);
        Security::setCurrentUser($member);

        $jwt = JwtCodec::singleton();
        $session = AuthSession::create([
            'MemberID' => $member->ID,
            'Label' => substr($label ?: (string) ($request->getHeader('User-Agent') ?: 'Unknown device'), 0, 255),
            'ExpiresAt' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $session->write();

        $accessToken = $jwt->encode([
            'sub' => $member->UUID,
            'sid' => $session->UUID,
            'iat' => time(),
            'exp' => time() + $jwt->expiry(),
        ]);

        $refreshToken = bin2hex(random_bytes(32));
        $session->RefreshTokenHash = password_hash($refreshToken, PASSWORD_DEFAULT);
        $session->write();

        $nativeTransport = $this->usesNativeRefreshTransport($request);
        if (!$nativeTransport) {
            $this->setRefreshCookie($request, $refreshToken);
        }

        $payload = [
            'accessToken' => $accessToken,
            'member' => [
                'uuid' => $member->UUID,
                'email' => $member->Email,
            ],
            'session' => [
                'uuid' => $session->UUID,
                'label' => $session->Label,
                'expiresAt' => $session->ExpiresAt,
            ],
        ];
        if ($nativeTransport) {
            $payload['refreshToken'] = $refreshToken;
        }
        return $this->respond($payload);
    }

    protected function usesNativeRefreshTransport(HTTPRequest $request): bool
    {
        return strtolower(trim((string) $request->getHeader('X-Restful-Client'))) === 'native'
            && trim((string) $request->getHeader('Origin')) === '';
    }

    protected function setRefreshCookie(HTTPRequest $request, string $refreshToken): void
    {
        Cookie::set(
            self::REFRESH_COOKIE,
            $refreshToken,
            30,
            '/api/auth',
            null,
            Director::isLive() || Director::is_https($request),
            true,
            Cookie::SAMESITE_LAX
        );
    }
}

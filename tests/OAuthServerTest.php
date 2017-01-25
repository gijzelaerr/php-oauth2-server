<?php
/**
 *  Copyright (C) 2016 François Kooman <fkooman@tuxed.net>.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\OAuth\Server;

use DateTime;
use fkooman\OAuth\Server\Exception\OAuthException;
use PDO;
use PHPUnit_Framework_TestCase;

class OAuthServerTest extends PHPUnit_Framework_TestCase
{
    /** @var OAuthServer */
    private $server;

    public function setUp()
    {
        $random = $this->getMockBuilder('\fkooman\OAuth\Server\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $oauthClients = [
            'token-client' => [
                'redirect_uri' => 'http://example.org/token-cb',
                'response_type' => 'token',
                'display_name' => 'Token Client',
            ],
            'code-client' => [
                'redirect_uri' => 'http://example.org/code-cb',
                'response_type' => 'code',
                'display_name' => 'Code Client',
            ],
            'code-client-query-redirect' => [
                'redirect_uri' => 'http://example.org/code-cb?keep=this',
                'response_type' => 'code',
                'display_name' => 'Code Client',
            ],
            'code-client-secret' => [
                'redirect_uri' => 'http://example.org/code-cb',
                'response_type' => 'code',
                'display_name' => 'Code Client',
                'client_secret' => '123456',
            ],
        ];

        $getClientInfo = function ($clientId) use ($oauthClients) {
            if (!array_key_exists($clientId, $oauthClients)) {
                return false;
            }

            return $oauthClients[$clientId];
        };

        $tokenStorage = new TokenStorage(new PDO('sqlite::memory:'));
        $tokenStorage->init();

        $tokenStorage->storeCode('foo', 'XYZ', 'abcdefgh', 'code-client', 'config', 'http://example.org/code-cb', new DateTime('2016-01-01'), 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
        $tokenStorage->storeCode('foo', 'ABC', 'abcdefgh', 'code-client-query-redirect', 'config', 'http://example.org/code-cb?keep=this', new DateTime('2016-01-01'), 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
        $tokenStorage->storeCode('foo', 'DEF', 'abcdefgh', 'code-client-secret', 'config', 'http://example.org/code-cb', new DateTime('2016-01-01'), 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');

        $this->server = new OAuthServer(
            $tokenStorage,
            $random,
            new DateTime('2016-01-01'),
            $getClientInfo
        );
    }

    public function testAuthorizeToken()
    {
        $this->assertSame(
            [
                'client_id' => 'token-client',
                'display_name' => 'Token Client',
                'scope' => 'config',
                'redirect_uri' => 'http://example.org/token-cb',
            ],
            $this->server->getAuthorize(
                [
                    'client_id' => 'token-client',
                    'redirect_uri' => 'http://example.org/token-cb',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ]
            )
        );
    }

    public function testAuthorizeCode()
    {
        $this->assertSame(
            [
                'client_id' => 'code-client',
                'display_name' => 'Code Client',
                'scope' => 'config',
                'redirect_uri' => 'http://example.org/code-cb',
            ],
            $this->server->getAuthorize(
                [
                    'client_id' => 'code-client',
                    'redirect_uri' => 'http://example.org/code-cb',
                    'response_type' => 'code',
                    'scope' => 'config',
                    'state' => '12345',
                    'code_challenge_method' => 'S256',
                    'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
                ]
            )
        );
    }

    public function testAuthorizeTokenPost()
    {
        $this->assertSame(
            'http://example.org/token-cb#access_token=cmFuZG9tXzE.cmFuZG9tXzI&state=12345&expires_in=3600',
            $this->server->postAuthorize(
                [
                    'client_id' => 'token-client',
                    'redirect_uri' => 'http://example.org/token-cb',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ],
                [
                    'approve' => 'yes',
                ],
                'foo'
            )
        );
    }

    public function testAuthorizeTokenPostNotApproved()
    {
        $this->assertSame(
            'http://example.org/token-cb#error=access_denied&error_description=user+refused+authorization&state=12345',
            $this->server->postAuthorize(
                [
                    'client_id' => 'token-client',
                    'redirect_uri' => 'http://example.org/token-cb',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ],
                [
                    'approve' => 'no',
                ],
                'foo'
            )
        );
    }

    public function testAuthorizeCodePost()
    {
        $this->assertSame(
            'http://example.org/code-cb?code=cmFuZG9tXzE.cmFuZG9tXzI&state=12345',
            $this->server->postAuthorize(
                [
                    'client_id' => 'code-client',
                    'redirect_uri' => 'http://example.org/code-cb',
                    'response_type' => 'code',
                    'scope' => 'config',
                    'state' => '12345',
                    'code_challenge_method' => 'S256',
                    'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
                ],
                [
                    'approve' => 'yes',
                ],
                'foo'
            )
        );
    }

    public function testAuthorizeTokenPostRedirectUriWithQuery()
    {
        $this->assertSame(
            'http://example.org/code-cb?keep=this&code=cmFuZG9tXzE.cmFuZG9tXzI&state=12345',
            $this->server->postAuthorize(
                [
                    'client_id' => 'code-client-query-redirect',
                    'redirect_uri' => 'http://example.org/code-cb?keep=this',
                    'response_type' => 'code',
                    'scope' => 'config',
                    'state' => '12345',
                    'code_challenge_method' => 'S256',
                    'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
                ],
                [
                    'approve' => 'yes',
                ],
                'foo'
            )
        );
    }

    public function testPostToken()
    {
        $tokenResponse = $this->server->postToken(
            [
                'grant_type' => 'authorization_code',
                'code' => 'XYZ.abcdefgh',
                'redirect_uri' => 'http://example.org/code-cb',
                'client_id' => 'code-client',
                'code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
            ],
            null,
            null
        );

        $this->assertSame(200, $tokenResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
            $tokenResponse->getHeaders()
        );
        $this->assertSame(
            [
                'access_token' => 'XYZ.cmFuZG9tXzE',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ],
            $tokenResponse->getArrayBody()
        );
    }

    public function testPostTokenSecret()
    {
        $tokenResponse = $this->server->postToken(
            [
                'grant_type' => 'authorization_code',
                'code' => 'DEF.abcdefgh',
                'redirect_uri' => 'http://example.org/code-cb',
                'client_id' => 'code-client-secret',
            ],
            'code-client-secret',
            '123456'
        );

        $this->assertSame(200, $tokenResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
            $tokenResponse->getHeaders()
        );
        $this->assertSame(
            [
                'access_token' => 'DEF.cmFuZG9tXzE',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ],
            $tokenResponse->getArrayBody()
        );
    }

    public function testPostTokenMissingCodeVerifierPublicClient()
    {
    }

    public function testBrokenPostToken()
    {
        try {
            $this->server->postToken(
                [
                ],
                null,
                null
            );
            $this->assertFalse(true);
        } catch (OAuthException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('invalid_request', $e->getMessage());
            $this->assertEquals('missing "grant_type" parameter', $e->getDescription());
        }
    }

    public function testPostTokenSecretInvalid()
    {
        try {
            $this->server->postToken(
                [
                    'grant_type' => 'authorization_code',
                    'code' => 'DEF.abcdefgh',
                    'redirect_uri' => 'http://example.org/code-cb',
                    'client_id' => 'code-client-secret',
                ],
                'code-client-secret',
                '654321'
            );
            $this->assertTrue(false);
        } catch (OAuthException $e) {
            $this->assertEquals(401, $e->getCode());
            $this->assertEquals('invalid_client', $e->getMessage());
            $this->assertEquals('invalid credentials (invalid client_secret)', $e->getDescription());
        }
    }

    public function testSignedAccessToken()
    {
        $signatureKeyPair = 'jq7s7JVBhXk02Nn0Hng4+BNcUlwYOPRR9IXngC51XQDYvQHEgaAvFVHewDvRHtTD5uuVk4cfBbKqT10ckGCJ2Ni9AcSBoC8VUd7AO9Ee1MPm65WThx8FsqpPXRyQYInY';
        $this->server->setSignatureKeyPair(base64_decode($signatureKeyPair));

        $this->assertSame(
            'http://example.org/token-cb#access_token=cmFuZG9tXzE.oEATiQtpc8gzh7FB4eIrW9xsDVwglt_rMT1JqO7rqErlKO0TaJbXCj2zLCDx3nOwPVUwChzPTlr6T59nQ0RdCmNtRnVaRzl0WHpJ&state=12345&expires_in=3600',
            $this->server->postAuthorize(
                [
                    'client_id' => 'token-client',
                    'redirect_uri' => 'http://example.org/token-cb',
                    'response_type' => 'token',
                    'scope' => 'config',
                    'state' => '12345',
                ],
                [
                    'approve' => 'yes',
                ],
                'foo'
            )
        );
    }

    public function testPostReuseCode()
    {
    }

    public function testExpiredCode()
    {
    }

    /**
     * Test getting access_token when one was already issued to same client
     * with same scope.
     */
    public function testAuthorizeTokenExistingAccessToken()
    {
    }
}

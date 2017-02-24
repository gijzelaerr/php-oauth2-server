[![Build Status](https://travis-ci.org/fkooman/php-oauth2-server.svg?branch=master)](https://travis-ci.org/fkooman/php-oauth2-server)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fkooman/php-oauth2-server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fkooman/php-oauth2-server/?branch=master)

# Introduction
This is a very simple OAuth 2.0 server for integration in your own application. 
It has minimal dependencies, but still tries to be secure. The main purpose is 
to be compatible with PHP 5.4.

**NOTE**: if you are not bound to PHP 5.4, use the OAuth 2.0 server of 
the League of Extraordinary Packages! It can be found 
[here](https://oauth2.thephpleague.com/).

# Clients

All (optional) OAuth authorization and token requests MUST always be sent.

# Features

- Simplicity;
- Easy integration with your own application and/or framework;
- Does not force a framework on you;
- Only conforming OAuth 2.0 clients will work;
- There will be no toggles to shoot yourself in the foot;
- Uses [libsodium-php](https://github.com/jedisct1/libsodium-php) for secure 
  random strings, constant time string compare and encryption/signing of 
  tokens and authorization codes;
- Supports [PKCE](https://tools.ietf.org/html/rfc7636);

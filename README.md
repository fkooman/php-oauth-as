# Introduction
This project provides an OAuth 2.0 Authorization Server that is easy to 
integrate with your existing REST services, written in any language, without 
requiring extensive changes.

[![Build Status](https://travis-ci.org/fkooman/php-oauth.png?branch=master)](https://travis-ci.org/fkooman/php-oauth)

# License
Licensed under the GNU Affero General Public License as published by the Free 
Software Foundation, either version 3 of the License, or (at your option) any 
later version.

    https://www.gnu.org/licenses/agpl.html

This rougly means that if you use this software in your service you need to 
make the source code available to the users of your service (if you modify
it). Refer to the license for the exact details.

# Features
* PDO (database abstraction layer for various databases) storage backend for
  OAuth tokens
* OAuth 2.0 (authorization code and implicit grant) support
* SimpleAuth authentication support ([php-simple-auth](https://github.com/fkooman/php-simple-auth/))
* SAML authentication support ([simpleSAMLphp](http://www.simplesamlphp.org)) 
* Token introspection for resource servers

# Screenshots
This is a screenshot of the OAuth consent dialog.

![oauth_consent](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent.png)

# Requirements
On Fedora/CentOS:

    $ sudo yum install php-pdo php-pecl-uuid php-openssl php-password-compat httpd'

We tested Fedora 20 and CentOS 6 and 7.

# Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www
    $ sudo mkdir php-oauth-as
    $ sudo chown fkooman:fkooman php-oauth-as
    $ git clone git://github.com/fkooman/php-oauth.git php-oauth-as
    $ cd php-oauth-as

Install the external dependencies in the `vendor` directory using 
[Composer](http://getcomposer.org/):

    $ php /path/to/composer.phar install

Next make sure to configure the database settings in `config/oauth.ini`, and 
possibly other settings. Make sure you set the correct path for `dsn`. If you
follow the instructions above make it 
`sqlite:/var/www/php-oauth-as/data/db.sqlite`. 

Now to set the permissions and initialize the database, i.e. to install the 
tables, run:
    
    $ mkdir data
    $ chmod 750 data
    $ sudo chown apache:apache data
    $ sudo -u apache ./bin/php-oauth-as-initdb

It is also possible to already preregister some clients which makes sense if 
you want to use the management clients mentioned below. 

    $ sudo -u apache ./bin/php-oauth-as-register docs/apps.json

This should take care of the initial setup. You can now use the 
[manage](https://www.php-oauth.net/app/manage/index.html) and 
[authorize](https://www.php-oauth.net/app/authorize/index.html) clients hosted
on [https://www.php-oauth.net](https://www.php-oauth.net) from. This is secure 
because the access token will never leave the user's browser.

# Management Clients
There are two management clients available:

* [Manage Applications](https://github.com/fkooman/html-manage-applications/). 
* [Manage Authorizations](https://github.com/fkooman/html-manage-authorizations/). 

These clients are written in HTML, CSS and JavaScript only and can be hosted on 
any (static) web server. See the accompanying READMEs for more information.

# SELinux
To update the policy for the data directory use the following commands:

    # semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/php-oauth-as/data(/.*)?'
    # restorecon -R /var/www/php-oauth-as/data

To remove the policy:

    # semanage fcontext -d -t httpd_sys_rw_content_t '/var/www/php-oauth-as/data(/.*)?'

# Apache
There is an example configuration file in `docs/apache.conf`. 

On Red Hat based distributions the file can be placed in 
`/etc/httpd/conf.d/php-oauth-as.conf`. On Debian based distributions the file can
be placed in `/etc/apache2/conf.d/php-oauth-as`. Be sure to modify it to suit your 
environment and do not forget to restart Apache. 

The `bin/configure.sh` script from the previous section outputs a config for 
your system which replaces the `/PATH/TO/APP` with the actual install directory.

## Security
Please follow the recommended Apache configuration, and set the headers 
mentioned there to increase security. Important headers are 
[`Content-Security-Policy`](https://developer.mozilla.org/en-US/docs/Security/CSP), 
[`X-Frame-Options`](https://developer.mozilla.org/en-US/docs/HTTP/X-Frame-Options) and 
[`Strict-Transport-Security`](https://developer.mozilla.org/en-US/docs/Security/HTTP_Strict_Transport_Security). 

You MUST also disable any connection to this service over HTTP, and only use
HTTPS.

# Authentication
There are thee plugins provided to authenticate users:

* `DummyResourceOwner` - one static account configured in `config/oauth.ini`
* `SimpleAuthResourceOwner` - very simple username/password authentication \
  library (DEFAULT)
* `SspResourceOwner` - simpleSAMLphp plugin for SAML authentication

You can configure which plugin to use by modifying the `authenticationMechanism`
setting in `config/oauth.ini`.

## Entitlements
A more complex part of the authentication and authorization is the use of 
entitlements. This is a bit similar to scope in OAuth, only entitlements are 
for a specific resource owner, while scope is only for an OAuth client.

The entitlements are for example used by the `php-oauth-as` API. It is possible to 
write a client application that uses the `php-oauth-as` API to manage OAuth client 
registrations. The problem now is how to decide who is allowed to manage 
OAuth client registrations. Clearly not all users who can successfully 
authenticate, but only a subset. The way now to determine who gets to do what
is accomplished through entitlements. 

In particular, the authenticated user (resource owner) needs to have the 
`http://php-oauth.net/entitlement/manage` entitlement in order to be able to modify 
application registrations. The entitlements are part of the resource owner's 
attributes. This maps perfectly to SAML attributes obtained through the
simpleSAMLphp integration.

## DummyResourceOwner
For instance in the `DummyResourceOwner` section, the user has this entitlement
as shown in the snippet below:

    ; Dummy Configuration
    [DummyResourceOwner]
    uid           = "fkooman"
    entitlement[] = "http://php-oauth.net/entitlement/manage"
    entitlement[] = "foo"
    entitlement[] = "bar"

Here you can see that the resource owner will be granted the 
`http://php-oauth.net/entitlement/manage`, `foo` and `bar` entitlements. As there is only 
one account in the `DummyResourceOwner` configuration it is quite boring.

## SimpleAuthResourceOwner 
The entitlements for the `SimpleAuthResourceOwner` are configured in the 
entitlement file, located in `config/simpleAuthEntitlement.json`. An example is 
also available. You can assign entitlements to resource owner identifiers.

The users listed match the default set from `php-simple-auth`. You can copy
the example file to `config/simpleAuthEntitlement.json` and modify it for your
needs. This authentication backend is not meant for production use as it will
require a lot of manual configuration per user. Better use the 
`SspResourceOwner` authentication library for serious deployments.

For this authentication source you also need to install and configure
([php-simple-auth](https://github.com/fkooman/php-simple-auth/)).

## SspResourceOwner
Now, for the `SspResourceOwner` configuration it is a little bit more complex.
Dealing with this is left to the simpleSAMLphp configuration and we just 
expect a certain configuration.

In the configuration file `config/oauth.ini` only a few aspects can be 
configured. To configure the SAML integration, make sure the following settings 
are at least correct.

    authenticationMechanism = "SspResourceOwner"

    ; simpleSAMLphp configuration
    [SspResourceOwner]
    sspPath = "/var/simplesamlphp"
    authSource = "default-sp"
    ;resourceOwnerIdAttribute = "eduPersonPrincipalName"

Now on to the simpleSAMLphp configuration. You configure simpleSAMLphp 
according to the manual. The snippets below will help you with the 
configuration to get the entitlements right.

First the `metadata/saml20-idp-remote.php` to configure the IdP that is used
by the simpleSAMLphp as SP:

    $metadata['http://localhost/simplesaml/saml2/idp/metadata.php'] = array(
        'SingleSignOnService' => 'http://localhost/simplesaml/saml2/idp/SSOService.php',
        'SingleLogoutService' => 'http://localhost/simplesaml/saml2/idp/SingleLogoutService.php',
        'certFingerprint' => '4bff319a0fa4903e4f6ed52956fb02e1ebec5166',
    );

You need to modify this (the URLs and the certificate fingerprint) to work with 
your IdP and possibly the attribute mapping rules. 

# Resource Servers
If you are writing a resource server (RS) an API is available to verify the 
`Bearer` token you receive from the client. Currently a draft specification
(draft-richer-oauth-introspection) is implemented to support this.

An example, the RS gets the following `Authorization` header from the client:

    Authorization: Bearer eeae9c3366af8cb7acb74dd5635c44e6

Now in order to verify it, the RS can send a request to the OAuth service:

    $ curl http://localhost/php-oauth-as/introspect.php?token=eeae9c3366af8cb7acb74dd5635c44e6

If the token is valid, a response (formatted here for display purposes) will be 
given back to the RS:

    {
        "active": true, 
        "client_id": "testclient", 
        "exp": 2366377846, 
        "iat": 1366376612, 
        "scope": "foo bar", 
        "sub": "fkooman", 
        "x-entitlement": [
            "urn:x-foo:service:access", 
            "urn:x-bar:privilege:admin"
        ]
    }

The RS can now figure out more about the resource owner. If you provide an 
invalid access token, the following response is returned:

    {
        "active": false
    }

If your service needs to provision a user, the field `sub` SHOULD to be used 
for that. The `scope` field can be used to determine the scope the client was 
granted by the resource owner.

There are two proprietary extensions to this format: `x-entitlement` and 
`x-ext`. The former one gives the entitlement values as an array. The `x-ext` 
provides additional "raw" information obtained through the authentication 
framework. For instance all SAML attributes released are placed in this 
`x-ext` field. They can contain for instance an email address or display name.

A library written in PHP to access the introspection endpoint is available 
[here](https://github.com/fkooman/php-oauth-lib-rs).

# Resource Owner Data
Whenever a resource owner successfully authenticates using some of the supported
authentication mechanisms, some user information, like the entitlement a user
has, is stored in the database. This is done to give this information to 
registered clients and to resource servers that have a valid access token.

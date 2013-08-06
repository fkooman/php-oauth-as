# Introduction
This project aims at providing a stand-alone OAuth v2 Authorization Server that
is easy to integrate with your existing REST services, written in any language, 
without requiring extensive changes.

[![Build Status](https://www.travis-ci.org/fkooman/php-oauth.png?branch=master)](https://www.travis-ci.org/fkooman/php-oauth)

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
* OAuth v2 (authorization code and implicit grant) support
* SimpleAuth authentication support ([php-simple-auth](https://github.com/fkooman/php-simple-auth/))
* SAML authentication support ([simpleSAMLphp](http://www.simplesamlphp.org)) 
* [Mozilla Persona](https://login.persona.org/) authentication support using 
([php-browserid](https://github.com/fkooman/php-browserid/))
* Token introspection for resource servers

# Screenshots
This is a screenshot of the OAuth consent dialog.

![oauth_consent](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent.png)

# Requirements
The installation requirements on Fedora/CentOS can be installed like this:

    $ su -c 'yum install git php-pdo php httpd'

On Debian/Ubuntu:

    $ sudo apt-get install git sqlite3 php5 php5-sqlite

# Installation
*NOTE*: in the `chown` line you need to use your own user account name!
*NOTE*: On Ubuntu (Debian) you would typically install in `/var/www/php-oauth` and not 
in `/var/www/html/php-oauth` and you use `sudo` instead of `su -c`.

    $ cd /var/www/html
    $ su -c 'mkdir php-oauth'
    $ su -c 'chown fkooman:fkooman php-oauth'
    $ git clone git://github.com/fkooman/php-oauth.git
    $ cd php-oauth

Install the external dependencies in the `vendor` directory using [Composer](http://getcomposer.org/):

    $ php /path/to/composer.phar install

Now you can create the default configuration files, the paths will be 
automatically set, permissions set and a sample Apache configuration file will 
be generated and shown on the screen (see below for more information on
Apache configuration).

    $ docs/configure.sh

Next make sure to configure the database settings in `config/oauth.ini`, and 
possibly other settings. If you want to keep using SQlite you are good to go 
without fiddling with the database settings. Now to initialize the database,
i.e. to install the tables, run:

    $ php docs/initOAuthDatabase.php

It is also possible to already preregister some clients which makes sense if 
you want to use the management clients mentioned below. The sample registrations
are listed in `docs/registration.json`. By default they point to 
`http://localhost`, but if you run this software on a "real" domain you need to
modify the `docs/registration.json` file to point to your domain name and 
full path where the management clients will be installed.

To modify the domain of where the clients will be located in one go, you can
run the following command:

    $ sed 's|http://localhost|https://www.example.org|g' docs/registration.json > docs/myregistration.json

You can still modify the `docs/myregistration.json` by hand if you desire, and 
then load them in the database:

    $ php docs/registerClients.php docs/myregistration.json

This should take care of the initial setup and you can now move to installing 
the management clients, see below.

# Management Clients
There are two reference management clients available:

* [Manage Applications](https://github.com/fkooman/html-manage-applications/). 
* [Manage Authorizations](https://github.com/fkooman/html-manage-authorizations/). 

These clients are written in HTML, CSS and JavaScript only and can be hosted on 
any (static) web server. See the accompanying READMEs for more information. If 
you followed the client registration in the previous section they should start
working immediately if you install the applications at the correct URL. Do not
forget to enable the management API in `config/oauth.ini`.

# SELinux
The install script already takes care of setting the file permissions of the
`data/` directory to allow Apache to write to the directory. If you want to use
the Mozilla Persona authentication plugin you also need to give Apache permission 
to access the network. These permissions can be given by using `setsebool` as 
root:

    $ sudo setsebool -P httpd_can_network_connect=on

If you want the logger to send out email, you need the following as well:

    $ sudo setsebool -P httpd_can_sendmail=on

This is only for Red Hat based Linux distributions like RHEL, CentOS and 
Fedora.

If you want the labeling of the `data/` directory to survive file system 
relabeling you have to update the policy as well.

*FIXME*: add how to update the policy...

# Apache
There is an example configuration file in `docs/apache.conf`. 

On Red Hat based distributions the file can be placed in 
`/etc/httpd/conf.d/php-oauth.conf`. On Debian based distributions the file can
be placed in `/etc/apache2/conf.d/php-oauth`. Be sure to modify it to suit your 
environment and do not forget to restart Apache. 

The `docs/configure.sh` script from the previous section outputs a config for 
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
  library
* `SspResourceOwner` - simpleSAMLphp plugin for SAML authentication
* `PersonaResourceOwner` - Mozilla Persona plugin

You can configure which plugin to use by modifying the `authenticationMechanism`
setting in `config/oauth.ini`.

## Entitlements
A more complex part of the authentication and authorization is the use of 
entitlements. This is a bit similar to scope in OAuth, only entitlements are 
for a specific resource owner, while scope is only for an OAuth client.

The entitlements are for example used by the `php-oauth` API. It is possible to 
write a client application that uses the `php-oauth` API to manage OAuth client 
registrations. The problem now is how to decide who is allowed to manage 
OAuth client registrations. Clearly not all users who can successfully 
authenticate, but only a subset. The way now to determine who gets to do what
is accomplished through entitlements. 

In the `[Api]` section the management API can be enabled:

    [Api]
    enableApi = TRUE

In particular, the authenticated user (resource owner) needs to have the 
`urn:x-oauth:entitlement:applications` entitlement in order to be able to modify 
application registrations. The entitlements are part of the resource owner's 
attributes. This maps perfectly to SAML attributes obtained through the
simpleSAMLphp integration.

## DummyResourceOwner
For instance in the `DummyResourceOwner` section, the user has this entitlement
as shown in the snippet below:

    ; Dummy Configuration
    [DummyResourceOwner]
    uid           = "fkooman"
    entitlement[] = "urn:x-oauth:entitlement:applications"
    entitlement[] = "foo"
    entitlement[] = "bar"

Here you can see that the resource owner will be granted the 
`urn:x-oauth:entitlement:applications`, `foo` and `bar` entitlements. As there is only 
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

    $ curl http://localhost/php-oauth/introspect.php?token=eeae9c3366af8cb7acb74dd5635c44e6

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

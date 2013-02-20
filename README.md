# Introduction
This project aims at providing a stand-alone OAuth v2 Authorization Server that
is easy to integrate with your existing REST services, written in any language, 
without requiring extensive changes.

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
* [BrowserID](http://browserid.org) authentication support using 
([php-browserid](https://github.com/fkooman/php-browserid/))

# Screenshots
Below are some screenshots of the OAuth consent dialog, the first one is the 
default view, the second is the view when one clicks the "Details" button.

![oauth_consent_simple](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent_simple.png)

![oauth_consent_advanced](https://github.com/fkooman/php-oauth/raw/master/docs/oauth_consent_advanced.png)

# Requirements
The installation requirements on Fedora/CentOS can be installed like this:

    $ su -c 'yum install git php-pdo php httpd'

On Debian/Ubuntu:

    $ sudo apt-get install git sqlite3 php5 php5-sqlite

# Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www/html
    $ su -c 'mkdir php-oauth'
    $ su -c 'chown fkooman:fkooman php-oauth'
    $ git clone git://github.com/fkooman/php-oauth.git
    $ cd php-oauth

Install the external dependencies:

    $ sh docs/install_dependencies.sh

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

*NOTE*: On Ubuntu (Debian) you would typically install in `/var/www/php-oauth` and not 
in `/var/www/html/php-oauth` and you use `sudo` instead of `su -c`.

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
the BrowserID authentication plugin you also need to give Apache permission to 
access the network. These permissions can be given by using `setsebool` as root:

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

# Authentication
There are thee plugins provided to authenticate users:

* `DummyResourceOwner` - one static account configured in `config/oauth.ini`
* `SimpleAuthResourceOwner` - very simple username/password authentication \
  library
* `SspResourceOwner` - simpleSAMLphp plugin for SAML authentication
* `BrowserIDResourceOwner` - BrowserID / Mozilla Persona plugin

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
    resourceOwnerId = "1234-5678-9999"

    [DummyResourceOwnerAttributes]
    uid[]         = "fkooman"
    displayName[] = "François Kooman"
    eduPersonEntitlement[] = "urn:x-oauth:entitlement:applications"
    eduPersonEntitlement[] = "foo"
    eduPersonEntitlement[] = "bar"

Here you can see that the resource owner will be granted the 
`urn:x-oauth:entitlement:applications`, `foo` and `bar` entitlements. As there is only 
one account in the `DummyResourceOwner` configuration it is quite boring.

## SimpleAuthResourceOwner 
The entitlements for the `SimpleAuthResourceOwner` are configured in the 
attributes file, located in `config/simpleAuthAttributes.json`. An example is 
also available. The listed attributes there also contain an 
`eduPersonEntitlement` attribute for the users `admin` and `teacher`. These 
users match the default example set from `php-simple-auth`. You can copy
the example file to `config/simpleAuthAttributes.json` and modify it for your
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

Now on to the simpleSAMLphp configuration. You configure simpleSAMLphp 
according to the manual. The snippets below will help you with the 
configuration to get the entitlements right.

First the `metadata/saml20-idp-remote.php` to configure the IdP that is used
by the simpleSAMLphp as SP:

    $metadata['http://localhost/simplesaml/saml2/idp/metadata.php'] = array(
        'SingleSignOnService' => 'http://localhost/simplesaml/saml2/idp/SSOService.php',
        'SingleLogoutService' => 'http://localhost/simplesaml/saml2/idp/SingleLogoutService.php',
        'certFingerprint' => '4bff319a0fa4903e4f6ed52956fb02e1ebec5166',

        // clean up the attributes received from the IdP and modify them to use
        // our naming convention
        'authproc' => array(
            50 => array(
                'class' => 'core:AttributeMap',
                'urn2name',
            ),
        ),

    );

You need to modify this (the URLs and the certificate fingerprint) to work with 
your IdP and possibly the attribute mapping rules. 

Rule `50` changes the attributes to their base name. For example, if your 
IdP provides the `urn:mace:dir:attribute-def:eduPersonEntitlement` attribute, 
this is now reduced to just `eduPersonEntitlement`, the same for all the 
other `urn:mace` prefixed attributes. 

# Resource Servers
If you are writing a resource server (RS) an API is available to verify the 
`Bearer` token you receive from the client. It is the same API as 
[used by Google](https://developers.google.com/accounts/docs/OAuth2Login#validatingtoken).

An example, the RS gets the following `Authorization` header from the client:

    Authorization: Bearer eeae9c3366af8cb7acb74dd5635c44e6

Now in order to verify it, the RS can send a request to the OAuth service:

    $ curl http://localhost/php-oauth/tokeninfo.php?access_token=eeae9c3366af8cb7acb74dd5635c44e6

If the token is valid, a response (formatted here for display purposes) will be 
given back to the RS:

    {
        "attributes": {
            "displayName": [
                "Margie Korn"
            ], 
            "eduPersonEntitlement": [
                "urn:x-oauth:entitlement:administration"
            ], 
            "uid": [
                "teacher"
            ]
        }, 
        "audience": "html-view-grades", 
        "client_id": "html-view-grades", 
        "expires_in": 3567, 
        "resource_owner_id": "6b976124bc1747b3e8b249fe3bd6edff16d546ac", 
        "scope": "grades", 
        "user_id": "6b976124bc1747b3e8b249fe3bd6edff16d546ac"
    }

The RS can now figure out more about the resource owner. If you provide an 
invalid access token, an error is returned:

    HTTP/1.1 400 Bad Request

    {"error":"invalid_token","error_description":"the token was not found"}

If your service needs to provision a user, the field `resource_owner_id` or 
its alias `user_id` SHOULD to be used for that. The `scope` field can be used
to determine the scope the client was granted by the resource owner.

An example RS that uses this protocol written in PHP is available 
[here](https://github.com/fkooman/php-oauth-example-rs). As this is so simple, 
it should be straightforward to implement this token verification in any 
language.

# Clients
Clients can also verify the access token and retrieve more information about
the resource owner. It is the same API as 
[used by Google](https://developers.google.com/accounts/docs/OAuth2Login#validatingtoken). 
However, this endpoint is no replacement for proper 
authentication at the service. One SHOULD NOT use the OAuth authorization 
server to authenticate users! The only clients that SHOULD ever use this 
endpoint are "user-agent-based-applications" as defined in the OAuth 
specification, i.e.: applications written in HTML, JavaScript and CSS where the 
endpoint is used to retrieve information from the authenticated user to 
customize the application view.

If clients use the access token verification endpoint they should make sure 
that the `client_id` field or its alias `audience` matches the OAuth client ID
they registered at the service.

# Resource Owner Data
Whenever a resource owner successfully authenticates, the attributes belonging
to that user are stored in the database. This is done to give the information
to registered clients and to resource servers that have a valid access token.

Care should be taken in making sure that only the attributes that are needed
for a correct service operation are provided as attributes. Also, this data, 
which may be privacy sensitive SHOULD be removed from the database after a 
certain amount of time expired when the user did not login to the service.

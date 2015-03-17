# Upgrading

## 0.2.x to 0.3.x
A new configuration option was added to allow the API script to access the 
token endpoint without validating the SSL certificate. 

    disableServerCertCheck = true

It defauls to `false`. 

## 0.1.8 to 0.2.x
In order to upgrade from 0.1.8 to 0.2.x a number of things need to change. The
most important are the configuration file changes and the database changes.

The configuration file changes are easy, but the database changes are more
extensive and require some fiddling.

If you run from a git checkout you also need to run 
`php /path/to/composer.phar update` after checking out the new version.

### Configuration
The following fields are removed from the configuration file and no longer have
any effect:

    serviceName
    serviceLogoUri
    serviceLogoWidth
    serviceLogoHeight
    allowRemoteStorageClients

    [PdoStorage]
    persistentConnection
    [SimpleAuthResourceOwner]

The following configuration options were renamed:

    authenticationMechanism -> authenticationPlugin

The following configuration option was added to allow for `wildcard` support 
for redirect URIs:

    allowRegExpRedirectUriMatch

There are now three supported authentication backends:

    BasicAuthentication
    MellonAuthentication
    SimpleSamlAuthentication

These can be the values of `authenticationPlugin`. Each of them has their own 
configuration section as well. The `SimpleAuthResourceOwner` backend support 
was removed. The `SimpleSamlAuthentication` backend is deprecated and one 
SHOULD use `MellonAuthentication` instead.

See the example configuration file in `config/oauth.ini.defaults` for more 
information on how to update the configuration for each of the authentication
backends.

See below on how to update the consent dialog template if you have the need.

### Entitlements
All entitlements are now configured through the file 
`config/entitlements.json` and no longer through the authentication backend. 
This makes it much easier to configure and does not require support from the
authentication backend to support entitlements. 

We can go a number of ways after this:
- remove `entitlements.json` and add it to the normal `config/oauth.ini` file;
- remove entitlements altogether and leave it up to the resource server (the
  RS can use the `sub` field for entitlement mapping through the introspection
  endpoint.

The second solution seems to be the best one, so maybe it is better to not 
depend too much on this functionality being available.

### Database
TBD

### Housekeeping
A new script is available to delete expired tokens from the database to be run
from a "cron" task. The script is called `php-oauth-as-housekeeping` and should
be run periodically, say once every day. If you are using SQlite as a database
the script should be run as the `apache` user, or `root` (not recommended).

The following `crontab(5)` entry can be used to run the housekeeping script, 
for example, every night 5 minutes after midnight.

    5 0 * * *     /usr/bin/php-oauth-as-housekeeping

### UI Customization
One can now customize the consent dialog by copying the file 
`views/askAuthorization.twig` to `config/views/askAuthorization.twig` and 
modifying it there.

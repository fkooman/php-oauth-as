# Upgrading

## 0.1.8 to 0.2.0
In order to upgrade from 0.1.8 to 0.2.0 a number of things need to change. The
most important are the configuration file changes and the database changes.

The configuration file changes are easy, but the database changes are more
extensive and require some fiddling.

If you run from a git checkout you also need to run 
`php /path/to/composer.phar upgrade` after checking out the new version.

### Configuration
The following field are removed from the configuration file and no longer have
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

### Entitlements
All entitlements are now configured through the file 
`config/entitlements.json` and no longer through the authentication backend. 
This makes it much easier to configure and does not require support from the
authentication backend to support entitlements. In the future it may be 
possible to use another source for entitlements.

### Database
TBD

### Housekeeping
A new script is available to delete expired tokens from the database to be run
from a "cron" task. The script is called `php-oauth-as-housekeeping` and should
be run periodically, say once every day. 

### UI Customization
One can now customize the consent dialog by copying the file 
`views/askAuthorization.twig` to `config/views/askAuthorization.twig` and 
modifying it there.

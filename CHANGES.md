# Changes

## 0.4.2
- update dependencies

## 0.4.1
- update dependencies

## 0.4.0
- remove entitlements completely, this is up to the resource server
- update `fkooman/rest`
- remove REST API for client management and client authorizations, use built in
  management interfaces instead
- remove the config option to disable certificate check, no longer relevant
- simplify the httpd config to the bare minimum
- remove simpleSAMLphp support, deprecated

## 0.3.2
- implement an 'approvals.php' script to manage the approvals per user, so
  no separate client is needed for this. Step one in making the server 
  self-managable, next up is the application registration
- add some more (missing) entitlement tests regarding the API (issue #36)
- update `fkooman/rest`
- update `fkooman/rest-plugin-bearer` 
- remove `x-entitlement` support from introspection data
- update API to use entitlements file directly instead of through 
  introspection as that is no longer provided

## 0.3.1 - 2015-04-13
- update `fkooman/rest` and all authentication plugins

## 0.3.0 - 2015-03-17
- update to `draft-ietf-oauth-introspection-05`, does not break compatibility
- update to `fkooman/rest` 0.7.4, update all code

## 0.2.3 - 2015-02-17
- determine the introspect URI automatically for use by the `web/api.php` 
  script instead of hard coding `http://localhost/php-oauth-as/introspect.php`

## 0.2.2 - 2015-02-15
- include support to allow clients to be automatically approved (issue #24)
- allow for using limited regexp as redirect_uri, they still should match 
  valid URI checks (issue #34)
- no longer provide CORS headers on introspect endpoint in httpd 
  config as clients should never use this endpoint
- restructure code to use a separate IO class for random number and time
  handling
- increase code coverage by writing better unit tests

## 0.2.1 - 2015-02-12
- fix refresh_token generation for implicit authorized clients
- fix redirects when client did not put a redirect_uri in the query
  parameters in the authorization request
- API calls now always return JSON, also on successful calls to not give
  problems in browser JS parsing

## 0.2.0 - 2015-02-11
- add `php-oauth-as-housekeeping` script to be run from crontab to ocassionaly
  delete expired tokens from the database
- major overhaul of configuration for authentication backends
- entitlements are for now only available through the configuration file, no
  longer through authentication, this was used for the SAML authentication 
  before, but for non of the other authentication libraries
- simplify the consent dialog
- no longer pass the `IniReader` object down the stack, keep all configuration 
  handling it in the `web/` scripts
- refactor DB to accept a `PDO` object in the constructor, no longer provide
  persistent configuration option
- rename client types to just `code` and `token` and get rid of the 
  `web_application`, `native_application` and `user_agent_based_application`
  types
- rename `ClientRegistration` class to `ClientData` and refactor all code 
  to use the object instead of object to array conversion
- allow regexp redirect_uris only by setting the explicit configuration 
  option
- remove template customizations from config file and implement template
  override by allowing templates to be placed in `config/views` from 
  `views`
- remove remoteStorage automatic client registration support
- no longer support public 'code' clients, i.e. without password so without
  password only `token` type is supported
- remove resource owner hinting
- better input validation
- actually use the matching `redirect_uri` instead of the registered 
  `redirect_uri`

## 0.1.8 - 2014-12-17
- move to `fkooman/ini` from `fkooman/config`
- update `fkooman/rest` to new version
- depend on `fkooman/rest-plugin-basic` and `fkooman/rest-plugin-bearer`
- update API substantially

## 0.1.7 - 2014-10-01
- fix small bug in remoteStorage client dynamic registation

## 0.1.6 - 2014-10-01
- better error handling for unregistered clients that are not 
  remoteStorage clients when dynamic registration for remoteStorage
  clients is enabled
- require `client_id` and `redirect_uri` to have the same host for
  remoteStorage clients

## 0.1.5 - 2014-09-24
- fix the registration script
- do not require `client_id` and `redirect_uri` to match for remoteStorage 
  clients

## 0.1.4 - 2014-09-23
- update dependency `fkooman/rest`
- support dynamic registration for [remoteStorage](http://remotestorage.io) 
  clients

## 0.1.3 - 2014-09-16
- update dependencies `fkooman/json`, `fkooman/config`, `fkooman/rest`

## 0.1.2 - 2014-09-01

## 0.1.1 - 2014-08-30

## 0.1.0 - 2014-08-12

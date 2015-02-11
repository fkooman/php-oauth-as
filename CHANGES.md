# Changelog

## 0.2.1
- fix refresh_token generation for implicit authorized clients
- fix redirects when client did not put a redirect_uri in the query
  parameters in the authorization request

## 0.2.0
- no longer need `fkooman/oauth-common`
- better input validation
- major overhaul of configuration for authentication backends
- entitlements are for now only available through the configuration file, no
  longer through authentication, this was used for the SAML authentication 
  before, but for non of the other authentication libraries
- simplify the consent dialog
- add `php-oauth-as-housekeeping` script to be run from crontab to ocassionaly
  delete expired tokens from the database
- no longer pass the `IniReader` object down the stack, keep all configuration 
  handling it in the `web/` scripts
- refactor DB to accept a `PDO` object in the constructor, no longer provide
  persistent configuration option
- remove remoteStorage automatic client registration support
- no longer support public 'code' clients, i.e. without password so without
  password only `token` type is supported
- rename client types to just `code` and `token` and get rid of the 
  `web_application`, `native_application` and `user_agent_based_application`
  types
- remove resource owner hinting
- update `fkooman/rest-plugin-basic`
- include support to allow clients to be automatically approved (issue #24)
- API calls now always return JSON, also on successful calls to not give
  problems in browser JS parsing
- rename `ClientRegistration` class to `ClientData` and refactor all code 
  to use the object instead of object to array conversion
- allow for using limited regexp as redirect_uri, they still should match 
  valid URI checks (issue #34)
- actually use the matching `redirect_uri` instead of the registered 
  `redirect_uri`
- allow regexp redirect_uris only by setting the explicit configuration 
  option
- remove template customizations from config file and implement template
  override by allowing templates to be placed in `config/views` from 
  `views`

## 0.1.8
- move to `fkooman/ini` from `fkooman/config`
- update `fkooman/rest` to new version
- depend on `fkooman/rest-plugin-basic` and `fkooman/rest-plugin-bearer`
- update API substantially

## 0.1.7
- fix small bug in remoteStorage client dynamic registation

## 0.1.6
- better error handling for unregistered clients that are not 
  remoteStorage clients when dynamic registration for remoteStorage
  clients is enabled
- require `client_id` and `redirect_uri` to have the same host for
  remoteStorage clients

## 0.1.5
- fix the registration script
- do not require `client_id` and `redirect_uri` to match for remoteStorage 
  clients

## 0.1.4
- update dependency `fkooman/rest`
- support dynamic registration for [remoteStorage](http://remotestorage.io) 
  clients

## 0.1.3
- update dependencies `fkooman/json`, `fkooman/config`, `fkooman/rest`

## 0.1.2

## 0.1.1

## 0.1.0


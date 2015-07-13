[![Build Status](https://travis-ci.org/fkooman/php-oauth-as.png?branch=master)](https://travis-ci.org/fkooman/php-oauth-as)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fkooman/php-oauth-as/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fkooman/php-oauth-as/?branch=master)

# Introduction
This is an OAuth 2.0 Authorization Server written in PHP that is easy to 
integrate with your existing REST services, whether or not they are written in
PHP. It will require minimal changes to your existing software.

Part of the development of this software was made possible by 
[SURFnet](https://www.surfnet.nl).

# Features
* Authorization code and implicit grant profile support
* `BasicAuthenication` Backend (using username/password)
* `MellonAuthentication` Backend (SAML)
* PDO database backend support
* Token introspection for resource servers
* Management interfaces to manage client registration and authorized clients

# Installation
The prefered method of installation is to use the RPM packages. The RPM 
packages can for now be found in the 
[repository](https://copr.fedoraproject.org/coprs/fkooman/php-oauth/). For 
setting up a development environment, see below.

To enable the repositories on Fedora do the following:

    $ sudo yum -y install yum-plugin-copr
    $ yum copr enable -y fkooman/php-base
    $ yum copr enable -y fkooman/php-oauth
    $ yum install -y php-oauth-as

Restart Apache:

    $ sudo service httpd restart

You can now configure the OAuth server in `/etc/php-oauth-as/oauth.ini`. After 
this is done you can initialize the database.

    $ sudo -u apache php-oauth-as-initdb

# Development Requirements
On Fedora/CentOS:

    $ sudo yum install php-pdo php-openssl httpd'

You also need to download [Composer](https://getcomposer.org/).

The software is being developed on Fedora 21, but should run on CentOS 6 and 
CentOS 7 and should also work on RHEL 6 and RHEL 7.

# Development Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www
    $ sudo mkdir php-oauth-as
    $ sudo chown fkooman.fkooman php-oauth-as
    $ git clone https://github.com/fkooman/php-oauth-as.git
    $ cd php-oauth-as
    $ /path/to/composer.phar install
    $ mkdir data
    $ sudo chown apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/php-oauth-as/data(/.*)?'
    $ sudo restorecon -R /var/www/php-oauth-as/data
    $ cp config/oauth.ini.defaults config/oauth.ini

Edit `oauth.ini` to match the configuration. You need to at least modify the
following line, and set it to the value shown here:

    dsn = "sqlite:/var/www/php-oauth-as/data/db.sqlite"

Now initialize the database:

    $ sudo -u apache bin/php-oauth-as-initdb 

Copy paste the contents of the Apache section in the file
`/etc/httpd/conf.d/php-oauth-as.conf`:

    Alias /php-oauth-as /var/www/php-oauth-as/web

    <Directory /var/www/php-oauth-as/web>
        AllowOverride None

        Require local
        #Require all granted

        SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1
    </Directory>

Now restart Apache:

    $ sudo service httpd restart

If you ever remove the software, you can also remove the SELinux context:

    $ sudo semanage fcontext -d -t httpd_sys_rw_content_t '/var/www/php-oauth-as/data(/.*)?'

# Templates
The templates in the `views` folder can be copied to the `config/views` folder
and overridden that way. If you install through the RPM package you can copy
the files from `/usr/share/php-oauth-as/views` to `/etc/php-oauth-as/views` and
modify them there. There you can also update them to point to a different CSS
file for example.

# Authentication
There ares some plugins provided for user authentication:

* `BasicAuthentication` - Simple static username/password authentication 
  configured through `config/oauth.ini` (**DEFAULT**)
* `MellonAuthentication` - Plugin for SAML authentication

You can configure which plugin to use by modifying the 
`authenticationPlugin` setting in `config/oauth.ini`.

You do need to configure these authentication backends separately. See the 
respective documentation for those projects.

# Housekeeping
In order to delete stale tokens a housekeeping script is available, 
`php-oauth-as-housekeeping`. It should be ran periodically using `crontab(5)` 
or a similar mechanism. For example, to run it every night 5 minutes after 
midnight use the following as a cron entry:

    5 0 * * *     /usr/bin/php-oauth-as-housekeeping

When using SQlite this script should be run as the same user as the web server,
or as root (**NOT RECOMMENDED**).

# Resource Servers
If you are writing a resource server (RS) an API is available to verify the 
`Bearer` token you receive from the client. Currently a draft specification
`draft-richer-oauth-introspection` is implemented to support this.

An example, the RS gets the following `Authorization` header from the client:

    Authorization: Bearer 40da7666a9f76b4b6b87969a7cc06421

Now in order to verify it, the RS can send a request to the OAuth service:

    $ curl -d 'token=40da7666a9f76b4b6b87969a7cc06421' https://localhost/php-oauth-as/introspect.php
    {
        "active": true,
        "client_id": "2352ea44-612d-448b-be10-6e29562e5130",
        "exp": 1433112094,
        "iat": 1433108494,
        "iss": "localhost",
        "scope": "http://php-oauth.net/scope/manage",
        "sub": "admin",
        "token_type": "bearer",
        "user_id": "admin"
    }
    
This way the RS can figure out more about the resource owner who authorized 
the client. If you provide an invalid access token, the following response is 
returned:

    {
        "active": false
    }

If your service needs to provision a user, the field `sub` SHOULD to be used 
for that. The `scope` field can be used to determine the scope the client was 
granted by the resource owner.

A plugin for `fkooman/rest`, `fkooman/rest-plugin-bearer` is available to 
integrate with this OAuth 2.0 AS service using 
[Composer](https://getcomposer.org). Or see the project 
[site](https://github.com/fkooman/php-lib-rest-plugin-bearer).

# Management
It is possible to manage the clients by going to: 
        
    https://localhost/php-oauth-as/manage.php

You can remove the approvals by going to:

    https://localhost/php-oauth-as/approvals.php

# License
Licensed under the GNU Affero General Public License as published by the Free 
Software Foundation, either version 3 of the License, or (at your option) any 
later version.

    https://www.gnu.org/licenses/agpl.html

This roughly means that if you use this software in your service you need to 
make the source code available to the users of your service (if you modify
it). Refer to the license for the exact details.

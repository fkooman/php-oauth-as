# Add eduPersonEntitlement to your LDAP
Although this is really out of scope here, I found it useful to keep this 
information here as documentation about this doesn't seem to be widely 
available on the Internet, at least not for 389 Directory Server.

This document is only relevant if you are using the simpleSAMLphp 
authentication backend together with some LDAP server.

## 389 Directory Server
We assume you are running a recently modern 389 Directory Server instance, for 
example on Fedora 20.

Make sure you are able to use `ldapsearch` to query your LDAP and are able to 
authenticate using the Directory Manager account. For example:

    $ ldapsearch -W -H ldap://localhost -D 'cn=Directory Manager' -b 'ou=People,dc=wind,dc=surfnet,dc=nl' dn

This should return all users in the `People` organizational unit. Now the 
good thing is that 389 Directory Server already has the `eduPerson` schema 
available by default, at `/etc/dirsrv/schema/60eduperson.ldif`.

### Adding `eduPerson` `objectClass`
All we have to do is adding the `eduPerson` `objectClass` to an existing entry
for a user, that can easily be done using the following `LDIF` file:

    dn: uid=fkooman,ou=People,dc=wind,dc=surfnet,dc=nl
    changetype: modify
    add: objectClass
    objectClass: eduPerson

Now you can add this to the LDAP using `ldapmodify`:

    $ ldapmodify -W -H ldap://localhost -D 'cn=Directory Manager' < add_eduPerson.ldif

That is all to add the `objectClass` to the entry for the user `fkooman`.

### Adding the `eduPersonEntitlement`
To add the entitlements `http://php-oauth.net/entitlement/manage`, `foo`, `bar` 
and `baz`, the following `LDIF` file can be used:

    dn: uid=fkooman,ou=People,dc=wind,dc=surfnet,dc=nl
    changetype: modify
    add: eduPersonEntitlement
    eduPersonEntitlement: http://php-oauth.net/entitlement/manage
    eduPersonEntitlement: foo
    eduPersonEntitlement: bar
    eduPersonEntitlement: baz

And added to the LDAP like this:

    $ ldapmodify -W -H ldap://localhost -D 'cn=Directory Manager' < add_eduPersonEntitlement.ldif

The `http://php-oauth.net/entitlement/manage` entitlement will make it possible 
for this user to manage the OAuth client registrations using the API.

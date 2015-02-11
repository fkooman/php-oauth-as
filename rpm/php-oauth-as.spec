%global github_owner     fkooman
%global github_name      php-oauth-as

Name:       php-oauth-as
Version:    0.1.9
Release:    1%{?dist}
Summary:    OAuth 2.0 Authorization Server written in PHP

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    https://github.com/%{github_owner}/%{github_name}/archive/%{version}.tar.gz
Source1:    php-oauth-as-httpd.conf
Source2:    php-oauth-as-autoload.php

BuildArch:  noarch

Requires:   php >= 5.3.3
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires:   php-composer(fkooman/json) >= 0.6.0
Requires:   php-composer(fkooman/json) < 0.7.0
Requires:   php-composer(fkooman/ini) >= 0.2.0
Requires:   php-composer(fkooman/ini) < 0.3.0
Requires:   php-composer(fkooman/rest) >= 0.6.7
Requires:   php-composer(fkooman/rest) < 0.7.0
Requires:   php-composer(fkooman/rest-plugin-bearer) >= 0.2.1
Requires:   php-composer(fkooman/rest-plugin-bearer) < 0.3.0
Requires:   php-composer(fkooman/rest-plugin-basic) >= 0.4.0
Requires:   php-composer(fkooman/rest-plugin-basic) < 0.5.0
Requires:   php-composer(fkooman/rest-plugin-mellon) >= 0.2.0
Requires:   php-composer(fkooman/rest-plugin-mellon) < 0.3.0
Requires:   php-composer(fkooman/rest-plugin-simplesaml) >= 0.1.1
Requires:   php-composer(fkooman/rest-plugin-simplesaml) < 0.2.0
Requires:   php-pear(pear.twig-project.org/Twig) >= 1.15
Requires:   php-pear(pear.twig-project.org/Twig) < 2.0

#Starting F21 we can use the composer dependency for Symfony
#Requires:   php-composer(symfony/classloader) >= 2.3.9
#Requires:   php-composer(symfony/classloader) < 3.0
Requires:   php-pear(pear.symfony.com/ClassLoader) >= 2.3.9
Requires:   php-pear(pear.symfony.com/ClassLoader) < 3.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This project aims at providing a stand-alone OAuth v2 Authorization 
Server that is easy to integrate with your existing REST services, 
written in any language, without requiring extensive changes.

%prep
%setup -qn %{github_name}-%{version}

sed -i "s|dirname(__DIR__)|'%{_datadir}/php-oauth-as'|" bin/php-oauth-as-initdb
sed -i "s|dirname(__DIR__)|'%{_datadir}/php-oauth-as'|" bin/php-oauth-as-register
sed -i "s|dirname(__DIR__)|'%{_datadir}/php-oauth-as'|" bin/php-oauth-as-housekeeping

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/php-oauth-as.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as
cp -pr web views src ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as

# use our own class loader
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as/vendor
cp -pr %{SOURCE2} ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as/vendor/autoload.php

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as
cp -p config/oauth.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/oauth.ini
cp -p config/entitlements.json.example ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/entitlements.json
ln -s ../../../etc/php-oauth-as ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as/config

# Data
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/php-oauth-as

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-oauth-as(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/php-oauth-as || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/php-oauth-as(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/php-oauth-as.conf
%config(noreplace) %{_sysconfdir}/php-oauth-as
%{_bindir}/php-oauth-as-initdb
%{_bindir}/php-oauth-as-register
%{_bindir}/php-oauth-as-housekeeping
%dir %{_datadir}/php-oauth-as
%{_datadir}/php-oauth-as/src
%{_datadir}/php-oauth-as/vendor
%{_datadir}/php-oauth-as/web
%{_datadir}/php-oauth-as/views
%{_datadir}/php-oauth-as/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/php-oauth-as
%doc CHANGES.md UPGRADING.md README.md agpl-3.0.txt composer.json docs/ config/

%changelog
* Wed Feb 11 2015 François Kooman <fkooman@tuxed.net> - 0.1.9-1
- update to 0.1.9

* Wed Dec 17 2014 François Kooman <fkooman@tuxed.net> - 0.1.8-1
- update to 0.1.8

* Wed Oct 01 2014 François Kooman <fkooman@tuxed.net> - 0.1.7-1
- update to 0.1.7

* Wed Oct 01 2014 François Kooman <fkooman@tuxed.net> - 0.1.6-1
- update to 0.1.6

* Wed Sep 24 2014 François Kooman <fkooman@tuxed.net> - 0.1.5-1
- update to 0.1.5

* Mon Sep 22 2014 François Kooman <fkooman@tuxed.net> - 0.1.4-1
- update to 0.1.4

* Tue Sep 16 2014 François Kooman <fkooman@tuxed.net> - 0.1.3-1
- update to 0.1.3

* Mon Sep 01 2014 François Kooman <fkooman@tuxed.net> - 0.1.2-1
- update to 0.1.2

* Sun Aug 31 2014 François Kooman <fkooman@tuxed.net> - 0.1.1-3
- fix the autoloader to check both pearDir and vendorDir for CentOS 7
  compatibility

* Sat Aug 30 2014 François Kooman <fkooman@tuxed.net> - 0.1.1-2
- fix wrong basedir in autoload file

* Sat Aug 30 2014 François Kooman <fkooman@tuxed.net> - 0.1.1-1
- initial package

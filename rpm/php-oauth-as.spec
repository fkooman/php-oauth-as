Name:       php-oauth-as
Version:    0.1.0
Release:    0.29%{?dist}
Summary:    OAuth 2.0 Authorization Server written in PHP

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/fkooman/php-oauth
Source0:    https://github.com/fkooman/php-oauth/releases/download/%{version}/php-oauth-as-%{version}.tar.xz
Source1:    php-oauth-as-httpd-conf
Patch0:     php-oauth-as-autoload.patch

BuildArch:  noarch

Requires:   php >= 5.3.3
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires:   php-composer(fkooman/json) >= 0.4.0
Requires:   php-composer(fkooman/json) < 0.5.0
Requires:   php-composer(fkooman/config) >= 0.3.1
Requires:   php-composer(fkooman/config) < 0.4.0
Requires:   php-composer(fkooman/rest) >= 0.4.0
Requires:   php-composer(fkooman/rest) < 0.5.0
Requires:   php-composer(fkooman/oauth-common) >= 0.5.0
Requires:   php-composer(fkooman/oauth-common) < 0.6.0
Requires:   php-pear(pear.twig-project.org/Twig) >= 1.15
Requires:   php-pear(pear.twig-project.org/Twig) < 2.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This project aims at providing a stand-alone OAuth v2 Authorization 
Server that is easy to integrate with your existing REST services, 
written in any language, without requiring extensive changes.

%prep
%setup -q

%patch0

# remove bundled dependencies
rm -rf vendor/fkooman
rm -rf vendor/twig
rm -rf vendor/symfony

sed -i 's|dirname(__DIR__)|%{_datadir}/php-oauth-as|' bin/php-oauth-as-initdb
sed -i 's|dirname(__DIR__)|%{_datadir}/php-oauth-as|' bin/php-oauth-as-register

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/php-oauth-as.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as
cp -pr web vendor views src ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as
cp -p config/oauth.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/oauth.ini
cp -p config/simpleAuthEntitlement.json.example ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/simpleAuthEntitlement.json
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
%dir %{_datadir}/php-oauth-as
%{_datadir}/php-oauth-as/src
%{_datadir}/php-oauth-as/vendor
%{_datadir}/php-oauth-as/web
%{_datadir}/php-oauth-as/views
%{_datadir}/php-oauth-as/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/php-oauth-as
%doc README.md agpl-3.0.txt composer.json docs/ config/

%changelog
* Wed Aug 20 2014 François Kooman <fkooman@tuxed.net> - 0.1.0-0.29
- initial package

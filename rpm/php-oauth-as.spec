Name:       php-oauth-as
Version:    0.1.0
Release:    1%{?dist}
Summary:    OAuth 2.0 Authorization Server written in PHP

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/fkooman/php-oauth
Source0:    https://github.com/fkooman/php-oauth/releases/download/%{version}/php-oauth-as-%{version}.tar.xz
Source1:    php-oauth-as-httpd-conf
BuildArch:  noarch

Requires:   php >= 5.3.3
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This project aims at providing a stand-alone OAuth v2 Authorization 
Server that is easy to integrate with your existing REST services, 
written in any language, without requiring extensive changes.

%prep
%setup -q

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/php-oauth-as.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as
cp -pr web vendor views src bin ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as

mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as
cp -p config/oauth.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/oauth.ini
cp -p config/simpleAuthEntitlement.json.example ${RPM_BUILD_ROOT}%{_sysconfdir}/php-oauth-as/simpleAuthEntitlement.json

ln -s ../../../etc/php-oauth-as ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as/config

# Data directory
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

%dir %{_datadir}/php-oauth-as
%{_datadir}/php-oauth-as/src
%{_datadir}/php-oauth-as/vendor
%{_datadir}/php-oauth-as/web
%{_datadir}/php-oauth-as/views
%{_datadir}/php-oauth-as/bin
%{_datadir}/php-oauth-as/config

%dir %attr(0750,apache,apache) %{_localstatedir}/lib/php-oauth-as

%doc README.md agpl-3.0.txt docs/ config/

%changelog
* Tue Aug 12 2014 François Kooman <fkooman@tuxed.net> - 0.1.0-1
- initial package

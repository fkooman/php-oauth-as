Name:       php-oauth-as
Version:    0.1.0
Release:    1%{?dist}
Summary:    OAuth 2.0 Authorization Server written in PHP

Group:      Applications/Internet
License:    AGPLv3+
URL:        https://github.com/fkooman/php-oauth
Source0:    https://github.com/fkooman/php-oauth/releases/download/%{version}/fkooman-php-oauth-as-%{version}.tar.xz
Source1:    php-oauth-as-httpd-conf
BuildArch:  noarch

Requires:   php >= 5.3.3
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

%description
This project aims at providing a stand-alone OAuth v2 Authorization 
Server that is easy to integrate with your existing REST services, 
written in any language, without requiring extensive changes.

%prep
%setup -qc php-oauth-as-%{version}

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/php-oauth-as.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as
cp -pr web vendor views src bin config ${RPM_BUILD_ROOT}%{_datadir}/php-oauth-as

# Data directory
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/php-oauth-as

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/php-oauth-as.conf

%dir %{_datadir}/php-oauth-as
%{_datadir}/php-oauth-as/src
%{_datadir}/php-oauth-as/vendor
%{_datadir}/php-oauth-as/web
%{_datadir}/php-oauth-as/views
%{_datadir}/php-oauth-as/config
%{_datadir}/php-oauth-as/bin

%dir %attr(0755,apache,apache) %{_localstatedir}/lib/php-oauth-as

%doc README.md agpl-3.0.txt docs/*

%changelog
* Tue Aug 12 2014 François Kooman <fkooman@tuxed.net> - 0.1.0-1
- initial package

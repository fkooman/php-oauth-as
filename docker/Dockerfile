FROM centos:centos6
MAINTAINER François Kooman <fkooman@tuxed.net>

# Add EPEL
RUN yum -y install https://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm; yum clean all

# PHP base repo 
RUN curl -s -L -o /etc/yum.repos.d/fkooman-php-base-epel-6.repo https://copr.fedoraproject.org/coprs/fkooman/php-base/repo/epel-6/fkooman-php-base-epel-6.repo

# PHP OAuth repo
RUN curl -s -L -o /etc/yum.repos.d/fkooman-php-oauth-epel-6.repo https://copr.fedoraproject.org/coprs/fkooman/php-oauth/repo/epel-6/fkooman-php-oauth-epel-6.repo

# Install updates
RUN yum -y update; yum clean all

RUN yum install -y mod_ssl php-oauth-as; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-oauth-as.conf

# disable the certificate check for use within the docker image, as there is
# no trusted certificate for "localhost" so verification will fail...
RUN sed -i 's/;disableServerCertCheck/disableServerCertCheck/' /etc/php-oauth-as/oauth.ini

USER apache

# Init Database
RUN php-oauth-as-initdb

# Register Default Clients
RUN php-oauth-as-register https://www.php-oauth.net/app/config.json

USER root

# Expose port 443 and set httpd as our entrypoint
EXPOSE 80
EXPOSE 443
ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]

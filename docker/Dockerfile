FROM       fedora:21
MAINTAINER François Kooman <fkooman@tuxed.net>

RUN yum -y update; yum clean all
RUN yum install -y mod_ssl yum-plugin-copr; yum clean all 

RUN yum copr enable -y fkooman/php-base
RUN yum copr enable -y fkooman/php-oauth

RUN yum install -y php-oauth-as; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-oauth-as.conf

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

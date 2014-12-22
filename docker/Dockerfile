FROM       fedora:21
MAINTAINER François Kooman <fkooman@tuxed.net>

RUN yum -y update; yum clean all
RUN yum install -y mod_ssl yum-plugin-copr; yum clean all 

RUN yum copr enable -y fkooman/php-base
RUN yum copr enable -y fkooman/php-oauth

RUN yum install -y php-oauth-as php-simple-auth; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-simple-auth.conf
RUN sed -i 's/Allow from 127.0.0.1/Allow from All/' /etc/httpd/conf.d/php-simple-auth.conf
RUN sed -i 's/Allow from ::1//' /etc/httpd/conf.d/php-simple-auth.conf

# Add users to php-simple-auth
RUN php-simple-auth-add-user admin adm1n
RUN php-simple-auth-add-user fkooman foobar

USER apache

# Init Database
RUN php-oauth-as-initdb

# Register Default Clients
RUN curl -s -L -o /tmp/config.json https://www.php-oauth.net/app/config.json
RUN php-oauth-as-register /tmp/config.json

USER root

# Expose port 443 and set httpd as our entrypoint
EXPOSE 443
ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]

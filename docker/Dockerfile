FROM       fedora:20
#FROM       centos:centos7
MAINTAINER François Kooman <fkooman@tuxed.net>

# Add EPEL repository (CentOS only)
#RUN yum -y install https://download.fedoraproject.org/pub/epel/beta/7/x86_64/epel-release-7-0.2.noarch.rpm ; yum clean all

# Add php-oauth repo to /etc/yum.repos.d
RUN curl -s -o /etc/yum.repos.d/fedora-php-oauth.repo https://repos.fedorapeople.org/repos/fkooman/php-oauth/fedora-php-oauth.repo

# Perform updates
RUN yum -y update; yum clean all

# Install dependencies (and SSL module)
RUN yum install -y mod_ssl php-oauth-as php-simple-auth; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-simple-auth.conf

# Add a new user to php-simple-auth
RUN php-simple-auth-add-user /etc/php-simple-auth/users.json fkooman foobar

USER apache

# Init Database
RUN php-oauth-as-initdb

# Register Default Clients
# Doesn't work on CentOS as no docs are installed in Docker image...
RUN php-oauth-as-register /usr/share/doc/php-oauth-as/docs/apps.json

USER root

# Expose port 443 and set httpd as our entrypoint
EXPOSE 443
ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]

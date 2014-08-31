FROM       fedora:20
#FROM       centos:centos6
#FROM       centos:centos7
MAINTAINER François Kooman <fkooman@tuxed.net>

# Add EPEL repository (CentOS 6)
#RUN yum -y install https://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm

# Add EPEL repository (CentOS 7)
#RUN yum -y install https://download.fedoraproject.org/pub/epel/7/x86_64/e/epel-release-7-1.noarch.rpm

# Add php-oauth repository (Fedora, CentOS 6, CentOS 7)
RUN curl -s -L -o /etc/yum.repos.d/fedora-php-oauth.repo https://repos.fedorapeople.org/repos/fkooman/php-oauth/fedora-php-oauth.repo

# Perform updates
RUN yum -y update; yum clean all

# Install dependencies (and SSL module)
RUN yum install -y mod_ssl php-oauth-as php-simple-auth; yum clean all

# Allow connections from everywhere
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-oauth-as.conf
RUN sed -i 's/Require local/Require all granted/' /etc/httpd/conf.d/php-simple-auth.conf

# Add users to php-simple-auth
RUN php-simple-auth-add-user /etc/php-simple-auth/users.json admin adm1n
RUN php-simple-auth-add-user /etc/php-simple-auth/users.json fkooman foobar

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

#!/bin/sh
APP_NAME="php-oauth"

INSTALL_DIR=`pwd`

# create directories
mkdir -p data
mkdir -p data/logs

# create SQlite files
touch data/oauth2.sqlite
chmod o+w data/oauth2.sqlite

# set permissions
chmod -R o+w data/
chcon -R -t httpd_sys_rw_content_t data/

# httpd configuration
echo "***********************"
echo "* HTTPD Configuration *"
echo "***********************"
echo "---- cut ----"
cat docs/apache.conf | sed "s|/PATH/TO/APP|${INSTALL_DIR}|g" | sed "s|APPNAME|${APP_NAME}|g"
echo "---- cut ----"

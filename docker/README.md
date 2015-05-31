# Introduction
These are all the files to get a Docker instance running with `php-oauth-as`.

To build the Docker image:

    docker build --rm -t fkooman/php-oauth-as .

To run the container:

    docker run -d -p 443:443 fkooman/php-oauth-as

That should be all. Use your browser to go to 
[https://localhost/php-oauth-as/](https://localhost/php-oauth-as/) and follow 
the instructions there.

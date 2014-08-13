# Introduction
These are all the files to get a Docker instance running with `php-oauth-as`
and `php-simple-auth`. 

To build the Docker image:

    docker build --rm -t fkooman/php-oauth-as .

To run the container:

    docker run -d -p 443:443 fkooman/php-oauth-as

That should be all. You can replace `fkooman` with your own name of course.

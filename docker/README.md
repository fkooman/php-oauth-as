# Introduction
These are all the files to get a Docker instance running with `php-oauth-as`
and `php-simple-auth`. 

To build the Docker image:

    docker build --rm -t fkooman/php-oauth-as .

To run the container:

    docker run -d -p 443:443 fkooman/php-oauth-as

That should be all. You can replace `fkooman` with your own name of course.

Once this runs you can use the management tools at 
[https://www.php-oauth.net](https://www.php-oauth.net) to manage the server. It 
points to `https://localhost/php-oauth-as` as the service to connect to which 
will work with the Docker instance.

Do not forget to first go to [https://localhost](https://localhost) with your
browser to accept the self signed certificate.

# TODO
* Implement proper logging of calls, errors, sensitive operations;
* Return existing access code when a new request comes in from the same client,
  resource owner and scope;
* Make it possible to disable token expiry
* Create a "remove me" API call to completely remove all user data from the 
  service
* make it possible to link a client to one or more resource servers instead of 
  "allowing access" to all resource servers
* make it possible to skip user consent, e.g. for management clients

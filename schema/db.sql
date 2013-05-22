CREATE TABLE IF NOT EXISTS resource_owner (
    id VARCHAR(255) NOT NULL,
    entitlement TEXT DEFAULT NULL,
    ext TEXT DEFAULT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS Client (
    id VARCHAR(64) NOT NULL,
    name TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    secret TEXT DEFAULT NULL,
    redirect_uri TEXT NOT NULL,
    type TEXT NOT NULL,
    icon TEXT DEFAULT NULL,
    allowed_scope TEXT DEFAULT NULL,
    contact_email TEXT DEFAULT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS AccessToken (
    access_token VARCHAR(64) NOT NULL,
    client_id VARCHAR(64) NOT NULL,
    resource_owner_id VARCHAR(64) NOT NULL,
    issue_time INTEGER DEFAULT NULL,
    expires_in INTEGER DEFAULT NULL,
    scope TEXT NOT NULL,
    PRIMARY KEY (access_token),
    FOREIGN KEY (client_id)
        REFERENCES Client (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (resource_owner_id)
        REFERENCES resource_owner (id)
        ON UPDATE CASCADE ON DELETE CASCADE
);
  
CREATE TABLE IF NOT EXISTS Approval (
    client_id VARCHAR(64) NOT NULL,
    resource_owner_id VARCHAR(64) NOT NULL,
    scope TEXT DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL,
    FOREIGN KEY (client_id)
        REFERENCES Client (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (client_id , resource_owner_id),
    FOREIGN KEY (resource_owner_id)
        REFERENCES resource_owner (id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS AuthorizationCode (
    authorization_code VARCHAR(64) NOT NULL,
    client_id VARCHAR(64) NOT NULL,
    resource_owner_id VARCHAR(64) NOT NULL,
    redirect_uri TEXT DEFAULT NULL,
    issue_time INTEGER NOT NULL,
    scope TEXT DEFAULT NULL,
    PRIMARY KEY (authorization_code),
    FOREIGN KEY (client_id)
        REFERENCES Client (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (resource_owner_id)
        REFERENCES resource_owner (id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS db_changelog (
    patch_number INTEGER NOT NULL,
    description TEXT NOT NULL,
    PRIMARY KEY (patch_number)
);

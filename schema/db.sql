CREATE TABLE IF NOT EXISTS `ResourceOwner` (
    `id` VARCHAR(64) NOT NULL,
    `time` INT(11) NOT NULL,
    `attributes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `Client` (
    `id` varchar(64) NOT NULL,
    `name` text NOT NULL,
    `description` text DEFAULT NULL,
    `secret` text DEFAULT NULL,
    `redirect_uri` text NOT NULL,
    `type` text NOT NULL,
    `icon` text DEFAULT NULL,
    `allowed_scope` text DEFAULT NULL,
    `contact_email` text DEFAULT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `AccessToken` (
    `access_token` varchar(64) NOT NULL,
    `client_id` varchar(64) NOT NULL,
    `resource_owner_id` varchar(64) NOT NULL,
    `issue_time` int(11) DEFAULT NULL,
    `expires_in` int(11) DEFAULT NULL,
    `scope` text NOT NULL,
    PRIMARY KEY (`access_token`),
    FOREIGN KEY (`client_id`)
        REFERENCES `Client` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`resource_owner_id`)
        REFERENCES `ResourceOwner` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
);
  

CREATE TABLE IF NOT EXISTS `Approval` (
    `client_id` varchar(64) NOT NULL,
    `resource_owner_id` varchar(64) NOT NULL,
    `scope` text DEFAULT NULL,
    `refresh_token` text DEFAULT NULL,
    FOREIGN KEY (`client_id`)
        REFERENCES `Client` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (`client_id` , `resource_owner_id`),
    FOREIGN KEY (`resource_owner_id`)
        REFERENCES `ResourceOwner` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `AuthorizationCode` (
    `authorization_code` varchar(64) NOT NULL,
    `client_id` varchar(64) NOT NULL,
    `resource_owner_id` varchar(64) NOT NULL,
    `redirect_uri` text DEFAULT NULL,
    `issue_time` int(11) NOT NULL,
    `scope` text DEFAULT NULL,
    PRIMARY KEY (`authorization_code`),
    FOREIGN KEY (`client_id`)
        REFERENCES `Client` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (`resource_owner_id`)
        REFERENCES `ResourceOwner` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
);

# Statistics

## Active Access Tokens

    SELECT 
        client_id, COUNT(resource_owner_id)
    FROM
        AccessToken
    GROUP BY client_id

## Given Consent (not necessarily active access tokens)

    SELECT 
        client_id, COUNT(resource_owner_id)
    FROM
        Approval
    GROUP BY client_id;

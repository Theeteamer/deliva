USE deliva;

UPDATE providers p
INNER JOIN services s ON s.id = p.service_id
SET p.description = CONCAT(
    'Reliable local ',
    s.name,
    ' services available in ',
    p.location,
    '. Contact us for availability and details.'
)
WHERE p.is_demo = 1;

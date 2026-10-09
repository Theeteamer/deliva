ALTER TABLE providers
    ADD COLUMN photography_type VARCHAR(10) NOT NULL DEFAULT '' AFTER service_id;

UPDATE providers p
INNER JOIN services old_service ON old_service.id = p.service_id
INNER JOIN services photo_service
    ON photo_service.slug = 'outdoor-photography'
SET p.photography_type = CASE
        WHEN old_service.slug = 'indoor-photography' OR old_service.name = 'indoor photography'
            THEN 'indoor'
        ELSE 'outdoor'
    END,
    p.service_id = photo_service.id
WHERE old_service.slug IN ('indoor-photography', 'outdoor-photography');

UPDATE services
SET slug = 'photography',
    name = 'photography',
    description = 'Find indoor and outdoor photographers near you.',
    sort_order = 5,
    requires_location = 1,
    requires_business_name = 1,
    requires_photos = 0,
    requires_owner_name = 0,
    requires_owner_phone = 1,
    supports_delivery = 0,
    online_only = 0
WHERE slug = 'outdoor-photography';

DELETE FROM services
WHERE slug = 'indoor-photography';

UPDATE services SET sort_order = 6 WHERE slug = 'mama-fua';
UPDATE services SET sort_order = 7 WHERE slug = 'house-party';

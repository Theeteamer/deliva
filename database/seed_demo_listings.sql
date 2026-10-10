USE deliva;

START TRANSACTION;

CREATE TEMPORARY TABLE demo_listing_profiles (
    profile_index SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    first_name VARCHAR(40) NOT NULL,
    last_name VARCHAR(40) NOT NULL,
    location VARCHAR(150) NOT NULL
) ENGINE=MEMORY;

INSERT INTO demo_listing_profiles (profile_index, first_name, last_name, location) VALUES
    (0, 'Akinyi', 'Odhiambo', 'Kisii Town'),
    (1, 'Wanjiku', 'Mwangi', 'Suneka'),
    (2, 'Mwende', 'Mutua', 'Ogembo'),
    (3, 'Chebet', 'Kiptoo', 'Nyamache'),
    (4, 'Achieng', 'Otieno', 'Nyanchwa'),
    (5, 'Wambui', 'Kamau', 'Mosocho'),
    (6, 'Naliaka', 'Wekesa', 'Marani'),
    (7, 'Muthoni', 'Njoroge', 'Daraja Mbili'),
    (8, 'Atieno', 'Omondi', 'Keumbu'),
    (9, 'Jebet', 'Rotich', 'Ichuni'),
    (10, 'Nyambura', 'Githinji', 'Gesusu'),
    (11, 'Moraa', 'Onyango', 'Riana'),
    (12, 'Kendi', 'Muriuki', 'Nyamagwa'),
    (13, 'Njeri', 'Kariuki', 'Nyacheki'),
    (14, 'Adhiambo', 'Ochieng', 'Masimba'),
    (15, 'Wairimu', 'Maina', 'Tabaka'),
    (16, 'Mumbi', 'Karanja', 'Etago'),
    (17, 'Chepkemoi', 'Langat', 'Moticho'),
    (18, 'Auma', 'Abdalla', 'Nyakoe'),
    (19, 'Wanjala', 'Barasa', 'Birongo');

CREATE TEMPORARY TABLE demo_listing_service_counts (
    service_id INT UNSIGNED NOT NULL PRIMARY KEY,
    listing_count INT UNSIGNED NOT NULL
) ENGINE=MEMORY;

INSERT INTO demo_listing_service_counts (service_id, listing_count)
SELECT service_id, COUNT(*)
FROM providers
WHERE is_demo = 1
GROUP BY service_id;

INSERT INTO providers
    (service_id, photography_type, owner_name, is_demo, name, location, address, phone, description,
     opening_time, closing_time, closed_days, delivery_available, active, approval_status)
SELECT
    s.id,
    CASE
        WHEN s.slug = 'photography' AND MOD(profile.profile_index, 2) = 0 THEN 'indoor'
        WHEN s.slug = 'photography' THEN 'outdoor'
        ELSE ''
    END,
    CONCAT(profile.first_name, ' ', profile.last_name),
    1,
    CONCAT(
        profile.first_name,
        ' ',
        CASE s.slug
            WHEN 'laundry' THEN 'Laundry Care'
            WHEN 'gas-delivery' THEN 'Gas Services'
            WHEN 'water' THEN 'Water Supply'
            WHEN 'food' THEN 'Kitchen'
            WHEN 'photography' THEN 'Photography'
            WHEN 'mama-fua' THEN 'Mama Fua'
            WHEN 'house-party' THEN 'Events'
            ELSE CONCAT(UPPER(LEFT(s.name, 1)), SUBSTRING(s.name, 2))
        END
    ),
    profile.location,
    '',
    CONCAT(
        '+254 000 ',
        LPAD(s.id * 100 + profile.profile_index + 1, 3, '0'),
        ' ',
        LPAD(profile.profile_index + 1, 3, '0')
    ),
    CONCAT(
        'Reliable local ',
        CASE s.slug
            WHEN 'laundry' THEN 'Laundry Care'
            WHEN 'gas-delivery' THEN 'Gas Services'
            WHEN 'water' THEN 'Water Supply'
            WHEN 'food' THEN 'Kitchen'
            WHEN 'photography' THEN 'Photography'
            WHEN 'mama-fua' THEN 'Mama Fua'
            WHEN 'house-party' THEN 'Events'
            ELSE CONCAT(UPPER(LEFT(s.name, 1)), SUBSTRING(s.name, 2))
        END,
        ' services available in ',
        profile.location,
        '. Contact us for availability and details.'
    ),
    MAKETIME(7 + MOD(profile.profile_index, 4), 0, 0),
    MAKETIME(17 + MOD(profile.profile_index, 5), 0, 0),
    ELT(
        MOD(profile.profile_index, 7) + 1,
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
    ),
    IF(s.supports_delivery = 1 AND MOD(profile.profile_index, 2) = 0, 1, 0),
    1,
    'approved'
FROM services AS s
CROSS JOIN demo_listing_profiles AS profile
LEFT JOIN demo_listing_service_counts AS existing
    ON existing.service_id = s.id
WHERE s.active = 1
  AND COALESCE(existing.listing_count, 0) < 20
  AND profile.profile_index >= COALESCE(existing.listing_count, 0)
ORDER BY s.sort_order, s.name, profile.profile_index;

INSERT INTO provider_photos (provider_id, file_path)
SELECT
    p.id,
    CONCAT('uploads/providers/demo-', LPAD(profile.profile_index + 1, 2, '0'), '.jpg')
FROM providers AS p
INNER JOIN services AS s ON s.id = p.service_id
INNER JOIN demo_listing_profiles AS profile
    ON p.phone = CONCAT(
        '+254 000 ',
        LPAD(s.id * 100 + profile.profile_index + 1, 3, '0'),
        ' ',
        LPAD(profile.profile_index + 1, 3, '0')
    )
WHERE p.is_demo = 1
  AND NOT EXISTS (
      SELECT 1
      FROM provider_photos AS photo
      WHERE photo.provider_id = p.id
        AND photo.file_path = CONCAT(
            'uploads/providers/demo-',
            LPAD(profile.profile_index + 1, 2, '0'),
            '.jpg'
        )
  );

COMMIT;

DROP TEMPORARY TABLE demo_listing_service_counts;
DROP TEMPORARY TABLE demo_listing_profiles;

USE deliva;

UPDATE providers
SET location = CASE MOD(id, 20)
    WHEN 0 THEN 'Kisii Town'
    WHEN 1 THEN 'Suneka'
    WHEN 2 THEN 'Ogembo'
    WHEN 3 THEN 'Nyamache'
    WHEN 4 THEN 'Nyanchwa'
    WHEN 5 THEN 'Mosocho'
    WHEN 6 THEN 'Marani'
    WHEN 7 THEN 'Daraja Mbili'
    WHEN 8 THEN 'Keumbu'
    WHEN 9 THEN 'Ichuni'
    WHEN 10 THEN 'Gesusu'
    WHEN 11 THEN 'Riana'
    WHEN 12 THEN 'Nyamagwa'
    WHEN 13 THEN 'Nyacheki'
    WHEN 14 THEN 'Masimba'
    WHEN 15 THEN 'Tabaka'
    WHEN 16 THEN 'Etago'
    WHEN 17 THEN 'Moticho'
    WHEN 18 THEN 'Nyakoe'
    ELSE 'Birongo'
END
WHERE location <> '';

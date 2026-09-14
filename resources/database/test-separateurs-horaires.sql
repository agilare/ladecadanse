/**
 * Test data for issue #105: Time-based separators in agenda
 * Creates test events with different start times to test the separator functionality
 */

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 17h30', CURDATE(), 'Lieu Test 1', 'Rue Test 1', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 17:30:00'), CONCAT(CURDATE(), ' 19:00:00'), 'Événement de test pour séparateur 16h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 18h30', CURDATE(), 'Lieu Test 2', 'Rue Test 2', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 18:30:00'), CONCAT(CURDATE(), ' 20:00:00'), 'Événement de test pour séparateur 18h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 19h00', CURDATE(), 'Lieu Test 3', 'Rue Test 3', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 19:00:00'), CONCAT(CURDATE(), ' 21:00:00'), 'Événement de test pour séparateur 18h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 20h30', CURDATE(), 'Lieu Test 4', 'Rue Test 4', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 20:30:00'), CONCAT(CURDATE(), ' 22:30:00'), 'Événement de test pour séparateur 20h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 21h15', CURDATE(), 'Lieu Test 5', 'Rue Test 5', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 21:15:00'), CONCAT(CURDATE(), ' 23:00:00'), 'Événement de test pour séparateur 20h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 22h00', CURDATE(), 'Lieu Test 6', 'Rue Test 6', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 22:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 00:30:00'), 'Événement de test pour séparateur 22h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 23h30', CURDATE(), 'Lieu Test 7', 'Rue Test 7', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 23:30:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 01:00:00'), 'Événement de test pour séparateur 22h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, horaire_complement, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement sans heure', CURDATE(), 'Lieu Test 8', 'Rue Test 8', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 06:00:01'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 06:00:01'), 'Horaire à confirmer', 'Événement de test sans heure de début', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR HIER - Événement 18h00', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Lieu Test Hier 1', 'Rue Test Hier 1', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 18:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 20:00:00'), 'Événement de test pour hier 18h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR HIER - Événement 19h30', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Lieu Test Hier 2', 'Rue Test Hier 2', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 19:30:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 21:30:00'), 'Événement de test pour hier 18h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR HIER - Événement 20h00', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Lieu Test Hier 3', 'Rue Test Hier 3', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 20:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 22:00:00'), 'Événement de test pour hier 20h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR HIER - Événement 21h15', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Lieu Test Hier 4', 'Rue Test Hier 4', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 21:15:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 23:00:00'), 'Événement de test pour hier 20h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR HIER - Événement 22h30', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Lieu Test Hier 5', 'Rue Test Hier 5', 'Plainpalais', 1, 'ge',
    CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 22:30:00'), CONCAT(CURDATE(), ' 00:30:00'), 'Événement de test pour hier 22h', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 20h00 (1)', CURDATE(), 'Lieu Test Même Heure 1', 'Rue Test Même Heure 1', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 20:00:00'), CONCAT(CURDATE(), ' 22:00:00'), 'Premier événement à 20h00', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 20h00 (2)', CURDATE(), 'Lieu Test Même Heure 2', 'Rue Test Même Heure 2', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 20:00:00'), CONCAT(CURDATE(), ' 22:30:00'), 'Deuxième événement à 20h00', 'entrée libre', NOW(), NOW()
);

INSERT INTO evenement (
    idPersonne, statut, genre, titre, dateEvenement, nomLieu, adresse, quartier, localite_id, region,
    horaire_debut, horaire_fin, description, prix, dateAjout, date_derniere_modif
) VALUES (
    1, 'actif', 'fête', 'TEST SEPARATEUR - Événement 20h00 (3)', CURDATE(), 'Lieu Test Même Heure 3', 'Rue Test Même Heure 3', 'Plainpalais', 1, 'ge',
    CONCAT(CURDATE(), ' 20:00:00'), CONCAT(CURDATE(), ' 23:00:00'), 'Troisième événement à 20h00', 'entrée libre', NOW(), NOW()
);


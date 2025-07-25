ALTER TABLE evenement
  ADD FULLTEXT INDEX ft_evenement_titre (titre),
  ADD FULLTEXT INDEX ft_evenement_nomLieu (nomLieu),
  ADD FULLTEXT INDEX ft_evenement_description (description);

ALTER TABLE lieu
  ADD FULLTEXT idx_lieu_fulltext (nom);
/**
 * Some wrong values were discovered in evenement table. The first problem was due to a bug in evenement-copy.php, fixed in v3.5.0
 * These queries have been executed on prod (ladecadanse.darksite.ch) just after v3.5.0 deployment on 2023-06-26
 * Created: 27 juin 2023
 */

-- probleme 1 : a bug in evenement-copy.php copied wrong values for horaire_debut and horaire_fin; the queries below fix the existing values in this table

update evenement SET  horaire_debut = DATE_ADD(horaire_debut, INTERVAL 1 DAY) WHERE TIME(horaire_debut) >= '00:00:00' and TIME(horaire_debut) < '06:00:01' and DATE(horaire_debut) = dateEvenement and horaire_debut != '0000-00-00 00:00:00'

update evenement SET  horaire_fin = DATE_ADD(horaire_fin, INTERVAL 1 DAY) WHERE TIME(horaire_fin) >= '00:00:00' and TIME(horaire_fin) < '06:00:01' and DATE(horaire_fin) = dateEvenement and horaire_fin != '0000-00-00 00:00:00'


-- probleme 2 : some horaire_debut and horaire_fin fields can have erroneous values, for unkown reason

update evenement SET  horaire_debut = CONCAT(date(dateEvenement), " ", "06:00:01") WHERE horaire_debut='0000-00-00 00:00:00'

update evenement SET  horaire_fin = CONCAT(date(dateEvenement), " ", "06:00:01") WHERE horaire_fin='0000-00-00 00:00:00'
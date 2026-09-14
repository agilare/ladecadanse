ALTER TABLE
    `localite` ADD `regions_covered`
    SET    ('ge', 'vd', 'rf', 'hs') NULL DEFAULT NULL AFTER `canton`;
UPDATE
    localite
SET
    regions_covered = 'ge,vd'
WHERE
    commune IN(
               'Arnex-sur-Nyon',
               'Arzier-Le Muids',
               'Bassins',
               'Begnins',
               'Bogis-Bossey',
               'Borex',
               'Bursinel',
               'Bursins',
               'Burtigny',
               'Chavannes-de-Bogis',
               'Chavannes-des-Bois',
               'Chéserex',
               'Coinsins',
               'Commugny',
               'Coppet',
               'Crans-près-Céligny',
               'Crassier',
               'Duillier',
               'Dully',
               'Essertines-sur-Rolle',
               'Eysins',
               'Founex',
               'Genolier',
               'Gilly',
               'Gingins',
               'Givrins',
               'Gland',
               'Grens',
               'La Rippe',
               'Le Vaud',
               'Longirod',
               'Luins',
               'Marchissy',
               'Mies',
               'Mont-sur-Rolle',
               'Nyon',
               'Perroy',
               'Prangins',
               'Rolle',
               'Saint-Cergue',
               'Saint-George',
               'Signy-Avenex',
               'Tannay',
               'Tartegnin',
               'Trélex',
               'Vich',
               'Vinzel'
        );

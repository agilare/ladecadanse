<?php

namespace Ladecadanse;

use Ladecadanse\Utils\Validateur;
use Ladecadanse\Utils\DbConnectorPdo;

class SalleEdition extends Edition
{
    private DbConnectorPdo $pdo;
    private int $idPersonne;
    private ?int $idSalle = null;

    /**
     * Les instances arrivent en paramètre pour que la classe soit exerçable hors
     * requête HTTP ; les valeurs par défaut évitent d'imposer un conteneur à la page.
     */
    public function __construct(
        ?DbConnectorPdo $pdo = null,
        private readonly Validateur $verif = new Validateur(),
    )
    {
        $valeurs = [
            'idLieu' => '',
            'nom' => '',
            'emplacement' => '',
        ];

        parent::__construct('salle', $valeurs, []);

        // Le connecteur est un singleton, qu'un défaut de paramètre ne sait pas appeler
        $this->pdo = $pdo ?? DbConnectorPdo::getInstance();
    }

    public function setIdPersonne(int $idPersonne): void
    {
        $this->idPersonne = $idPersonne;
    }

    public function setIdSalle(?int $idSalle): void
    {
        $this->idSalle = $idSalle;
    }

    #[\Override]
    public function traitement(array $post, array $files): bool
    {
        foreach ($this->valeurs as $nom => $val) {
            if (isset($post[$nom])) {
                $this->valeurs[$nom] = $post[$nom];
            }
        }

        if (!$this->verification()) {
            return false;
        }

        return $this->enregistrer();
    }

    #[\Override]
    public function enregistrer(): bool
    {
        return match ($this->action) {
            'insert' => $this->insert($this->idPersonne) !== null,
            'update' => $this->update($this->idSalle),
            default => false,
        };
    }

    #[\Override]
    public function verification(): bool
    {
        $this->verif->valider($this->valeurs['idLieu'], "idLieu", "texte", 1, 60, 1);
        $this->verif->valider($this->valeurs['nom'], "nom", "texte", 2, 100, 1);
        $this->verif->valider($this->valeurs['emplacement'], "emplacement", "texte", 2, 100, 0);

        if ($this->verif->nbErreurs() === 0) {
            $stmt = $this->pdo->prepare("SELECT idLieu FROM lieu WHERE idLieu = :idLieu");
            $stmt->execute([':idLieu' => $this->valeurs['idLieu']]);
            if (!$stmt->fetch()) {
                $this->verif->setErreur("idLieu", "Ce lieu n'est pas dans la liste");
            }
        }

        $this->erreurs = array_merge($this->erreurs, $this->verif->getErreurs());

        return $this->verif->nbErreurs() === 0;
    }

    /**
     * @return bool false si la salle n'existe pas — à la page de répondre 404
     */
    #[\Override]
    public function loadValeurs(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM salle WHERE idSalle = :idSalle");
        $stmt->execute([':idSalle' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }

        foreach ($row as $key => $value) {
            if (array_key_exists($key, $this->valeurs)) {
                $this->valeurs[$key] = $value;
            }
        }
        $this->id = $id;

        return true;
    }

    public function insert(int $idPersonne): ?int
    {
        $now = date("Y-m-d H:i:s");

        $stmt = $this->pdo->prepare("
            INSERT INTO salle (idLieu, nom, emplacement, dateAjout, date_derniere_modif, idPersonne)
            VALUES (:idLieu, :nom, :emplacement, :dateAjout, :dateModif, :idPersonne)
        ");

        $result = $stmt->execute([
            ':idLieu' => $this->valeurs['idLieu'],
            ':nom' => $this->valeurs['nom'],
            ':emplacement' => $this->valeurs['emplacement'],
            ':dateAjout' => $now,
            ':dateModif' => $now,
            ':idPersonne' => $idPersonne,
        ]);

        if ($result) {
            $this->id = (int)$this->pdo->lastInsertId();
            $this->message = "Salle <em>" . sanitizeForHtml($this->valeurs['nom']) . "</em> ajoutée";
            return $this->id;
        }

        return null;
    }

    public function update(int $idSalle): bool
    {
        $now = date("Y-m-d H:i:s");

        $stmt = $this->pdo->prepare("
            UPDATE salle 
            SET nom = :nom, emplacement = :emplacement, date_derniere_modif = :dateModif
            WHERE idSalle = :idSalle
        ");

        $result = $stmt->execute([
            ':nom' => $this->valeurs['nom'],
            ':emplacement' => $this->valeurs['emplacement'],
            ':dateModif' => $now,
            ':idSalle' => $idSalle,
        ]);

        if ($result) {
            $this->message = "Salle modifiée";
            return true;
        }

        return false;
    }

    public function getLieux(): array
    {
        $stmt = $this->pdo->query("SELECT idLieu, nom FROM lieu ORDER BY nom");
        return $stmt->fetchAll();
    }

    public function getValidationError(string $field): string
    {
        return $this->verif->getErreur($field);
    }

    public function hasErrors(): bool
    {
        return $this->verif->nbErreurs() > 0;
    }

    public function getErrorCount(): int
    {
        return $this->verif->nbErreurs();
    }
}

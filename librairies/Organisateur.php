<?php

namespace Ladecadanse;

use Ladecadanse\Element;
use Ladecadanse\Utils\Text;


class Organisateur extends Element
{

    function __construct()
	{
        parent::__construct();
		$this->table = "organisateur";
	}


    public static function getListLinkedHtml(array $organisateurs): string
    {
        ob_start();
        ?>
        <ul class="event_orga" aria-label="Organisateurs">
            <?php foreach ($organisateurs as $eo) : ?>
                <li>
                    <a href="/organisateur.php?idO=<?= (int) $eo['idOrganisateur']; ?>"><b><?= sanitizeForHtml($eo['nom']); ?></b></a><?php if (!empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?= sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?= sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                    <?php } ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }
}
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
            <?php foreach ($organisateurs as $eo) { ?>
                <li>
                    <a href="/organisateur.php?idO=<?php echo (int) $eo['idOrganisateur']; ?>"><?php echo sanitizeForHtml($eo['nom']); ?></a><?php if (!empty($eo['url'])) { $organisateurUrl = Text::getUrlWithName($eo['url']); ?> -&nbsp;<a href="<?php echo sanitizeForHtml($organisateurUrl['url']); ?>" title="Site web de l'organisateur" class="lien_ext" target="_blank"><?php echo sanitizeForHtml($organisateurUrl['urlName']); ?></a>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
        <?php
        $result = ob_get_contents();
        ob_clean();
        return $result;
    }
}
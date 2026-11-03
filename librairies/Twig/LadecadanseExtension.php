<?php

declare(strict_types=1);

namespace Ladecadanse\Twig;

use Ladecadanse\Evenement;
use Ladecadanse\EvenementCalendarRenderer;
use Ladecadanse\EvenementRenderer;
use Ladecadanse\EvenementWithSeparatorCollection;
use Ladecadanse\EventCategory;
use Ladecadanse\HtmlShrink;
use Ladecadanse\Lieu;
use Ladecadanse\Security\Authorization;
use Ladecadanse\Utils\DateHelper;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Les helpers qui renvoient du HTML déjà échappé sont marqués is_safe : pas de |raw dans les templates. */
final class LadecadanseExtension extends AbstractExtension implements GlobalsInterface
{
    private const array SAFE_HTML = ['is_safe' => ['html']];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly array $session,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'csp_nonce' => CSP_NONCE,
            'site_canonical_url' => SITE_CANONICAL_URL,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('event_short_article', EvenementRenderer::eventShortArticleHtml(...), self::SAFE_HTML),
            new TwigFunction('event_favorite_button', EvenementRenderer::favoriteButtonHtml(...), self::SAFE_HTML),
            new TwigFunction('event_unpublish_link', static fn (int $idEvenement, string|\Stringable $labelHtml): string
                => EvenementRenderer::unpublishLinkHtml($idEvenement, (string) $labelHtml), self::SAFE_HTML),
            new TwigFunction('event_main_figure', EvenementRenderer::mainFigureHtml(...), self::SAFE_HTML),
            new TwigFunction('event_title', EvenementRenderer::titreSelonStatutHtml(...), self::SAFE_HTML),
            new TwigFunction('event_calendar_menu', EvenementCalendarRenderer::renderMenuHtml(...), self::SAFE_HTML),
            new TwigFunction('event_lieu', Evenement::getLieu(...)),
            new TwigFunction('events_with_separators', static fn (array $events, bool $isChronologicalOrder, string $dateCurrent): EvenementWithSeparatorCollection
                => new EvenementWithSeparatorCollection($events, $isChronologicalOrder, $dateCurrent)),
            new TwigFunction('lieu_link_name', Lieu::getLinkNameHtml(...), self::SAFE_HTML),
            new TwigFunction('category_label', Evenement::categoryLabel(...)),
            new TwigFunction('msg_info', HtmlShrink::msgInfoHtml(...), self::SAFE_HTML),
            new TwigFunction('msg_ok', HtmlShrink::msgOkHtml(...), self::SAFE_HTML),
            new TwigFunction('can_edit_event', fn (array $event): bool => $this->authorization->isPersonneAllowedToEditEvenement($this->session, $event)),
            new TwigFunction('can_edit_event_now', fn (array $event): bool => $this->authorization->isPersonneAllowedToEditEvenementNow($this->session, $event)),
            new TwigFunction('can_manage_event', fn (array $event): bool => $this->authorization->isPersonneAllowedToManageEvenement($this->session, $event)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('int', intval(...)),
            new TwigFilter('date_fr', DateHelper::isoToFr(...), self::SAFE_HTML),
            new TwigFilter('category_anchor', EventCategory::anchor(...)),
            new TwigFilter('ucfirst', ucfirst(...), ['preserves_safety' => ['html']]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\DCA;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Database;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Hofff\Contao\LanguageRelations\LanguageRelations;
use Hofff\Contao\LanguageRelations\Util\EnvironmentProxy;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_unshift;
use function assert;
use function count;
use function rtrim;
use function sprintf;
use function str_repeat;

class GroupDCA
{
    /** @var string[]|int[] */
    private array $roots;

    public function __construct()
    {
        $this->roots = [];
    }

    public function keySelectriAJAXCallback(DataContainer $dataContainer): string
    {
        if (! $dataContainer instanceof DC_Table) {
            throw new BadRequestException();
        }

        $key = 'isAjaxRequest';

        // the X-Requested-With gets deleted on ajax requests by selectri widget,
        // to enable regular contao DC process, but we need this behavior for the
        // editAll call respecting the passed id
        $$key = EnvironmentProxy::getCacheValue($key);
        EnvironmentProxy::setCacheValue($key, true);

        $return = $dataContainer->editAll(Input::get('hofff_language_relations_id'));

        // this would never be reached, but we clean up the env
        EnvironmentProxy::setCacheValue($key, $$key);

        return $return;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function keyEditRelations(): void
    {
        $fields = ['hofff_language_relations_info', 'hofff_language_relations'];
        $roots  = (array) $_GET['roots'];
        $roots  = array_map('intval', $roots);
        $roots  = array_filter($roots, static function ($root) {
            return $root >= 1;
        });
        $roots  = array_unique($roots);
        $ids    = null;

        switch ($_GET['filter']) {
            case 'incomplete':
                $ids         = LanguageRelations::getIncompleteRelatedPages($roots[0]);
                $ids || $msg = $GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['noIncompleteRelations'];
                break;

            case 'ambiguous':
                $ids         = LanguageRelations::getAmbiguousRelatedPages($roots[0]);
                $ids || $msg = $GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['noAmbiguousRelations'];
                break;

            default:
                if ($roots) {
                    $wildcards = rtrim(str_repeat('?,', count($roots)), ',');
                    $result    = Database::getInstance()->prepare(
                        'SELECT id FROM tl_page WHERE hofff_root_page_id IN (' . $wildcards . ') AND type != \'root\''
                    )->execute($roots);
                    $ids       = $result->fetchEach('id');
                }

                break;
        }

        if (! $ids) {
            Message::addConfirmation($msg ?? $GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['noPagesToEdit']);
            Controller::redirect(System::getReferer());

            return;
        }

        $sessionService = System::getContainer()->get('session');
        assert($sessionService instanceof SessionInterface);

        $session                       = $sessionService->all();
        $session['CURRENT']['IDS']     = $ids;
        $session['CURRENT']['tl_page'] = $fields;
        $sessionService->replace($session);

        $tokenManager = System::getContainer()->get('contao.csrf.token_manager');
        assert($tokenManager instanceof ContaoCsrfTokenManager);

        Controller::redirect(
            'contao?do=hofff_language_relations_group&table=tl_page&act=editAll&fields=1&rt='
            . $tokenManager->getDefaultTokenValue()
        );
    }

    /**
     * @param mixed[] $row
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function groupGroup(string $group, ?string $mode, string $field, array $row): string
    {
        return $row['title'];
    }

    /**
     * @param mixed[] $row
     */
    public function labelGroup(array $row): string
    {
        $sql    = 'SELECT * FROM tl_page WHERE hofff_language_relations_group_id = ? ORDER BY title';
        $result = Database::getInstance()->prepare($sql)->execute($row['id']);

        $groupRoots = [];
        while ($result->next()) {
            $row               = $result->row();
            $row['incomplete'] = LanguageRelations::getIncompleteRelatedPages((int) $row['id']);
            $row['ambiguous']  = LanguageRelations::getAmbiguousRelatedPages((int) $row['id']);
            $groupRoots[]      = $row;
        }

        $tpl             = new BackendTemplate('hofff_language_relations_group_roots');
        $tpl->groupRoots = $groupRoots;

        return $tpl->parse();
    }

    /**
     * @return string[][]
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function getRootsOptions(): array
    {
        $sql    = <<<SQL
SELECT
	page.id			AS page_id,
	page.title		AS page_title,
	page.language	AS page_language,
	grp.id			AS group_id,
	grp.title		AS group_title
FROM
	tl_page
	AS page
LEFT JOIN
	tl_hofff_language_relations_group
	AS grp
	ON grp.id = page.hofff_language_relations_group_id
WHERE
	page.type = ?
ORDER BY
	grp.title IS NOT NULL,
	grp.title,
	page.title
SQL;
        $result = Database::getInstance()->prepare($sql)->execute('root');

        $options = [];
        while ($result->next()) {
            $groupTitle                             = $result->group_id
            ? sprintf('%s (ID %s)', $result->group_title, $result->group_id)
            : $GLOBALS['TL_LANG']['tl_hofff_language_relations_group']['notGrouped'];
            $options[$groupTitle][$result->page_id] = sprintf(
                '%s [%s] (ID %s)',
                $result->page_title,
                $result->page_language,
                $result->page_id
            );
        }

        return $options;
    }

    public function onsubmitGroup(DataContainer $dataContainer): void
    {
        if (! isset($this->roots[$dataContainer->id])) {
            return;
        }

        Database::getInstance()
            ->prepare(
                'UPDATE tl_page SET hofff_language_relations_group_id=NULL WHERE hofff_language_relations_group_id=?'
            )
            ->execute($dataContainer->id);

        $roots = StringUtil::deserialize($this->roots[$dataContainer->id], true);
        if (! $roots) {
            return;
        }

        $wildcards = rtrim(str_repeat('?,', count($roots)), ',');
        $sql       = 'UPDATE tl_page SET hofff_language_relations_group_id=? WHERE id IN (' . $wildcards . ')';
        array_unshift($roots, $dataContainer->id);
        Database::getInstance()->prepare($sql)->execute($roots);
    }

    /**
     * @param mixed         $value
     * @param DataContainer $dataContainer
     *
     * @return mixed[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function loadRoots($value, $dataContainer): array
    {
        $sql    = 'SELECT id FROM tl_page WHERE hofff_language_relations_group_id=? AND type=? ORDER BY title';
        $result = Database::getInstance()->prepare($sql)->execute($dataContainer->id, 'root');

        return $result->fetchEach('id');
    }

    /**
     * @param string|int $value
     *
     * @return null
     */
    public function saveRoots($value, DataContainer $dataContainer)
    {
        $this->roots[$dataContainer->id] = $value;

        return null;
    }
}

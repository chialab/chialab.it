<?php
declare(strict_types=1);

namespace App\Controller\Component;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Database\Expression\CommonTableExpression;
use Cake\Database\Expression\FunctionExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query\SelectQuery;
use Cake\Database\StatementInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;

/**
 * Component to find the canonical URL for an object.
 */
class CanonicalUrlComponent extends Component
{
    use LocatorAwareTrait;

    /**
     * @inheritDoc
     */
    protected $_defaultConfig = [
        'statusLevel' => null,
        'propertyField' => 'extra',
        'propertyName' => 'public_url',
        'cache' => 'canonical_urls',
    ];

    /**
     * Get the frontend URL for the canonical tree position of an object.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object we're looking for.
     * @param string $propertyField The field where the frontend URL is stored.
     * @param string $propertyName The property name where the frontend URL is stored.
     * @return string|null
     */
    public function getCanonicalFrontendUrl(ObjectEntity $object, string $propertyField, string $propertyName): string|null
    {
        /** @var \BEdita\Core\Model\Table\TreesTable $trees */
        $trees = $this->fetchTable('Trees');
        $frontendUrl = new FunctionExpression('JSON_UNQUOTE', [new FunctionExpression('JSON_EXTRACT', [
            $trees->Objects->aliasField($propertyField) => 'identifier',
            sprintf('$.%s', $propertyName),
        ])]);

        /** @var \Cake\ORM\Query\SelectQuery $query */
        $query = $trees->find();
        $query = $query
            ->useReadRole()
            ->disableHydration()
            ->with(
                fn (CommonTableExpression $cte, SelectQuery $query): CommonTableExpression => $cte
                    ->recursive()
                    ->name('paths')
                    ->field(['canonical', 'frontend_url', 'parent_id'])
                    ->query(
                        $trees->find()
                            ->select([
                                $trees->aliasField('canonical'),
                                $frontendUrl,
                                $trees->aliasField('parent_id'),
                            ])
                            ->innerJoinWith(
                                $trees->Objects->getName(),
                                fn (Query $query): Query => $query->find('available'),
                            )
                            ->where([$trees->aliasField('object_id') => $object->id])

                            ->unionAll(
                                $trees->find()
                                    ->select([
                                        'paths.canonical',
                                        $frontendUrl,
                                        $trees->aliasField('parent_id'),
                                    ])
                                    ->innerJoinWith(
                                        $trees->Objects->getName(),
                                        fn (Query $query): Query => $query->find('available'),
                                    )
                                    ->innerJoin(
                                        'paths',
                                        [fn (QueryExpression $exp): QueryExpression => $exp
                                            ->equalFields('paths.parent_id', $trees->aliasField('object_id'))],
                                    ),
                            ),
                    ),
            )
            ->select(fn (Query $query): array => [
                'frontend_url' => 'paths.frontend_url',
                'canonical' => $query->func()
                    ->aggregate('BIT_OR', ['paths.canonical' => 'identifier'], return: 'boolean'),
            ])
            ->from('paths')
            /*
             * We now filter, out of all tree positions we've walked up considering only published parents at each step,
             * only those where we were able to find an ancestor node at a certain point that has the frontend URL set.
             */
            ->where(fn (QueryExpression $exp): QueryExpression => $exp->isNotNull('paths.frontend_url'))
            /*
             * We now sort by canonical position first.
             * Also, if an object is present multiple times in the same frontend, for the sake of the canonical URL
             * we consider it to be a single position.
             */
            ->group('paths.frontend_url')
            ->orderDesc('canonical')
            /*
             * Limit to 2 results because:
             *  - if zero rows are returned, we cannot find a canonical URL.
             *  - if one row is returned, that should be considered the canonical URL regardless of whether it was
             *      explicitly set as canonical or not.
             *  - if two rows are returned and the first of them is explicitly set as canonical, that should be
             *      considered the canonical URL, and we're not interested in how many more positions there are.
             *  - if two rows are returned and none of them is explicitly set as canonical, we cannot decide which
             *      of them is the canonical one, and this is true regardless of how many more positions there may be.
             */
            ->limit(2);

        /** @var array{frontend_url: string, canonical: bool}[]|false $results */
        $results = Cache::remember(
            $object->uname,
            fn (): array|bool => $query->execute()->fetchAll(StatementInterface::FETCH_TYPE_ASSOC),
            $this->getConfigOrFail('cache'),
        );

        if (empty($results)) {
            return null;
        }

        ['frontend_url' => $frontendUrl, 'canonical' => $canonical] = array_shift($results);
        if ($canonical || empty($results)) {
            return $frontendUrl;
        }

        return null;
    }

    /**
     * Build the canonical URL for an object.
     *
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object we're looking for.
     * @return string|null
     */
    public function buildCanonicalUrl(ObjectEntity $object): string|null
    {
        $propertyField = $this->getConfigOrFail('propertyField');
        $propertyName = $this->getConfigOrFail('propertyName');

        if (!empty($object[$propertyField][$propertyName])) {
            // This case should cover root folders only.
            // object has a frontend URL set, return it immediately.
            return $object[$propertyField][$propertyName];
        }

        $canonicalFrontendUrl = $this->getCanonicalFrontendUrl($object, $propertyField, $propertyName);
        if ($canonicalFrontendUrl === null) {
            return null;
        }

        return sprintf('%s/objects/%s', rtrim($canonicalFrontendUrl, '/'), $object->uname);
    }
}

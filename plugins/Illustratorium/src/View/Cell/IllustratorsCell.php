<?php

declare(strict_types=1);

namespace Illustratorium\View\Cell;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\Database\Expression\FunctionExpression;
use Cake\View\Cell;
use Chialab\FrontendKit\Model\ObjectsLoader;

use function Cake\Collection\collection;

/**
 * Illustrators cell
 */
class IllustratorsCell extends Cell
{
    /**
     * BEdita objects loader.
     *
     * @var \Chialab\FrontendKit\Model\ObjectsLoader
     */
    protected ObjectsLoader $loader;

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loader = new ObjectsLoader([
            'objects' => ['include' => 'poster|1'],
            'profiles' => ['include' => 'poster|1,see_also,has_media'],
        ], [
            'see_also' => 2,
            'has_media' => 2,
        ]);
    }

    /**
     * Get clean surname from a given profile object or the best available full name part.
     * @param \BEdita\Core\Model\Entity\ObjectEntity $obj Profile object.
     *
     * @return string Clean surname.
     */
    protected function getCleanSurname(ObjectEntity $obj): string
    {
        $surname = (string)($obj->get('surname')
            ?? $obj->get('name')
            ?? $obj->get('pseudonym')
            ?? $obj->get('title')
            ?? '');
        if ($surname === '') {
            return '';
        }

        return preg_replace('/^[^\p{L}]+/u', '', $surname) ?? '';
    }

    /**
     * Sort illustrators by surname initial.
     *
     * @param iterable<\BEdita\Core\Model\Entity\ObjectEntity> $illustrators Illustrators to sort.
     * @return array<\BEdita\Core\Model\Entity\ObjectEntity> Sorted illustrators.
     */
    protected function sortBySurnameInitial(iterable $illustrators): array
    {
        $illustrators = is_array($illustrators) ? $illustrators : collection($illustrators)->toArray();
        usort($illustrators, fn (ObjectEntity $a, ObjectEntity $b) => strcasecmp($this->getCleanSurname($a), $this->getCleanSurname($b)));

        return $illustrators;
    }

    /**
     * Load illustrators data for the index and cards view.
     *
     * @param int|null $limit Number of illustrators to load.
     * @param bool $randomize Whether to randomize the illustrators order.
     * @return void
     */
    protected function loadIllustratorsData(int|null $limit = 6, bool $randomize = true): void
    {
        $folder = $this->loader->loadObject('illustrators', 'folders');
        $illustrators = $this->loader->loadRelatedObjects('illustrators', 'folders', 'children');

        if ($randomize) {
            $illustrators = $illustrators->order(new FunctionExpression('RAND', returnType: 'double'), true);
        } else {
            $illustrators = $illustrators->formatResults(fn (iterable $illustrators): iterable => collection($this->sortBySurnameInitial($illustrators)));
        }
        $illustrators = $illustrators->all();
        $index = $this->index($illustrators);
        if ($limit !== null) {
            $illustrators = collection($illustrators)->chunk($limit)->first() ?? [];
        }

        $this->set(compact('folder', 'illustrators', 'index'));
    }

    /**
     * Group illustrators by surname's initial letter and sort by surname within each group.
     *
     * @param iterable<\BEdita\Core\Model\Entity\ObjectEntity> $illustrators Illustrators to index.
     * @return array Grouped illustrators.
     */
    protected function index(iterable $illustrators): array
    {
        // Group illustrators by surname's initial letter
        $illustratorsByLetter = collection($illustrators)
            ->reduce(function ($grouped, $illustrator) {
                $cleanSurname = $this->getCleanSurname($illustrator);
                if ($cleanSurname === '') {
                    return $grouped;
                }
                $initial = strtoupper(mb_substr($cleanSurname, 0, 1));
                if (!isset($grouped[$initial])) {
                    $grouped[$initial] = [];
                }
                $grouped[$initial][] = $illustrator;

                return $grouped;
            }, []);

        // Sort by letter and by surname within each letter
        ksort($illustratorsByLetter);
        foreach ($illustratorsByLetter as $letter => $list) {
            $illustratorsByLetter[$letter] = $this->sortBySurnameInitial($list);
        }

        return $illustratorsByLetter;
    }

    /**
     * Load data for homepage.
     */
    public function display(): void
    {
        $this->loadIllustratorsData(6, true);
    }

    /**
     * Load all illustrators.
     */
    public function all(): void
    {
        $this->loadIllustratorsData(null, false);
    }

}

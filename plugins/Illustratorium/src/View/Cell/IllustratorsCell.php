<?php

declare(strict_types=1);

namespace Illustratorium\View\Cell;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\Database\Expression\FunctionExpression;
use Cake\Datasource\Paging\NumericPaginator;
use Cake\View\Cell;
use Chialab\FrontendKit\Model\ObjectsLoader;

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
            'profiles' => ['include' => 'poster|1,see_also'],
        ], [
            'see_also' => 2,
        ]);
    }

    /**
     * Get clean surname from a given profile object or the best available full name part.
     * @param \BEdita\Core\Model\Entity\ObjectEntity $obj Profile object.
     *
     * @return string Clean surname.
     */
    private function getCleanSurname(ObjectEntity $obj): string
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
     * @param array<\BEdita\Core\Model\Entity\ObjectEntity> $illustrators Illustrators to sort.
     *
     * @return array<\BEdita\Core\Model\Entity\ObjectEntity> Sorted illustrators.
     */
    private function sortBySurnameInitial(array $illustrators): array
    {
        usort($illustrators, function (ObjectEntity $a, ObjectEntity $b) {
            $aSurname = $this->getCleanSurname($a);
            $bSurname = $this->getCleanSurname($b);
            return strcasecmp($aSurname, $bSurname);
        });
        return $illustrators;
    }

    /**
     * Display cards view for illustrators.
     *
     * @return void
     */
    public function cards(array $illustrators = null): void
    {
        if (empty($illustrators)) {
            $paginator = new NumericPaginator();
            $illustrators = $paginator->paginate($this->loader
                ->loadRelatedObjects('illustrators', 'folders', 'children')
                )->toArray();
            $illustrators = $this->sortBySurnameInitial($illustrators);
        }
        $this->set(compact('illustrators'));
    }

    /**
     * Display home illustrators section.
     *
     * @return void
     */
    public function display(): void
    {
        $folder = $this->loader->loadObject('illustrators', 'folders');
        $randomIllustrators = $this->loader
            ->loadRelatedObjects('illustrators', 'folders', 'children')
            ->order(new FunctionExpression('RAND', returnType: 'double'), true)
            ->limit(6)
            ->toArray();
        $this->set(compact('folder', 'randomIllustrators'));
        // per qualche motivo la Cell non riesce a trovare il template se chiamo la funzione `home`
        $this->viewBuilder()->setTemplate('home');
    }

    /**
     * Display all illustrators from `illustrators` folder grouped by surname's initial letter.
     *
     * @return void
     */
    public function index(): void
    {
        $folder = $this->loader->loadObject('illustrators', 'folders');
        $illustrators = $this->loader
            ->loadRelatedObjects('illustrators', 'folders', 'children');

        // Group illustrators by surname's initial letter
        $illustratorsByLetter = $illustrators
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
        $this->set(compact('folder', 'illustratorsByLetter'));
    }
}

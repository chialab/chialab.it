<?php

declare(strict_types=1);

namespace Illustratorium\View\Cell;

use BEdita\Core\Model\Entity\ObjectEntity;
use Cake\Database\Expression\FunctionExpression;
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
     * Get uppercase initial from a cleaned surname.
     */
    private function surnameInitial(string $cleanSurname): string
    {
        return strtoupper(mb_substr($cleanSurname, 0, 1));
    }

    /**
     * Display 6 random illustrators from `illustrators` folder.
     *
     * @return void
     */
    public function cards(): void
    {
        $illustrators = $this->loader
            ->loadRelatedObjects('illustrators', 'folders', 'children')
            ->order(new FunctionExpression('RAND', returnType: 'double'), true)
            ->limit(6)
            ->toArray();

        $this->set(compact('illustrators'));
    }

    /**
     * Display all illustrators from `illustrators` folder grouped by surname's initial letter.
     *
     * @return void
     */
    public function display(): void
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
                $initial = $this->surnameInitial($cleanSurname);
                if (!isset($grouped[$initial])) {
                    $grouped[$initial] = [];
                }
                $grouped[$initial][] = $illustrator;

                return $grouped;
            }, []);

        // Sort by letter and by surname within each letter
        ksort($illustratorsByLetter);
        foreach ($illustratorsByLetter as $letter => $list) {
            usort($list, function ($a, $b) {
                $aSurname = $this->getCleanSurname($a);
                $bSurname = $this->getCleanSurname($b);
                return strcasecmp($aSurname, $bSurname);
            });
            $illustratorsByLetter[$letter] = $list;
        }
        $this->set(compact('folder', 'illustratorsByLetter'));
    }
}

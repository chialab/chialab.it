<?php
declare(strict_types=1);

namespace App\View\Helper;

use BEdita\Core\Model\Entity\Profile;
use Cake\View\Helper;

/**
 * Vcard helper
 */
class VCardHelper extends Helper
{
    /**
     * Generate vcard content.
     *
     * @param \BEdita\Core\Model\Entity\Profile $card
     * @return string
     */
    public function content(Profile $card): string
    {
        $attrs = [
            'VERSION' => '3.0',
            'REV' => date('Ymd'),
        ];
        if (!empty($card['name']) && !empty($card['surname'])) {
            $attrs += [
                'N' => $card['surname'] . ';' . $card['name'],
                'FN' => $card['name'] . ' ' . $card['surname'],
            ];
        }
        if (!empty($card['organization'])) {
            $attrs += [
                'ORG' => $card['organization'],
            ];
        }
        if (!empty($card['customProperties']['person_role'])) {
            $attrs += [
                'title' => strip_tags($card['customProperties']['person_role']),
            ];
        }
        if (!empty($card['email'])) {
            $attrs += [
                'EMAIL;TYPE=internet,pref' => $card['email'],
            ];
        }
        $vcard = "BEGIN:VCARD\r\n";
        foreach ($attrs as $key => $value) {
            $vcard .= $key . ':' . $value . "\r\n";
        }
        $vcard .= 'END:VCARD';

        return $vcard;
    }

    /**
     * Generate vcard url.
     *
     * @param \BEdita\Core\Model\Entity\Profile $card
     * @return string
     */
    public function url(Profile $card): string
    {
        $content = $this->content($card);

        return 'data:text/x-vcard;base64,' . base64_encode($content);
    }
}

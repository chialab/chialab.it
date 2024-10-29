<?php
declare(strict_types=1);

namespace App\View\Helper;

use BEdita\Core\Utility\Text;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;
use Cake\View\Helper;
use DOMDocument;
use DOMElement;

/**
 * Text helper
 *
 * @property \Chialab\FrontendKit\View\Helper\PlaceholdersHelper $Placeholders
 */
class TextHelper extends Helper
{
    /**
     * @inheritDoc
     */
    protected $helpers = ['Placeholders'];

    /**
     * Default error reporting.
     *
     * @var int
     */
    protected static int $_defaultErrorReporting = 1;

    /**
     * Convert all major XML entities in a string to the unicode form.
     *
     * @param string $text
     * @return string
     */
    protected static function convertXmlEntity(string $text): string
    {
        static $table = [
            '&quot;' => '&#34;', '&amp;' => '&#38;', '&lt;' => '&#60;', '&gt;' => '&#62;', '&OElig;' => '&#338;', '&oelig;' => '&#339;', '&Scaron;' => '&#352;',
            '&scaron;' => '&#353;', '&Yuml;' => '&#376;', '&circ;' => '&#710;', '&tilde;' => '&#732;', '&ensp;' => '&#8194;', '&emsp;' => '&#8195;',
            '&thinsp;' => '&#8201;', '&zwnj;' => '&#8204;', '&zwj;' => '&#8205;', '&lrm;' => '&#8206;', '&rlm;' => '&#8207;', '&ndash;' => '&#8211;',
            '&mdash;' => '&#8212;', '&lsquo;' => '&#8216;', '&rsquo;' => '&#8217;', '&sbquo;' => '&#8218;', '&ldquo;' => '&#8220;', '&rdquo;' => '&#8221;',
            '&bdquo;' => '&#8222;', '&dagger;' => '&#8224;', '&Dagger;' => '&#8225;', '&permil;' => '&#8240;', '&lsaquo;' => '&#8249;', '&rsaquo;' => '&#8250;',
            '&euro;' => '&#8364;', '&fnof;' => '&#402;', '&Alpha;' => '&#913;', '&Beta;' => '&#914;', '&Gamma;' => '&#915;', '&Delta;' => '&#916;', '&Epsilon;' => '&#917;',
            '&Zeta;' => '&#918;', '&Eta;' => '&#919;', '&Theta;' => '&#920;', '&Iota;' => '&#921;', '&Kappa;' => '&#922;', '&Lambda;' => '&#923;', '&Mu;' => '&#924;',
            '&Nu;' => '&#925;', '&Xi;' => '&#926;', '&Omicron;' => '&#927;', '&Pi;' => '&#928;', '&Rho;' => '&#929;', '&Sigma;' => '&#931;', '&Tau;' => '&#932;', '&Upsilon;' => '&#933;',
            '&Phi;' => '&#934;', '&Chi;' => '&#935;', '&Psi;' => '&#936;', '&Omega;' => '&#937;', '&alpha;' => '&#945;', '&beta;' => '&#946;', '&gamma;' => '&#947;', '&delta;' => '&#948;',
            '&epsilon;' => '&#949;', '&zeta;' => '&#950;', '&eta;' => '&#951;', '&theta;' => '&#952;', '&iota;' => '&#953;', '&kappa;' => '&#954;', '&lambda;' => '&#955;', '&mu;' => '&#956;',
            '&nu;' => '&#957;', '&xi;' => '&#958;', '&omicron;' => '&#959;', '&pi;' => '&#960;', '&rho;' => '&#961;', '&sigmaf;' => '&#962;', '&sigma;' => '&#963;', '&tau;' => '&#964;',
            '&upsilon;' => '&#965;', '&phi;' => '&#966;', '&chi;' => '&#967;', '&psi;' => '&#968;', '&omega;' => '&#969;', '&thetasym;' => '&#977;', '&upsih;' => '&#978;', '&piv;' => '&#982;',
            '&bull;' => '&#8226;', '&hellip;' => '&#8230;', '&prime;' => '&#8242;', '&Prime;' => '&#8243;', '&oline;' => '&#8254;', '&frasl;' => '&#8260;', '&weierp;' => '&#8472;',
            '&image;' => '&#8465;', '&real;' => '&#8476;', '&trade;' => '&#8482;', '&alefsym;' => '&#8501;', '&larr;' => '&#8592;', '&uarr;' => '&#8593;', '&rarr;' => '&#8594;', '&darr;' => '&#8595;',
            '&harr;' => '&#8596;', '&crarr;' => '&#8629;', '&lArr;' => '&#8656;', '&uArr;' => '&#8657;', '&rArr;' => '&#8658;', '&dArr;' => '&#8659;', '&hArr;' => '&#8660;', '&forall;' => '&#8704;',
            '&part;' => '&#8706;', '&exist;' => '&#8707;', '&empty;' => '&#8709;', '&nabla;' => '&#8711;', '&isin;' => '&#8712;', '&notin;' => '&#8713;', '&ni;' => '&#8715;', '&prod;' => '&#8719;',
            '&sum;' => '&#8721;', '&minus;' => '&#8722;', '&lowast;' => '&#8727;', '&radic;' => '&#8730;', '&prop;' => '&#8733;', '&infin;' => '&#8734;', '&ang;' => '&#8736;', '&and;' => '&#8743;',
            '&or;' => '&#8744;', '&cap;' => '&#8745;', '&cup;' => '&#8746;', '&int;' => '&#8747;', '&there4;' => '&#8756;', '&sim;' => '&#8764;', '&cong;' => '&#8773;', '&asymp;' => '&#8776;',
            '&ne;' => '&#8800;', '&equiv;' => '&#8801;', '&le;' => '&#8804;', '&ge;' => '&#8805;', '&sub;' => '&#8834;', '&sup;' => '&#8835;', '&nsub;' => '&#8836;', '&sube;' => '&#8838;',
            '&supe;' => '&#8839;', '&oplus;' => '&#8853;', '&otimes;' => '&#8855;', '&perp;' => '&#8869;', '&sdot;' => '&#8901;', '&lceil;' => '&#8968;', '&rceil;' => '&#8969;', '&lfloor;' => '&#8970;',
            '&rfloo(r;' => '&#8971;', '&lang;' => '&#9001;', '&rang;' => '&#9002;', '&loz;' => '&#9674;', '&spades;' => '&#9824;', '&clubs;' => '&#9827;', '&hearts;' => '&#9829;', '&diams;' => '&#9830;',
            '&nbsp;' => '&#160;', '&iexcl;' => '&#161;', '&cent;' => '&#162;', '&pound;' => '&#163;', '&curren;' => '&#164;', '&yen;' => '&#165;', '&brvbar;' => '&#166;', '&sect;' => '&#167;', '&uml;' => '&#168;',
            '&copy;' => '&#169;', '&ordf;' => '&#170;', '&laquo;' => '&#171;', '&not;' => '&#172;', '&shy;' => '&#173;', '&reg;' => '&#174;', '&macr;' => '&#175;', '&deg;' => '&#176;', '&plusmn;' => '&#177;',
            '&sup2;' => '&#178;', '&sup3;' => '&#179;', '&acute;' => '&#180;', '&micro;' => '&#181;', '&para;' => '&#182;', '&middot;' => '&#183;', '&cedil;' => '&#184;', '&sup1;' => '&#185;',
            '&ordm;' => '&#186;', '&raquo;' => '&#187;', '&frac14;' => '&#188;', '&frac12;' => '&#189;', '&frac34;' => '&#190;', '&iquest;' => '&#191;', '&Agrave;' => '&#192;', '&Aacute;' => '&#193;',
            '&Acirc;' => '&#194;', '&Atilde;' => '&#195;', '&Auml;' => '&#196;', '&Aring;' => '&#197;', '&AElig;' => '&#198;', '&Ccedil;' => '&#199;', '&Egrave;' => '&#200;', '&Eacute;' => '&#201;',
            '&Ecirc;' => '&#202;', '&Euml;' => '&#203;', '&Igrave;' => '&#204;', '&Iacute;' => '&#205;', '&Icirc;' => '&#206;', '&Iuml;' => '&#207;', '&ETH;' => '&#208;', '&Ntilde;' => '&#209;',
            '&Ograve;' => '&#210;', '&Oacute;' => '&#211;', '&Ocirc;' => '&#212;', '&Otilde;' => '&#213;', '&Ouml;' => '&#214;', '&times;' => '&#215;', '&Oslash;' => '&#216;', '&Ugrave;' => '&#217;',
            '&Uacute;' => '&#218;', '&Ucirc;' => '&#219;', '&Uuml;' => '&#220;', '&Yacute;' => '&#221;', '&THORN;' => '&#222;', '&szlig;' => '&#223;', '&agrave;' => '&#224;', '&aacute;' => '&#225;',
            '&acirc;' => '&#226;', '&atilde;' => '&#227;', '&auml;' => '&#228;', '&aring;' => '&#229;', '&aelig;' => '&#230;', '&ccedil;' => '&#231;', '&egrave;' => '&#232;', '&eacute;' => '&#233;',
            '&ecirc;' => '&#234;', '&euml;' => '&#235;', '&igrave;' => '&#236;', '&iacute;' => '&#237;', '&icirc;' => '&#238;', '&iuml;' => '&#239;', '&eth;' => '&#240;', '&ntilde;' => '&#241;',
            '&ograve;' => '&#242;', '&oacute;' => '&#243;', '&ocirc;' => '&#244;', '&otilde;' => '&#245;', '&ouml;' => '&#246;', '&divide;' => '&#247;', '&oslash;' => '&#248;', '&ugrave;' => '&#249;',
            '&uacute;' => '&#250;', '&ucirc;' => '&#251;', '&uuml;' => '&#252;', '&yacute;' => '&#253;', '&thorn;' => '&#254;', '&yuml;' => '&#255;',
        ];

        return str_replace(array_keys($table), array_values($table), $text);
    }

    /**
     * Disable error reporting.
     *
     * @return void
     */
    protected static function disableErrorReporting(): void
    {
        static::$_defaultErrorReporting = error_reporting();
    }

    /**
     * Restore error reporting to previous state.
     *
     * @return void
     */
    protected static function restoreErrorReporting(): void
    {
        error_reporting(0);
    }

    /**
     * Parses html text.
     *
     * @param string $text
     * @param bool $convertEntities Convert encoding and XML entities.
     * @return \DOMDocument
     */
    protected function parseHTML(string $text, bool $convertEntities = true): DOMDocument
    {
        static::disableErrorReporting();

        if ($convertEntities) {
            $text = static::convertXmlEntity(mb_encode_numericentity($text, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        $doc->recover = true;
        @$doc->loadHTML($text);

        static::restoreErrorReporting();

        return $doc;
    }

    /**
     * Change element tag.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $element
     * @param string $name
     * @return \DOMElement
     */
    protected function changeTag(DOMDocument $dom, DOMElement $element, string $name): DOMElement
    {
        /**
         * @var \DOMElement $newNode
         */
        $newNode = $dom->createElement($name);
        $newNode->nodeValue = $element->nodeValue;
        foreach ($element->childNodes as $child) {
            $newNode->appendChild($child);
        }
        foreach ($element->attributes as $attrName => $attrNode) {
            $newNode->setAttribute($attrName, $attrNode->value);
        }
        $element->parentNode->replaceChild($newNode, $element);

        return $newNode;
    }

    /**
     * Downgrade heading levels.
     *
     * @param \DOMDocument $dom
     * @param int $downgrade
     * @return void
     */
    protected function downgradeHeadings(DOMDocument $dom, int $downgrade = 0): void
    {
        static $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

        for ($index = count($headings) - 1; $index >= 0; $index--) {
            $tag = $headings[$index];
            $elements = $dom->getElementsByTagName($tag);
            $len = $elements->length;
            for ($i = $len - 1; $i >= 0; $i--) {
                /**
                 * @var \DOMElement|null $element
                 */
                $element = $elements->item($i);
                if (!$element) {
                    continue;
                }

                $text = $element->textContent;
                if ($element->hasAttribute('id')) {
                    $slug = $element->getAttribute('id');
                } else {
                    $slug = strtolower(preg_replace('/_/', '-', Text::slug($text)));
                }
                if (!empty($downgrade)) {
                    $element = $this->changeTag($dom, $element, 'h' . min($index + 1 + $downgrade, count($headings)));
                }
                $element->setAttribute('id', $slug);
            }
        }
    }

    /**
     * Remove empty paragraphs.
     *
     * @param \DOMDocument $dom
     * @return void
     */
    protected function removeEmptyParagraphs(DOMDocument $dom)
    {
        $elements = $dom->getElementsByTagName('p');
        $len = $elements->length;
        for ($i = $len - 1; $i >= 0; $i--) {
            /**
             * @var \DOMElement|null $element
             */
            $element = $elements->item($i);
            if (!$element) {
                continue;
            }

            $content = preg_replace('/[\s]+/mu', '', $element->textContent);
            if (empty($content)) {
                $element->parentNode->removeChild($element);
            }
        }
    }

    /**
     * Render a body text with some transformations:
     * * Downgrade headings
     * * Add pilcrow link to headings
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity.
     * @param string $field Field to be templated.
     * @param array|null $options Render options.
     * @return string
     */
    public function renderBody(EntityInterface $entity, string $field, array|null $options = []): string
    {
        $text = $this->Placeholders->template($entity, $field);
        $dom = $this->parseHTML($text);
        $this->downgradeHeadings($dom, Hash::get($options, 'downgradeHeadings', 0));
        $this->removeEmptyParagraphs($dom);

        $content = $dom->saveHTML();
        $content = preg_replace(
            '/^<!DOCTYPE.+?>/',
            '',
            str_replace(['<html>', '</html>', '<head>', '<meta http-equiv="Content-type" content="text/html; charset=UTF-8">', '</head>', '<body>', '</body>'], '', $content)
        );

        return $content;
    }
}

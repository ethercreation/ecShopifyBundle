<?php

namespace bundles\ecShopifyBundle\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use bundles\ecMiddleBundle\Services\Outils;
use Pimcore\Model\DataObject\{Address,
    Attribut,
    Carac,
    Product,
    Client,
    Config,
    Declinaison,
    Diffusion,
    Entrepot,
    Marque,
    Paiement};
use SimpleXMLElement;
use Locale;
// use Pimcore\Localization\Service;
use Pimcore\Model\DataObject\PreGetValueHookInterface;
use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Graphql;
use Shopify\Context;
use bundles\ecMiddleBundle\Services\DbFile;
use Pimcore\Model\WebsiteSetting;


class MigrationController extends FrontendController
{

    private Graphql $client;
    private $config;

    public $catalog_name = 'productShopify';
    public $category_name = 'collectionShopify';

    public $tab_cat = [
        'active' => '',
        'reference' => '',
        'crossid' => 'id',
        'id' => 'id',
        'ean13' => '',
        'upc' => '',
        'isbn' => '',
        'quantity' => '',
        'width' => '',
        'height' => '',
        'depth' => '',
        'weight' => '',
        'wholesale_price' => '',
        'price' => '',
        'id_tax' => '',
        'id_category_default' => '',
        'name' => 'title',
        'description' => 'descriptionHtml',
        'description_short' => 'description',
        'meta_description' => '',
        'meta_title' => '',
        'link_rewrite' => '',

        'listCategories' => 'collections',
        'listPictures' => 'images', // Attention, deprecated
        'listVariant' => 'variants',
        'listFeatures' => 'metafields',
    ];


    /**
     * @Route("/migrationecShopify", name="migrationecShopify")
     */
    public function indexAction(Request $request)
    {
        $data = $request->query->all();
        
        $client = self::declareClient();

        // $retour = $this->cronGetFile([]);
        // $retour = $this->getProductsTest();
        // $retour = $this->getMetafieldDefinition();
        // $retour = $this->cronFillCatalog([]);
        // $retour = self::getMetaobjectByID();
        // $retour = self::getProductByID('gid://shopify/Product/9873437131074');
        return new JsonResponse([$retour ?? 'OK'], 200);

        
        // Lancement du cronGetFile en manuel (limité à 20 tour de boucle)
        $ret = true;
        $lstRet = [];
        foreach (range(0, 25) as $i) {
            $data['nbCron'] = $i;
            $data['cron'] = 'manualTest';

            $retour = $this->cronGetFile($data);
            $lstRet[] = 'Appel '.$i.', retour '.json_encode($retour).', cronData : '.json_encode(WebsiteSetting::getByName($data['cron'])->getData());
            $ret &= (is_numeric($retour) ? true : ((bool) $retour));

            if (is_bool($retour)) {
                break;
            }
        }
        return new JsonResponse([$lstRet], 200);
    }

    public function getProductsTest($cursor = '')
    {
        $client = self::declareClient();

        $addAfter = !$cursor ? '' : (', after: "'.$cursor.'"');
        $query ='{
            product (id: "gid://shopify/Product/9873437131074") {
                id
                title
                handle
                descriptionHtml
                description
                vendor
                productType
                tags
                updatedAt
                createdAt
                metafields(first: 50) {  
                    edges {
                        node {
                            id
                            key
                            namespace
                            value
                            type
                            definition {
                                id
                                name
                                key
                            }
                        }
                    }
                }
            }
        }';
        
        $retour =  $client->query(["query" => $query])->getDecodedBody();
        return $retour;

        
        if (!isset($retour['data']['products']['edges'][0])) { // Retour incorrect
            return false;
        }

        $lst = [
            'items' => [],
            'cursor' => false, // Plus aucune page à récupérer
        ];
        foreach ($retour['data']['products']['edges'] as $line) {
            $lst['items'][] = $line['node'];
        }
        
        if (true === $retour['data']['products']['pageInfo']['hasNextPage']) { // Boucle
            $lst['cursor'] = $retour['data']['products']['pageInfo']['endCursor'];
        }

        return $lst;
    }

    public function getMetafieldDefinition($cursor = '')
    {
        $client = self::declareClient();

        $addAfter = !$cursor ? '' : (', after: "'.$cursor.'"');
        $query ='{
            metafieldDefinitions(first: 250, ownerType: PRODUCT'.$addAfter.') {
                edges {
                    node {
                        id
                        name
                        key
                        ownerType
                    }
                }
            }
        }';
        
        $retour =  $client->query(["query" => $query])->getDecodedBody();
        return $retour;

        
        if (!isset($retour['data']['products']['edges'][0])) { // Retour incorrect
            return false;
        }

        $lst = [
            'items' => [],
            'cursor' => false, // Plus aucune page à récupérer
        ];
        foreach ($retour['data']['products']['edges'] as $line) {
            $lst['items'][] = $line['node'];
        }
        
        if (true === $retour['data']['products']['pageInfo']['hasNextPage']) { // Boucle
            $lst['cursor'] = $retour['data']['products']['pageInfo']['endCursor'];
        }

        return $lst;
    }

    public static function getMetaobjectByID($gid = '')
    {
        $client = self::declareClient();

        // $gid = 'gid://shopify/Metaobject/92302147906'; // TEST
        $query = '{
            metaobject(id: "'.$gid.'") {
                id
                displayName
                fields {
                    key
                    value
                    type
                }
            }
        }';
        
        $retour =  $client->query(["query" => $query])->getDecodedBody();

        return $retour['data']['metaobject'] ?? false;
    }

    public static function declareClient()
    {
        $diffusion = Dataobject::getByPath('/Diffusion/Shopify');
        $config = [
            'shopify_api_hostname' => Outils::getConfigByName($diffusion, 'shopify_api_hostname'),
            'shopify_access_token' => Outils::getConfigByName($diffusion, 'shopify_access_token'),
            'shopify_api_secret' => Outils::getConfigByName($diffusion, 'shopify_api_secret'),
            'shopify_api_key' => Outils::getConfigByName($diffusion, 'shopify_api_key'),
            'shopify_api_scope' => Outils::getConfigByName($diffusion, 'shopify_api_scope'),
            'shopify_api_version' => Outils::getConfigByName($diffusion, 'shopify_api_version'),
        ];

        Context::initialize(
            apiKey: $config['shopify_api_key'],
            apiSecretKey: $config['shopify_api_key'],
            scopes: $config['shopify_api_scope'],
            hostName: $config['shopify_api_hostname'],
            sessionStorage: new FileSessionStorage('/tmp/php_sessions'),
            apiVersion: $config['shopify_api_version'],
            isEmbeddedApp: false,
        );
        
        $client = new Graphql(
            $config['shopify_api_hostname'],
            $config['shopify_access_token'],
        );

        return $client;
    }

    public function getProducts($cursor = '')
    {
        $client = self::declareClient();

        $addAfter = !$cursor ? '' : (', after: "'.$cursor.'"');
        $query ='{
            products (first: 10'.$addAfter.') {
                edges {
                    node {
                        id
                        title
                        handle
                        descriptionHtml
                        description
                        vendor
                        productType
                        tags
                        updatedAt
                        createdAt
                        status
                        collections(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    descriptionHtml
                                    handle
                                }
                            }
                        }
                        images(first: 100) {
                            edges {
                                node {
                                    id
                                    originalSrc
                                    altText
                                }
                            }
                        }
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    barcode
                                    # weight
                                    # weightUnit
                                    price
                                    compareAtPrice
                                    inventoryQuantity
                                    updatedAt
                                    createdAt
                                    taxCode
                                    taxable
                                    selectedOptions {
                                        name
                                        value
                                    }
                                }
                            }
                        }
                        metafields(first: 50) {  
                            edges {
                                node {
                                    id
                                    key
                                    namespace
                                    value
                                    type
                                    definition {
                                        id
                                        name
                                        key
                                    }
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }';
        
        $retour =  $client->query(["query" => $query])->getDecodedBody();
        // return $retour;
        
        if (!isset($retour['data']['products']['edges'][0])) { // Retour incorrect
            return false;
        }

        $lst = [
            'items' => [],
            'cursor' => false, // Plus aucune page à récupérer
        ];
        foreach ($retour['data']['products']['edges'] as $line) {
            $lst['items'][] = $line['node'];
        }
        
        if (true === $retour['data']['products']['pageInfo']['hasNextPage']) { // Boucle
            $lst['cursor'] = $retour['data']['products']['pageInfo']['endCursor'];
        }

        return $lst;
    }

    public static function getProductByID($gid, $fileName = 'produitUnitaireShopify')
    {
        $client = self::declareClient();

        $query ='{
            product (id: "'.$gid.'") {
                id
                title
                handle
                descriptionHtml
                description
                vendor
                productType
                tags
                updatedAt
                createdAt
                status
                collections(first: 100) {
                    edges {
                        node {
                            id
                            title
                            descriptionHtml
                            handle
                        }
                    }
                }
                images(first: 100) {
                    edges {
                        node {
                            id
                            originalSrc
                            altText
                        }
                    }
                }
                variants(first: 100) {
                    edges {
                        node {
                            id
                            title
                            sku
                            barcode
                            # weight
                            # weightUnit
                            price
                            compareAtPrice
                            inventoryQuantity
                            updatedAt
                            createdAt
                            taxCode
                            taxable
                            selectedOptions {
                                name
                                value
                            }
                        }
                    }
                }
                metafields(first: 50) {  
                    edges {
                        node {
                            id
                            key
                            namespace
                            value
                            type
                            definition {
                                id
                                name
                                key
                            }
                        }
                    }
                }
            }
        }';
        
        $retour =  $client->query(["query" => $query])->getDecodedBody();

        if (!isset($retour['data']['product'])) {
            return false;
        }

        $ret = DbFile::buildFromArray([$retour['data']['product']], $fileName, false, [get_class(), 'completeFields']);
        if (!is_numeric($ret)) {
            Outils::addLog('(EcShopify ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 1);
            return false;
        }
        
        return true;
    }

    public function getCollections($cursor = '')
    {
        $client = self::declareClient();

        $addAfter = !$cursor ? '' : (', after: "'.$cursor.'"');
        $query ='{
            collections (first: 50'.$addAfter.') {
                nodes {
                    id
                    title
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }';
        
        $retour = $client->query(["query" => $query])->getDecodedBody();

        if (!isset($retour['data']['collections']['nodes'][0])) { // Retour incorrect
            return false;
        }

        $lst = [
            'items' => [],
            'cursor' => false, // Plus aucune page à récupérer
        ];
        foreach ($retour['data']['collections']['nodes'] as $line) {
            $lst['items'][] = $line;
        }
        
        if (true === $retour['data']['collections']['pageInfo']['hasNextPage']) { // Boucle
            $lst['cursor'] = $retour['data']['products']['pageInfo']['endCursor'];
        }

        return $lst;
    }

    public static function arJsonDecodeRecur($a, $strip_slashes = false)
    {
        if (is_null($a)) {
            return $a;
        } elseif (!is_array($a) && is_null($a2 = json_decode($strip_slashes ? self::myStripslashes($a) : $a, true)) && is_null($a3 = json_decode($a, true))) { // we need to strip slashes
            return $a;
        } elseif (!is_array($a) && is_array($a2 ?? $a3)) {
            return self::arJsonDecodeRecur($a2 ?? $a3, $strip_slashes);
        } elseif (!is_array($a)) {
            return $a2 ?? $a3;
        }

        foreach ($a as &$v) {
            if (!is_null($v)) {
                $v = is_array($v) ? self::arJsonDecodeRecur($v, $strip_slashes) : ((is_null($w = json_decode($strip_slashes ? self::myStripslashes($v) : $v, true)) && is_null($x = json_decode($v, true))) ? $v : ($w ?? $x)); // we need to strip slashes
            }
        }

        return $a;
    }

    public static function myStripslashes($string)
    {
        return str_replace(
            [
                '\\\'',
                '\\"',
                '\\\\',
                '\\NULL',
            ],
            [
                '\'',
                '\"',
                '\\',
                'NULL',
            ],
            $string
        );
    }

    public function cronGetFile(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);

        if (!$nbCron) {
            Outils::setWebSetting($cron, json_encode([]));
        } 
        $cronData = json_decode(WebsiteSetting::getByName($cron)->getData(), true);

        $work = [
            $this->catalog_name => [
                'method' => 'getProducts',
                'sliced' => true,
                'required' => true,
                'icallback' => [get_class(), 'completeFields'],
            ],
            // $this->category_name => [
            //     'method' => 'getCollections',
            //     'sliced' => true,
            //     'required' => true,
            //     'icallback' => null,
            // ],
        ];

        $nTraite = 0;
        $tabFiles = array_keys($work);
        $fichier = $cronData['fichier'] ?? $tabFiles[$nTraite];
        foreach ($work as $fileName => $fileInfos) {
            if ($fichier && ($fichier !== $fileName)) {
                $nTraite++;
                continue;
            }

            $cursor = $cronData['cursor'] ?? '';
            $continue = empty($cursor) ? false : true;

            // Appel GraphQL
            $retour = $this->{$fileInfos['method']}($cursor);
            if (false === $retour) { // Retour incorrect
                return false;
            }
            if (empty($retour['items'])) { // Retour vide
                return false;
            }

            // Passage en DbFile
            $ret = DbFile::buildFromArray($retour['items'], $fileName, $continue, $fileInfos['icallback']);
            if (!is_numeric($ret)) {
                Outils::addLog('(EcShopify ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 1);
                return '(EcShopify ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true);
            }

            if (false !== $retour['cursor']) { // Boucle sur le même fichier
                $cronData['fichier'] = $tabFiles[$nTraite];
                $cronData['cursor'] = $retour['cursor'];
                Outils::setWebSetting($cron, json_encode($cronData));

                return ($nbCron + 1);
            }

            if (isset($tabFiles[($nTraite + 1)])) { // Boucle sur un autre fichier
                $cronData['fichier'] = $tabFiles[($nTraite + 1)];
                $cronData['cursor'] = '';
                Outils::setWebSetting($cron, json_encode($cronData));

                return ($nbCron + 1);
            }
        }

        return true;
    }

    public static function completeFields($item)
    {
        if (!isset($item['metafields']['edges'][0])) {
            return $item;
        }

        foreach ($item['metafields']['edges'] as $data) {
            if (!array_key_exists('node', $data) || !array_key_exists('value', $data['node'])) {
                continue;
            }

            $lstMetaObject = json_decode($data['node']['value'], true);

            if (empty($lstMetaObject) || !is_array($lstMetaObject)) {
                continue;
            }

            foreach ($lstMetaObject as $idMetaObject) {
                $retour = self::getMetaobjectByID($idMetaObject);
                // return $retour;
                if (is_array($retour) && isset($retour['id'])) {
                    $item['metaObject'][$retour['id']] = $retour;
                }
            }
        }


        return $item;

    }
}

<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to a commercial license from SARL Ether Création
 * Use, copy, modification or distribution of this source file without written
 * license agreement from the SARL Ether Création is strictly forbidden.
 * In order to obtain a license, please contact us: contact@ethercreation.com
 * ...........................................................................
 * INFORMATION SUR LA LICENCE D'UTILISATION
 *
 * L'utilisation de ce fichier source est soumise a une licence commerciale
 * concedee par la societe Ether Création
 * Toute utilisation, reproduction, modification ou distribution du present
 * fichier source sans contrat de licence ecrit de la part de la SARL Ether Création est
 * expressement interdite.
 * Pour obtenir une licence, veuillez contacter la SARL Ether Création a l'adresse: contact@ethercreation.com
 * ...........................................................................
 *
 * @author    Théodore Riant <theodore@ethercreation.com>
 * @copyright 2008-2018 Ether Création SARL
 * @license   Commercial license
 * International Registered Trademark & Property of PrestaShop Ether Création SARL
 *
 */

namespace bundles\ecShopifyBundle\Services;

use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecShopifyBundle\ecShopifyBundle;
use Pimcore\Model\DataObject\{Attribut, Carac, Category, Declinaison, Diffusion, Product};
use JsonException;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Rest;
use Shopify\Clients\Graphql;
use Shopify\Rest\Admin2024_07\InventoryLevel;
use Shopify\Utils;
use Shopify\Context;
use Shopify\Exception\{CookieNotFoundException,
    InvalidArgumentException,
    MissingArgumentException,
    UninitializedContextException,
    WebhookRegistrationException
};
use Shopify\Webhooks\{Registry, Topics};

class ShopifyApiClient extends ecShopifyBundle
{
    const PUBLISHED_SCOPE = 'web';
    const INVENTORY_POLICY = 'DENY';
    const FULFILLMENT_SERVICE = 'manual';
    const INVENTORY_MANAGEMENT = 'shopify';
    private Graphql $client;
    private ?\Shopify\Auth\Session $session;
    private $diffusion;
    /**
     * @var mixed|string|null
     */
    private mixed $shopOrigin;
    /**
     * @var mixed|string|null
     */
    private mixed $accessToken;

    /**
     * @param null $apiKey App API key
     * @param null $apiSecretKey App API secret
     * @param null $hostName App host name e.g. www.google.ca. May include scheme
     * @param null $accessToken
     * @param null $scopes App scopes
     * @param null $apiVersion App API key, defaults to unstable
     * @throws MissingArgumentException
     * @throws CookieNotFoundException
     * @throws UninitializedContextException
     */
    public function __construct($apiKey = null, $apiSecretKey = null, $hostName = null, $accessToken = null, $scopes = null, $apiVersion = null)
    {

        $listIdDiffs = Outils::query('SELECT id FROM object_'.Outils::getIDClass('diffusion').' WHERE published = 1 AND plateforme = "Shopify" AND (archive IS NULL OR archive = 0)');
        
        $config = [];
        foreach($listIdDiffs as $id){
            // $diffName = DataObject::getById($id['id']);
            // Outils::addLog($diffName->getName(), 1);
            // $diffusion = Diffusion::getByPath('/Diffusion/'.$diffName->getName());
            
            $diffusion = Diffusion::getById($id['id']);

            $config[$id['id']]['shopify_api_key'] = Outils::getConfigByName($diffusion, 'shopify_api_key');    
            $config[$id['id']]['shopify_api_secret'] = Outils::getConfigByName($diffusion, 'shopify_api_secret');    
            $config[$id['id']]['shopify_api_scope'] = Outils::getConfigByName($diffusion, 'shopify_api_scope');    
            $config[$id['id']]['shopify_api_hostname'] = Outils::getConfigByName($diffusion, 'shopify_api_hostname');    
            $config[$id['id']]['shopify_api_version'] = Outils::getConfigByName($diffusion, 'shopify_api_version');    
            $config[$id['id']]['shopify_access_token'] = Outils::getConfigByName($diffusion, 'shopify_access_token');    
        }
        
        // $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $this->diffusion = $diffusion;
        // foreach ($diffusion->getConfig() as $conf) {
        //     $config[$conf->getKey()] = $conf->getValeur();
        // }
        
        // Outils::addLog(json_encode($listIdDiffs), 1);
        foreach($config as $conf){
            Context::initialize(
                apiKey: $apiKey ?? $conf['shopify_api_key'],
                apiSecretKey: $apiSecretKey ?? $conf['shopify_api_secret'],
                scopes: $scopes ?? $conf['shopify_api_scope'],
                hostName: $hostName ?? $conf['shopify_api_hostname'],
                sessionStorage: new FileSessionStorage('/tmp/php_sessions'),
                apiVersion: $apiVersion ?? $conf['shopify_api_version'],
                isEmbeddedApp: false,
            );
            
            $this->client = new Graphql(
                $hostName ?? $conf['shopify_api_hostname'],
                $accessToken ?? $conf['shopify_access_token'],
            );
    
            $this->shopOrigin = $hostName ?? $conf['shopify_api_hostname'];
            $this->accessToken = $accessToken ?? $conf['shopify_access_token'];
        
        }
                
    }


    /**
     * Récupère les produits de la boutique
     */
    // public function getProducts()
    // {
    //     try {
    //         $data = $this->client->get(
    //             path: 'products'
    //         );
    //         $products = $data->getDecodedBody()['products'];
    //         foreach ($products as &$product) {
    //             foreach ($product['variants'] as &$variant) {
    //                 $inventory_item = $this->client->get(
    //                     path: 'inventory_items', query: ['ids' => $variant['inventory_item_id']]
    //                 );
    //                 $variant['inventory_item'] = $inventory_item->getDecodedBody()['inventory_items'][0];
    //             }
    //         }
    //         unset($variant);
    //         unset($product);
    //         return $products;
    //     } catch (ClientExceptionInterface $e) {
    //         return $e->getMessage();
    //     } catch (MissingArgumentException|UninitializedContextException|JsonException $e) {
    //         return $e->getMessage();
    //     }
    // }

    /**
     * Récupère les collections d'un produit
     */
    // public function getProductCollections($id)
    // {
    //     try {
    //         $collection = $this->client->get(
    //             path: 'custom_collections', query: ['product_id' => $id]
    //         );
    //         return json_decode(json_encode($collection->getDecodedBody()['custom_collections']), false);
    //     } catch (ClientExceptionInterface $e) {
    //         return $e->getMessage();
    //     } catch (MissingArgumentException|UninitializedContextException|\JsonException $e) {
    //         return $e->getMessage();
    //     }
    // }

    /**
     * # Création d'un produit
     * Créer un produit dans Shopify. Prend une ID en paramètre et retourne l'ID du produit créé.
     * @param int $id
     * @return string
     * @throws \JsonException
     */
    // public function createProduct(Product $pimcore_product): string
    // {
    //     Outils::addLog('on passe dans createProduct', 1);

    //     if (Outils::getCrossId(obj: $pimcore_product, source: $this->diffusion) !== null) {
    //         return 'Le produit existe déjà dans Shopify ';
    //     }

    //     $options = [
    //         'option1' => ['name' => null, 'value' => null, 'all_values' => []],
    //         'option2' => ['name' => null, 'value' => null, 'all_values' => []],
    //         'option3' => ['name' => null, 'value' => null, 'all_values' => []]
    //     ];
    //     $variants = [];
    //     foreach ($pimcore_product->getDecli() as $variant) {
    //         $attributes = $variant->getAttribut();
    //         $options['option1']['value'] = null;
    //         $options['option2']['value'] = null;
    //         $options['option3']['value'] = null;
    //         foreach ($attributes as $attribute) {
    //             $name = $attribute->getParent()->getName();
    //             $value = $attribute->getName();

    //             foreach ($options as $key => &$option) {
    //                 if ($name === $option['name'] || $option['name'] === null) {
    //                     $option['name'] = $name;
    //                     $option['value'] = $value;
    //                     $option['all_values'][] = $value;
    //                     break;
    //                 }
    //             }
    //             unset($option); // Important: annule la référence pour éviter les problèmes ultérieurs
    //         }
    //         $option1 = $options['option1'];
    //         $option2 = $options['option2'];
    //         $option3 = $options['option3'];

    //         $variants[] = [
    //             // 'id' => 1070325026,
    //             // 'product_id' => 1071559574,
    //             'title' => $option1['name'],
    //             'price' => $variant->getPrix_vente(),
    //             'cost' => $pimcore_product->getPrice_buying() ?? $pimcore_product->getPrice_buying_default(),
    //             'sku' => $variant->getReference_declinaison(),
    //             'position' => 1,
    //             'inventory_policy' => self::INVENTORY_POLICY,
    //             'compare_at_price' => null,
    //             'fulfillment_service' => self::FULFILLMENT_SERVICE,
    //             'inventory_management' => self::INVENTORY_MANAGEMENT,
    //             'option1' => $option1['value'] ?? 'par défaut',
    //             'option2' => $option2['value'] ?? 'par défaut',
    //             'option3' => $option3['value'] ?? 'par défaut',
    //             // 'created_at' => '2023-06-14T14:23:53-04:00',
    //             // 'updated_at' => '2023-06-14T14:23:53-04:00',
    //             // 'taxable' => true,
    //             'barcode' => $variant->getEan13(),
    //             // 'grams' => null,
    //             // 'image_id' => null,
    //             'weight' => $variant->getWeight(),
    //             'weight_unit' => Dataobject::getByPath('/Config/unit_weight')->getValeur(),
    //             // 'inventory_item_id' => 1070325026,
    //             'inventory_quantity' => $variant->getQuantity(),
    //             // 'old_inventory_quantity' => 0,
    //             // 'presentment_prices' => [
    //             //     [
    //             //         'price' => [
    //             //             'amount' => $variant->getPrix_vente(),
    //             //             'currency_code' => self::CURRENCY_CODE
    //             //         ],
    //             //         'compare_at_price' => $variant->getPrix_vente(),
    //             //     ]
    //             // ],
    //             // 'requires_shipping' => true,
    //             // 'admin_graphql_api_id' => 'gid://shopify/ProductVariant/1070325026'
    //         ];
    //     }


    //     $shopify_product = [
    //         'product' => [
    //             //  'id' => 687468438,
    //             'title' => $pimcore_product->getName(),
    //             'body_html' => $pimcore_product->getDescription(),
    //             // 'vendor' => $pimcore_product->getManufacturer(),
    //             'product_type' => $pimcore_product->getCategory_default(),
    //             // 'created_at' => $pimcore_product->getCreationDate(),
    //             // 'handle' => 'burton-custom-freestyle-151',
    //             // 'updated_at' => '2023-06-14T14:23:53-04:00',
    //             // 'published_at' => null,
    //             // 'template_suffix' => null,
    //             'status' => $pimcore_product->getPublished() ? 'active' : 'draft',
    //             'published_scope' => self::PUBLISHED_SCOPE,
    //             // 'tags' => ,
    //             // 'admin_graphql_api_id' => 'gid://shopify/Product/1071559574',
    //             'variants' => $variants,
    //             "options" => [],
    //             'images' => [],
    //             'image' => null]
    //     ];

    //     if (isset($option1['name']))
    //         $shopify_product['product']['options'][] = [
    //             "name" => $option1['name'],
    //             "position" => 1,
    //             "values" => $option1['all_values']
    //         ];
    //     if (isset($option2['name']))
    //         $shopify_product['product']['options'][] = [
    //             "name" => $option2['name'],
    //             "position" => 2,
    //             // "values" => ['Bleu', 'Rouge']
    //             "values" => $option2['all_values']
    //         ];
    //     if (isset($option3['name']))
    //         $shopify_product['product']['options'][] = [
    //             "name" => $option3['name'],
    //             "position" => 3,
    //             "values" => $option3['all_values']
    //         ];

    //     try {
    //         $variables = [
    //             "input" => [
    //                 "title" => $shopify_product['product']['title'],
    //                 "productOptions" => $shopify_product['product']['options'],
    //                 "descriptionHtml" => $shopify_product['product']['body_html'],
    //                 "category" => $shopify_product['product']['product_type'],
    //                 "productType" => $shopify_product['product']['product_type'],
    //                 "status" => $shopify_product['product']['status'],
    //             ],
    //         ];
    //         $query = <<<QUERY
    //         mutation ProductCreate($input: ProductInput!) {
    //           productCreate(input: $input) {
    //             product {
    //               id
    //               title
    //               descriptionHtml
    //               category
    //               productType
    //               status
    //               options {
    //                 id
    //                 name
    //                 position
    //                 optionValues {
    //                   id
    //                   name
    //                   hasVariants
    //                 }
    //               }
    //             }
    //             userErrors {
    //               field
    //               message
    //             }
    //           }
    //         }
    //       QUERY;
    //         $result = $this->client->query(['query' => $query, 'variables' => $variables]);
    //         // $result = $this->client->post(
    //         //     path: 'products',
    //         //     body: $shopify_product
    //         // )->getDecodedBody();

    //         // $category = $pimcore_product->getCategory()[0];
    //         // if ($category_crossid = Outils::getCrossid($category, $this->diffusion, 'ext_id')) {
    //         //     $this->client->post(path: 'collects', body: [
    //         //         'collect' => [
    //         //             'product_id' => $result['product']['id'],
    //         //             'collection_id' => $category_crossid
    //         //         ]
    //         //     ]);
    //         // } else {
    //         //     $collection_body = [
    //         //         'custom_collection' => [
    //         //             'title' => $category->getName(),
    //         //             'collects' => [
    //         //                 [
    //         //                     'product_id' => $result['product']['id'],
    //         //                 ]
    //         //             ]
    //         //         ]
    //         //     ];
    //         //     $collection_result = $this->client
    //         //         ->post(path: 'custom_collections', body: $collection_body)
    //         //         ->getDecodedBody();
    //         //     // dump([$category, $this->diffusion, $collection_result['custom_collection']['id']]);
    //         //     Outils::addCrossid(object: $category, source: $this->diffusion, ext_id: $collection_result['custom_collection']['id']);

    //         // }
    //         Outils::addCrossid(object: $pimcore_product, source: $this->diffusion, ext_id: $result['product']['id']);
    //         return json_encode($result);
    //     } catch
    //     (ClientExceptionInterface $e) {
    //         return $e->getMessage();
    //     } catch (UninitializedContextException $e) {
    //         return $e->getMessage();
    //     }
    // }

    public function updateStock(Product $pimcore_product)
    {
        Outils::addLog('updateStock');

        // Verif si produit actif
        $productActive = $pimcore_product->isPublished();
        if(!$productActive){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit désactivé'); 
            return true;
        }
        $diffusionActive = $pimcore_product->getDiffusions_active();
        $tabIdDiffAct = [];
        foreach($diffusionActive as $diff){
            $tabIdDiffAct[] = $diff;
           
        }
        if(!in_array($this->diffusion->getId(), $tabIdDiffAct)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit pas diffusé'); 
            return true;
        }

        $variants = [];
        foreach ($pimcore_product->getDecli() as $variant) {
            $sto = 0;
            $stoDepot = Outils::getStockProduct($pimcore_product, $variant, 'dispo', 0, 0, 0 ,1);

            foreach($stoDepot as $depot){
                $sto = $sto + $depot['quantity_physique'];
            }            
            

            // GET inventory item
            $query = 'query inventoryItems {
              inventoryItems(first: 1, query: "sku:\''.$variant->getReference_declinaison().'\'") {
                edges {
                  node {
                    id
                    tracked
                    sku
                  }
                }
              }
            }';
            try{
                $response = $this->client->query(['query' => $query])->getDecodedBody();
                // Outils::addLog(json_encode($response));
            } catch
            (ClientExceptionInterface $e) {
                return $e->getMessage();
            } catch (UninitializedContextException $e) {
                return $e->getMessage();
            }
            // Outils::addLog(json_encode($response));
            if(is_null($response['data']['inventoryItems']['edges'])){
                return true;
            }
            $inventoryItem = reset($response['data']['inventoryItems']['edges']);
            if(!$inventoryItem || !isset($inventoryItem['node']['id'])){
                continue;
            }
            $idInventoryItem = $inventoryItem['node']['id'];
            // updateStock variante
            $query = 'mutation InventorySet($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                    inventoryAdjustmentGroup {
                        reason
                        changes {
                        name
                        delta
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
              }';
            $variables = [
                "input" => [
                  "name" => "available",
                  "ignoreCompareQuantity" => true,
                  "reason" => "correction",
                  "quantities" => [
                        [
                            "inventoryItemId"=>$idInventoryItem, 
                            // "locationId"=>"gid://shopify/Location/102113280322", 
                            "locationId"=> 'gid://shopify/Location/'.outils::getConfigByName($this->diffusion,'shopify_location_id'), 
                            "quantity"=>$sto, 
                            // "compareQuantity" => 0,
                        ]
                    ],
                ],
            ];
            try{
                $response = $this->client->query(['query' => $query, 'variables' => $variables])->getDecodedBody();
                // Outils::addLog(json_encode($response));
            } catch
            (ClientExceptionInterface $e) {
                return $e->getMessage();
            } catch (UninitializedContextException $e) {
                return $e->getMessage();
            }
            // Outils::addLog('retour updateStock');
            
        }
        // if (count($variants) === 0) {
        //     $sto = 0;
        //     $stoDepot = Outils::getStockProduct($pimcore_product, 0, 'dispo', 0, 0, 0 ,1);
        //     foreach($stoDepot as $depot){
        //         $sto = $sto + $depot['quantity_physique'];
        //     }
        //     $variants[] = [
        //         'inventoryQuantities' => [
        //             'availableQuantity' => $sto,
        //             'locationId' => 'gid://shopify/Location/102113280322',
        //             'locationId' => 'gid://shopify/Location/'.outils::getConfigByName($this->diffusion,'shopify_location_id'),
        //         ],
        //     ];
        // }
        Outils::addLog('fin updateStock');
        return true;
    }

    /**
     * # Mise à jour d'un produit
     * Créer un produit dans Shopify. Prend une ID en paramètre et retourne l'ID du produit créé.
     * @param int $id
     * @return string
     * @throws \JsonException
     */
    public function updateProduct(Product $pimcore_product): string
    {
        $diffusionActive = $pimcore_product->getDiffusions_active();
        $tabIdDiffAct = [];

        if(!is_array($diffusionActive)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit pas a diffusé car pas de diffusion'); 
            return true;
        }

        foreach($diffusionActive as $diff){
            $tabIdDiffAct[] = $diff;
        }
        if(!in_array($this->diffusion->getId(), $tabIdDiffAct)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit pas a diffusé' . $this->diffusion->getId()); 
            return true;
        }

        $halfCrossID = Outils::getCrossId($pimcore_product, $this->diffusion);
        $active = $pimcore_product->getPublished();
        if(!$active && !$halfCrossID){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit désactivé' . $this->diffusion->getId()); 
            return true;
        }

        $time = microtime(true);
        $lang = Tool::getValidLanguages()[0];
        Outils::addLog('fonction updateProduct ' . $time);
        // Outils::addLog($pimcore_product->getId(), 1);
        /////////////
        //// Partie Collection
        /////////////
        
        $lstObjCateg = $pimcore_product->getDiffusion();
        $categories = []; // liste de toutes les catégories de toutes les diffusions
        $plus_long_chemin = $maxID = 0;
        if (!empty($lstObjCateg)) {
            foreach ($lstObjCateg as $i => $objCateg) {
                if ($this->diffusion->getId() != ($objCateg->getData()['id_diffusion'] ?? '')) {
                    continue;
                }
                $objCateg = $objCateg->getElement();
                $depth = 0;
                while (true) {
                    if ('folder' == $objCateg->getType()) {
                        break;
                    }
                    $depth++;
                    $categories[$i]['ids'][] = $objCateg->getId();
                    $categories[$i]['names'][] = $objCateg->getName('fr');
                    $categories[$i]['paths'][] = $objCateg->getPath();
                    $objCateg = DataObject::getById($objCateg->getParentId());
                }
                $categories[$i]['depth'] = $depth;
                // $plus_long_chemin = max($plus_long_chemin, $depth);
                // if($plus_long_chemin == $depth){
                //     $maxID = $i;
                // }
            }
        }
        $listIdCateg = [];
        // Outils::addLog(json_encode($categories));
        if(!empty($categories)){
            // $listCateg = $categories[$maxID];
                            // Outils::addLog('yolo (ShopifyApiClient:' . __LINE__ . ') -'.'catégories ' . json_encode($categories)); 
            foreach($categories as $listCateg){
                // Outils::addLog('yolo (ShopifyApiClient:' . __LINE__ . ') -'.'listCateg ' . json_encode($listCateg)); 
                foreach($listCateg['ids'] as $key => $id){
                    $cat = DataObject::getById($id);
                    $halfCrossIDCateg = Outils::getCrossid($cat, $this->diffusion);
                    // Outils::addLog('yolo (ShopifyApiClient:' . __LINE__ . ') -'.'data ' . json_encode(['key' => $key,'id' => $id, 'halfCrossIDCateg' => $halfCrossIDCateg])); 
                    // Outils::addLog('halfcross');
                    // Outils::addLog(json_encode($halfCrossID));
                    // Outils::addLog(json_encode($id));
                    // Création Collection
                    if(!$halfCrossIDCateg){
                        // Outils::addLog('CREATION categ');
                        $query = 'mutation CollectionCreate($input: CollectionInput!) {
                        collectionCreate(input: $input) {
                                collection {
                                    id
                                    title
                                    descriptionHtml
                                    updatedAt
                                    handle                           
                                }
                                userErrors {
                                    field
                                    message
                                }
                            }
                        }';
                        $variables = [
                            "input" => [
                            "title" => $listCateg['names'][$key],
                            "descriptionHtml" => "This is a custom collection.",
                            ],
                        ];
                        try{
                            $response = [];
                            // Outils::addLog('yolo (ShopifyApiClient:' . __LINE__ . ') -'.'catégorie variable ' . json_encode($variables)); 
                            $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
                            // Outils::addLog('yolo (ShopifyApiClient:' . __LINE__ . ') -'.'catégorie response ' . json_encode($response)); 
                            // Outils::addLog('REPONSE CREATION COLLECTION');
                            // Outils::addLog(json_encode($response));
                        } catch
                        (ClientExceptionInterface $e) {
                            return $e->getMessage();
                        } catch (UninitializedContextException $e) {
                            return $e->getMessage();
                        }
                        // Outils::addLog('REPONSE CREATION COLLECTION');
                        // Outils::addLog(json_encode($response));
                        
                        if(is_array($response) && isset($response['data']['collectionCreate']['collection']['id'])){
                            $responseId = $response['data']['collectionCreate']['collection']['id'];
                            $slashedArray = explode('/', $responseId);
                            $id = end($slashedArray);
                            Outils::addCrossid(object: $cat, source: $this->diffusion, ext_id: $id);
                            $listIdCateg[] = $responseId;
                        }
                        
                        
                    } else { // Collection déjà crée, on recup l'id collection Shopify
                        $crossIDCateg = 'gid://shopify/Collection/' . $halfCrossIDCateg;
                        $listIdCateg[] = $crossIDCateg;
                    }
                }
            }
        }
        // Outils::addLog('Liste collection');
        // Outils::addLog(json_encode($listIdCateg));
        
        /////////////
        //// Partie product
        /////////////

        // $variants = [];
        
        // if (count($variants) === 0) {
        //     $sto = 0;
        //     $stoDepot = Outils::getStockProduct($pimcore_product, 0, 'dispo', 0, 0, 0 ,1);
        //     foreach($stoDepot as $depot){
        //         $sto = $sto + $depot['quantity_physique'];
        //     }
        //     $variants[] = [
        //         // 'title' => 'par defaut',
        //         'price' => $pimcore_product->getPrice_recommended(),
        //         'inventoryItem' => [
        //             'sku' => $pimcore_product->getReference(),
        //             // 'cost' => $pimcore_product->getPrice_buying_default(),
        //             'measurement' => [
        //                 'weight' => [
        //                     'value' => $pimcore_product->getWeight(),
        //                     // 'unit' => Dataobject::getByPath('/Config/unit_weight')->getValeur(),
        //                     'unit' => 'GRAMS',
        //                 ],
        //             ],
        //         ],
        //         'inventoryPolicy' => self::INVENTORY_POLICY,

        //         'barcode' => $pimcore_product->getEan13(),   
        //         'inventoryQuantities' => [
        //             'availableQuantity' => $sto,
        //             // 'locationId' => 'gid://shopify/Location/102113280322',
        //             'locationId' => 'gid://shopify/Location/'.outils::getConfigByName($this->diffusion,'shopify_location_id'),
        //         ],
        //         'optionValues' => [ // Options for the variant (e.g., Size, Color)
        //             [ 
        //                 'name' => 'Par defaut',
        //                 'optionName' => 'Title' 
        //             ],
        //         ],
                       
        //         // 'inventory_quantity' => $sto,
        //     ];
        // }
        $shopify_product = [
            'product' => [
                'title' => $pimcore_product->getName(),
                'body_html' => $pimcore_product->getDescription($lang),
                // 'vendor' => $pimcore_product->getManufacturer(),
                'product_type' => 'Default',
                // 'product_type' => $pimcore_product->getCategory_default(),
                'price' => $pimcore_product->getPrice_recommended(),
                'cost' => $pimcore_product->getPrice_buying_default(),
                'sku' => $pimcore_product->getReference(),
                'barcode' => $pimcore_product->getEan13(),
                'collectionsToJoin' => $listIdCateg,
                'weight' => $pimcore_product->getWeight(),
                'weight_unit' => Dataobject::getByPath('/Config/unit_weight')->getValeur(),
                // 'inventory_quantity' => $pimcore_product->getQuantity(),
                'inventory_policy' => self::INVENTORY_POLICY,
                // 'compare_at_price' => null,
                'fulfillment_service' => self::FULFILLMENT_SERVICE,
                'inventory_management' => self::INVENTORY_MANAGEMENT,
                // 'created_at' => $pimcore_product->getCreationDate(),
                // 'handle' => 'burton-custom-freestyle-151',
                // 'updated_at' => '2023-06-14T14:23:53-04:00',
                // 'published_at' => null,
                // 'template_suffix' => null,
                'status' => $active ? 'ACTIVE' : 'DRAFT',
                'published_scope' => self::PUBLISHED_SCOPE,
                // 'tags' => ,
                // 'variants' => $variants,
                'vendor' => $pimcore_product->getManufacturer()->getName(),
                "options" => [],
                'images' => [],
                'image' => null
                ]
        ];        

        $shopify_product['product']['images'] = array_map(function ($item) {
            return [
                'src' => \Pimcore\Tool::getHostUrl() . $item->getImage()->getFullpath(),
            ];
        }, $pimcore_product->getGalery()->getItems());

        
        // Pas de crossId donc création sur Shopify
        if(!$halfCrossID){
            // Outils::addLog('cas creation');
            try {
                $variables = [
                    "input" => [
                        "title" => $shopify_product['product']['title'],
                        // "productOptions" => $shopify_product['product']['options'],
                        // "productOptions" => [['name' => 'Default', 'values' => [['name' => 'Default']]]],
                        "descriptionHtml" => $shopify_product['product']['body_html'],
                        // "variants" => $shopify_product['product']['variants'],
                        "productType" => $shopify_product['product']['product_type'],
                        "status" => $shopify_product['product']['status'],
                        "collectionsToJoin" => $shopify_product['product']['collectionsToJoin'],
                        "vendor" => $shopify_product['product']['vendor'],
                    ],
                ];
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'variables creation produit ' . json_encode($variables) ); 
                $query = 'mutation ProductCreate($input: ProductInput!) {
                    productCreate(input: $input) {
                        product {
                        id
                        title
                        descriptionHtml
                        productType
                        status
                        vendor
                        options {
                            id
                            name
                            position
                            optionValues {
                            id
                            name
                            hasVariants
                            }
                        }
                        }
                        userErrors {
                        field
                        message
                        }
                    }
                }';
                $result = $this->client->query(['query' => $query, 'variables' => $variables])->getDecodedBody();
                // Outils::addLog(json_encode($result) . $time); 

                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'add crossid ' . $time);         
                if(is_array($result) && isset($result['data']['productCreate']['product']['id'])){
                    $completeId = $result['data']['productCreate']['product']['id'];
                    $slashedId = explode('/',$completeId);
                    $idProd = end($slashedId);
                    //Outils::addLog(json_encode($idProd) . '  ' . $time); 
                    $pimcore_product = DataObject::getById($pimcore_product->getId());
                    
                    // double appel parce que c'est magique, si 1 ça marche PAS
                    // ICI C EST POUDLARD !!!!
                    Outils::addCrossid(object: $pimcore_product->getId(), source: $this->diffusion->getId(), ext_id: $idProd);
                    Outils::addCrossid(object: $pimcore_product->getId(), source: $this->diffusion->getId(), ext_id: $idProd);
                }     
                
                // Update Decli 
                // P'tete retirer ce bloc pour les updatesDecli sont lancé par hook
                foreach ($pimcore_product->getDecli() as $variant) {
                    $this->updateDecli($variant);
                }
                // return json_encode($result);
            } catch
            (ClientExceptionInterface $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            } catch (UninitializedContextException $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            }
        } 
        else { // crossId, donc on connait le produit shopify => updates
            // Outils::addLog('cas update');
            $crossID = 'gid://shopify/Product/' . $halfCrossID;
            try {

                $query = 'query CollectionsForProduct($productId: ID!) {
                    product(id: $productId) {
                      collections(first: 10) {
                        nodes {
                          id
                          title
                        }
                      }
                    }
                  }';

                $variables = [
                "productId" => $crossID,
                ];

                $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
                // Outils::addLog(json_encode($response));

                // on determine les collections sur le produit shopify
                $listCollectionToRemove = [];
                if(isset($response['data']['product']['collections']['nodes'])){
                    foreach($response['data']['product']['collections']['nodes'] as $node){
                        $listCollectionToRemove[] = $node['id'];
                    }
                }

                // on determine les collection a virer
                
                $temp = array_diff($listCollectionToRemove, $shopify_product['product']['collectionsToJoin']);

                $variables = [
                    "input" => [
                        "id" => $crossID,
                        // "title" => $shopify_product['product']['title'],
                        // "descriptionHtml" => $shopify_product['product']['body_html'],
                        // // "variants" => $shopify_product['product']['variants'],
                        // // "category" => $shopify_product['product']['product_type'],
                        // "productType" => $shopify_product['product']['product_type'],
                        "status" => $shopify_product['product']['status'],
                        // "collectionsToJoin" => $shopify_product['product']['collectionsToJoin'],
                        // "collectionsToLeave" => $temp,
                    ],
                ];

                $query = 'mutation ProductUpdate($input: ProductInput!) {
                    productUpdate(input: $input) {
                      product {
                        id
                        title
                        descriptionHtml
                        productType
                        status
                      }
                      userErrors {
                        field
                        message
                      }
                    }
                  }';
                $result = $this->client->query(['query' => $query, 'variables' => $variables])->getDecodedBody();
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') variables ' .json_encode($variables));                
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') resulte '. json_encode($result));                
            } catch
            (ClientExceptionInterface $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            } catch (UninitializedContextException $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            }
        } 
        
        Outils::addLog('fin fonction updateProduct ');
        return true; 
    }

    /**
     * Supprimer un produit dans Shopify
     */
    // public function deleteProduct_old(Product $pimcore_product): array|string|null
    // {
    //     Outils::addLog('fonction deleteProduct ', 1);
    //     // Outils::addLog(json_encode($pimcore_product->getId()));

    //     try {

    //         // suppression variante
    //         $idProductShopify = Outils::getCrossId($pimcore_product, source: $this->diffusion);
    //         // Outils::addLog($idProductShopify);
    //         $tabIdVariantShopify = [];
    //         foreach ($pimcore_product->getDecli() as $variant){
    //             $tabIdVariantShopify[] = Outils::getCrossId($variant, $this->diffusion);
    //             // Outils::removeCrossid(object: $variant, source: $this->diffusion);
    //             unset($variant);
    //         }
    //         // Outils::addLog(json_encode($tabIdVariantShopify));
    //         $query = 'mutation bulkDeleteProductVariants($productId: ID!, $variantsIds: [ID!]!) {
    //             productVariantsBulkDelete(productId: $productId, variantsIds: $variantsIds) {
    //                 product {
    //                     id
    //                     title
    //                 }
    //                 userErrors {
    //                     field
    //                     message
    //                 }
    //             }
    //         }';

    //         $variables = [
    //             'productId' => $idProductShopify,
    //             'variantsIds' => $tabIdVariantShopify
    //         ];
            
    //         $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
    //         // Outils::addLog('resultat suppression variants');
    //         // Outils::addLog(json_encode($response));
    //         // suppression product
    //         $query = 'mutation {
    //             productDelete(input: {id: "'. $idProductShopify .'"}) {
    //               deletedProductId
    //               userErrors {
    //                 field
    //                 message
    //               }
    //             }
    //           }';

            
    //         // $data = $this->client->delete(
    //         //     path: 'products/' . Outils::getCrossId(obj: $pimcore_product, source: $this->diffusion),
    //         // );
    //         $data = $this->client->query(['query' => $query])->getDecodedBody();
    //         // Outils::addLog('Suppression du produit :' . json_encode($data), 1, [], 'NOMDULOG');
    //         // Outils::addLog('resultat suppression product');
    //         // Outils::addLog(json_encode($data));
          
    //         // Outils::removeCrossid(object: $pimcore_product, source: $this->diffusion);
    //         unset($product);
    //         return $data;
    //     } catch (ClientExceptionInterface $e) {
    //         return $e->getMessage();
    //     } catch (MissingArgumentException|UninitializedContextException|JsonException $e) {
    //         return $e->getMessage();
    //     }
    // }
    public function deleteProduct(Product $pimcore_product): array|string|null
    {
        Outils::addLog('fonction deleteProduct ');
        $diffusionActive = $pimcore_product->getDiffusions_active();
        $tabIdDiffAct = [];
        foreach($diffusionActive as $diff){
            $tabIdDiffAct[] = $diff;
           
        }
        if(!in_array($this->diffusion->getId(), $tabIdDiffAct)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit pas diffusé' . $diff); 
            return true;
        }

        $halfCrossID = Outils::getCrossId($pimcore_product, $this->diffusion);
        if(!$halfCrossID){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit jamais diffusé' . $diff); 
            return true;
        }

        $shopify_product = [
            'product' => [
                'status' => 'DRAFT',
                ]
        ]; 
        $crossID = 'gid://shopify/Product/' . $halfCrossID;
        try {
            $variables = [
                "input" => [
                    "id" => $crossID,
                    "status" => $shopify_product['product']['status'],
                ],
            ];

            $query = 'mutation ProductUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                    id
                    title
                    status
                    }
                    userErrors {
                    field
                    message
                    }
                }
                }';
            $result = $this->client->query(['query' => $query, 'variables' => $variables])->getDecodedBody();
            // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') variables ' .json_encode($variables));                
            // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') resulte '. json_encode($result));                
        } catch
        (ClientExceptionInterface $e) {
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
            return true;
        } catch (UninitializedContextException $e) {
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
            return true;
        }
        return true;
        /////////////////////////
        /////////////////////////
        /////////////////////////
        /////////////////////////
        /////////////////////////
        /////////////////////////
        /////////////////////////
        
        // Outils::addLog(json_encode($pimcore_product->getId()));

        try { 
            // suppression variante
            foreach ($pimcore_product->getDecli() as $variant){
                $this->deleteDecli($variant);
                // Outils::removeCrossid(object: $variant, source: $this->diffusion);
                unset($variant);
            }
            
            // suppression product
            $halfidProductShopify = Outils::getCrossId($pimcore_product, source: $this->diffusion);
            $idProductShopify = 'gid://shopify/Product/' . $halfidProductShopify;
            $query = 'mutation {
                productDelete(input: {id: "'. $idProductShopify .'"}) {
                  deletedProductId
                  userErrors {
                    field
                    message
                  }
                }
              }';


            $data = $this->client->query(['query' => $query])->getDecodedBody();

            unset($product);
            return $data;
        } catch (ClientExceptionInterface $e) {
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
            return true;
        } catch (MissingArgumentException|UninitializedContextException|JsonException $e) {
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
            return true;
        }
    }

    public function deleteDecli(Declinaison $decl){

        $sto = 0;      

        // GET inventory item
        $query = 'query inventoryItems {
          inventoryItems(first: 1, query: "sku:\''.$decl->getReference_declinaison().'\'") {
            edges {
              node {
                id
                tracked
                sku
              }
            }
          }
        }';
        try{
            $response = $this->client->query(['query' => $query])->getDecodedBody();
            // Outils::addLog(json_encode($response));
        } catch
        (ClientExceptionInterface $e) {
            return $e->getMessage();
        } catch (UninitializedContextException $e) {
            return $e->getMessage();
        }
        // Outils::addLog(json_encode($response));
        if(is_null($response['data']['inventoryItems']['edges'])){
            return true;
        }
        $inventoryItem = reset($response['data']['inventoryItems']['edges']);
        if(!$inventoryItem || !isset($inventoryItem['node']['id'])){
            return true;
        }
        $idInventoryItem = $inventoryItem['node']['id'];
        // updateStock variante
        $query = 'mutation InventorySet($input: InventorySetQuantitiesInput!) {
            inventorySetQuantities(input: $input) {
                inventoryAdjustmentGroup {
                    reason
                    changes {
                    name
                    delta
                    }
                }
                userErrors {
                    field
                    message
                }
            }
          }';
        $variables = [
            "input" => [
              "name" => "available",
              "ignoreCompareQuantity" => true,
              "reason" => "correction",
              "quantities" => [
                    [
                        "inventoryItemId"=>$idInventoryItem, 
                        // "locationId"=>"gid://shopify/Location/102113280322", 
                        "locationId"=> 'gid://shopify/Location/'.outils::getConfigByName($this->diffusion,'shopify_location_id'), 
                        "quantity"=>$sto, 
                        // "compareQuantity" => 0,
                    ]
                ],
            ],
        ];
        try{
            $response = $this->client->query(['query' => $query, 'variables' => $variables])->getDecodedBody();
            // Outils::addLog(json_encode($response));
        } catch
        (ClientExceptionInterface $e) {
            return $e->getMessage();
        } catch (UninitializedContextException $e) {
            return $e->getMessage();
        }
        // Outils::addLog('retour updateStock');


        //////////////
        //////////////
        //////////////
        //////////////
        //////////////
        //////////////


        // $query = 'mutation bulkDeleteProductVariants($productId: ID!, $variantsIds: [ID!]!) {
        //     productVariantsBulkDelete(productId: $productId, variantsIds: $variantsIds) {
        //         product {
        //             id
        //             title
        //         }
        //         userErrors {
        //             field
        //             message
        //         }
        //     }
        // }';

        // $pimProd = DataObject::getById($decl->getParentId());
        // $idShopProd = Outils::getCrossId($pimProd, $this->diffusion);
        // $fullIdShopProd = 'gid://shopify/Product/'.$idShopProd;

        // $halfIdDecl = Outils::getCrossId($decl, $this->diffusion);
        // $idDecl = 'gid://shopify/ProductVariant/' . $halfIdDecl;

        // $variables = [
        //     'productId' => $fullIdShopProd,
        //     'variantsIds' => [$idDecl],
        // ];
        // try{
        //     $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
        // } catch
        // (ClientExceptionInterface $e) {
        //     Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
        //     return true;
        // } catch (UninitializedContextException $e) {
        //     Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
        //     return true;
        // }
        // unset($decl);
        
        // return $response;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws UninitializedContextException
     * @throws WebhookRegistrationException
     */
    // public function installWebhooks(): array|string|null
    // {
    //     $response = $this->client->post(
    //         path: 'webhooks',
    //         body: [
    //             'webhook' => [
    //                 'topic' => 'collections/delete',
    //                 'address' => 'https://devpim.midpim.com:8044/ec_shopify/webhook/collection/delete',
    //                 'format' => 'json'
    //             ]
    //         ]
    //     );
    //     // $response = Registry::register(
    //     //     path: '/shopify/webhooks',
    //     //     topic: Topics::APP_UNINSTALLED,
    //     //     shop: $this->shopOrigin,
    //     //     accessToken: $this->accessToken,
    //     // );
    //     return $response->getDecodedBody();
    //     // if ($response->isSuccess()) {
    //     //     return 'Les webhooks ont été créés correctement';
    //     // } else {
    //     //     return 'Erreur dans la création des Webhooks.<br>' . var_export($response, true);
    //     // }
    // }

    /**
     * @throws UninitializedContextException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    // public function createCollection(\Pimcore\Model\DataObject\Category $category)
    // {
    //     if ($category_crossid = Outils::getCrossid($category, $this->diffusion, 'ext_id')) {
    //         return $category_crossid;
    //     }
    //     $collection_body = [
    //         'custom_collection' => [
    //             'title' => $category->getName(),
    //         ]
    //     ];
    //     $collection_result = $this->client
    //         ->post(path: 'custom_collections', body: $collection_body)
    //         ->getDecodedBody();
    //     Outils::addCrossid(object: $category, source: $this->diffusion, ext_id: $collection_result['custom_collection']['id']);
    //     return $category_crossid;
    // }

    /**
     * Suppression d'une collection dans Shopify
     */
    // public function deleteCollection(Category $category)
    // {
    //     try {
    //         $data = $this->client->delete(
    //             path: 'custom_collections', query: ['id' => Outils::getCrossId(obj: $category, source: $this->diffusion)]
    //         );
    //         return $data->getDecodedBody();
    //     } catch (ClientExceptionInterface $e) {
    //         return $e->getMessage();
    //     } catch (MissingArgumentException|UninitializedContextException|JsonException $e) {
    //         return $e->getMessage();
    //     }
    // }

    // public function updateCarac(Product $product)
    // {
    // }

    // public function updateFeature($carac)
    // {
    // }

    /**
     * @throws ClientExceptionInterface
     * @throws UninitializedContextException
     * @throws JsonException
     */
    public function updateDecli(Declinaison $decli)
    {
        Outils::addLog('debut fonction updateDecli ');
        
        $lang = Tool::getValidLanguages()[0];
        $pimcore_product = DataObject::getById($decli->getParentId());
        
        // Check si le pere est diffusé
        $diffusionActive = $pimcore_product->getDiffusions_active();
        $tabIdDiffAct = [];
        foreach($diffusionActive as $diff){
            $tabIdDiffAct[] = $diff;
           
        }
        if(!in_array($this->diffusion->getId(), $tabIdDiffAct)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit parent pas diffusé'); 
            return true;
        }   
        
        // Check si decl active
        $isActive = $decli->getPublished();
        $halfCrossID = Outils::getCrossId($decli, $this->diffusion);
        if(!$isActive){
            // en attendant d'avoir un updateStockDecli
            // Le delete mets les stock a 0 pour le moment
            if($halfCrossID){
                $this->deleteDecli($decli);
            }            
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'declinaison inactive'); 
            return true;
        }
        
        $halfproductCrossID = Outils::getCrossId($pimcore_product, $this->diffusion);
        $productCrossID = 'gid://shopify/Product/' . $halfproductCrossID;
        if(!$halfproductCrossID){
            return true;
        }

        $sto = 0;
        $stoDepot = Outils::getStockProduct($pimcore_product, $decli, 'dispo', 0, 0, 0 ,1);
        foreach($stoDepot as $depot){
            $sto = $sto + $depot['quantity_physique'];
        }
        $options = [
            'option1' => ['name' => null, 'value' => null, 'all_values' => []],
            'option2' => ['name' => null, 'value' => null, 'all_values' => []],
            'option3' => ['name' => null, 'value' => null, 'all_values' => []]
        ];
        $attributes = $decli->getAttribut();
        $options['option1']['value'] = null;
        $options['option2']['value'] = null;
        $options['option3']['value'] = null;
        foreach ($attributes as $attribute_value) {
            /* @var $attribute_value Attribut */
            $attribute = $attribute_value->getParent();
            $name = $attribute->getName($lang);
            $value = $attribute_value->getName($lang);

            foreach ($options as $key => &$option) {
                if ($name === $option['name'] || $option['name'] === null) {
                    $option['name'] = $name;
                    $option['value'] = $value;
                    $option['all_values'][] = $value;
                    break;
                }
            }
            unset($option); // Important: annule la référence pour éviter les problèmes ultérieurs
        }
        // Outils::addLog('Mise à jour du produit (options) :' . json_encode($options), 1, [], 'NOMDULOG');

        $optionTab = [];
        foreach($options as $option){
            if($option['value'] != null || $option['value'] != ''){
                $optionTab[] = $option['value'];
            }
        }
        $price = Outils::getBestPriceVente(product: $pimcore_product,tax: true, id_diffusion: $this->diffusion->getId(), id_declinaison: $decli->getId());
        $variants = [
            // 'title' => 'par defaut',
            'price' => $price ?? $pimcore_product->getPrice_recommended(),
            'inventoryItem' => [
                'sku' => $decli->getReference_declinaison(),
                // 'cost' => $pimcore_product->getPrice_buying_default(),
                'measurement' => [
                    'weight' => [
                        'value' => $decli->getWeight(),
                        // 'unit' => Dataobject::getByPath('/Config/unit_weight')->getValeur(),
                        'unit' => 'GRAMS',
                    ],
                ],
            ],
            'inventoryPolicy' => self::INVENTORY_POLICY,

            'barcode' => $decli->getEan13(),
            'inventoryQuantities' => [
                'availableQuantity' => $sto,
                // 'locationId' => 'gid://shopify/Location/102113280322',
                'locationId' => 'gid://shopify/Location/'.outils::getConfigByName($this->diffusion,'shopify_location_id'),
            ],
            'optionValues' => [ // Options for the variant (e.g., Size, Color)
                [ 
                    'name' => join(' / ', $optionTab),
                    'optionName' => 'Title' 
                ],
            ],
        ];

        
        
        // CAS UPDATE
        if($halfCrossID){
            $crossID = 'gid://shopify/ProductVariant/' . $halfCrossID;
            $query = 'mutation UpdateProductVariantsOptionValuesInBulk($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
            productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                product {
                    id
                    }
                    productVariants {
                    id
                    title
                    price
                    }
                    userErrors {
                    field
                    message
                    }
                }
            }';
            // $query = 'mutation UpdateProductVariantsOptionValuesInBulk($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
            // productVariantsBulkUpdate(productId: $productId, variants: $variants) {
            //     product {
            //         id
            //         }
            //         productVariants {
            //         id
            //         title
            //         selectedOptions {
            //             name
            //             value
            //         }
            //         barcode
            //         price
            //         inventoryPolicy                            
            //         inventoryItem {
            //             sku
            //             measurement {
            //                 weight {
            //                     unit
            //                     value
            //                 }
            //             }
            //         }  
            //         }
            //         userErrors {
            //         field
            //         message
            //         }
            //     }
            // }';
            
            $price = Outils::getBestPriceVente(product: $pimcore_product,tax: true, id_diffusion: $this->diffusion->getId(), id_declinaison: $decli->getId());
            $variantShopify = [
                // 'title' => 'par defaut',
                'id' => $crossID,
                'price' => $price ?? $pimcore_product->getPrice_recommended(),
                // 'inventoryItem' => [
                // s'sku' => $variant->getReference_declinaison(),
                //     // 'cost' => $pimcore_product->getPrice_buying_default(),
                //     'measurement' => [
                //         'weight' => [
                //             'value' => $variant->getWeight(),
                //             // 'unit' => Dataobject::getByPath('/Config/unit_weight')->getValeur(),
                //             'unit' => 'GRAMS',
                //         ],
                //     ],
                // ],
                // 'inventoryPolicy' => self::INVENTORY_POLICY,

                // 'barcode' => $variant->getEan13(),
                'optionValues' => [ // Options for the variant (e.g., Size, Color)
                    [ 
                        'name' => join(' / ', $optionTab),
                        'optionName' => 'Title' 
                    ],
                ],
            ];

            $variables = [
                'productId' => $productCrossID,
                'variants' => $variantShopify,
            ];
            
            try{
                $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
                // Outils::addLog(json_encode($response));
            } catch
            (ClientExceptionInterface $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            } catch (UninitializedContextException $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage(), 1);
                return true;
            }
        } else { // CAS CREATION
            $variables = [
                "productId" => $productCrossID,
                "variants" => $variants,
            ];
            // Outils::addLog('query create Variante');
            // Outils::addLog(json_encode($variables));
            $query = 'mutation ProductVariantsCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkCreate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        title
                        selectedOptions {
                            name
                            value
                        }
                        barcode
                        price
                        inventoryPolicy                            
                        inventoryItem {
                            sku
                            measurement {
                                weight {
                                    unit
                                    value
                                }
                            }
                        }                                                      
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';
           
            try{
                $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
                $prodVar = $response['data']['productVariantsBulkCreate']['productVariants'];
                Outils::addLog('ShopifyAPiClient ' . __LINE__ . json_encode($response)); 
            } catch
            (ClientExceptionInterface $e) {
                Outils::addLog('ShopifyAPiClient ' . __LINE__ . $e->getMessage());
                return true;
            } catch (UninitializedContextException $e) {
                Outils::addLog('ShopifyAPiClient ' . __LINE__ . $e->getMessage());
                return true;
            }
            $prodVar = reset($prodVar);
            // Outils::addLog('ShopifyAPiClient ' . __LINE__ . json_encode($prodVar));
            if(is_array($prodVar) && isset($prodVar['id'])){
                $completeId = $prodVar['id'];
                $slashedId = explode('/', $completeId);
                $id = end($slashedId);
                // Outils::addLog('addCrossid ' . __LINE__ );
                $temp = [];
                $crossIDs = $decli->getCrossid(); 
                foreach($crossIDs as $cross){
                    $temp[] = $cross->getData();
                }
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. json_encode($temp));
                Outils::addCrossid(object: $decli, source: $this->diffusion, ext_id: $id);
                $temp = [];
                $crossIDs = $decli->getCrossid(); 
                foreach($crossIDs as $cross){
                    $temp[] = $cross->getData();
                }
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. json_encode($temp));
            }
     
        }
        Outils::addLog('fin fonction updateDecli');
    }
    /**
     * @throws ClientExceptionInterface
     * @throws UninitializedContextException
     * @throws JsonException
     */
    public function updateDecliPrix(Declinaison $decli)
    {
        Outils::addLog('debut fonction updateDecliPrix ');
        
        $lang = Tool::getValidLanguages()[0];
        $pimcore_product = DataObject::getById($decli->getParentId());
        
        // Check si le pere est diffusé
        $diffusionActive = $pimcore_product->getDiffusions_active();
        $tabIdDiffAct = [];
        foreach($diffusionActive as $diff){
            $tabIdDiffAct[] = $diff;
        }
        if(!in_array($this->diffusion->getId(), $tabIdDiffAct)){
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'produit parent pas diffusé'); 
            return true;
        }   
        
        // Check si decl active
        $isActive = $decli->getPublished();
        $halfCrossID = Outils::getCrossId($decli, $this->diffusion);
        if(!$isActive){        
            Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'.'declinaison inactive'); 
            return true;
        }
        
        $halfproductCrossID = Outils::getCrossId($pimcore_product, $this->diffusion);
        $productCrossID = 'gid://shopify/Product/' . $halfproductCrossID;
        if(!$halfproductCrossID){
            return true;
        }
       
        $price = Outils::getBestPriceVente(product: $pimcore_product,tax: true, id_diffusion: $this->diffusion->getId(), id_declinaison: $decli->getId());
        
        
        // CAS UPDATE
        if($halfCrossID){
            $crossID = 'gid://shopify/ProductVariant/' . $halfCrossID;
            $query = 'mutation UpdateProductVariantsOptionValuesInBulk($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
            productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                product {
                    id
                    }
                    productVariants {
                    id
                    title
                    price
                    }
                    userErrors {
                    field
                    message
                    }
                }
            }';

            
            $price = Outils::getBestPriceVente(product: $pimcore_product,tax: true, id_diffusion: $this->diffusion->getId(), id_declinaison: $decli->getId());
            $variantShopify = [
                'id' => $crossID,
                'price' => $price ?? $pimcore_product->getPrice_recommended(),
            ];

            $variables = [
                'productId' => $productCrossID,
                'variants' => $variantShopify,
            ];
            
            try{
                $response = $this->client->query(["query" => $query, "variables" => $variables])->getDecodedBody();
                // Outils::addLog(json_encode($response));
                // Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. json_encode($response)); 
            } catch
            (ClientExceptionInterface $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            } catch (UninitializedContextException $e) {
                Outils::addLog('(ShopifyApiClient:' . __LINE__ . ') -'. $e->getMessage());
                return true;
            }
        } 
    }

    static function getShopifyClient(): Rest
    {
        $config = [];
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        foreach ($diffusion->getConfig() as $conf) {
            $config[$conf->getKey()] = $conf->getValeur();
        }
        Context::initialize(
            apiKey: $apiKey ?? $config['shopify_api_key'],
            apiSecretKey: $apiSecretKey ?? $config['shopify_api_secret'],
            scopes: $scopes ?? $config['shopify_api_scope'],
            hostName: $hostName ?? $config['shopify_api_hostname'],
            sessionStorage: new FileSessionStorage('/tmp/php_sessions'),
            apiVersion: $apiVersion ?? $config['shopify_api_version'],
            isEmbeddedApp: false,
        );
        return new Rest(
            domain: $hostName ?? $config['shopify_api_hostname'],
            accessToken: $accessToken ?? $config['shopify_access_token'],
        );
    }

}
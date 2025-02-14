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
 * @author    Ether Création SARL <contact@ethercreation.com>
 * @copyright 2008-2018 Ether Création SARL
 * @license   Commercial license
 * International Registered Trademark & Property of PrestaShop Ether Création SARL
 *
 */

namespace bundles\ecShopifyBundle\Controller;

use bundles\ecMiddleBundle\Overrides\PriceSelling;
use bundles\ecMiddleBundle\Services\Outils;
use CustomerManagementFrameworkBundle\RESTApi\Response;
use bundles\ecShopifyBundle\Services\PimcoreActions;
use bundles\ecShopifyBundle\Services\ShopifyApiClient;
use Pimcore\Model\DataObject\Attribut;
use Pimcore\Model\DataObject\AttributValue;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Declinaison;
use Pimcore\Model\DataObject\Diffusion;
use Pimcore\Model\DataObject\Product;
use Psr\Http\Client\ClientExceptionInterface;
use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Exception\MissingArgumentException;
use Shopify\Exception\UninitializedContextException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * # PimcoreHookController
 */
#[Route('/hook-action', name: 'hook_action')]
class PimcoreHookController extends DefaultController
{
    const DIFFUSION_SHOPIFY = '/Diffusion/Shopify';
    const WEIGHT_UNIT = 'kg';
    const PUBLISHED_SCOPE = 'web';
    const INVENTORY_POLICY = 'deny';
    const FULFILLMENT_SERVICE = 'manual';
    const INVENTORY_MANAGEMENT = null;
    const CURRENCY_CODE = 'EUR';

    /**
     * Actions à effectuer lors de la création d'un produit dans pimcore
     * @throws \Exception
     */
    #[Route('/create-product', name: 'create_product')]
    public function hookCreateProduct($params): Response
    {
        Outils::addLog('Création d\'un produit', 1, [], 'NOMDULOG');
        /**
         * @var Product $product
         */
        $product = $params['product'];
        $shopify = new ShopifyApiClient();
        $result = $shopify->createProduct($product);
        return new Response('<pre>' . json_encode([$result]) . '</pre>');
    }

    public function hookUpdateStockShopify($params): Response
    {
        /**
         * @var Product $product
         */
        $obj = $params['stock']; 
        // $object['object'] = 'stock';
        $obj->setHideUnpublished(false);
        $produit = $obj->getStock_product()??0;
        Outils::addLog('fonction hookUpdateStockShopify ', 3);
        // $product = $params['product'];
        $shopify = new ShopifyApiClient();
        $result = $shopify->updateStock($produit);
        Outils::addLog('Modification stock d\'un produit: ' . $result, 3, [], 'NOMDULOG');
        return new Response('<pre>' . json_encode([$result]) . '</pre>');
    }

    /**
     * Actions à effectuer lors de la modification d'un produit dans pimcore
     * @throws \JsonException
     */
    #[Route('/update-product', name: 'update_product')]
    public function hookUpdateProduct($params): Response
    {
        /**
         * @var Product $product
         */
        // Outils::addLog('fonction hookUpdateProduct', 1, [], 'NOMDULOG');
        Outils::addLog('debut fonction hookUpdateProduct', 3, [], 'NOMDULOG');
        
        $product = $params['product'];
        $shopify = new ShopifyApiClient();
        $result = $shopify->updateProduct($product);
        Outils::addLog('Modification d\'un produit: ' . $result, 1, [], 'NOMDULOG');
        Outils::addLog('fin fonction hookUpdateProduct', 3, [], 'NOMDULOG');
        return new Response('<pre>' . json_encode([$result]) . '</pre>');
    }

    /**
     * Actions à effectuer lors de la suppression d'un produit dans pimcore
     */
    #[Route('/delete-product', name: 'delete_product')]
    public function hookDeleteBeforeProduct($params): Response
    {
        /**
         * @var Product $product
         */
        $product = $params['product'];
        Outils::addLog('fonction hookDeleteBeforeProduct ', 3);
        $shopify = new ShopifyApiClient();
        $result = $shopify->deleteProduct($product);
        Outils::addLog('fin fonction hookDeleteBeforeProduct ', 3);
        return new Response('ok');
    }

    /**
     * Actions à effectuer lors de la création d'une catégorie dans pimcore
     */
    #[Route('/create-category', name: 'create_category')]
    public function hookCreateCategory($params): Response
    {
        Outils::addLog("Hook creation de produit tapé", 1);
        /**
         * @var Category $category
         */
        $category = json_decode(json_encode($params['category']));
        $shopify = new ShopifyApiClient();
        try {
            $shopify->createCollection($category);
        } catch (ClientExceptionInterface|UninitializedContextException|\JsonException $e) {
            return new Response($e->getMessage());
        }
        return new Response('ok');
    }

    /**
     * Actions à effectuer lors de la modification d'une catégorie dans pimcore
     */
    #[Route('/update-category', name: 'update_category')]
    public function hookUpdateCategory(): Response
    {
        return new Response('Pas encore disponible');
    }

    /**
     * Actions à effectuer lors de la suppression d'une catégorie dans pimcore
     */
    #[Route('/delete-category', name: 'delete_category')]
    public function hookDeleteCategory($params): Response
    {
        /**
         * @var Category $category
         */
        $category = json_decode(json_encode($params['category']));
        $shopify = new ShopifyApiClient();
        try {
            $shopify->deleteCollection($category);
        } catch (ClientExceptionInterface|UninitializedContextException|\JsonException $e) {
            return new Response($e->getMessage());
        }
        return new Response('Collection supprimée');
    }

    /**
     * Actions à effectuer lors de la création d'un client dans pimcore
     */
    #[Route('/create-customer', name: 'create_customer')]
    public function hookCreateCustomer(): Response
    {

        return new Response('ok');
    }

    /**
     * Actions à effectuer lors de la modification d'un client dans pimcore
     */
    #[Route('/update-customer', name: 'update_customer')]
    public function hookUpdateCustomer(): Response
    {
        return new Response('Pas encore disponible');
    }

    /**
     * Actions à effectuer lors de la suppression d'un client dans pimcore
     */
    #[Route('/delete-customer', name: 'delete_customer')]
    public function hookDeleteCustomer(): Response
    {
        return new Response('Pas encore disponible');
    }


    public function hookUpdateDecli($params)
    {
        Outils::addLog('debut fonction hookUpdateDecli', 3, [], 'NOMDULOG');
        /** @var Declinaison $decli */
        $decli = $params['declinaison'];
        /** @var Product $product */
        $product = $decli->getParent();
        $shopify = new ShopifyApiClient();
        $result = $shopify->updateDecli($decli);
        // $result = '';
        Outils::addLog('Modification d\'un produit: ' . $result, 1, [], 'NOMDULOG');
        Outils::addLog('fin fonction hookUpdateDecli', 3, [], 'NOMDULOG');
        return new Response('<pre>' . json_encode([$result]) . '</pre>');
    }

    public function hookDeleteDecli($params)
    {
        Outils::addLog('fonction hookDeleteDecli ', 3);
        /** @var Declinaison $decli */
        $decli = $params['declinaison'];
        /** @var Product $product */
        $shopify_client = new ShopifyApiClient();
        $result = $shopify_client->deleteDecli($decli);
        Outils::addLog('Suppression d\'une déclinaison: ' . $result, 1, [], 'NOMDULOG');
        return new Response('<pre>' . json_encode([$result]) . '</pre>');
    }


    /**
     * Actions à effectuer lors de la modification d'une déclinaison dans pimcore
     * @throws MissingArgumentException
     * @throws \JsonException
     */
    #[Route('/update-decli-old', name: 'hook_update_decli-old')]
    public function hookUpdateDecliOld($params): array|string
    {
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $client = $this->getShopifyClient();
        /**
         * @var Declinaison $decli
         */
        $decli = $params['declinaison'];
        if (!$id_product_shopify = Outils::getCrossId(obj: $decli->getParent(), source: $diffusion)) {
            return true;
        }
        $options = [
            'option1' => ['name' => null, 'value' => null, 'all_values' => []],
            'option2' => ['name' => null, 'value' => null, 'all_values' => []],
            'option3' => ['name' => null, 'value' => null, 'all_values' => []]
        ];
        $options['option1']['value'] = null;
        $options['option2']['value'] = null;
        $options['option3']['value'] = null;
        foreach ($decli->getAttribut() as $attribute) {
            $value = $attribute->getParent()->getName();
            $name = $attribute->getName();

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
        $option1 = $options['option1'];
        $option2 = $options['option2'];
        $option3 = $options['option3'];
        $variant = [
//            'id' => Outils::getCrossId(obj: $decli, source: $this->diffusion) ?? null,
//                        'product_id' => 1071559574,
            'title' => join(' / ', array_map(function ($attribut) {
                return $attribut->getName();
            }, $decli->getAttribut())),
            'price' => $decli->getPrix_vente(),
            'cost' => $decli->getPrix_achat(),
            'sku' => $decli->getReference_declinaison(),
//            'position' => 1,
            'inventory_policy' => self::INVENTORY_POLICY,
            'compare_at_price' => null,
            'fulfillment_service' => self::FULFILLMENT_SERVICE,
            'inventory_management' => self::INVENTORY_MANAGEMENT,
            'option1' => $option1['value'],
            'option2' => $option2['value'],
            'option3' => $option3['value'],
//                        'created_at' => '2023-06-14T14:23:53-04:00',
//                        'updated_at' => '2023-06-14T14:23:53-04:00',
//                        'taxable' => true,
            'barcode' => $decli->getEan13(),
//                        'grams' => null,
//                        'image_id' => null,
            'weight' => $decli->getWeight(),
            'weight_unit' => self::WEIGHT_UNIT,
//                        'inventory_item_id' => 1070325026,
//            'inventory_quantity' => $decli->getQuantity(),
//                        'old_inventory_quantity' => 0,
        ];
        try {
            if (($id_variant_shopify = Outils::getCrossId(obj: $decli, source: $diffusion)) !== 0) {
                $result = $client->put(
                    path: "products/$id_product_shopify/variants/$id_variant_shopify",
                    body: ['variant' => $variant]
                )->getDecodedBody();
            } else {
                $result = $client->post(
                    path: "products/$id_product_shopify/variants",
                    body: ['variant' => $variant]
                )->getDecodedBody();
                if (key_exists('variant', $result)) {
                    Outils::addCrossid(object: $decli, source: $diffusion, ext_id: $result['variant']['id']);
                }

            }
        } catch (ClientExceptionInterface|UninitializedContextException $e) {
            $result = $e->getMessage();
        }
        Outils::addLog('Modification d\'une déclinaison: ' . json_encode($result), 1, [], 'NOMDULOG');

        return new Response('ok');
    }

    /**
     * Actions à effectuer lors de la mise à jour/création d'un prix dans pimcore
     * @throws MissingArgumentException
     * @throws \JsonException
     */
    #[Route('/update-price', name: 'hook_update_price')]
    public function hookUpdatePrice($params): array|string
    {
        return new Response('ok');
    }

    /**
     * Mise à jour d'une attribute value
     * @throws MissingArgumentException
     */
    public function updateAttributeValue($params)
    {
        /**
         * @var AttributValue $attribut_value
         */
        $attribut_value = $params['attributeValue'];
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $client = $this->getShopifyClient();
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $client = $this->getShopifyClient();
        return new Response('ok');
    }

    /**
     * @throws MissingArgumentException
     */
    public function getShopifyClient(): Rest
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

    /**
     * @throws MissingArgumentException
     */
    public function hookUpdatePriceSelling($params)
    {
        /**
         * @var \Pimcore\Model\DataObject\PriceSelling $price_selling
         */
        $price_selling = $params['priceSelling'];
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $client = $this->getShopifyClient();
        Outils::addLog('Modification d\'un prix (debut): ' . 'products/' . Outils::getCrossId($price_selling->getParent(), $diffusion) . '/variants/' . Outils::getCrossId($price_selling->getDecli(), $diffusion), 1, [], 'NOMDULOG');

        try {
            $result = $client->post(
                path: 'products/' . Outils::getCrossId($price_selling->getParent(), $diffusion) . '/variants/' . Outils::getCrossId($price_selling->getDecli(), $diffusion),
                body: [
                    'variant' => [
                        'price' => $price_selling->getPrice_ht(),
                    ]
                ]
            )->getDecodedBody();
        } catch (\JsonException|ClientExceptionInterface|UninitializedContextException $e) {
            $result = $e->getMessage();
        }
        Outils::addLog('Modification d\'un prix: ' . json_encode($result), 1, [], 'NOMDULOG');
        return new Response('ok');
    }
}
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

use bundles\ecMiddleBundle\Services\Outils;
use Pimcore\Model\DataObject\{Address, Category, Client, Diffusion, Order, Customer, Product};
use Dflydev\DotAccessData\Data;
use Pimcore\Model\DataObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\{Response, Request};
use Shopify\Rest\Admin2023_04\Order as ShopifyOrder;

use stdClass;
use Symfony\Component\Routing\Annotation\Route;

/**
 * # Webhook Controller
 * Gestion des déclenchements d'actions pour les webhooks
 */
#[Route('/ec_shopify/webhook')]
class WebhookController extends DefaultController
{
    /**
     *
     */
    const ID_DIFFUSION = 68311;


    /**
     * ## Webhook - Suppression d'une collection
     * Est déclenché lorsqu'une **collection** est **supprimée** dans la boutique **Shopify**.
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @return Response
     * @author Théodore Riant <theodore@ethercreation.com>
     */
    public function collectionDeleteAction(Request $request, LoggerInterface $logger): Response
    {
        // $diffusion = Diffusion::getById(self::ID_DIFFUSION);
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $body = json_decode($request->getContent());
        $object = Outils::getObjectByCrossId(crossid: $body->id, class: 'category', diffusion: $diffusion);
        if (!$object) {
            $logger->info('collection not found', ['id' => $body->id]);
            return new Response(content: '<pre>ERROR: Object not found</pre>', status: 200);
        }
        try {
            Outils::removeCrossid(object: $object, source: $diffusion);
            $logger->error(message: 'collection deleted');
            return new Response('<pre>OK</pre>');
        } catch (\Exception $e) {
            $logger->error(message: 'collection deletion error', context: [$e->getMessage()]);
            return new Response(content: '<pre>ERROR: ' . $e->getMessage() . '</pre>');
        }
    }

    /**
     * ## Webhook - Suppression d'un produit
     * Est déclenché lorsqu'un **produit** est **supprimé** dans la boutique **Shopify**.
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @return Response
     * @author Théodore Riant <theodore@ethercreation.com>
     */
    public function productDeleteAction(Request $request, LoggerInterface $logger): Response
    {
        // $diffusion = Diffusion::getById(self::ID_DIFFUSION);
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $object = Outils::getObjectByCrossId(crossid: $request->getContent()['id'], class: 'product', diffusion: $diffusion);
        if (!$object) {
            return new Response(content: '<pre>ERROR: Object not found</pre>', status: 200);
        }
        try {
            Outils::removeCrossid(object: $object, source: $diffusion);
            return new Response(content: '<pre>OK</pre>', status: 200);
        } catch (\Exception $e) {
            $logger->error(message: 'productDeleteAction', context: [$e->getMessage()]);
            return new Response(content: '<pre>ERROR: ' . $e->getMessage() . '</pre>', status: 500);
        }
    }

    /**
     * ## Webhook - Création d'une commande
     *
     * Est déclenché lorsqu'une **commande** est **créée** dans la boutique **Shopify**.
     * @param Request $request
     * @param LoggerInterface $logger
     * @return Response
     * @throws \Exception
     * @author Théodore Riant <theodore@ethercreation.com>
     */
    #[Route('/order/create')]
    public function orderCreateAction(Request $request): Response
    {
        Outils::answer('Okey !');

        Outils::addLog('orderCreateAction',3);
        Outils::addLog(json_encode($request->getContent()),3);
        
        // $data = json_decode($json, true);
        $data = json_decode($request->getContent(), true);
        if (!array_key_exists('id', $data)) {
            $data = $data['order'];
        }
        $date = $data['created_at']??date('Y-m-d H:i:s');
        $dateTime = new \DateTime($date);
        $formattedDate = $dateTime->format('Y-m-d H:i:s');

        // Transformation du JSON
        // $data['id'] =  $data['id'] + time();
        $transformedData = [
            'order' => [
                'payment' => $data['payment_gateway_names'][0] ?? 'Unknown',
                'total_paid' => (float) $data['total_price'],
                'total_paid_tax_incl' => (float) $data['total_price'],
                'total_paid_tax_excl' => (float) $data['subtotal_price'],
                'total_paid_real' => (float) $data['total_price'],
                'total_products' => (float) $data['total_line_items_price'],
                'total_products_wt' => (float) $data['subtotal_price'],
                'total_shipping' => (float) $data['total_shipping_price_set']['shop_money']['amount'],
                'total_shipping_tax_incl' => (float) $data['total_shipping_price_set']['shop_money']['amount'],
                'total_shipping_tax_excl' => (float) $data['total_shipping_price_set']['shop_money']['amount'],
                'date_add' => $formattedDate,
                'reference' => $data['id'], //$data['name'], // time(),
                'id' => $data['name'],
                'current_state' => $data['fulfillment_status'],
            ],
            'address_delivery' => [
                'country' => $data['shipping_address']['country'] ?? $data['billing_address']['country'],
                'firstname' => $data['shipping_address']['first_name'] ?? $data['billing_address']['first_name'],
                'lastname' => $data['shipping_address']['last_name'] ?? $data['billing_address']['last_name'],
                'company' => $data['shipping_address']['company'] ?? $data['billing_address']['company'],
                'address1' => $data['shipping_address']['address1'] ?? $data['billing_address']['address1'],
                'address2' => $data['shipping_address']['address2'] ?? $data['billing_address']['address2'],
                'postcode' => $data['shipping_address']['zip'] ?? $data['billing_address']['zip'],
                'city' => $data['shipping_address']['city'] ?? $data['billing_address']['city'],
                'phone' => $data['shipping_address']['phone'] ?? $data['billing_address']['phone'],
                'phone_mobile' => '',
                'iso_code' => $data['shipping_address']['country_code'] ?? $data['billing_address']['country_code'],
            ],
            'address_invoice' => [
                'country' => $data['billing_address']['country'] ?? $data['shipping_address']['country'],
                'firstname' => $data['billing_address']['first_name'] ?? $data['shipping_address']['first_name'],
                'lastname' => $data['billing_address']['last_name'] ?? $data['shipping_address']['last_name'],
                'company' => $data['billing_address']['company'] ?? $data['shipping_address']['company'],
                'address1' => $data['billing_address']['address1'] ?? $data['shipping_address']['address1'],
                'address2' => $data['billing_address']['address2'] ?? $data['shipping_address']['address2'],
                'postcode' => $data['billing_address']['zip'] ?? $data['shipping_address']['zip'],
                'city' => $data['billing_address']['city'] ?? $data['shipping_address']['city'],
                'phone' => $data['billing_address']['phone'] ?? $data['shipping_address']['phone'],
                'phone_mobile' => '',
                'iso_code' => $data['billing_address']['country_code'] ?? $data['shipping_address']['country_code'],
            ],
            'customer' => [
                'firstname' => $data['customer']['first_name'],
                'lastname' => $data['customer']['last_name'],
                'email' => $data['customer']['email'],
                'id_default_group' => $data['source_name'] ?? 'Web',
            ],
            'details' => array_map(function ($item) {
                // $rate = $item['tax_lines']['rate'];
                $valTaxe = 0;
                if(isset($item['tax_lines']['price'])){
                    $valTaxe = $item['tax_lines']['price'];
                }
                return [
                    'id_order' => $item['id'],
                    'product_id' => $item['product_id'],
                    'product_attribute_id' => $item['variant_id'] ?? 0,
                    'product_quantity' => $item['quantity'],
                    'product_price' => (float) $item['price'],
                    'product_name' => $item['title'],
                    'product_reference' => $item['sku'],
                    'total_price_tax_incl' => (float) $item['price_set']['shop_money']['amount'] * $item['quantity'],
                    'total_price_tax_excl' => (float) ($item['price_set']['shop_money']['amount'] - $valTaxe) * $item['quantity'],
                    'unit_price_tax_excl' => (float) $item['price_set']['shop_money']['amount'] - $valTaxe,
                    'unit_price_tax_incl' => (float) $item['price_set']['shop_money']['amount'],
                ];
            }, $data['line_items'])
        ];
        Outils::addLog('DEBUT CREA COMMANDDE', 3);
        //dump(json_encode($transformedData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
        // $diffusion = Diffusion::getById(self::ID_DIFFUSION);
        // $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $diffusion = Diffusion::getByPath('/Diffusion/Hadrien');
        Outils::importOrder($diffusion->getId(), json_encode($transformedData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
        Outils::addLog('FIN CREA COMMANDDE', 3, [], 'NOMDULOG'); 
        return new Response(content: '<pre>OK</pre>', status: 200);
 
    }
}